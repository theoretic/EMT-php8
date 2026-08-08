<?php
declare(strict_types=1);

namespace EMT\Engine\Rules\Groups;

use EMT\EMT_Lib;
use EMT\Engine\Rules\Rule;
use EMT\Engine\Rules\RuleContext;

/**
 * Port of EMT_Tret_Space. Tag-boundary atoms gained their placeholder
 * equivalents (`>` context also matches \x{E001}, `[^\<]` also excludes
 * \x{E000}); /e replacements became closures. EMT_Lib::strtolower is kept
 * (including its known Ы bug) for parity.
 */
final class SpaceRules
{
    /** Legacy EMT_Tret_Space::$domain_zones, verbatim (duplicate 'info' included). */
    private const DOMAIN_ZONES = ['ru','ру','ком','орг','уа','ua','uk','co','fr','com','net','edu','gov','org','mil','int','info','biz','info','name','pro'];

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
                id: 'nobr_twosym_abbr',
                patterns: ['/([a-zA-Zа-яёА-ЯЁ])(\040|\t)+([A-ZА-ЯЁ]{2})([\s\;\.\?\!\:\(\"]|\&(ra|ld)quo\;|$)/u'],
                replacements: ['\1&nbsp;\3\4'],
            ),
            new Rule(
                id: 'remove_space_before_punctuationmarks',
                patterns: ['/((\040|\t|\&nbsp\;)+)([\,\:\.\;\?])(\s+|$)/'],
                replacements: ['\3\4'],
            ),
            new Rule(
                id: 'autospace_after_comma',
                patterns: [
                    '/(\040|\t|\&nbsp\;)\,([а-яёa-z0-9])/iu',
                    '/([^0-9])\,([а-яёa-z0-9])/iu',
                ],
                replacements: [
                    ', \2',
                    '\1, \2',
                ],
            ),
            new Rule(
                id: 'autospace_after_pmarks',
                patterns: ['/(\040|\t|\&nbsp\;|^|\n)([a-zа-яё0-9]+)(\040|\t|\&nbsp\;)?(\:|\)|\,|\&hellip\;|(?:\!|\?)+)([а-яёa-z])/iu'],
                replacements: ['\1\2\4 \5'],
            ),
            new Rule(
                id: 'autospace_after_dot',
                patterns: [
                    '/(\040|\t|\&nbsp\;|^)([a-zа-яё0-9]+)(\040|\t|\&nbsp\;)?\.([а-яёa-z]{5,})($|[^a-zа-яё])/iu',
                    '/(\040|\t|\&nbsp\;|^)([a-zа-яё0-9]+)\.([а-яёa-z]{1,4})($|[^a-zа-яё])/iu',
                ],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . $m[2] . '.' . ($m[5] == '.' ? '' : ' ') . $m[4] . $m[5],
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . $m[2] . '.'
                        . (in_array(EMT_Lib::strtolower($m[3]), self::DOMAIN_ZONES) ? '' : ($m[4] == '.' ? '' : ' '))
                        . $m[3] . $m[4],
                ],
            ),
            new Rule(
                id: 'autospace_after_hellips',
                patterns: ['/([\?\!]\.\.)([а-яёa-z])/iu'],
                replacements: ['\1 \2'],
            ),
            new Rule(
                id: 'many_spaces_to_one',
                patterns: ['/(\040|\t)+/'],
                replacements: [' '],
            ),
            new Rule(
                id: 'clear_percent',
                patterns: ['/(\d+)([\t\040]+)\%/'],
                replacements: ['\1%'],
            ),
            new Rule(
                id: 'nbsp_before_open_quote',
                patterns: ['/(^|\040|\t|>|\x{E001})([a-zа-яё]{1,2})\040(\&laquo\;|\&bdquo\;)/u'],
                replacements: ['\1\2&nbsp;\3'],
            ),
            new Rule(
                id: 'nbsp_before_pretext',
                patterns: ['/([a-zA-Zа-яёА-ЯЁ])((\s|&nbsp;|\t)+)(без|в|для|до|за|из|из-за|из-под|к|ко|на|над|о|об|от|перед|по|под|пред|при|про|с|со|у|через)(\s|&nbsp;)/iu'],
                replacements: ['\1\3\4&nbsp;'],
            ),
            new Rule(
                id: 'nbsp_after_numbers',
                patterns: ['/(\d+)((\s|&nbsp;|\t)+)([a-zA-Zа-яёА-ЯЁ])/iu'],
                replacements: ['\1&nbsp;\4'],
            ),
            new Rule(
                id: 'nbsp_before_month',
                patterns: ['/(\d)(\s)+(января|февраля|марта|апреля|мая|июня|июля|августа|сентября|октября|ноября|декабря)([^\<\x{E000}]|$)/iu'],
                replacements: ['\1&nbsp;\3\4'],
            ),
            new Rule(
                id: 'spaces_on_end',
                patterns: ['/ +$/'],
                replacements: [''],
            ),
            new Rule(
                id: 'no_space_posle_hellip',
                patterns: ['/(\&laquo\;|\&bdquo\;)( |\&nbsp\;)?\&hellip\;( |\&nbsp\;)?([a-zа-яё])/ui'],
                replacements: ['\1&hellip;\4'],
            ),
            new Rule(
                id: 'space_posle_goda',
                patterns: ['/(^|\040|\&nbsp\;)([0-9]{3,4})(год([ауе]|ом)?)([^a-zа-яё]|$)/ui'],
                replacements: ['\1\2 \3\5'],
            ),
        ];
    }
}
