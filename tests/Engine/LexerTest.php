<?php
declare(strict_types=1);

use EMT\Engine\Lexer\Lexer;
use EMT\Engine\Lexer\SafeBlockSet;
use EMT\Engine\Lexer\TokenType;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class LexerTest extends TestCase
{
    /** The invariant everything else stands on: lexing loses no bytes, ever. */
    #[DataProvider('allInputFiles')]
    public function testRoundTripIdentity(string $file): void
    {
        $input = (string) file_get_contents($file);
        $stream = (new Lexer())->tokenize($input);
        self::assertSame($input, $stream->raw(), basename($file));
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

    public function testAttributeWithGtStaysOneTag(): void
    {
        $input = 'до <a href="/c?a>b" title="a > b - тест">ссылка</a> после';
        $tokens = (new Lexer())->tokenize($input)->tokens;

        $tags = array_values(array_filter($tokens, fn($t) => $t->type === TokenType::Tag));
        self::assertCount(2, $tags);
        self::assertSame('<a href="/c?a>b" title="a > b - тест">', $tags[0]->raw);
        self::assertSame('a', $tags[0]->name);
        self::assertSame('</a>', $tags[1]->raw);
        self::assertTrue($tags[1]->closing);
    }

    public function testLiteralAngleBracketsStayText(): void
    {
        // `<` followed by space/digit/`-` is prose (v2 destroyed it). `<y...>`
        // is a tag in HTML5 tokenization too, so we intentionally match spec.
        $input = 'если a < b и b > c, стрелки <- и < 5 тоже текст';
        $tokens = (new Lexer())->tokenize($input)->tokens;
        self::assertCount(1, $tokens);
        self::assertSame(TokenType::Text, $tokens[0]->type);
        self::assertSame($input, $tokens[0]->raw);
    }

    public function testLtBeforeLetterOpensTagLikeHtml5(): void
    {
        $input = 'то x<y всегда; стрелка -> и хвост';
        $tokens = (new Lexer())->tokenize($input)->tokens;
        $types = array_map(fn($t) => $t->type, $tokens);
        self::assertSame([TokenType::Text, TokenType::Tag, TokenType::Text], $types);
        self::assertSame('<y всегда; стрелка ->', $tokens[1]->raw);
    }

    public function testScriptRawTextEndsAtFirstCloseTag(): void
    {
        $input = '<script>if (a < b) { s = "</div>"; }</script>текст';
        $tokens = (new Lexer())->tokenize($input)->tokens;
        $types = array_map(fn($t) => $t->type, $tokens);
        self::assertSame(
            [TokenType::Tag, TokenType::Protected, TokenType::Tag, TokenType::Text],
            $types
        );
        self::assertSame('if (a < b) { s = "</div>"; }', $tokens[1]->raw);
    }

    public function testNestedPreProtectsToOuterClose(): void
    {
        $input = '<pre>a -- b <pre>c</pre> d -- e</pre>хвост';
        $tokens = (new Lexer())->tokenize($input)->tokens;
        $protected = array_values(array_filter($tokens, fn($t) => $t->type === TokenType::Protected));
        self::assertCount(1, $protected);
        self::assertSame('a -- b <pre>c</pre> d -- e', $protected[0]->raw);
    }

    public function testUnterminatedRawTextTagLexesContentNormally(): void
    {
        $input = '<pre>незакрытый блок с "кавычками"';
        $tokens = (new Lexer())->tokenize($input)->tokens;
        self::assertSame(TokenType::Tag, $tokens[0]->type);
        self::assertSame(TokenType::Text, $tokens[1]->type);
        self::assertSame('незакрытый блок с "кавычками"', $tokens[1]->raw);
    }

    public function testCommentAndCdata(): void
    {
        $input = 'a<!-- ком -- ент -->b<![CDATA[ c < d ]]>e';
        $tokens = (new Lexer())->tokenize($input)->tokens;
        $types = array_map(fn($t) => $t->type, $tokens);
        self::assertSame(
            [TokenType::Text, TokenType::Comment, TokenType::Text, TokenType::Cdata, TokenType::Text],
            $types
        );
        self::assertSame('<!-- ком -- ент -->', $tokens[1]->raw);
        self::assertSame('<![CDATA[ c < d ]]>', $tokens[3]->raw);
    }

    public function testDelimiterBlock(): void
    {
        $blocks = new SafeBlockSet();
        $blocks->addDelimiterBlock('mustache', '{{', '}}');
        $input = 'до {{ raw "внутри" }} после';
        $tokens = (new Lexer($blocks))->tokenize($input)->tokens;
        $types = array_map(fn($t) => $t->type, $tokens);
        self::assertSame([TokenType::Text, TokenType::Protected, TokenType::Text], $types);
        self::assertSame('{{ raw "внутри" }}', $tokens[1]->raw);
        self::assertSame($input, (new Lexer($blocks))->tokenize($input)->raw());
    }

    public function testFuzzRoundTrip(): void
    {
        // Deterministic pseudo-random byte soup heavy on lexer-relevant chars.
        mt_srand(20260808);
        $alphabet = ['<', '>', '"', "'", '/', '!', '-', 'a', 'p', 'р', 'е', ' ', "\n", 'pre', 'script', '<pre>', '</pre>', '<a href="', '{{', '}}'];
        $lexer = new Lexer();
        for ($round = 0; $round < 200; $round++) {
            $s = '';
            $len = mt_rand(0, 60);
            for ($k = 0; $k < $len; $k++) {
                $s .= $alphabet[mt_rand(0, count($alphabet) - 1)];
            }
            self::assertSame($s, $lexer->tokenize($s)->raw(), 'fuzz seed round ' . $round);
        }
    }
}
