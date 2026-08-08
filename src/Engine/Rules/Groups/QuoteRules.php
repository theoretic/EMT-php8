<?php
declare(strict_types=1);

namespace EMT\Engine\Rules\Groups;

use EMT\EMT_Lib;
use EMT\Engine\Rules\Rule;
use EMT\Engine\Rules\RuleContext;

/**
 * Port of EMT_Tret_Quote, including the nested-quotation state machine.
 *
 * Pattern translation: the legacy `%%___` a-tag marker became placeholder
 * class 'a'; generic encoded-tag atoms `\<[^\>]+\>` additionally match a
 * non-prot placeholder; `\>`/`\<`/`\<\/` boundaries gained \x{E001}/\x{E000}/
 * \x{E000}\/ alternatives. The nester is the same algorithm with the byte
 * in-place writes replaced by substr_replace (entities stay 7-byte ASCII) and
 * the `global $__ax,$__ay` last-match trick replaced by a local counter.
 */
final class QuoteRules
{
    private const OPEN   = '&laquo;';  // EMT_Tret::QUOTE_FIRS_OPEN
    private const CLOSE  = '&raquo;';  // EMT_Tret::QUOTE_FIRS_CLOSE
    private const COPEN  = '&bdquo;';  // EMT_Tret::QUOTE_CRAWSE_OPEN
    private const CCLOSE = '&ldquo;';  // EMT_Tret::QUOTE_CRAWSE_CLOSE

    /** @return list<Rule> */
    public static function rules(): array
    {
        return [
            new Rule(
                id: 'quotes_outside_a',
                patterns: ['/(\x{E000}a[0-9]+\x{E001})\"(.+?)\"(\x{E000}\/a[0-9]+\x{E001})/su'],
                replacements: ['"\1\2\3"'],
            ),
            new Rule(
                id: 'open_quote',
                patterns: ['/(^|\(|\s|\>|\x{E001}|-)((\"|\\\")+)(\S+)/iu'],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . str_repeat(self::OPEN, substr_count($m[2], '"')) . $m[4],
                ],
            ),
            new Rule(
                id: 'close_quote',
                patterns: ['/([a-zа-яё0-9]|\.|\&hellip\;|\!|\?|\>|\x{E001}|\)|\:|\+|\%|\@|\#|\$|\*)((\"|\\\")+)(\.|\&hellip\;|\;|\:|\?|\!|\,|\s|\)|\<\/|\x{E000}\/|\<|\x{E000}|$)/ui'],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . str_repeat(self::CLOSE, substr_count($m[2], '"')) . $m[4],
                ],
            ),
            new Rule(
                id: 'close_quote_adv',
                patterns: [
                    '/([a-zа-яё0-9]|\.|\&hellip\;|\!|\?|\>|\x{E001}|\)|\:|\+|\%|\@|\#|\$|\*)((\"|\\\"|\&laquo\;)+)((?:\<[^\>]+\>)|(?:\x{E000}(?!prot)[^\x{E001}]+\x{E001}))(\.|\&hellip\;|\;|\:|\?|\!|\,|\)|\<\/|\x{E000}\/|$| )/ui',
                    '/([a-zа-яё0-9]|\.|\&hellip\;|\!|\?|\>|\x{E001}|\)|\:|\+|\%|\@|\#|\$|\*)(\s+)((\"|\\\")+)(\s+)(\.|\&hellip\;|\;|\:|\?|\!|\,|\)|\<\/|\x{E000}\/|$| )/ui',
                    '/(\>|\x{E001})(\&laquo\;)\.($|\s|\<|\x{E000})/ui',
                    '/(\>|\x{E001})(\&laquo\;),($|\s|\<|\x{E000}|\S)/ui',
                    '/(\>|\x{E001})(\&laquo\;):($|\s|\<|\x{E000}|\S)/ui',
                    '/(\>|\x{E001})(\&laquo\;);($|\s|\<|\x{E000}|\S)/ui',
                    '/(\>|\x{E001})(\&laquo\;)\)($|\s|\<|\x{E000}|\S)/ui',
                    '/((\"|\\\")+)$/u',
                ],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1]
                        . str_repeat(self::CLOSE, substr_count($m[2], '"') + substr_count($m[2], '&laquo;'))
                        . $m[4] . $m[5],
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . $m[2]
                        . str_repeat(self::CLOSE, substr_count($m[3], '"') + substr_count($m[3], '&laquo;'))
                        . $m[5] . $m[6],
                    '\1&raquo;.\3',
                    '\1&raquo;,\3',
                    '\1&raquo;:\3',
                    '\1&raquo;;\3',
                    '\1&raquo;)\3',
                    static fn(array $m, RuleContext $ctx): string =>
                        str_repeat(self::CLOSE, substr_count($m[1], '"')),
                ],
            ),
            new Rule(
                id: 'open_quote_adv',
                patterns: ['/(^|\(|\s|\>|\x{E001})(\"|\\\")(\s)(\S+)/iu'],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . self::OPEN . $m[4],
                ],
            ),
            new Rule(
                id: 'close_quote_adv_2',
                patterns: ['/(\S)((\"|\\\")+)(\.|\&hellip\;|\;|\:|\?|\!|\,|\s|\)|\<\/|\x{E000}\/|\<|\x{E000}|$)/ui'],
                replacements: [
                    static fn(array $m, RuleContext $ctx): string =>
                        $m[1] . str_repeat(self::CLOSE, substr_count($m[2], '"')) . $m[4],
                ],
            ),
            new Rule(
                id: 'quotation',
                procedure: self::buildSubQuotations(...),
            ),
            new Rule(
                id: 'backtick',
                patterns: ['/`/'],
                replacements: ['&backquote;'],
            ),
            new Rule(
                id: 'apostrophe_open',
                patterns: ['/ʻ/'],
                replacements: ['&lsquo;'],
            ),
            new Rule(
                id: 'apostrophe_close',
                patterns: ['/ʼ/'],
                replacements: ['&rsquo;'],
            ),
        ];
    }

    /**
     * Port of EMT_Tret_Quote::build_sub_quotations(). Same algorithm; the
     * paragraph separator detection uses the placeholder class for </p>
     * (legacy "</cA===>" string equality), and chunks are re-joined with the
     * exact delimiters that split them.
     */
    private static function buildSubQuotations(RuleContext $ctx): void
    {
        $text = $ctx->doc->text();
        $closeP = '/(\x{E000}\/p[0-9]+\x{E001})/u';

        if (preg_match($closeP, $text)) {
            $parts = preg_split($closeP, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
            $out = '';
            foreach ($parts as $i => $part) {
                $out .= ($i % 2 === 0) ? self::processChunk($part, $ctx) : $part;
            }
        } else {
            $exp = str_contains($text, "\r\n") ? "\r\n\r\n" : "\n\n";
            $out = implode($exp, array_map(
                static fn(string $chunk): string => self::processChunk($chunk, $ctx),
                explode($exp, $text)
            ));
        }
        $ctx->doc->setText($out);
    }

    private static function processChunk(string $textx, RuleContext $ctx): string
    {
        $noBdquotes = $ctx->isOn('no_bdquotes');
        $noInches = $ctx->isOn('no_inches');

        $okposstack = [0];
        $okpos = 0;
        $level = 0;
        $off = 0;
        while (true) {
            $p = EMT_Lib::strpos_ex($textx, [self::OPEN, self::CLOSE], $off);
            if ($p === false) {
                break;
            }
            if ($p['str'] == self::OPEN) {
                if ($level > 0 && !$noBdquotes) {
                    $textx = substr_replace($textx, self::COPEN, $p['pos'], strlen(self::COPEN));
                }
                $level++;
            }
            if ($p['str'] == self::CLOSE) {
                $level--;
                if ($level > 0 && !$noBdquotes) {
                    $textx = substr_replace($textx, self::CCLOSE, $p['pos'], strlen(self::CCLOSE));
                }
            }
            $off = $p['pos'] + strlen($p['str']);
            if ($level == 0) {
                $okpos = $off;
                array_push($okposstack, $okpos);
            } elseif ($level < 0) { // уровень стал меньше нуля
                if (!$noInches) {
                    do {
                        $lokpos = array_pop($okposstack) ?? 0;
                        $k = substr($textx, $lokpos, $off - $lokpos);
                        $k = str_replace(self::COPEN, self::OPEN, $k);
                        $k = str_replace(self::CCLOSE, self::CLOSE, $k);

                        $amount = 0;
                        $ax = preg_match_all('/(^|[^0-9])([0-9]+)\&raquo\;/ui', $k, $mm);
                        if ($ax) {
                            $ay = 0;
                            $k = preg_replace_callback(
                                '/(^|[^0-9])([0-9]+)\&raquo\;/ui',
                                static function (array $m) use (&$ay, $ax): string {
                                    $ay++;
                                    if ($ay == $ax) {
                                        return $m[1] . $m[2] . '&Prime;';
                                    }
                                    return $m[0];
                                },
                                $k
                            );
                            $amount = 1;
                        }
                    } while (($amount == 0) && count($okposstack));

                    // успешно сделали замену
                    if ($amount == 1) {
                        // заново просмотрим содержимое
                        $textx = substr($textx, 0, $lokpos) . $k . substr($textx, $off);
                        $off = $lokpos;
                        $level = 0;
                        continue;
                    }

                    // иначе просто заменим последнюю явно на &quot; от отчаяния
                    if ($amount == 0) {
                        // говорим, что всё в порядке
                        $level = 0;
                        $textx = substr($textx, 0, $p['pos']) . '&quot;' . substr($textx, $off);
                        $off = $p['pos'] + strlen('&quot;');
                        $okposstack = [$off];
                        continue;
                    }
                }
            }
        }
        // не совпало количество, отменяем все подкавычки
        if ($level != 0) {
            // закрывающих меньше, чем надо
            if ($level > 0) {
                $k = substr($textx, $okpos);
                $k = str_replace(self::COPEN, self::OPEN, $k);
                $k = str_replace(self::CCLOSE, self::CLOSE, $k);
                $textx = substr($textx, 0, $okpos) . $k;
            }
        }
        return $textx;
    }
}
