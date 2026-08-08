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

    /** @param array<int, Token> $tokenTable */
    public function __construct(array $tokenTable, string $text)
    {
        $this->tokenTable = $tokenTable;
        $this->text = $text;
        $this->expectedPlaceholders = self::countPlaceholders($text);
    }

    public function text(): string
    {
        return $this->text;
    }

    /**
     * @throws PlaceholderIntegrityError when a placeholder was lost, forged
     *         or duplicated by the caller
     */
    public function setText(string $text): void
    {
        $found = self::countPlaceholders($text);
        if ($found !== $this->expectedPlaceholders) {
            $missing = array_diff_assoc($this->expectedPlaceholders, $found);
            $extra   = array_diff_assoc($found, $this->expectedPlaceholders);
            throw new PlaceholderIntegrityError(sprintf(
                'placeholder set changed: missing [%s], unexpected [%s]',
                implode(', ', array_keys($missing)),
                implode(', ', array_keys($extra))
            ));
        }
        $this->text = $text;
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
    private static function countPlaceholders(string $text): array
    {
        preg_match_all(Placeholder::PATTERN, $text, $m);
        $counts = [];
        foreach ($m[0] as $placeholder) {
            $counts[$placeholder] = ($counts[$placeholder] ?? 0) + 1;
        }
        ksort($counts);
        return $counts;
    }
}
