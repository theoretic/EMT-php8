<?php
declare(strict_types=1);

namespace EMT\Engine\Rules\Groups;

use EMT\Engine\Rules\Rule;
use EMT\Engine\Rules\RuleContext;

/**
 * Port of EMT_Tret_OptAlign. oaquote_extra's paragraph-tag probe becomes the
 * placeholder class pattern (legacy BASE64_PARAGRAPH_TAG equality covered
 * bare input <p> and rule-created paragraphs alike).
 */
final class OptAlignRules
{
    /** @return array<string, string> */
    public static function classes(): array
    {
        return [
            'oa_obracket_sp_s' => 'margin-right:0.3em;',
            'oa_obracket_sp_b' => 'margin-left:-0.3em;',
            'oa_obracket_nl_b' => 'margin-left:-0.3em;',
            'oa_comma_b'       => 'margin-right:-0.2em;',
            'oa_comma_e'       => 'margin-left:0.2em;',
            'oa_oquote_nl'     => 'margin-left:-0.44em;',
            'oa_oqoute_sp_s'   => 'margin-right:0.44em;',
            'oa_oqoute_sp_q'   => 'margin-left:-0.44em;',
        ];
    }

    /** @return list<Rule> */
    public static function rules(): array
    {
        return [
            new Rule(
                id: 'oa_oquote',
                patterns: [
                    '/([a-zа-яё\-]{3,})(\040|\&nbsp\;|\t)(\&laquo\;)/ui',
                    '/(\n|\r|^)(\&laquo\;)/ui',
                ],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . $ctx->tag($m[2], 'span', ['class' => 'oa_oqoute_sp_s'])
                        . $ctx->tag($m[3], 'span', ['class' => 'oa_oqoute_sp_q']),
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . $ctx->tag($m[2], 'span', ['class' => 'oa_oquote_nl']),
                ],
            ),
            new Rule(
                id: 'oa_oquote_extra',
                procedure: self::oaquoteExtra(...),
            ),
            new Rule(
                id: 'oa_obracket_coma',
                patterns: [
                    '/(\040|\&nbsp\;|\t)\(/i',
                    '/(\n|\r|^)\(/i',
                ],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        $ctx->tag($m[1], 'span', ['class' => 'oa_obracket_sp_s'])
                        . $ctx->tag('(', 'span', ['class' => 'oa_obracket_sp_b']),
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . $ctx->tag('(', 'span', ['class' => 'oa_obracket_nl_b']),
                ],
            ),
        ];
    }

    /**
     * Port of EMT_Tret_OptAlign::oaquote_extra(): a « right after a paragraph
     * open tag hangs into the margin. The legacy replacement drops the matched
     * whitespace ($m[2]) — kept.
     */
    private static function oaquoteExtra(RuleContext $ctx): void
    {
        $text = preg_replace_callback(
            '/(\x{E000}p[0-9]+\x{E001})([\040\t]+)?(\&laquo\;)/u',
            static fn(array $m): string => $m[1] . $ctx->tag($m[3], 'span', ['class' => 'oa_oquote_nl']),
            $ctx->doc->text()
        );
        if ($text !== null) {
            $ctx->doc->setText($text);
        }
    }
}
