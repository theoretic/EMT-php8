<?php
declare(strict_types=1);

namespace EMT\Engine\Rules\Groups;

use EMT\Engine\Rules\Rule;
use EMT\Engine\Rules\RuleContext;

/**
 * Port of EMT_Tret_Dash. `\>`/`\<` tag-boundary atoms gained placeholder
 * equivalents; the shared /e replacement became one closure.
 */
final class DashRules
{
    private static function nbspAwareHyphenJoin(): \Closure
    {
        return static fn(array $m, RuleContext $ctx): string =>
            ($m[1] == '&nbsp;' ? ' ' : $m[1]) . $m[2] . '-' . $m[4] . ($m[5] == '&nbsp;' ? ' ' : $m[5]);
    }

    /** @return list<Rule> */
    public static function rules(): array
    {
        $join = self::nbspAwareHyphenJoin();
        return [
            new Rule(
                id: 'double_minus_to_html_mdash',
                patterns: ['/ [\-]{2} /iu'],
                replacements: [' &mdash; '],
            ),
            new Rule(
                id: 'mdash_symbol_to_html_mdash',
                patterns: ['/ — /iu'],
                replacements: [' &mdash; '],
            ),
            new Rule(
                id: 'mdash',
                patterns: [
                    '/([a-zа-яё0-9]+|\,|\:|\)|\&(ra|ld)quo\;|\|\"|\>|\x{E001})(\040|\t)(—|\-|\&mdash\;)(\s|$|\<|\x{E000})/ui',
                    '/(\,|\:|\)|\")(—|\-|\&mdash\;)(\s|$|\<|\x{E000})/ui',
                ],
                replacements: [
                    '\1&nbsp;&mdash;\5',
                    '\1&nbsp;&mdash;\3',
                ],
            ),
            new Rule(
                id: 'mdash_2',
                patterns: ['/(\n|\r|^|\>|\x{E001})(\-|\&mdash\;)(\t|\040)/u'],
                replacements: ['\1&mdash;&nbsp;'],
            ),
            new Rule(
                id: 'mdash_3',
                patterns: ['/(\.|\!|\?|\&hellip\;)(\040|\t|\&nbsp\;)(\-|\&mdash\;)(\040|\t|\&nbsp\;)/'],
                replacements: ['\1 &mdash;&nbsp;'],
            ),
            new Rule(
                id: 'iz_za_pod',
                patterns: ['/(\s|\&nbsp\;|\>|\x{E001}|^)(из)(\040|\t|\&nbsp\;)\-?(за|под)([\.\,\!\?\:\;]|\040|\&nbsp\;)/ui'],
                replacements: [$join],
            ),
            new Rule(
                id: 'to_libo_nibud',
                patterns: ['/(\s|^|\&nbsp\;|\>|\x{E001})(кто|кем|когда|зачем|почему|как|что|чем|где|чего|кого)\-?(\040|\t|\&nbsp\;)\-?(то|либо|нибудь)([\.\,\!\?\;]|\040|\&nbsp\;|$)/ui'],
                replacements: [$join],
                cycled: true,
            ),
            new Rule(
                id: 'koe_kak',
                patterns: [
                    '/(\s|^|\&nbsp\;|\>|\x{E001})(кое)\-?(\040|\t|\&nbsp\;)\-?(как)([\.\,\!\?\;]|\040|\&nbsp\;|$)/ui',
                    '/(\s|^|\&nbsp\;|\>|\x{E001})(кой)\-?(\040|\t|\&nbsp\;)\-?(кого)([\.\,\!\?\;]|\040|\&nbsp\;|$)/ui',
                    '/(\s|^|\&nbsp\;|\>|\x{E001})(вс[её])\-?(\040|\t|\&nbsp\;)\-?(таки)([\.\,\!\?\;]|\040|\&nbsp\;|$)/ui',
                ],
                replacements: [$join, $join, $join],
                cycled: true,
            ),
            new Rule(
                id: 'ka_de_kas',
                patterns: [
                    '/(\s|^|\&nbsp\;|\>|\x{E001})([а-яё]+)(\040|\t|\&nbsp\;)(ка)([\.\,\!\?\;]|\040|\&nbsp\;|$)/ui',
                    '/(\s|^|\&nbsp\;|\>|\x{E001})([а-яё]+)(\040|\t|\&nbsp\;)(де)([\.\,\!\?\;]|\040|\&nbsp\;|$)/ui',
                    '/(\s|^|\&nbsp\;|\>|\x{E001})([а-яё]+)(\040|\t|\&nbsp\;)(кась)([\.\,\!\?\;]|\040|\&nbsp\;|$)/ui',
                ],
                replacements: [$join, $join, $join],
                disabled: true,
            ),
        ];
    }
}
