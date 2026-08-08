<?php
declare(strict_types=1);

namespace EMT\Engine\Rules\Groups;

use EMT\EMT_Lib;
use EMT\Engine\Rules\Rule;
use EMT\Engine\Rules\RuleContext;
use EMT\Engine\Rules\RuleEngine;

/**
 * Port of EMT_Tret_Etc. The remove_nbsp / nobr_to_nbsp procedures locate
 * rule-emitted nowrap tags by the same trick as v2 (build a probe tag, split
 * on the sentinel) — in v3 the probe yields placeholder strings instead of
 * base64 markup, everything else is verbatim. acute_accent keeps its known
 * byte-oriented /i-without-/u bug for parity.
 */
final class EtcRules
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
                id: 'acute_accent',
                patterns: ['/(у|е|ы|а|о|э|я|и|ю|ё)\`(\w)/i'],
                replacements: ['\1&#769;\2'],
            ),
            new Rule(
                id: 'word_sup',
                patterns: ['/((\s|\&nbsp\;|^)+)\^([a-zа-яё0-9\.\:\,\-]+)(\s|\&nbsp\;|$|\.$)/iu'],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        '' . $ctx->tag($ctx->tag($m[3], 'small'), 'sup') . $m[4],
                ],
            ),
            new Rule(
                id: 'century_period',
                patterns: ['/(\040|\t|\&nbsp\;|^)([XIV]{1,5})(-|\&mdash\;)([XIV]{1,5})(( |\&nbsp\;)?(в\.в\.|вв\.|вв|в\.|в))/u'],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . $ctx->tag($m[2] . '&mdash;' . $m[4] . ' вв.', 'span', ['class' => 'nowrap']),
                ],
            ),
            new Rule(
                id: 'time_interval',
                patterns: ['/([^\d\>\x{E001}]|^)([\d]{1,2}\:[\d]{2})(-|\&mdash\;|\&minus\;)([\d]{1,2}\:[\d]{2})([^\d\<\x{E000}]|$)/ui'],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . $ctx->tag($m[2] . '&mdash;' . $m[4], 'span', ['class' => 'nowrap']) . $m[5],
                ],
            ),
            new Rule(
                id: 'split_number_to_triads',
                patterns: ['/([^a-zA-Z0-9<\)\x{E000}]|^)([0-9]{5,})([^a-zA-Z>\(\x{E001}]|$)/u'],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . str_replace(' ', '&thinsp;', EMT_Lib::split_number($m[2])) . $m[3],
                ],
            ),
            new Rule(
                id: 'expand_no_nbsp_in_nobr',
                procedure: self::removeNbsp(...),
            ),
            new Rule(
                id: 'nobr_to_nbsp',
                procedure: self::nobrToNbsp(...),
                disabled: true,
            ),
        ];
    }

    /** @return array{0: string, 1: string} quoted open/close of the group's nowrap tag */
    private static function nowrapTagBounds(RuleContext $ctx): array
    {
        $probe = $ctx->tag('###', 'span', ['class' => 'nowrap']);
        $arr = explode('###', $probe);
        return [preg_quote($arr[0], '/'), preg_quote($arr[1], '/')];
    }

    /** Port of EMT_Tret_Etc::remove_nbsp(). */
    private static function removeNbsp(RuleContext $ctx): void
    {
        [$b, $e] = self::nowrapTagBounds($ctx);
        $text = $ctx->doc->text();

        $match = '/(^|[^a-zа-яё])([a-zа-яё]+)\&nbsp\;(' . $b . ')/iu';
        $iter = 0;
        do {
            $text = preg_replace($match, '\1\3\2 ', $text, -1, $count) ?? $text;
        } while ($count > 0 && ++$iter < RuleEngine::MAX_CYCLES);

        $match = '/(' . $e . ')\&nbsp\;([a-zа-яё]+)($|[^a-zа-яё])/iu';
        $iter = 0;
        do {
            $text = preg_replace($match, ' \2\1\3', $text, -1, $count) ?? $text;
        } while ($count > 0 && ++$iter < RuleEngine::MAX_CYCLES);

        $text = preg_replace_callback(
            '/' . $b . '.*?' . $e . '/iu',
            static fn(array $m): string => str_replace('&nbsp;', ' ', $m[0]),
            $text
        ) ?? $text;

        $ctx->doc->setText($text);
    }

    /** Port of EMT_Tret_Etc::nobr_to_nbsp(). */
    private static function nobrToNbsp(RuleContext $ctx): void
    {
        [$b, $e] = self::nowrapTagBounds($ctx);
        $text = preg_replace_callback(
            '/' . $b . '(.*?)' . $e . '/iu',
            static fn(array $m): string => str_replace(' ', '&nbsp;', $m[1]),
            $ctx->doc->text()
        );
        if ($text !== null) {
            $ctx->doc->setText($text);
        }
    }
}
