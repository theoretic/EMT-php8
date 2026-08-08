<?php
declare(strict_types=1);

namespace EMT\Engine\Ir;

use EMT\Engine\Lexer\Token;

/**
 * Linearized document: one string of (escaped, optionally entity-normalized)
 * text with non-text tokens reduced to placeholders, plus the token table
 * holding the original raw bytes.
 *
 * Rules mutate the string via setText(); the placeholder multiset is validated
 * on every write so a rule that eats or duplicates a tag becomes a loud error
 * instead of silent corruption (the legacy engine's failure mode).
 */
final class IrDocument
{
    /** @var array<int, Token> index => non-text token, as referenced by placeholders */
    private array $tokenTable;

    private string $text;

    /** @var array<string, int> placeholder string => expected count */
    private array $expectedPlaceholders;

    /**
     * Token indices created by rules mid-run (TagBuilder). Rules may insert
     * such a placeholder any number of times (or drop it), so these are
     * exempt from the strict multiset check that guards input tokens.
     *
     * @var array<int, true>
     */
    private array $createdTokens = [];

    /** @var array<string, string> raw tag => placeholder, memoized by createTag() */
    private array $createdByRaw = [];

    /** @param array<int, Token> $tokenTable */
    public function __construct(array $tokenTable, string $text)
    {
        $this->tokenTable = $tokenTable;
        $this->text = $text;
        $this->expectedPlaceholders = $this->countPlaceholders($text);
    }

    /**
     * Register a rule-emitted tag and return its placeholder. The same raw
     * string maps to the same token (placeholders carry no per-occurrence
     * state; rendering just substitutes the raw bytes).
     */
    public function createTag(string $raw, ?string $name, bool $closing): string
    {
        if (isset($this->createdByRaw[$raw])) {
            return $this->createdByRaw[$raw];
        }
        $token = new Token(\EMT\Engine\Lexer\TokenType::Tag, $raw, $name, $closing);
        $index = $this->registerCreated($token);
        return $this->createdByRaw[$raw] = Placeholder::forToken($token, $index, created: true);
    }

    /**
     * Register a rule-created protected fragment (legacy EMT_Lib::iblock()):
     * the raw bytes are restored verbatim at render, and the placeholder
     * carries class 'ib' so paragraph rules can address it.
     */
    public function createInternalBlock(string $raw): string
    {
        $token = new Token(\EMT\Engine\Lexer\TokenType::Protected, $raw);
        $index = $this->registerCreated($token);
        return Placeholder::OPEN . 'ib' . $index . Placeholder::CLOSE;
    }

    private function registerCreated(Token $token): int
    {
        $index = count($this->tokenTable) === 0 ? 0 : max(array_keys($this->tokenTable)) + 1;
        $this->tokenTable[$index] = $token;
        $this->createdTokens[$index] = true;
        return $index;
    }

    /**
     * When true (default), every setText() validates the placeholder multiset
     * — precise attribution of a tag-eating rule, at the cost of a full-text
     * scan per rule. The pipeline turns this off for production runs and calls
     * validate() once at the end instead.
     */
    public bool $validateOnWrite = true;

    public function text(): string
    {
        return $this->text;
    }

    /**
     * @throws PlaceholderIntegrityError when a placeholder was lost, forged
     *         or duplicated by the caller (only when $validateOnWrite is on)
     */
    public function setText(string $text): void
    {
        $this->text = $text;
        if ($this->validateOnWrite) {
            $this->validate();
        }
    }

    /** @throws PlaceholderIntegrityError */
    public function validate(): void
    {
        $found = $this->countPlaceholders($this->text);
        if ($found !== $this->expectedPlaceholders) {
            $missing = array_diff_assoc($this->expectedPlaceholders, $found);
            $extra   = array_diff_assoc($found, $this->expectedPlaceholders);
            throw new PlaceholderIntegrityError(sprintf(
                'placeholder set changed: missing [%s], unexpected [%s]',
                implode(', ', array_keys($missing)),
                implode(', ', array_keys($extra))
            ));
        }
    }

    /** Restore the original byte stream around the (possibly rewritten) text. */
    public function render(): string
    {
        $out = preg_replace_callback(
            Placeholder::PATTERN,
            fn(array $m): string => $this->tokenTable[(int) $m[2]]->raw,
            $this->text
        );
        return Placeholder::unescapeText($out ?? $this->text);
    }

    /** @return array<int, Token> */
    public function tokenTable(): array
    {
        return $this->tokenTable;
    }

    /** @return array<string, int> */
    private function countPlaceholders(string $text): array
    {
        preg_match_all(Placeholder::PATTERN, $text, $m, PREG_SET_ORDER);
        $counts = [];
        foreach ($m as $match) {
            if (isset($this->createdTokens[(int) $match[2]])) {
                continue;
            }
            $counts[$match[0]] = ($counts[$match[0]] ?? 0) + 1;
        }
        ksort($counts);
        return $counts;
    }
}
