<?php
declare(strict_types=1);

/**
 * EMT benchmark: median-of-N fast_apply() timing over three corpus sizes.
 *
 * Usage:
 *   php bench/bench.php            # human-readable
 *   php bench/bench.php --json     # machine-readable (CI trend tracking)
 *   php bench/bench.php --runs=50
 *
 * Baseline numbers live in bench/BASELINE.md. Compare against them after any
 * engine change; the v3 rewrite targets >=2x on 22-medium-15k.html.
 */

require __DIR__ . '/../vendor/autoload.php';

use EMT\EMTypograph;

$runs = 30;
$json = in_array('--json', $argv, true);
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--runs=')) {
        $runs = max(3, (int) substr($arg, 7));
    }
}

$cases = [
    '02-plain-prose.txt',
    '14-html-fragment.html',
    '22-medium-15k.html',
    '23-large-100k.html',
    'sparse-15k.txt', // bench-only (bench/): prose without digits/quotes/links — shows rule-gate skips
];

$corpusDir = __DIR__ . '/../tests/Golden/corpus';
$results = [];

foreach ($cases as $case) {
    $path = file_exists("$corpusDir/$case") ? "$corpusDir/$case" : __DIR__ . "/$case";
    $input = file_get_contents($path);
    if ($input === false) {
        fwrite(STDERR, "missing corpus file: $case\n");
        exit(1);
    }

    // Warmup (fills PCRE pattern cache, opcache, realpath cache).
    EMTypograph::fast_apply($input);

    $times = [];
    for ($i = 0; $i < $runs; $i++) {
        $t0 = hrtime(true);
        EMTypograph::fast_apply($input);
        $times[] = (hrtime(true) - $t0) / 1e6; // ms
    }
    sort($times);
    $median = $times[intdiv($runs, 2)];
    $p90 = $times[(int) floor($runs * 0.9)];

    $results[] = [
        'case'      => $case,
        'bytes'     => strlen($input),
        'runs'      => $runs,
        'min_ms'    => round($times[0], 3),
        'median_ms' => round($median, 3),
        'p90_ms'    => round($p90, 3),
    ];
}

$meta = [
    'php'  => PHP_VERSION,
    'os'   => PHP_OS_FAMILY,
    'peak_mem_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
];

if ($json) {
    echo json_encode(['meta' => $meta, 'results' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}

printf("PHP %s on %s, %d runs per case, peak mem %.1f MB\n\n", $meta['php'], $meta['os'], $runs, $meta['peak_mem_mb']);
printf("%-26s %10s %10s %12s %10s\n", 'case', 'bytes', 'min ms', 'median ms', 'p90 ms');
foreach ($results as $r) {
    printf("%-26s %10d %10.3f %12.3f %10.3f\n", $r['case'], $r['bytes'], $r['min_ms'], $r['median_ms'], $r['p90_ms']);
}
