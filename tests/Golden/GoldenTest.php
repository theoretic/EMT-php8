<?php
declare(strict_types=1);

use EMT\EMTypograph;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Golden parity tests: engine output must match committed fixtures byte-for-byte.
 *
 * Fixtures were captured from the v2 engine and are the behavioral contract the
 * v3 engine rewrite must satisfy. Regenerate deliberately with:
 *   php tools/capture_golden.php --update
 *
 * Quarantine inputs (tests/Golden/quarantine/) are documented v2 bugs and are
 * intentionally NOT asserted here.
 */
final class GoldenTest extends TestCase
{
    private const GOLDEN_DIR = __DIR__;

    public static function profileCases(): iterable
    {
        $profiles = require self::GOLDEN_DIR . '/profiles.php';
        foreach ($profiles as $profileName => $options) {
            foreach (self::corpus() as $case => $input) {
                yield "$profileName/$case" => [$profileName, $options, $case, $input];
            }
        }
    }

    public static function optionCases(): iterable
    {
        foreach (glob(self::GOLDEN_DIR . '/fixtures/options/*.json') as $file) {
            $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            yield basename($file, '.json') => [$data['option'], $data['value'], $data['outputs']];
        }
    }

    #[DataProvider('profileCases')]
    public function testProfileParity(string $profile, array $options, string $case, string $input): void
    {
        $fixture = self::GOLDEN_DIR . "/fixtures/$profile/$case.out";
        self::assertFileExists($fixture, "Missing fixture — run: php tools/capture_golden.php --update");
        self::assertSame(
            file_get_contents($fixture),
            self::runEngine($input, $options),
            "Output diverged from golden fixture $profile/$case"
        );
    }

    #[DataProvider('optionCases')]
    public function testSingleOptionParity(string $option, string $value, array $outputs): void
    {
        $corpus = self::corpus();
        foreach ($outputs as $case => $expectedBase64) {
            self::assertArrayHasKey($case, $corpus, "Option-matrix corpus case missing: $case");
            self::assertSame(
                base64_decode($expectedBase64, true),
                self::runEngine($corpus[$case], [$option => $value]),
                "Output diverged for option $option=$value on $case"
            );
        }
    }

    /** @return array<string,string> */
    private static function corpus(): array
    {
        static $corpus = null;
        if ($corpus === null) {
            $corpus = [];
            foreach (glob(self::GOLDEN_DIR . '/corpus/*.{txt,html}', GLOB_BRACE) as $file) {
                $corpus[basename($file)] = (string) file_get_contents($file);
            }
            ksort($corpus);
        }
        return $corpus;
    }

    private static function runEngine(string $input, array $options): string
    {
        $obj = new EMTypograph();
        if ($options !== []) {
            $obj->setup($options);
        }
        $obj->set_text($input);
        return $obj->apply();
    }
}
