<?php
declare(strict_types=1);

/**
 * Shadow parity harness: run the legacy engine and the v3 pipeline over the
 * golden corpus, restricted to one or more migrated rule groups, and diff.
 *
 * The legacy side uses EMT_Base::apply(['EMT\EMT_Tret_X']) — full escape
 * pipeline with only the selected trets. The v3 side runs Pipeline with the
 * matching groups. Byte-identical output required.
 *
 * Usage:
 *   php tools/shadow_diff.php Symbol
 *   php tools/shadow_diff.php Symbol Punctmark Number
 *   php tools/shadow_diff.php --all            # every migrated group, one by one
 *   php tools/shadow_diff.php --combined       # all migrated groups in one run
 *   php tools/shadow_diff.php Symbol --quarantine  # include quarantine inputs (informational)
 *
 * Exit 1 on any corpus diff. Quarantine diffs are reported but do not fail
 * (documented v2 bugs live there).
 */

require __DIR__ . '/../vendor/autoload.php';

use EMT\EMTypograph;
use EMT\Engine\Pipeline;
use EMT\Engine\Rules\Registry;

$args = array_slice($argv, 1);
$includeQuarantine = in_array('--quarantine', $args, true);
$all = in_array('--all', $args, true);
$combined = in_array('--combined', $args, true);
$groups = array_values(array_filter($args, static fn(string $a): bool => !str_starts_with($a, '--')));

if ($all) {
    $sets = array_map(static fn(string $g): array => [$g], Registry::migratedInOrder());
} elseif ($combined) {
    $sets = [Registry::migratedInOrder()];
} elseif ($groups !== []) {
    $sets = [$groups];
} else {
    fwrite(STDERR, "usage: shadow_diff.php <Group...> | --all | --combined [--quarantine]\n");
    exit(2);
}

$corpusFiles = corpus(__DIR__ . '/../tests/Golden/corpus');
$quarantineFiles = $includeQuarantine ? corpus(__DIR__ . '/../tests/Golden/quarantine') : [];

$failures = 0;
foreach ($sets as $set) {
    foreach ($set as $g) {
        if (!Registry::has($g)) {
            fwrite(STDERR, "group not migrated: $g\n");
            exit(2);
        }
    }
    $label = implode('+', $set);
    $diffCount = 0;

    foreach ($corpusFiles as $name => $input) {
        [$v2, $v3] = run_both($input, $set);
        if ($v2 !== $v3) {
            $diffCount++;
            $failures++;
            report_diff("$label/$name", $v2, $v3);
        }
    }
    foreach ($quarantineFiles as $name => $input) {
        [$v2, $v3] = run_both($input, $set);
        if ($v2 !== $v3) {
            echo "QUARANTINE-DIFF (expected, informational) $label/$name\n";
        }
    }
    echo $diffCount === 0
        ? "OK $label: " . count($corpusFiles) . " corpus files identical\n"
        : "FAIL $label: $diffCount corpus diffs\n";
}
exit($failures === 0 ? 0 : 1);

/** @return array{0: string, 1: string} */
function run_both(string $input, array $groups): array
{
    $legacy = new EMTypograph();
    $legacy->set_text($input);
    $v2 = $legacy->apply(array_map(static fn(string $g): string => "EMT\\EMT_Tret_$g", $groups));

    $v3 = (new Pipeline())->run($input, $groups);
    return [$v2, $v3];
}

/** @return array<string, string> */
function corpus(string $dir): array
{
    $files = [];
    foreach (glob($dir . '/*.{txt,html}', GLOB_BRACE) as $file) {
        $files[basename($file)] = (string) file_get_contents($file);
    }
    ksort($files);
    return $files;
}

function report_diff(string $label, string $v2, string $v3): void
{
    $pos = 0;
    $max = min(strlen($v2), strlen($v3));
    while ($pos < $max && $v2[$pos] === $v3[$pos]) {
        $pos++;
    }
    fwrite(STDERR, sprintf(
        "DIFF %s at byte %d\n  v2: %s\n  v3: %s\n",
        $label,
        $pos,
        var_export(substr($v2, max(0, $pos - 30), 80), true),
        var_export(substr($v3, max(0, $pos - 30), 80), true)
    ));
}
