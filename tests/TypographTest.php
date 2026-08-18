<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use EMT\EMTypograph;

class TypographTest extends TestCase
{
    // ---------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------
    private function typo(string $text, array $options = []): string
    {
        return EMTypograph::fast_apply($text, $options ?: null);
    }

    // ---------------------------------------------------------------
    // Dash rules
    // ---------------------------------------------------------------
    public function testDoubleDashBecomesEmDash(): void
    {
        $out = $this->typo('Кот -- умный зверь, и он не дурак.');
        $this->assertStringContainsString('&mdash;', $out);
        $this->assertStringNotContainsString('--', $out);
    }

    // ---------------------------------------------------------------
    // Quote rules
    // ---------------------------------------------------------------
    public function testDoubleQuotesBecomeLaquo(): void
    {
        $out = $this->typo('Прочитал "Войну и мир".');
        $this->assertStringContainsString('&laquo;', $out);
        $this->assertStringContainsString('&raquo;', $out);
        // literal ASCII " should be replaced in text (HTML attr quotes are OK)
        $this->assertStringNotContainsString('"Войну', $out);
        $this->assertStringNotContainsString('мир".', $out);
    }

    public function testNestedQuotesUseBdquoLdquo(): void
    {
        $out = $this->typo('"Он сказал: "да"."');
        $this->assertStringContainsString('&laquo;', $out);
        // inner quotes become „ … "
        $this->assertStringContainsString('&bdquo;', $out);
        $this->assertStringContainsString('&ldquo;', $out);
    }

    // ---------------------------------------------------------------
    // Space / NOBR rules
    // ---------------------------------------------------------------
    public function testShortPrepositionsGetNbsp(): void
    {
        $out = $this->typo('В лесу и на поле было тихо.');
        $this->assertStringContainsString('В&nbsp;лесу', $out);
        $this->assertStringContainsString('и&nbsp;на', $out);
        $this->assertStringContainsString('на&nbsp;поле', $out);
    }

    public function testPhoneNumberWrappedInNobr(): void
    {
        $out = $this->typo('Тел. +7 495 123-45-67');
        $this->assertStringContainsString('<nobr>', $out);
    }

    // ---------------------------------------------------------------
    // Abbr rules
    // ---------------------------------------------------------------
    public function testNobrAbbreviations(): void
    {
        $out = $this->typo('Примеры: т.е. и т.д. и т.п. итд');
        $this->assertStringContainsString('<nobr>', $out);
        $this->assertStringContainsString('т. е.', $out);
        $this->assertStringContainsString('т. д.', $out);
    }

    // ---------------------------------------------------------------
    // Optical alignment
    // ---------------------------------------------------------------
    public function testOpticalAlignmentSpanAdded(): void
    {
        $out = $this->typo('Прочитал "Войну и мир".');
        // optical alignment adds span around the opening guillemet
        $this->assertStringContainsString('<span', $out);
        $this->assertStringContainsString('&laquo;', $out);
    }

    // ---------------------------------------------------------------
    // Paragraph wrapping
    // ---------------------------------------------------------------
    public function testPlainTextWrappedInParagraph(): void
    {
        $out = $this->typo('Простой текст.');
        $this->assertStringContainsString('<p>', $out);
        $this->assertStringContainsString('</p>', $out);
    }

    // ---------------------------------------------------------------
    // Full-pipeline snapshot test
    // ---------------------------------------------------------------
    public function testFullSampleSnapshot(): void
    {
        $input = '<p>Объект: Тайшетский аллюминиевый завод. Склад глинозема #1, #2.<br>Сроки: 11.06.18 по 30.11.18.<br>Заказчик: ОК "РУСАЛ".</p>';

        $expected = '<p>Объект: Тайшетский аллюминиевый завод. Склад глинозема #1, #2.<br>Сроки: <nobr>11.06.18 по</nobr> <nobr>30.11.18.</nobr><br>Заказчик: ОК &laquo;РУСАЛ&raquo;.</p>';

        $this->assertSame($expected, $this->typo($input));
    }

    // ---------------------------------------------------------------
    // Per-tret isolation tests
    // ---------------------------------------------------------------
    public function testDashTretAlone(): void
    {
        $obj = new EMTypograph();
        $obj->set_text('Кот -- зверь.');
        $result = $obj->apply('EMT\EMT_Tret_Dash');
        $this->assertStringContainsString('&mdash;', $result);
    }

    public function testQuoteTretAlone(): void
    {
        $obj = new EMTypograph();
        $obj->set_text('"Привет"');
        $result = $obj->apply('EMT\EMT_Tret_Quote');
        $this->assertStringContainsString('&laquo;', $result);
        $this->assertStringContainsString('&raquo;', $result);
    }

    public function testNbrTretAlone(): void
    {
        $obj = new EMTypograph();
        $obj->set_text('и он не дурак');
        $result = $obj->apply('EMT\EMT_Tret_Nobr');
        $this->assertStringContainsString('&nbsp;', $result);
    }

    // ---------------------------------------------------------------
    // API surface
    // ---------------------------------------------------------------
    public function testFastApplyReturnsString(): void
    {
        $result = EMTypograph::fast_apply('Тест.');
        $this->assertIsString($result);
    }

    public function testOkFlagSetAfterSuccessfulApply(): void
    {
        $obj = new EMTypograph();
        $obj->set_text('Тест.');
        $obj->apply();
        $this->assertTrue($obj->ok);
    }

    public function testSafeBlockPreservesContent(): void
    {
        $input = '<pre>if (a -- b) return;</pre>';
        $out   = $this->typo($input);
        // content inside <pre> must not be modified by dash rules
        $this->assertStringContainsString('a -- b', $out);
    }

    public function testGetStyleReturnsString(): void
    {
        $obj = new EMTypograph();
        $obj->set_tag_layout(\EMT\EMT_Lib::LAYOUT_CLASS);
        $style = $obj->get_style();
        $this->assertIsString($style);
    }

    // ---------------------------------------------------------------
    // Regressions
    // ---------------------------------------------------------------

    /** split_number() used to hit number_format()'s int|float type under strict_types */
    public function testLongNumberSplitIntoTriads(): void
    {
        $out = $this->typo('Сумма 1000000 рублей.');
        $this->assertStringContainsString('1&thinsp;000&thinsp;000', $out);
    }

    /** number length is unbounded in the pattern, so no int overflow either */
    public function testVeryLongNumberSplitIntoTriads(): void
    {
        $out = $this->typo('Код 123456789012345678901234 конец.');
        $this->assertStringContainsString('123&thinsp;456&thinsp;789&thinsp;012&thinsp;345&thinsp;678&thinsp;901&thinsp;234', $out);
    }

    public function testSplitNumberHelper(): void
    {
        $this->assertSame('1 000 000', \EMT\EMT_Lib::split_number('1000000'));
        $this->assertSame('12 345', \EMT\EMT_Lib::split_number(12345));
        $this->assertSame('123', \EMT\EMT_Lib::split_number('123'));
    }

    /** the ok-position stack was seeded with the string '0', tripping substr() */
    public function testUnbalancedClosingQuoteDoesNotCrash(): void
    {
        $out = $this->typo('Он сказал да" и ушел');
        $this->assertIsString($out);
        $this->assertStringContainsString('ушел', $out);
    }

    public function testMultipleUnbalancedQuotesDoNotCrash(): void
    {
        $out = $this->typo('текст" еще" и" тут"');
        $this->assertIsString($out);
        $this->assertStringContainsString('тут', $out);
    }

    /** the domain-zone guard was an array literal, so it was always truthy */
    public function testSpaceInsertedAfterDotBeforeShortWord(): void
    {
        $out = $this->typo('Привет.Мир и еще текст');
        $this->assertStringContainsString('Привет. Мир', $out);
    }

    public function testNoSpaceInsertedInsideDomainName(): void
    {
        $out = $this->typo('Смотри сайт.ру и еще');
        $this->assertStringContainsString('сайт.ру', $out);
        $this->assertStringNotContainsString('сайт. ру', $out);
    }

    /** the character class had a stray ] so semicolons were never collapsed */
    public function testRepeatedSemicolonsCollapse(): void
    {
        $this->assertStringContainsString('Текст; тут', $this->typo('Текст;;; тут'));
        $this->assertStringContainsString('Текст, тут', $this->typo('Текст,,, тут'));
        $this->assertStringContainsString('Текст: тут', $this->typo('Текст::: тут'));
    }

    /**
     * punctuation_marks_base_limit had ; in its character class, so an entity
     * terminator followed by , : ; read as a doubled mark and the ; was eaten:
     * "&Oslash;, mm" shipped as "&Oslash, mm".
     */
    public function testEntitySemicolonSurvivesFollowingPunctuation(): void
    {
        // entities EMT_Lib does not normalize away (&copy; becomes (c) before punctmark runs)
        $entities = ['&Oslash;', '&dagger;', '&Prime;', '&#216;', '&#x2126;'];

        foreach ($entities as $entity) {
            foreach ([',', ':', ';'] as $mark) {
                $out = $this->typo("Размер {$entity}{$mark} дальше текст");
                $this->assertStringContainsString($entity . $mark, $out);
            }
        }
    }

    /** entities the engine itself emits are just as vulnerable */
    public function testGeneratedEntitySemicolonSurvivesFollowingComma(): void
    {
        $out = $this->typo('"Погода в Питере - это лотерея", сказал официант.');
        $this->assertStringContainsString('&raquo;,', $out);
        $this->assertStringNotContainsString('&raquo,', $out);
    }

    /** guarding entities must not stop genuine duplicate collapsing */
    public function testDuplicateMarksAfterEntityStillCollapse(): void
    {
        $this->assertStringContainsString('&Oslash;, x', $this->typo('&Oslash;,, x'));
        $this->assertStringContainsString('&Oslash;; x', $this->typo('&Oslash;;; x'));
    }

    /** the guard must be identical in both engines or shadow parity drifts */
    public function testEntityGuardIsIdenticalInBothEngines(): void
    {
        $inputs = [
            'Размер &Oslash;, мм',
            'Знак &copy;: подпись',
            'Код &#216;; далее',
            '"Цитата", сказал он.',
            'Текст,,, тут',
        ];

        foreach ($inputs as $input) {
            \EMT\EMT_Base::$engine = 'v2';
            $v2 = EMTypograph::fast_apply($input);
            \EMT\EMT_Base::$engine = 'v3';
            $v3 = EMTypograph::fast_apply($input);
            \EMT\EMT_Base::$engine = null;

            $this->assertSame($v2, $v3, "engines diverged on: $input");
        }
    }

    /** Abbr declared nobr_vtch_itd_itp twice; the ^-anchored variant was discarded */
    public function testAbbreviationAtStringStart(): void
    {
        $out = $this->typo('и т.д. — это всё');
        $this->assertStringContainsString('т. д.', $out);
        $this->assertStringContainsString('<nobr>', $out);
    }

    public function testAbbrHasNoDuplicateRuleDefinition(): void
    {
        $tret = new \EMT\EMT_Tret_Abbr();
        $this->assertArrayHasKey('nobr_vtch_itd_itp', $tret->rules);
        $this->assertSame(
            'Объединение сокращений и т.д., и т.п., в т.ч.',
            $tret->rules['nobr_vtch_itd_itp']['description']
        );
    }

    /**
     * Replacements paired with an /e-flagged pattern are eval()'d as PHP
     * expressions, so a typo there is a latent ParseError rather than a
     * failing assertion. Lint every one of them.
     */
    public function testEvalRuleReplacementsCompile(): void
    {
        $obj     = new EMTypograph();
        $trets   = $obj->get_trets_list();
        $checked = 0;
        $this->assertNotEmpty($trets);

        foreach ($trets as $class) {
            $tret = new $class();
            foreach ($tret->rules as $id => $rule) {
                if (!isset($rule['pattern'], $rule['replacement'])) continue;
                if (!empty($rule['simple_replace'])) continue;
                if (!empty($rule['function'])) continue;

                $patterns = (array) $rule['pattern'];
                foreach ($patterns as $i => $pattern) {
                    if (!self::hasEvalFlag($pattern)) continue;

                    $repl = is_string($rule['replacement'])
                        ? $rule['replacement']
                        : $rule['replacement'][$i];

                    $error = self::lintError('<?php return function(array $m) { return ' . $repl . '; };');
                    $this->assertNull($error, "$class::\$rules[$id] replacement #$i is not valid PHP: $error");
                    $checked++;
                }
            }
        }

        $this->assertGreaterThan(0, $checked, 'no /e replacements were linted');
    }

    /** Mirrors EMT_Tret::apply_rule() — the trailing delimiter section carries the flags. */
    private static function hasEvalFlag(string $pattern): bool
    {
        $parts = explode(substr($pattern, 0, 1), $pattern);
        return str_contains(end($parts), 'e');
    }

    private static function lintError(string $code): ?string
    {
        try {
            // token_get_all with TOKEN_PARSE runs the parser without executing
            token_get_all($code, TOKEN_PARSE);
        } catch (\ParseError $e) {
            return $e->getMessage();
        }
        return null;
    }

    /** log_on() flipped debug_enabled instead of logging */
    public function testTretLogOnEnablesLogging(): void
    {
        $tret = new \EMT\EMT_Tret_Quote();
        $tret->log_on();
        $this->assertTrue($tret->logging);
        $this->assertFalse($tret->debug_enabled);
    }

    /** diagnostics used to accumulate across applies, pinning ok to false forever */
    public function testDiagnosticsResetBetweenApplies(): void
    {
        $obj = new EMTypograph();
        $obj->debug_on();
        $obj->log_on();

        $obj->set_text('Тест "раз".');
        $obj->apply();
        $debug = count($obj->debug_info);
        $logs  = count($obj->logs);

        $obj->set_text('Тест "два".');
        $obj->apply();

        $this->assertSame($debug, count($obj->debug_info));
        $this->assertSame($logs, count($obj->logs));
    }

    public function testOkFlagRecoversAfterEarlierError(): void
    {
        $obj = new EMTypograph();
        $obj->get_tret('NoSuchTret');       // records an error
        $this->assertNotEmpty($obj->errors);

        $obj->set_text('Тест.');
        $obj->apply();

        $this->assertTrue($obj->ok);
        $this->assertEmpty($obj->errors);
    }

    /** out-of-range numeric entities were coerced to "" and vanished */
    public function testOutOfRangeEntityIsPreserved(): void
    {
        $text = '&#99999999; и &#xFFFFFFFF;';
        \EMT\EMT_Lib::convert_html_entities_to_unicode($text);
        $this->assertStringContainsString('&#99999999;', $text);
        $this->assertStringContainsString('&#xFFFFFFFF;', $text);
    }

    public function testInRangeEntityIsConverted(): void
    {
        $text = '&#1055;&#x41;';
        \EMT\EMT_Lib::convert_html_entities_to_unicode($text);
        $this->assertSame('ПA', $text);
    }

    /** the tag name was interpolated raw into the open pattern */
    public function testSafeTagNameIsEscaped(): void
    {
        $obj = new EMTypograph();
        $obj->add_safe_tag('my.tag');
        $blocks = $obj->get_all_safe_blocks();
        $block  = end($blocks);
        $this->assertStringContainsString('my\.tag', $block['open']);
    }

    /** apply() indexed tret_objects unguarded -> fatal on an unknown name */
    public function testApplyWithUnknownTretIsGraceful(): void
    {
        $obj = new EMTypograph();
        $obj->set_text('Тест.');
        $result = $obj->apply('NoSuchTret');

        $this->assertIsString($result);
        $this->assertStringContainsString('Тест', $result);
        $this->assertFalse($obj->ok);
        $this->assertNotEmpty($obj->errors);
    }
}
