<?php
declare(strict_types=1);

namespace EMT\Engine;

use EMT\Engine\Ir\IrBuilder;
use EMT\Engine\Lexer\Lexer;
use EMT\Engine\Lexer\SafeBlockSet;
use EMT\Engine\Rules\Registry;
use EMT\Engine\Rules\Rule;
use EMT\Engine\Rules\RuleContext;
use EMT\Engine\Rules\RuleEngine;
use EMT\Engine\Support\TagBuilder;

/**
 * v3 pipeline: lex -> IR -> rule groups in v2 tret order -> render -> trim.
 *
 * Mirrors EMT_Base::apply() with the escape layer replaced by the lexer/IR.
 * Until all 12 groups are migrated this runs only the requested (migrated)
 * groups — used by the shadow harness; the legacy engine stays the default.
 */
final class Pipeline
{
    /** @var list<array{info: string, text: string}> */
    public array $errors = [];

    /** Legacy EMT_Base::$disable_notg_replace / $remove_notg flags. */
    public bool $disableNotgReplace = false;
    public bool $removeNotg = false;

    /** @param array<string, array<string, mixed>> $groupSettings group => settings */
    public function __construct(
        private readonly SafeBlockSet $safeBlocks = new SafeBlockSet(),
        private readonly TagBuilder $tags = new TagBuilder(),
        private readonly array $groupSettings = [],
        /** @var array<string, array<string, bool>> group => rule id => enabled override */
        private readonly array $ruleOverrides = [],
    ) {
    }

    /** @param list<string>|null $groups null = all migrated groups */
    public function run(string $input, ?array $groups = null): string
    {
        $this->errors = [];
        $groups ??= Registry::migratedInOrder();

        $stream = (new Lexer($this->safeBlocks))->tokenize($input);
        $doc = (new IrBuilder())->build($stream);
        $engine = new RuleEngine();

        foreach ($groups as $group) {
            $ctx = new RuleContext(
                $doc,
                $this->tags,
                $this->groupSettings[$group] ?? [],
                Registry::classesFor($group),
                Registry::classNamesFor($group),
            );
            foreach (Registry::rulesFor($group) as $rule) {
                if (!$this->isEnabled($group, $rule)) {
                    continue;
                }
                $engine->apply($rule, $ctx);
            }
            foreach ($ctx->errors as $error) {
                $this->errors[] = $error;
            }
        }

        $out = $doc->render();
        if (!$this->disableNotgReplace) {
            $repl = ['<span class="_notg_start"></span>', '<span class="_notg_end"></span>'];
            if ($this->removeNotg) {
                $repl = '';
            }
            $out = str_replace(['<notg>', '</notg>'], $repl, $out);
        }
        return trim($out);
    }

    private function isEnabled(string $group, Rule $rule): bool
    {
        return $this->ruleOverrides[$group][$rule->id] ?? !$rule->disabled;
    }
}
