<?php
declare(strict_types=1);

namespace EMT\Engine\Rules\Groups;

use EMT\Engine\Rules\Rule;

/**
 * Port of EMT_Tret_Punctmark. `\<` as "right before a tag" context rewritten
 * to the placeholder open codepoint \x{E000}.
 */
final class PunctmarkRules
{
    /** @return list<Rule> */
    public static function rules(): array
    {
        return [
            new Rule(
                id: 'auto_comma',
                patterns: ['/([a-zа-яё])(\s|&nbsp;)(но|а)(\s|&nbsp;)/iu'],
                replacements: ['\1,\2\3\4'],
            ),
            new Rule(
                id: 'punctuation_marks_limit',
                patterns: ['/([\!\.\?]){4,}/'],
                replacements: ['\1\1\1'],
            ),
            new Rule(
                id: 'punctuation_marks_base_limit',
                patterns: ['/([\,\:\;]){2,}/'],
                replacements: ['\1'],
            ),
            new Rule(
                id: 'hellip',
                patterns: ['...'],
                replacements: ['&hellip;'],
                simpleReplace: true,
                caseSensitive: true,
            ),
            new Rule(
                id: 'fix_excl_quest_marks',
                patterns: ['/([a-zа-яё0-9])\!\?(\s|$|\x{E000})/ui'],
                replacements: ['\1?!\2'],
            ),
            new Rule(
                id: 'fix_pmarks',
                patterns: [
                    '/([^\!\?])\.\./',
                    '/([a-zа-яё0-9])(\!|\.)(\!|\.|\?)(\s|$|\x{E000})/ui',
                    '/([a-zа-яё0-9])(\?)(\?)(\s|$|\x{E000})/ui',
                ],
                replacements: [
                    '\1.',
                    '\1\2\4',
                    '\1\2\4',
                ],
            ),
            new Rule(
                id: 'fix_brackets',
                patterns: ['/(\()(\040|\t)+/', '/(\040|\t)+(\))/'],
                replacements: ['\1', '\2'],
            ),
            new Rule(
                id: 'fix_brackets_space',
                patterns: ['/([a-zа-яё])(\()/iu'],
                replacements: ['\1 \2'],
            ),
            new Rule(
                id: 'dot_on_end',
                patterns: ['/([a-zа-яё0-9])(\040|\t|\&nbsp\;)*$/ui'],
                replacements: ['\1.'],
                disabled: true,
            ),
        ];
    }
}
