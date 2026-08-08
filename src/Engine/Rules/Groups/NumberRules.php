<?php
declare(strict_types=1);

namespace EMT\Engine\Rules\Groups;

use EMT\Engine\Rules\Gate;
use EMT\Engine\Rules\Rule;
use EMT\Engine\Rules\RuleContext;

/**
 * Port of EMT_Tret_Number. Negated classes that excluded `<`/`>` (tag
 * boundaries in the legacy encoded text) additionally exclude the placeholder
 * codepoints \x{E000}/\x{E001}; /e replacements became closures.
 */
final class NumberRules
{
    /** @return list<Rule> */
    public static function rules(): array
    {
        return [
            new Rule(
                id: 'minus_between_nums',
                patterns: ['/(\d+)\-(\d)/i'],
                replacements: ['\1&minus;\2'],
                gate: Gate::digits(),
            ),
            new Rule(
                id: 'minus_in_numbers_range',
                patterns: ['/(^|\s|\&nbsp\;)(\&minus\;|\-)(\d+)(\.\.\.|\&hellip\;)(\s|\&nbsp\;)?(\+|\-|\&minus\;)?(\d+)/i'],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . '&minus;' . $m[3] . $m[4] . $m[5] . ($m[6] == '+' ? $m[6] : '&minus;') . $m[7],
                ],
                gate: Gate::digits(),
            ),
            new Rule(
                id: 'auto_times_x',
                patterns: ['/([^a-zA-Z><\x{E000}\x{E001}]|^)(\&times\;)?(\d+)(\040*)(x|х)(\040*)(\d+)([^a-zA-Z><\x{E000}\x{E001}]|$)/u'],
                replacements: ['\1\2\3&times;\7\8'],
                cycled: true,
                gate: Gate::any('x', 'х'),
            ),
            new Rule(
                id: 'numeric_sub',
                patterns: ['/([a-zа-яё0-9])\_([\d]{1,3})([^@а-яёa-z0-9]|$)/iu'],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . $ctx->tag($ctx->tag($m[2], 'small'), 'sub') . $m[3],
                ],
                gate: Gate::any('_'),
            ),
            new Rule(
                id: 'numeric_sup',
                patterns: ['/([a-zа-яё0-9])\^([\d]{1,3})([^а-яёa-z0-9]|$)/iu'],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . $ctx->tag($ctx->tag($m[2], 'small'), 'sup') . $m[3],
                ],
                gate: Gate::any('^'),
            ),
            new Rule(
                id: 'simple_fraction',
                patterns: ['/(^|\D)1\/(2|4)(\D)/', '/(^|\D)3\/4(\D)/'],
                replacements: ['\1&frac1\2;\3', '\1&frac34;\2'],
                gate: Gate::any('1/', '3/'),
            ),
            new Rule(
                id: 'math_chars',
                patterns: ['/!=/', '/\<=/', '/([^=]|^)\>=/', '/~=/', '/\+-/'],
                replacements: ['&ne;', '&le;', '\1&ge;', '&cong;', '&plusmn;'],
                gate: Gate::any('!=', '<=', '>=', '~=', '+-'),
            ),
            new Rule(
                id: 'thinsp_between_number_triads',
                patterns: ['/([0-9]{1,3}( [0-9]{3}){1,})(.|$)/u'],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[3] == '-' ? $m[0] : str_replace(' ', '&thinsp;', $m[1]) . $m[3],
                ],
                gate: Gate::digits(),
            ),
            new Rule(
                id: 'thinsp_between_no_and_number',
                patterns: ['/(№|\&#8470\;)(\s|&nbsp;)*(\d)/iu'],
                replacements: ['&#8470;&thinsp;\3'],
                gate: Gate::any('№', '&#8470;'),
            ),
            new Rule(
                id: 'thinsp_between_sect_and_number',
                patterns: ['/(§|\&sect\;)(\s|&nbsp;)*(\d+|[IVX]+|[a-zа-яё]+)/ui'],
                replacements: ['&sect;&thinsp;\3'],
                gate: Gate::anyCI('§', '&sect;'),
            ),
        ];
    }
}
