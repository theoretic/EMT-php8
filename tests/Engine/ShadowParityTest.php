<?php
declare(strict_types=1);

use EMT\EMTypograph;
use EMT\Engine\Pipeline;
use EMT\Engine\Rules\Registry;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Shadow parity: for every migrated rule group, the v3 pipeline must produce
 * byte-identical output to the legacy engine restricted to the same tret(s),
 * over the whole golden corpus. Quarantine inputs are excluded by design
 * (documented v2 bugs, see docs/v3-behavior-changes.md).
 *
 * Ad-hoc exploration: php tools/shadow_diff.php <Group...> | --all | --combined
 */
final class ShadowParityTest extends TestCase
{
    public static function cases(): iterable
    {
        $sets = array_map(static fn(string $g): array => [$g], Registry::migratedInOrder());
        $sets[] = Registry::migratedInOrder(); // all migrated groups in one run

        foreach ($sets as $set) {
            $label = implode('+', $set);
            foreach (self::corpus() as $name => $input) {
                yield "$label/$name" => [$set, $input];
            }
        }
    }

    /** @param list<string> $groups */
    #[DataProvider('cases')]
    public function testParity(array $groups, string $input): void
    {
        $legacy = new EMTypograph();
        $legacy->set_text($input);
        $v2 = $legacy->apply(array_map(static fn(string $g): string => "EMT\\EMT_Tret_$g", $groups));

        $v3 = (new Pipeline())->run($input, $groups);

        self::assertSame($v2, $v3);
    }

    /**
     * Deterministic fuzz for the quote nester — the hardest parity item:
     * random soups of quotes, digits, words and inches must come out
     * byte-identical from both engines.
     */
    public function testQuoteNesterFuzz(): void
    {
        mt_srand(20260808);
        $alphabet = ['"', '"', '"', 'слово', 'word', ' ', ' ', '3.5', '25', '.', ',', '!', "\n\n", '«', '»', '(', ')'];
        for ($round = 0; $round < 300; $round++) {
            $s = '';
            $len = mt_rand(1, 25);
            for ($k = 0; $k < $len; $k++) {
                $s .= $alphabet[mt_rand(0, count($alphabet) - 1)];
            }
            $legacy = new EMTypograph();
            $legacy->set_text($s);
            $v2 = $legacy->apply(['EMT\EMT_Tret_Quote']);
            $v3 = (new Pipeline())->run($s, ['Quote']);
            self::assertSame($v2, $v3, 'quote fuzz round ' . $round . ' input: ' . var_export($s, true));
        }
    }

    /** @return array<string, string> */
    private static function corpus(): array
    {
        static $corpus = null;
        if ($corpus === null) {
            $corpus = [];
            foreach (glob(__DIR__ . '/../Golden/corpus/*.{txt,html}', GLOB_BRACE) as $file) {
                $corpus[basename($file)] = (string) file_get_contents($file);
            }
            ksort($corpus);
        }
        return $corpus;
    }
}
