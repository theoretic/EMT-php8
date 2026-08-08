<?php
declare(strict_types=1);

/**
 * Golden fixture capture / verification tool.
 *
 * Runs the CURRENT engine over tests/Golden/corpus/ for every profile in
 * tests/Golden/profiles.php and over the tier-2 per-option matrix, then either
 * compares against committed fixtures (default; non-zero exit on diff — CI
 * parity gate) or rewrites them (--update).
 *
 * Fixtures are the behavioral contract for the v3 engine rewrite. Regenerating
 * them is a deliberate act: review the diff before committing.
 *
 * Usage:
 *   php tools/capture_golden.php               # verify, exit 1 on any diff
 *   php tools/capture_golden.php --update      # rewrite fixtures
 *   php tools/capture_golden.php --filter=quotes --profile=default
 *
 * Quarantine inputs (tests/Golden/quarantine/) are pathological cases the
 * current engine handles badly (documented v2 bugs). Their output is recorded
 * as *.v2.out for reference but never asserted by GoldenTest.
 */

require __DIR__ . '/../vendor/autoload.php';

use EMT\EMTypograph;

const GOLDEN_DIR = __DIR__ . '/../tests/Golden';

/** Tier-2: corpus cases every single-option flip is captured against. */
const OPTION_MATRIX_CASES = ['02-plain-prose.txt', '14-html-fragment.html', '22-medium-15k.html'];

$update  = in_array('--update', $argv, true);
$filter  = null;
$profileFilter = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--filter=')) {
        $filter = substr($arg, 9);
    }
    if (str_starts_with($arg, '--profile=')) {
        $profileFilter = substr($arg, 10);
    }
}

$profiles = require GOLDEN_DIR . '/profiles.php';
$corpus   = load_dir(GOLDEN_DIR . '/corpus');
$quarantine = load_dir(GOLDEN_DIR . '/quarantine');

$diffs = 0;
$written = 0;
$checked = 0;

// ---- Tier 1: full profiles x full corpus -----------------------------------
foreach ($profiles as $profileName => $options) {
    if ($profileFilter !== null && $profileFilter !== $profileName) {
        continue;
    }
    foreach ($corpus as $case => $input) {
        if ($filter !== null && !str_contains($case, $filter)) {
            continue;
        }
        $output = run_engine($input, $options);
        $path = GOLDEN_DIR . "/fixtures/$profileName/$case.out";
        $checked++;
        if ($update) {
            write_fixture($path, $output);
            $written++;
        } else {
            $diffs += compare_fixture($path, $output, "$profileName/$case");
        }
    }
}

// ---- Tier 2: every option flipped from default x mini-corpus ---------------
if ($profileFilter === null) {
    $allOptions = (new EMTypograph())->get_options_list()['all'];
    foreach ($allOptions as $optName => $info) {
        if ($filter !== null && !str_contains($optName, $filter)) {
            continue;
        }
        if ($optName === 'OptAlign.layout') {
            $variants = ['class' => 'class']; // string option; 'style' is the default
        } else {
            // Flip from the option's default state.
            $defaultOn = !(is_array($info) && !empty($info['disabled']));
            $variants = [$defaultOn ? 'off' : 'on' => $defaultOn ? 'off' : 'on'];
        }
        foreach ($variants as $value) {
            $entry = [];
            foreach (OPTION_MATRIX_CASES as $case) {
                if (!isset($corpus[$case])) {
                    fwrite(STDERR, "option matrix case missing from corpus: $case\n");
                    exit(2);
                }
                $entry[$case] = base64_encode(run_engine($corpus[$case], [$optName => $value]));
            }
            $payload = json_encode(
                ['option' => $optName, 'value' => $value, 'outputs' => $entry],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ) . "\n";
            $safeName = str_replace('.', '_', $optName) . ".$value";
            $path = GOLDEN_DIR . "/fixtures/options/$safeName.json";
            $checked++;
            if ($update) {
                write_fixture($path, $payload);
                $written++;
            } else {
                $diffs += compare_fixture($path, $payload, "options/$safeName");
            }
        }
    }
}

// ---- Quarantine: record, never assert --------------------------------------
if ($profileFilter === null && $filter === null) {
    foreach ($quarantine as $case => $input) {
        $output = run_engine($input, []);
        if ($update) {
            write_fixture(GOLDEN_DIR . "/quarantine/$case.v2.out", $output);
        }
    }
}

if ($update) {
    echo "Updated $written fixtures.\n";
    exit(0);
}
echo $diffs === 0
    ? "OK: $checked fixtures match.\n"
    : "FAIL: $diffs of $checked fixtures differ. Run with --update after reviewing.\n";
exit($diffs === 0 ? 0 : 1);

// ----------------------------------------------------------------------------

function load_dir(string $dir): array
{
    $files = [];
    foreach (glob($dir . '/*.{txt,html}', GLOB_BRACE) as $file) {
        $files[basename($file)] = file_get_contents($file);
    }
    ksort($files);
    return $files;
}

function run_engine(string $input, array $options): string
{
    $obj = new EMTypograph();
    if ($options !== []) {
        $obj->setup($options);
    }
    $obj->set_text($input);
    $result = $obj->apply();
    if (!$obj->ok) {
        $msgs = array_map(
            static fn(array $e): string => ($e['info'] ?? '') . ' ' . ($e['text'] ?? ''),
            $obj->errors
        );
        fwrite(STDERR, "engine reported errors: " . implode('; ', $msgs) . "\n");
    }
    return $result;
}

function write_fixture(string $path, string $content): void
{
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }
    file_put_contents($path, $content);
}

function compare_fixture(string $path, string $actual, string $label): int
{
    if (!file_exists($path)) {
        fwrite(STDERR, "MISSING fixture: $label\n");
        return 1;
    }
    $expected = file_get_contents($path);
    if ($expected === $actual) {
        return 0;
    }
    $pos = 0;
    $max = min(strlen($expected), strlen($actual));
    while ($pos < $max && $expected[$pos] === $actual[$pos]) {
        $pos++;
    }
    fwrite(STDERR, sprintf(
        "DIFF %s at byte %d:\n  expected: %s\n  actual:   %s\n",
        $label,
        $pos,
        var_export(substr($expected, max(0, $pos - 20), 60), true),
        var_export(substr($actual, max(0, $pos - 20), 60), true)
    ));
    return 1;
}
