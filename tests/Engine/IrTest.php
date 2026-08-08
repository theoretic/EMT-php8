<?php
declare(strict_types=1);

use EMT\EMT_Lib;
use EMT\Engine\Ir\IrBuilder;
use EMT\Engine\Ir\Placeholder;
use EMT\Engine\Ir\PlaceholderIntegrityError;
use EMT\Engine\Lexer\Lexer;
use EMT\Engine\Lexer\TokenType;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class IrTest extends TestCase
{
    /** With normalization off, build->render is the identity for any input. */
    #[DataProvider('allInputFiles')]
    public function testRenderRoundTripIdentity(string $file): void
    {
        $input = (string) file_get_contents($file);
        $doc = (new IrBuilder(normalize: false))->build((new Lexer())->tokenize($input));
        self::assertSame($input, $doc->render(), basename($file));
    }

    public static function allInputFiles(): iterable
    {
        $dirs = [
            __DIR__ . '/../Golden/corpus',
            __DIR__ . '/../Golden/quarantine',
        ];
        foreach ($dirs as $dir) {
            foreach (glob($dir . '/*.{txt,html}', GLOB_BRACE) as $file) {
                yield basename(dirname($file)) . '/' . basename($file) => [$file];
            }
        }
    }

    public function testAdversarialPuaInputRoundTrips(): void
    {
        // Input containing the placeholder codepoints themselves must survive
        // and must not be able to forge a placeholder.
        $forged = "\u{E000}t0\u{E001}";
        $input = "текст {$forged} и еще \u{E004} байты \u{E000}\u{E001}";
        $doc = (new IrBuilder(normalize: false))->build((new Lexer())->tokenize($input));
        self::assertStringNotContainsString($forged, $doc->text(), 'forged placeholder must be escaped in IR text');
        self::assertSame($input, $doc->render());
    }

    public function testNormalizationMatchesLegacyOnTextOnly(): void
    {
        $input = 'Тире — и «кавычки» плюс <b>жирный — текст</b> конец';
        $doc = (new IrBuilder())->build((new Lexer())->tokenize($input));

        // Text got normalized exactly like the legacy chars table does...
        self::assertStringContainsString(EMT_Lib::clear_special_chars('—'), $doc->text());
        // ...but tag raw bytes are untouched in the rendered output.
        self::assertStringContainsString('<b>', $doc->render());
        $expectedText = EMT_Lib::clear_special_chars('Тире — и «кавычки» плюс ');
        self::assertStringStartsWith($expectedText, $doc->text());
    }

    public function testPlaceholderClasses(): void
    {
        $input = 'x<a href="/">y</a><p>z</p><br /><em>w</em><pre>q</pre><abbr title="a">b</abbr>';
        $stream = (new Lexer())->tokenize($input);
        $doc = (new IrBuilder(normalize: false))->build($stream);
        $text = $doc->text();

        self::assertMatchesRegularExpression('/\x{E000}a\d+\x{E001}/u', $text);
        self::assertMatchesRegularExpression('/\x{E000}\/a\d+\x{E001}/u', $text);
        self::assertMatchesRegularExpression('/\x{E000}p\d+\x{E001}/u', $text, 'bare <p> is class p');
        self::assertMatchesRegularExpression('/\x{E000}br\d+\x{E001}/u', $text, '<br /> exact form is class br');
        self::assertMatchesRegularExpression('/\x{E000}t\d+\x{E001}/u', $text, 'em collapses to generic t');
        self::assertMatchesRegularExpression('/\x{E000}prot\d+\x{E001}/u', $text, 'pre body is prot');
        // Legacy %%___ marker keyed off the first character, so the whole
        // a-family is marked — <abbr> included.
        self::assertSame(2, preg_match_all('/\x{E000}a\d+\x{E001}/u', $text));
    }

    public function testBrAndAttributedPCollapseToGeneric(): void
    {
        $input = '<br><p class="x">y</p>';
        $doc = (new IrBuilder(normalize: false))->build((new Lexer())->tokenize($input));
        self::assertDoesNotMatchRegularExpression('/\x{E000}br\d+\x{E001}/u', $doc->text(), '<br> without space-slash is generic');
        self::assertDoesNotMatchRegularExpression('/\x{E000}p\d+\x{E001}/u', $doc->text(), 'attributed <p> is generic');
    }

    public function testSetTextValidatesPlaceholderIntegrity(): void
    {
        $input = 'до <b>тега</b> после';
        $doc = (new IrBuilder(normalize: false))->build((new Lexer())->tokenize($input));

        // Legitimate edit around placeholders: fine.
        $doc->setText(str_replace('до', 'ДО', $doc->text()));
        self::assertStringContainsString('ДО', $doc->render());

        // Eating a placeholder: loud error.
        $this->expectException(PlaceholderIntegrityError::class);
        $doc->setText(preg_replace(Placeholder::PATTERN, '', $doc->text(), 1));
    }

    public function testRulesSeeTextWithoutTagBytes(): void
    {
        $input = '<a href="http://пример.рф/путь?q=1">ссылка</a>';
        $doc = (new IrBuilder(normalize: false))->build((new Lexer())->tokenize($input));
        self::assertStringNotContainsString('href', $doc->text(), 'attribute bytes must not leak into rule-visible text');
        self::assertStringContainsString('ссылка', $doc->text());
    }
}
