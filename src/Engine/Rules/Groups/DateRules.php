<?php
declare(strict_types=1);

namespace EMT\Engine\Rules\Groups;

use EMT\Engine\Rules\Gate;
use EMT\Engine\Rules\Rule;
use EMT\Engine\Rules\RuleContext;

/**
 * Port of EMT_Tret_Date. /e replacements became closures; `[^\>]`/`[^\<]`
 * tag-boundary classes additionally exclude the placeholder codepoints.
 */
final class DateRules
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
                id: 'years',
                patterns: ['/(с|по|период|середины|начала|начало|конца|конец|половины|в|между|\([cс]\)|\&copy\;)(\s+|\&nbsp\;)([\d]{4})(-|\&mdash\;|\&minus\;)([\d]{4})(( |\&nbsp\;)?(г\.г\.|гг\.|гг|г\.|г)([^а-яёa-z]))?/ui'],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . $m[2]
                        . (intval($m[3]) >= intval($m[5]) ? $m[3] . $m[4] . $m[5] : $m[3] . '&mdash;' . $m[5])
                        . (isset($m[6]) ? '&nbsp;гг.' : '')
                        . ($m[9] ?? ''),
                ],
                gate: Gate::digits(),
            ),
            new Rule(
                id: 'mdash_month_interval',
                patterns: ['/((январ|феврал|сентябр|октябр|ноябр|декабр)([ьяюе]|[её]м)|(апрел|июн|июл)([ьяюе]|ем)|(март|август)([ауе]|ом)?|ма[йяюе]|маем)\-((январ|феврал|сентябр|октябр|ноябр|декабр)([ьяюе]|[её]м)|(апрел|июн|июл)([ьяюе]|ем)|(март|август)([ауе]|ом)?|ма[йяюе]|маем)/iu'],
                replacements: ['\1&mdash;\8'],
                disabled: true,
            ),
            new Rule(
                id: 'nbsp_and_dash_month_interval',
                patterns: ['/([^\>\x{E001}]|^)(\d+)(\-|\&minus\;|\&mdash\;)(\d+)( |\&nbsp\;)(января|февраля|марта|апреля|мая|июня|июля|августа|сентября|октября|ноября|декабря)([^\<\x{E000}]|$)/iu'],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . $ctx->tag($m[2] . '&mdash;' . $m[4] . ' ' . $m[6], 'span', ['class' => 'nowrap']) . $m[7],
                ],
                disabled: true,
            ),
            new Rule(
                id: 'nobr_year_in_date',
                patterns: [
                    '/(\s|\&nbsp\;)([0-9]{2}\.[0-9]{2}\.([0-9]{2})?[0-9]{2})(\s|\&nbsp\;)?г(\.|\s|\&nbsp\;)/iu',
                    '/(\s|\&nbsp\;)([0-9]{2}\.[0-9]{2}\.([0-9]{2})?[0-9]{2})(\s|\&nbsp\;|\.(\s|\&nbsp\;|$)|$)/iu',
                ],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . $ctx->tag($m[2] . ' г.', 'span', ['class' => 'nowrap']) . (($m[5] ?? '') === '.' ? '' : ' '),
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . $ctx->tag($m[2], 'span', ['class' => 'nowrap']) . $m[4],
                ],
                gate: Gate::digits(),
            ),
            new Rule(
                id: 'nbsp_posle_goda_abbr',
                patterns: ['/(^|\040|\&nbsp\;|\"|\&laquo\;)([0-9]{3,4})[ ]?(г\.)([^a-zа-яё]|$)/ui'],
                replacements: ['\1\2&nbsp;\3\4'],
                gate: Gate::digits(),
            ),
        ];
    }
}
