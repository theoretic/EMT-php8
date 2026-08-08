<?php
declare(strict_types=1);

namespace EMT\Engine\Rules\Groups;

use EMT\Engine\Rules\Gate;
use EMT\Engine\Rules\Rule;
use EMT\Engine\Rules\RuleContext;

/**
 * Port of EMT_Tret_Abbr. `\>`/`\<` tag-boundary atoms gained placeholder
 * equivalents; /e replacements became closures.
 */
final class AbbrRules
{
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
                id: 'nobr_abbreviation',
                patterns: ['/(\s+|^|\>|\x{E001})(\d+)(\040|\t)*(dpi|lpi)([\s\;\.\?\!\:\(]|$)/iu'],
                replacements: ['\1\2&nbsp;\4\5'],
                gate: Gate::anyCI('dpi', 'lpi'),
            ),
            new Rule(
                id: 'nobr_acronym',
                patterns: ['/(\s|^|\>|\x{E001}|\()(гл|стр|рис|илл?|ст|п|с)\.(\040|\t)*(\d+)(\&nbsp\;|\s|\.|\,|\?|\!|$)/iu'],
                replacements: ['\1\2.&nbsp;\4\5'],
                gate: Gate::digits(),
            ),
            new Rule(
                id: 'nobr_sm_im',
                patterns: ['/(\s|^|\>|\x{E001}|\()(см|им)\.(\040|\t)*([а-яё0-9a-z]+)(\s|\.|\,|\?|\!|$)/iu'],
                replacements: ['\1\2.&nbsp;\4\5'],
            ),
            new Rule(
                id: 'nobr_locations',
                patterns: [
                    '/(\s|^|\>|\x{E001})(г|ул|пер|просп|пл|бул|наб|пр|ш|туп)\.(\040|\t)*([а-яё0-9a-z]+)(\s|\.|\,|\?|\!|$)/iu',
                    '/(\s|^|\>|\x{E001})(б\-р|пр\-кт)(\040|\t)*([а-яё0-9a-z]+)(\s|\.|\,|\?|\!|$)/iu',
                    '/(\s|^|\>|\x{E001})(д|кв|эт)\.(\040|\t)*(\d+)(\s|\.|\,|\?|\!|$)/iu',
                ],
                replacements: [
                    '\1\2.&nbsp;\4\5',
                    '\1\2&nbsp;\4\5',
                    '\1\2.&nbsp;\4\5',
                ],
            ),
            new Rule(
                id: 'nbsp_before_unit',
                patterns: [
                    '/(\s|^|\>|\x{E001}|\&nbsp\;|\,)(\d+)( |\&nbsp\;)?(м|мм|см|дм|км|гм|km|dm|cm|mm)(\s|\.|\!|\?|\,|$|\&plusmn\;|\;|\<|\x{E000})/iu',
                    '/(\s|^|\>|\x{E001}|\&nbsp\;|\,)(\d+)( |\&nbsp\;)?(м|мм|см|дм|км|гм|km|dm|cm|mm)([32]|&sup3;|&sup2;)(\s|\.|\!|\?|\,|$|\&plusmn\;|\;|\<|\x{E000})/iu',
                ],
                replacements: [
                    '\1\2&nbsp;\4\5',
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . $m[2] . '&nbsp;' . $m[4]
                        . ($m[5] == '3' || $m[5] == '2' ? '&sup' . $m[5] . ';' : $m[5]) . $m[6],
                ],
                gate: Gate::digits(),
            ),
            new Rule(
                id: 'nbsp_before_weight_unit',
                patterns: ['/(\s|^|\>|\x{E001}|\&nbsp\;|\,)(\d+)( |\&nbsp\;)?(г|кг|мг|т)(\s|\.|\!|\?|\,|$|\&nbsp\;|\;)/iu'],
                replacements: ['\1\2&nbsp;\4\5'],
                gate: Gate::digits(),
            ),
            new Rule(
                id: 'nobr_before_unit_volt',
                patterns: ['/(\d+)([вВ]| В)(\s|\.|\!|\?|\,|$)/u'],
                replacements: ['\1&nbsp;В\3'],
                gate: Gate::digits(),
            ),
            new Rule(
                id: 'ps_pps',
                patterns: ['/(^|\040|\t|\>|\x{E001}|\r|\n)(p\.\040?)(p\.\040?)?(s\.)([^\<\x{E000}])/iu'],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . $ctx->tag(
                            trim($m[2]) . ' ' . ($m[3] ? trim($m[3]) . ' ' : '') . $m[4],
                            'span',
                            ['class' => 'nowrap']
                        ) . $m[5],
                ],
                gate: Gate::anyCI('p.'),
            ),
            new Rule(
                id: 'nobr_vtch_itd_itp',
                patterns: [
                    '/(^|\s|\&nbsp\;)и( |\&nbsp\;)т\.?[ ]?д(\.|$|\s|\&nbsp\;)/u',
                    '/(^|\s|\&nbsp\;)и( |\&nbsp\;)т\.?[ ]?п(\.|$|\s|\&nbsp\;)/u',
                    '/(^|\s|\&nbsp\;)в( |\&nbsp\;)т\.?[ ]?ч(\.|$|\s|\&nbsp\;)/u',
                ],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . $ctx->tag('и т. д.', 'span', ['class' => 'nowrap']) . ($m[3] != '.' ? $m[3] : ''),
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . $ctx->tag('и т. п.', 'span', ['class' => 'nowrap']) . ($m[3] != '.' ? $m[3] : ''),
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . $ctx->tag('в т. ч.', 'span', ['class' => 'nowrap']) . ($m[3] != '.' ? $m[3] : ''),
                ],
                cycled: true,
            ),
            new Rule(
                id: 'nbsp_te',
                patterns: ['/(^|\s|\&nbsp\;)([тТ])\.?[ ]?е\./u'],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . $ctx->tag($m[2] . '. е.', 'span', ['class' => 'nowrap']),
                ],
            ),
            new Rule(
                id: 'nbsp_money_abbr',
                patterns: ['/(\d)((\040|\&nbsp\;)?(тыс|млн|млрд)\.?(\040|\&nbsp\;)?)?(\040|\&nbsp\;)?(руб\.|долл\.|евро|€|&euro;|\$|у[\.]? ?е[\.]?)/iu'],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1]
                        . ($m[4] ? '&nbsp;' . $m[4] . ($m[4] == 'тыс' ? '.' : '') : '')
                        . '&nbsp;'
                        . (!preg_match('#у[\.]? ?е[\.]?#iu', $m[7]) ? $m[7] : 'у.е.'),
                ],
                gate: Gate::digits(),
            ),
            new Rule(
                id: 'nbsp_money_abbr_rev',
                patterns: ['/(€|&euro;|\$)\s?(\d)/iu'],
                replacements: ['\1&nbsp;\2'],
                gate: Gate::anyCI('€', '&euro;', '$'),
            ),
            new Rule(
                id: 'nbsp_org_abbr',
                patterns: ['/([^a-zA-Zа-яёА-ЯЁ]|^)(ООО|ЗАО|ОАО|НИИ|ПБОЮЛ) ([a-zA-Zа-яёА-ЯЁ]|\"|\&laquo\;|\&bdquo\;|<|\x{E000})/u'],
                replacements: ['\1\2&nbsp;\3'],
                gate: Gate::any('ООО', 'ЗАО', 'ОАО', 'НИИ', 'ПБОЮЛ'),
            ),
            new Rule(
                id: 'nobr_gost',
                patterns: [
                    '/(\040|\t|\&nbsp\;|^)ГОСТ( |\&nbsp\;)?(\d+)((\-|\&minus\;|\&mdash\;)(\d+))?(( |\&nbsp\;)(\-|\&mdash\;))?/iu',
                    '/(\040|\t|\&nbsp\;|^|\>|\x{E001})ГОСТ( |\&nbsp\;)?(\d+)(\-|\&minus\;|\&mdash\;)(\d+)/iu',
                ],
                replacements: [
                    // v2 quirk kept: isset() is true for a participating-but-empty
                    // group, so an empty $m[6]/$m[7] still appends the dash.
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . $ctx->tag(
                            'ГОСТ ' . $m[3]
                            . (isset($m[6]) ? '&ndash;' . $m[6] : '')
                            . (isset($m[7]) ? ' &mdash;' : ''),
                            'span',
                            ['class' => 'nowrap']
                        ),
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . 'ГОСТ ' . $m[3] . '&ndash;' . $m[5],
                ],
                gate: Gate::digits(),
            ),
        ];
    }
}
