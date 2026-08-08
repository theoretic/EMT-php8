<?php
declare(strict_types=1);

namespace EMT\Engine\Rules\Groups;

use EMT\Engine\Ir\Placeholder;
use EMT\Engine\Rules\Gate;
use EMT\Engine\Rules\Rule;
use EMT\Engine\Rules\RuleContext;

/**
 * Port of EMT_Tret_Nobr. The phone_builder tag-adjacency guards
 * ($m[1] == ">" / $m[11] == "<") also recognize the placeholder boundary
 * codepoints, which is what those characters mean in the v3 IR.
 */
final class NobrRules
{
    /** @return array<string, string> */
    public static function classes(): array
    {
        return ['nowrap' => 'word-spacing:nowrap;'];
    }

    private static function phoneBuilderClosure(): \Closure
    {
        return static function (array $m, RuleContext $ctx): string {
            $joined = $m[2] . ' ' . $m[4] . ' ' . $m[6] . '-' . $m[8] . '-' . $m[10];
            $adjacent = $m[1] == '>' || $m[1] == Placeholder::CLOSE
                || $m[11] == '<' || $m[11] == Placeholder::OPEN;
            return $m[1]
                . ($adjacent ? $joined : $ctx->tag($joined, 'span', ['class' => 'nowrap']))
                . $m[11];
        };
    }

    /** @return list<Rule> */
    public static function rules(): array
    {
        $phone = self::phoneBuilderClosure();
        return [
            new Rule(
                id: 'super_nbsp',
                patterns: ['/(\s|^|\&(la|bd)quo\;|\>|\x{E001}|\(|\&mdash\;\&nbsp\;)([a-zа-яё]{1,2}\s+)([a-zа-яё]{1,2}\s+)?([a-zа-яё0-9\-]{2,}|[0-9])/iu'],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . trim($m[3]) . '&nbsp;' . ($m[4] ? trim($m[4]) . '&nbsp;' : '') . $m[5],
                ],
            ),
            new Rule(
                id: 'nbsp_in_the_end',
                patterns: ['/([a-zа-яё0-9\-]{3,}) ([a-zа-яё]{1,2})\.( [A-ZА-ЯЁ]|$)/u'],
                replacements: ['\1&nbsp;\2.\3'],
            ),
            new Rule(
                id: 'phone_builder',
                patterns: [
                    '/([^\d\+]|^)([\+]?[0-9]{1,3})( |\&nbsp\;|\&thinsp\;)([0-9]{3,4}|\([0-9]{3,4}\))( |\&nbsp\;|\&thinsp\;)([0-9]{2,3})(-|\&minus\;)([0-9]{2})(-|\&minus\;)([0-9]{2})([^\d]|$)/u',
                    '/([^\d\+]|^)([\+]?[0-9]{1,3})( |\&nbsp\;|\&thinsp\;)([0-9]{3,4}|[0-9]{3,4})( |\&nbsp\;|\&thinsp\;)([0-9]{2,3})(-|\&minus\;)([0-9]{2})(-|\&minus\;)([0-9]{2})([^\d]|$)/u',
                ],
                replacements: [$phone, $phone],
                gate: Gate::digits(),
            ),
            new Rule(
                id: 'phone_builder_v2',
                patterns: ['/([^\d]|^)\+\s?([0-9]{1})\s?\(([0-9]{3,4})\)\s?(\d{3})(\d{2})(\d{2})([^\d]|$)/iu'],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1]
                        . $ctx->tag('+' . $m[2] . ' ' . $m[3] . ' ' . $m[4] . '-' . $m[5] . '-' . $m[6], 'span', ['class' => 'nowrap'])
                        . $m[7],
                ],
                gate: Gate::any('+'),
            ),
            new Rule(
                id: 'ip_address',
                patterns: ['/(\s|\&nbsp\;|^)(\d{0,3}\.\d{0,3}\.\d{0,3}\.\d{0,3})/iu'],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . self::nowrapIpAddress($ctx, $m[2]),
                ],
                gate: Gate::digits(),
            ),
            new Rule(
                id: 'dots_for_surname_abbr',
                patterns: [
                    '/(\s|^|\.|\,|\;|\:|\?|\!|\&nbsp\;)([А-ЯЁ])\.?(\s|\&nbsp\;)?([А-ЯЁ])(\s|\&nbsp\;)([А-ЯЁ][а-яё]+)(\s|$|\.|\,|\;|\:|\?|\!|\&nbsp\;)/u',
                    '/(\s|^|\.|\,|\;|\:|\?|\!|\&nbsp\;)([А-ЯЁ][а-яё]+)(\s|\&nbsp\;)([А-ЯЁ])\.?(\s|\&nbsp\;)?([А-ЯЁ])\.?(\s|$|\.|\,|\;|\:|\?|\!|\&nbsp\;)/u',
                ],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . $ctx->tag($m[2] . '. ' . $m[4] . '. ' . $m[6], 'span', ['class' => 'nowrap']) . $m[7],
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . $ctx->tag($m[2] . ' ' . $m[4] . '. ' . $m[6] . '.', 'span', ['class' => 'nowrap']) . $m[7],
                ],
                disabled: true,
            ),
            new Rule(
                id: 'spaces_nobr_in_surname_abbr',
                patterns: [
                    '/(\s|^|\.|\,|\;|\:|\?|\!|\&nbsp\;)([А-ЯЁ])\.(\s|\&nbsp\;)?([А-ЯЁ])\.(\s|\&nbsp\;)?([А-ЯЁ][а-яё]+)(\s|$|\.|\,|\;|\:|\?|\!|\&nbsp\;)/u',
                    '/(\s|^|\.|\,|\;|\:|\?|\!|\&nbsp\;)([А-ЯЁ][а-яё]+)(\s|\&nbsp\;)([А-ЯЁ])\.(\s|\&nbsp\;)?([А-ЯЁ])\.(\s|$|\.|\,|\;|\:|\?|\!|\&nbsp\;)/u',
                    '/(\s|^|\.|\,|\;|\:|\?|\!|\&nbsp\;)([А-ЯЁ])(\s|\&nbsp\;)?([А-ЯЁ])(\s|\&nbsp\;)([А-ЯЁ][а-яё]+)(\s|$|\.|\,|\;|\:|\?|\!|\&nbsp\;)/u',
                    '/(\s|^|\.|\,|\;|\:|\?|\!|\&nbsp\;)([А-ЯЁ][а-яё]+)(\s|\&nbsp\;)([А-ЯЁ])(\s|\&nbsp\;)?([А-ЯЁ])(\s|$|\.|\,|\;|\:|\?|\!|\&nbsp\;)/u',
                ],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . $ctx->tag($m[2] . '. ' . $m[4] . '. ' . $m[6], 'span', ['class' => 'nowrap']) . $m[7],
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . $ctx->tag($m[2] . ' ' . $m[4] . '. ' . $m[6] . '.', 'span', ['class' => 'nowrap']) . $m[7],
                    // v2 quirk kept: isset() is true for participating-but-empty groups.
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . $ctx->tag($m[2] . (isset($m[3]) ? ' ' : '') . $m[4] . (isset($m[5]) ? ' ' : '') . $m[6], 'span', ['class' => 'nowrap']) . $m[7],
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . $ctx->tag($m[2] . ' ' . $m[4] . (isset($m[5]) ? ' ' : '') . $m[6], 'span', ['class' => 'nowrap']) . $m[7],
                ],
            ),
            new Rule(
                id: 'nbsp_before_particle',
                patterns: ['/(\040|\t)+(ли|бы|б|же|ж)(\&nbsp\;|\.|\,|\:|\;|\&hellip\;|\?|\s)/iu'],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        '&nbsp;' . $m[2] . ($m[3] == '&nbsp;' ? ' ' : $m[3]),
                ],
            ),
            new Rule(
                id: 'nbsp_v_kak_to',
                patterns: ['/как то\:/ui'],
                replacements: ['как&nbsp;то:'],
            ),
            new Rule(
                id: 'nbsp_celcius',
                patterns: ['/(\s|^|\>|\x{E001}|\&nbsp\;)(\d+)( |\&nbsp\;)?(°|\&deg\;)(C|С)(\s|\.|\!|\?|\,|$|\&nbsp\;|\;)/iu'],
                replacements: ['\1\2&nbsp;\4C\6'],
                gate: Gate::anyCI('°', '&deg;'),
            ),
            new Rule(
                id: 'hyphen_nowrap_in_small_words',
                patterns: ['/(\&nbsp\;|\s|\>|\x{E001}|^)([a-zа-яё]{1}\-[a-zа-яё]{4}|[a-zа-яё]{2}\-[a-zа-яё]{3}|[a-zа-яё]{3}\-[a-zа-яё]{2}|[a-zа-яё]{4}\-[a-zа-яё]{1}|когда\-то|кое\-как|кой\-кого|вс[её]\-таки|[а-яё]+\-(кась|ка|де))(\s|\.|\,|\!|\?|\&nbsp\;|\&hellip\;|$)/ui'],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . $ctx->tag($m[2], 'span', ['class' => 'nowrap']) . $m[4],
                ],
                disabled: true,
                cycled: true,
            ),
            new Rule(
                id: 'hyphen_nowrap',
                patterns: ['/(\&nbsp\;|\s|\>|\x{E001}|^)([a-zа-яё]+)((\-([a-zа-яё]+)){1,2})(\s|\.|\,|\!|\?|\&nbsp\;|\&hellip\;|$)/ui'],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . $ctx->tag($m[2] . $m[3], 'span', ['class' => 'nowrap']) . $m[6],
                ],
                disabled: true,
                cycled: true,
            ),
        ];
    }

    /** Port of EMT_Tret_Nobr::nowrap_ip_address (IPv4 only). */
    private static function nowrapIpAddress(RuleContext $ctx, string $triads): string
    {
        foreach (explode('.', $triads) as $value) {
            if ((int) $value > 255) {
                return $triads;
            }
        }
        return $ctx->tag($triads, 'span', ['class' => 'nowrap']);
    }
}
