<?php
declare(strict_types=1);

namespace EMT\Engine\Rules;

/**
 * Applies one Rule to the document, mirroring EMT_Tret::apply_rule() +
 * replace_cycled() dispatch semantics: procedure > simple_replace > regex,
 * pattern arrays applied index-parallel, cycled rules re-run per pattern
 * until no replacement happened (capped), preg failures reported instead of
 * silently corrupting the text.
 */
final class RuleEngine
{
    public const MAX_CYCLES = 20; // EMT_Tret::MAX_CYCLES

    public function apply(Rule $rule, RuleContext $ctx): void
    {
        if ($rule->procedure !== null) {
            ($rule->procedure)($ctx);
            return;
        }

        $text = $ctx->doc->text();

        if ($rule->simpleReplace) {
            foreach ($rule->patterns as $i => $needle) {
                $replacement = $rule->replacements[$i];
                $text = $rule->caseSensitive
                    ? str_replace($needle, $replacement, $text)
                    : str_ireplace($needle, $replacement, $text);
            }
            $ctx->doc->setText($text);
            return;
        }

        foreach ($rule->patterns as $i => $pattern) {
            $replacement = $rule->replacements[$i];
            $iteration = 0;
            do {
                $count = 0;
                $result = $replacement instanceof \Closure
                    ? preg_replace_callback(
                        $pattern,
                        static fn(array $m): string => $replacement($m, $ctx),
                        $text,
                        -1,
                        $count
                    )
                    : preg_replace($pattern, $replacement, $text, -1, $count);
                if ($result === null) {
                    $ctx->error(
                        "Правило {$rule->id}",
                        'preg failure: ' . preg_last_error_msg() . " (pattern $pattern)"
                    );
                    break;
                }
                $text = $result;
            } while ($rule->cycled && $count > 0 && ++$iteration < self::MAX_CYCLES);
        }

        $ctx->doc->setText($text);
    }
}
