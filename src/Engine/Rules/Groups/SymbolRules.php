<?php
declare(strict_types=1);

namespace EMT\Engine\Rules\Groups;

use EMT\Engine\Rules\Gate;
use EMT\Engine\Rules\Rule;
use EMT\Engine\Rules\RuleContext;

/**
 * Port of EMT_Tret_Symbol. Pattern changes vs v2, all mechanical:
 *  - /e flags dropped, replacement expressions became closures;
 *  - `\>` as "right after a tag" context rewritten to the placeholder close
 *    codepoint \x{E001} (apostrophe rule).
 */
final class SymbolRules
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
                id: 'tm_replace',
                patterns: ['/([\040\t])?\(tm\)/i'],
                replacements: ['&trade;'],
                gate: Gate::anyCI('(tm)'),
            ),
            new Rule(
                id: 'r_sign_replace',
                patterns: ['/(.|^)\(r\)(.|$)/i'],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string => $m[1] . '&reg;' . $m[2],
                ],
                gate: Gate::anyCI('(r)'),
            ),
            new Rule(
                id: 'copy_replace',
                patterns: [
                    '/\((c|с)\)\s+/iu',
                    '/\((c|с)\)($|\.|,|!|\?)/iu',
                ],
                replacements: [
                    '&copy;&nbsp;',
                    '&copy;\2',
                ],
                gate: Gate::any('('),
            ),
            new Rule(
                id: 'apostrophe',
                patterns: ['/(\s|^|\x{E001}|\&rsquo\;)([a-zа-яё]{1,})\'([a-zа-яё]+)/ui'],
                replacements: ['\1\2&rsquo;\3'],
                cycled: true,
                gate: Gate::any("'"),
            ),
            new Rule(
                id: 'degree_f',
                patterns: ['/([0-9]+)F($|\s|\.|\,|\;|\:|\&nbsp\;|\?|\!)/u'],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        '' . $ctx->tag($m[1] . ' &deg;F', 'span', ['class' => 'nowrap']) . $m[2],
                ],
                gate: Gate::any('F'),
            ),
            new Rule(
                id: 'euro_symbol',
                patterns: ['€'],
                replacements: ['&euro;'],
                simpleReplace: true,
                caseSensitive: true,
            ),
            new Rule(
                id: 'arrows_symbols',
                patterns: ['/\-\>/', '/\<\-/', '/→/u', '/←/u'],
                replacements: ['&rarr;', '&larr;', '&rarr;', '&larr;'],
                gate: Gate::any('->', '<-', '→', '←'),
            ),
        ];
    }
}
