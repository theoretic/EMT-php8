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

    /** Matches one placeholder; capture 1 = class, capture 2 = index. */
    public const PATTERN = '/\x{E000}([a-z\/]{1,6})([0-9]+)\x{E001}/u';

    public static function forToken(Token $token, int $index, bool $created = false): string
    {
        return self::OPEN . self::classOf($token, $created) . $index . self::CLOSE;
    }

    /**
     * Class assignment mirrors the legacy engine's string-equality semantics:
     *  - 'a': input tags whose name starts with "a" (the legacy `%%___` marker
     *    keyed off the first character, so <abbr>/<article> were marked too).
     *    Rule-created tags were plain base64 without the marker — never 'a'.
     *  - 'p'/'br'/'nobr': only the exact bare forms <p>, </p>, <br />, <nobr>,
     *    </nobr> — the only forms whose legacy base64 encoding equals the
     *    BASE64_*_TAG constants rules match against. <p class="x"> is generic.
     *  - 'ib': rule-created internal blocks (legacy EMT_Lib::iblock()).
     *  - 'prot': protected bodies, comments, CDATA, doctypes.
     */
    public static function classOf(Token $token, bool $created = false): string
    {
        if ($token->type !== TokenType::Tag) {
            return 'prot';
        }
        $slash = $token->closing ? '/' : '';
        if (!$created && $token->name !== null && $token->name[0] === 'a') {
            return $slash . 'a';
        }
        $bare = match ($token->raw) {
            '<p>', '</p>' => 'p',
            '<br />' => 'br',
            '<nobr>', '</nobr>' => 'nobr',
            default => null,
        };
        if ($bare !== null) {
            return $slash . $bare;
        }
        return $slash . 't';
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
