<?php
declare(strict_types=1);

namespace EMT\Engine\Lexer;

/**
 * The set of regions the lexer must emit as Protected tokens.
 *
 * Two kinds, mirroring the legacy API:
 *  - raw-text tags (add_safe_tag / defaults pre, script, style, notg):
 *    body between open and matching close tag is protected;
 *  - delimiter blocks (add_safe_block with arbitrary open/close strings,
 *    e.g. '{{' / '}}'): the whole block including delimiters is protected.
 */
final class SafeBlockSet
{
    public const DEFAULT_TAGS = ['pre', 'script', 'style', 'notg'];

    /**
     * script/style are HTML raw-text elements and cannot nest; everything else
     * (pre, notg, user tags) is depth-tracked so nested same-name pairs are
     * protected to the OUTER close tag — this is the deliberate fix for the
     * legacy lazy-regex behavior that leaked trailing content of nested <pre>.
     */
    private const NON_NESTING = ['script', 'style'];

    /** @var array<string, bool> tag name => tracks nesting depth */
    private array $tags = [];

    /** @var list<array{id: string, open: string, close: string}> */
    private array $delimiterBlocks = [];

    /** @param list<string> $tags */
    public function __construct(array $tags = self::DEFAULT_TAGS)
    {
        foreach ($tags as $tag) {
            $this->addTag($tag);
        }
    }

    public function addTag(string $tag): void
    {
        $tag = strtolower(trim($tag));
        if ($tag !== '') {
            $this->tags[$tag] = !in_array($tag, self::NON_NESTING, true);
        }
    }

    public function addDelimiterBlock(string $id, string $open, string $close): void
    {
        $this->delimiterBlocks[] = ['id' => $id, 'open' => $open, 'close' => $close];
    }

    public function isRawTextTag(string $name): bool
    {
        return isset($this->tags[strtolower($name)]);
    }

    public function tagTracksNesting(string $name): bool
    {
        return $this->tags[strtolower($name)] ?? false;
    }

    /**
     * Find all delimiter-block ranges in $input as [startByte, lengthBytes],
     * non-overlapping, in document order. Unterminated blocks are not
     * protected (matches legacy: regex needed both delimiters to match).
     *
     * @return list<array{0: int, 1: int}>
     */
    public function findDelimiterRanges(string $input): array
    {
        if ($this->delimiterBlocks === []) {
            return [];
        }
        $ranges = [];
        foreach ($this->delimiterBlocks as $block) {
            $offset = 0;
            while (($start = strpos($input, $block['open'], $offset)) !== false) {
                $bodyStart = $start + strlen($block['open']);
                $end = strpos($input, $block['close'], $bodyStart);
                if ($end === false) {
                    break;
                }
                $stop = $end + strlen($block['close']);
                $ranges[] = [$start, $stop - $start];
                $offset = $stop;
            }
        }
        usort($ranges, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
        // Drop ranges overlapping an earlier one (first wins, document order).
        $result = [];
        $cursor = 0;
        foreach ($ranges as $range) {
            if ($range[0] >= $cursor) {
                $result[] = $range;
                $cursor = $range[0] + $range[1];
            }
        }
        return $result;
    }
}
