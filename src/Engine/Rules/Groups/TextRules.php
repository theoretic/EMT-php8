<?php
declare(strict_types=1);

namespace EMT\Engine\Rules\Groups;

use EMT\EMT_Lib;
use EMT\Engine\Rules\Rule;
use EMT\Engine\Rules\RuleContext;

/**
 * Port of EMT_Tret_Text. Paragraph/breakline synthesis works on placeholder
 * classes: 'p' covers bare input <p> and rule-created paragraphs exactly like
 * the legacy BASE64_PARAGRAPH_TAG string equality did; inter-paragraph
 * whitespace is preserved through 'ib' internal blocks (legacy iblock()).
 */
final class TextRules
{
    private const P_OPEN  = '\x{E000}p[0-9]+\x{E001}';
    private const P_CLOSE = '\x{E000}\/p[0-9]+\x{E001}';
    private const IB      = '\x{E000}ib[0-9]+\x{E001}';
    private const BR      = '\x{E000}br[0-9]+\x{E001}';

    /** @return array<string, string> */
    public static function classes(): array
    {
        return ['nowrap' => 'word-spacing:nowrap;'];
    }

    /** @return list<Rule> */
    public static function rules(): array
    {
        return [
            new Rule(
                id: 'auto_links',
                patterns: ['/(\s|^)(http|ftp|mailto|https)(:\/\/)([^\s\,\!\<\x{E000}]{4,})(\s|\.|\,|\!|\?|\<|\x{E000}|$)/iu'],
                replacements: [
                    static function (array $m, RuleContext $ctx): string {
                        $url = substr($m[4], -1) == '.' ? substr($m[4], 0, -1) : $m[4];
                        return $m[1]
                            . $ctx->tag($url, 'a', ['href' => $m[2] . $m[3] . $url])
                            . (substr($m[4], -1) == '.' ? '.' : '')
                            . $m[5];
                    },
                ],
            ),
            new Rule(
                id: 'email',
                patterns: ['/(\s|^|\&nbsp\;|\()([a-z0-9\-\_\.]{2,})\@([a-z0-9\-\.]{2,})\.([a-z]{2,6})(\)|\s|\.|\,|\!|\?|$|\<|\x{E000})/u'],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1]
                        . $ctx->tag(
                            $m[2] . '@' . $m[3] . '.' . $m[4],
                            'a',
                            ['href' => 'mailto:' . $m[2] . '@' . $m[3] . '.' . $m[4]]
                        )
                        . $m[5],
                ],
            ),
            new Rule(
                id: 'no_repeat_words',
                patterns: [
                    '/([а-яё]{3,})( |\t|\&nbsp\;)\1/iu',
                    '/(\s|\&nbsp\;|^|\.|\!|\?)(([А-ЯЁ])([а-яё]{2,}))( |\t|\&nbsp\;)(([а-яё])\4)/u',
                ],
                replacements: [
                    '\1',
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . ($m[7] === EMT_Lib::strtolower($m[3]) ? $m[2] : $m[2] . $m[5] . $m[6]),
                ],
                disabled: true,
            ),
            new Rule(
                id: 'paragraphs',
                procedure: self::buildParagraphs(...),
            ),
            new Rule(
                id: 'breakline',
                procedure: self::buildBrs(...),
            ),
        ];
    }

    /** Port of EMT_Tret_Text::do_paragraphs(). */
    private static function doParagraphs(string $text, RuleContext $ctx): string
    {
        $open  = $ctx->doc->createTag('<p>', 'p', false);
        $close = $ctx->doc->createTag('</p>', 'p', true);

        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);
        $text = $open . trim($text) . $close;
        $text = preg_replace_callback(
            '/([\040\t]+)?(\n)+([\040\t]*)(\n)+/',
            static fn(array $m): string =>
                $m[1] . $close . $ctx->doc->createInternalBlock($m[2] . $m[3]) . $open,
            $text
        ) ?? $text;
        // Remove empty paragraphs (optionally holding only an internal block).
        return preg_replace(
            '/' . self::P_OPEN . '(' . self::IB . ')?' . self::P_CLOSE . '/su',
            '',
            $text
        ) ?? $text;
    }

    /** Port of EMT_Tret_Text::build_paragraphs(). */
    private static function buildParagraphs(RuleContext $ctx): void
    {
        $text = $ctx->doc->text();

        $hasOpen  = preg_match('/' . self::P_OPEN . '/u', $text, $mo, PREG_OFFSET_CAPTURE) === 1;
        $hasClose = preg_match_all('/' . self::P_CLOSE . '/u', $text, $mc, PREG_OFFSET_CAPTURE) > 0;

        if ($hasOpen && $hasClose) {
            $r = $mo[0][1];
            $openLen = strlen($mo[0][0]);
            $last = end($mc[0]);
            $p = $last[1];
            $closeLen = strlen($last[0]);

            $beg = substr($text, 0, $r);
            $end = substr($text, $p + $closeLen);
            $mid = substr($text, $r + $openLen, $p - ($r + $openLen));
            // v2 rewrote the boundary tags with the canonical BASE64 string —
            // byte-identical to what it found. Keep the original placeholders
            // so input tokens are preserved (render output is the same).
            $text =
                (trim($beg) ? self::doParagraphs($beg, $ctx) . "\n" : '')
                . $mo[0][0] . $mid . $last[0]
                . (trim($end) ? "\n" . self::doParagraphs($end, $ctx) : '');
        } else {
            $text = self::doParagraphs($text, $ctx);
        }
        $ctx->doc->setText($text);
    }

    /** Port of EMT_Tret_Text::build_brs(). */
    private static function buildBrs(RuleContext $ctx): void
    {
        $text = preg_replace_callback(
            '/(' . self::P_CLOSE . ')([\r\n \t]+)(' . self::P_OPEN . ')/msu',
            static fn(array $m): string => $m[1] . $ctx->doc->createInternalBlock($m[2]) . $m[3],
            $ctx->doc->text()
        ) ?? $ctx->doc->text();

        if (!preg_match('/' . self::BR . '/u', $text)) {
            $text = str_replace("\r\n", "\n", $text);
            $text = str_replace("\r", "\n", $text);
            $br = $ctx->doc->createTag('<br />', 'br', false);
            $text = preg_replace('/(\n)/', $br . "\n", $text) ?? $text;
        }
        $ctx->doc->setText($text);
    }
}
