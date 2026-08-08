<?php
declare(strict_types=1);

namespace EMT\Engine\Lexer;

/**
 * Single-pass string-level HTML lexer.
 *
 * Splits input into Text / Tag / Comment / Cdata / Doctype / Protected tokens
 * without building a DOM and without rewriting a single byte: the concatenation
 * of all tokens' raw strings is always exactly the input (round-trip invariant,
 * enforced by tests). Typography rules later run on Text tokens only.
 *
 * Deliberate differences from the legacy base64 escape layer (documented
 * v2-bug fixes, see docs/v3-behavior-changes.md):
 *  - a `<` that does not start markup stays literal text and survives;
 *  - `>` inside quoted attribute values does not terminate the tag;
 *  - nested protected tags (e.g. <pre> inside <pre>) protect to the outer
 *    close tag instead of leaking the tail.
 */
final class Lexer
{
    public function __construct(
        private readonly SafeBlockSet $safeBlocks = new SafeBlockSet(),
    ) {
    }

    public function tokenize(string $input): TokenStream
    {
        $tokens = [];
        $n = strlen($input);
        $i = 0;
        $textStart = 0;

        $delimiterRanges = $this->safeBlocks->findDelimiterRanges($input);
        $nextRange = 0;

        $flushText = static function (int $upTo) use (&$tokens, &$textStart, $input): void {
            if ($upTo > $textStart) {
                $tokens[] = new Token(TokenType::Text, substr($input, $textStart, $upTo - $textStart));
            }
        };

        while ($i < $n) {
            // User-defined delimiter blocks take priority over tag parsing.
            while ($nextRange < count($delimiterRanges) && $delimiterRanges[$nextRange][0] < $i) {
                $nextRange++;
            }
            if ($nextRange < count($delimiterRanges) && $delimiterRanges[$nextRange][0] === $i) {
                [$start, $len] = $delimiterRanges[$nextRange];
                $flushText($i);
                $tokens[] = new Token(TokenType::Protected, substr($input, $start, $len));
                $i = $start + $len;
                $textStart = $i;
                $nextRange++;
                continue;
            }

            if ($input[$i] !== '<') {
                // Fast-forward to the next interesting byte.
                $lt = strpos($input, '<', $i);
                $rangeStart = $nextRange < count($delimiterRanges) ? $delimiterRanges[$nextRange][0] : PHP_INT_MAX;
                $i = (int) min($lt === false ? $n : $lt, $rangeStart, $n);
                continue;
            }

            $next = $input[$i + 1] ?? '';

            if ($next === '!') {
                if (substr($input, $i, 4) === '<!--') {
                    $end = strpos($input, '-->', $i + 4);
                    $stop = $end === false ? $n : $end + 3;
                    $flushText($i);
                    $tokens[] = new Token(TokenType::Comment, substr($input, $i, $stop - $i));
                    $i = $textStart = $stop;
                    continue;
                }
                if (substr($input, $i, 9) === '<![CDATA[') {
                    $end = strpos($input, ']]>', $i + 9);
                    $stop = $end === false ? $n : $end + 3;
                    $flushText($i);
                    $tokens[] = new Token(TokenType::Cdata, substr($input, $i, $stop - $i));
                    $i = $textStart = $stop;
                    continue;
                }
                $end = strpos($input, '>', $i + 2);
                if ($end === false) {
                    $i++; // unterminated: literal text
                    continue;
                }
                $flushText($i);
                $tokens[] = new Token(TokenType::Doctype, substr($input, $i, $end + 1 - $i));
                $i = $textStart = $end + 1;
                continue;
            }

            if ($next === '?') {
                $end = strpos($input, '>', $i + 2);
                if ($end === false) {
                    $i++;
                    continue;
                }
                $flushText($i);
                $tokens[] = new Token(TokenType::Tag, substr($input, $i, $end + 1 - $i));
                $i = $textStart = $end + 1;
                continue;
            }

            if ($next === '/' || ctype_alpha($next)) {
                $tagEnd = $this->scanTag($input, $i, $n);
                if ($tagEnd === null) {
                    $i++; // unterminated tag: literal text
                    continue;
                }
                $raw = substr($input, $i, $tagEnd - $i);
                $closing = $next === '/';
                $name = $this->tagName($raw, $closing);
                $flushText($i);
                $tokens[] = new Token(TokenType::Tag, $raw, $name, $closing);
                $i = $textStart = $tagEnd;

                if (!$closing && $name !== null
                    && $this->safeBlocks->isRawTextTag($name)
                    && !str_ends_with(rtrim(substr($raw, 0, -1)), '/')
                ) {
                    $bodyEnd = $this->findRawTextEnd($input, $i, $n, $name);
                    if ($bodyEnd !== null) {
                        if ($bodyEnd > $i) {
                            $tokens[] = new Token(TokenType::Protected, substr($input, $i, $bodyEnd - $i));
                        }
                        $i = $textStart = $bodyEnd;
                    }
                    // No close tag found: legacy engine left such content
                    // unprotected, so lex it normally for parity.
                }
                continue;
            }

            // `<` followed by anything else (space, digit, `-`, another `<`,
            // EOF) is literal prose.
            $i++;
        }

        $flushText($n);
        return new TokenStream($tokens);
    }

    /** Returns the byte offset just past the closing `>`, or null if unterminated. */
    private function scanTag(string $input, int $start, int $n): ?int
    {
        $quote = null;
        for ($j = $start + 1; $j < $n; $j++) {
            $c = $input[$j];
            if ($quote !== null) {
                if ($c === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($c === '"' || $c === "'") {
                $quote = $c;
                continue;
            }
            if ($c === '>') {
                return $j + 1;
            }
            if ($c === '<') {
                return null; // stray `<` before any `>`: not a tag
            }
        }
        return null;
    }

    private function tagName(string $raw, bool $closing): ?string
    {
        if (preg_match('/^<\/?([a-zA-Z][a-zA-Z0-9\-]*)/', $raw, $m) !== 1) {
            return null;
        }
        return strtolower($m[1]);
    }

    /**
     * Find the end of a raw-text body: the offset where the matching close tag
     * begins. Returns null when no close tag exists.
     */
    private function findRawTextEnd(string $input, int $from, int $n, string $name): ?int
    {
        $nesting = $this->safeBlocks->tagTracksNesting($name);
        $depth = 1;
        $len = strlen($name);
        $pos = $from;

        while ($pos < $n) {
            $lt = stripos($input, '</' . $name, $pos);
            if ($lt === false) {
                return null;
            }
            if (!$this->isNameBoundary($input, $lt + 2 + $len)) {
                $pos = $lt + 2;
                continue;
            }
            if ($nesting) {
                // Count intervening same-name open tags between $pos and $lt.
                $scan = $pos;
                while (($open = stripos($input, '<' . $name, $scan)) !== false && $open < $lt) {
                    if ($this->isNameBoundary($input, $open + 1 + $len)) {
                        $depth++;
                    }
                    $scan = $open + 1 + $len;
                }
                $depth--;
                if ($depth > 0) {
                    $pos = $lt + 2 + $len;
                    continue;
                }
            }
            return $lt;
        }
        return null;
    }

    /** True when the byte at $at cannot extend a tag name (or is EOF). */
    private function isNameBoundary(string $input, int $at): bool
    {
        if (!isset($input[$at])) {
            return true;
        }
        $c = $input[$at];
        return !(ctype_alnum($c) || $c === '-');
    }
}
