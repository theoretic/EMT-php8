<?php
declare(strict_types=1);

namespace EMT\Engine\Ir;

use EMT\Engine\Lexer\Token;
use EMT\Engine\Lexer\TokenType;

/**
 * Out-of-band placeholder grammar for non-text tokens inside the linearized
 * IR string: "\u{E000}<class><index>\u{E001}".
 *
 * U+E000/U+E001/U+E004 are Unicode private-use codepoints. Raw token bytes
 * never appear inside the IR text — only the class and the token-table index —
 * so no user input can forge or corrupt a placeholder (this replaces the
 * injectable %%%INTBLOCK%%% / base64 markers of the legacy engine). Literal
 * occurrences of the three PUA codepoints in input text are escaped with
 * U+E004 at ingest and restored at render, making the mapping total.
 */
final class Placeholder
{
    public const OPEN  = "\u{E000}";
    public const CLOSE = "\u{E001}";
    public const ESC   = "\u{E004}";

    /** Tag names rules distinguish; every other tag collapses to class "t". */
    private const NAMED_CLASSES = ['a', 'p', 'br', 'nobr'];

    /** Matches one placeholder; capture 1 = class, capture 2 = index. */
    public const PATTERN = '/\x{E000}([a-z\/]{1,6})([0-9]+)\x{E001}/u';

    public static function forToken(Token $token, int $index): string
    {
        return self::OPEN . self::classOf($token) . $index . self::CLOSE;
    }

    public static function classOf(Token $token): string
    {
        if ($token->type === TokenType::Tag && $token->name !== null
            && in_array($token->name, self::NAMED_CLASSES, true)
        ) {
            return ($token->closing ? '/' : '') . $token->name;
        }
        if ($token->type === TokenType::Tag) {
            return $token->closing ? '/t' : 't';
        }
        return 'prot';
    }

    /** Escape literal PUA codepoints in input text (total, reversible). */
    public static function escapeText(string $text): string
    {
        return str_replace(
            [self::ESC, self::OPEN, self::CLOSE],
            [self::ESC . '4', self::ESC . '0', self::ESC . '1'],
            $text
        );
    }

    /** Reverse escapeText(). */
    public static function unescapeText(string $text): string
    {
        return preg_replace_callback(
            '/\x{E004}([014])/u',
            static fn(array $m): string => match ($m[1]) {
                '0' => self::OPEN,
                '1' => self::CLOSE,
                '4' => self::ESC,
            },
            $text
        ) ?? $text;
    }
}
