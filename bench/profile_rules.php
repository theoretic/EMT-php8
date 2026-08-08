<?php
declare(strict_types=1);

/**
 * Per-rule cost profiler for the v3 engine: runs the pipeline over a corpus
 * file N times with a timing hook around every rule, prints the top offenders.
 *
 * Usage: php bench/profile_rules.php [case] [runs]
 */

require __DIR__ . '/../vendor/autoload.php';

use EMT\Engine\Ir\IrBuilder;
use EMT\Engine\Lexer\Lexer;
use EMT\Engine\Lexer\SafeBlockSet;
use EMT\Engine\Rules\Registry;
use EMT\Engine\Rules\RuleContext;
use EMT\Engine\Rules\RuleEngine;
use EMT\Engine\Support\TagBuilder;

$case = $argv[1] ?? '22-medium-15k.html';
$runs = (int) ($argv[2] ?? 20);
$input = file_get_contents(__DIR__ . "/../tests/Golden/corpus/$case");

$times = [];
$overhead = ['lex+ir' => 0.0, 'render' => 0.0];

for ($i = 0; $i < $runs; $i++) {
    $t0 = hrtime(true);
    $stream = (new Lexer(new SafeBlockSet()))->tokenize($input);
    $doc = (new IrBuilder())->build($stream);
    $doc->validateOnWrite = false;
    $overhead['lex+ir'] += (hrtime(true) - $t0) / 1e6;

    $engine = new RuleEngine();
    $tags = new TagBuilder();
    foreach (Registry::migratedInOrder() as $group) {
        $ctx = new RuleContext($doc, $tags, [], Registry::classesFor($group), Registry::classNamesFor($group));
        foreach (Registry::rulesFor($group) as $rule) {
            if ($rule->disabled) {
                continue;
            }
            $t0 = hrtime(true);
            $engine->apply($rule, $ctx);
            $times["$group.{$rule->id}"] = ($times["$group.{$rule->id}"] ?? 0) + (hrtime(true) - $t0) / 1e6;
        }
    }

    $t0 = hrtime(true);
    $doc->validate();
    $doc->render();
    $overhead['render'] += (hrtime(true) - $t0) / 1e6;
}

arsort($times);
$total = array_sum($times);
printf("case %s, %d runs; rules total %.2f ms/run, lex+ir %.2f, render+validate %.2f\n\n",
    $case, $runs, $total / $runs, $overhead['lex+ir'] / $runs, $overhead['render'] / $runs);
printf("%-42s %10s %7s\n", 'rule', 'ms/run', '%');
$shown = 0.0;
foreach ($times as $id => $ms) {
    if ($ms / $total < 0.01 && $shown / $total > 0.90) {
        break;
    }
    $shown += $ms;
    printf("%-42s %10.3f %6.1f%%\n", $id, $ms / $runs, 100 * $ms / $total);
}
