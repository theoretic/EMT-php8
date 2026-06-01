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
}
