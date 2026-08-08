<?php
declare(strict_types=1);

namespace EMT\Engine\Rules;

/**
 * One typography rule, the v3 counterpart of a legacy tret rule array.
 *
 * Legacy /e-flag replacements become real closures; patterns that referenced
 * the base64 tag encoding are rewritten to the placeholder grammar
 * (\x{E000}...\x{E001}) at porting time. Everything else is carried over
 * verbatim — parity with the v2 engine is proven per-rule by the shadow
 * harness (tools/shadow_diff.php), not assumed.
 */
final class Rule
{
    /**
     * @param list<string> $patterns
     * @param list<string|\Closure> $replacements parallel to $patterns;
     *        closures receive (array $m, RuleContext $ctx): string
     * @param \Closure(RuleContext): void|null $procedure free-form rule body
     *        (legacy 'function' rules without a pattern)
     */
    public function __construct(
        public readonly string $id,
        public readonly array $patterns = [],
        public readonly array $replacements = [],
        public readonly bool $simpleReplace = false,
        public readonly bool $caseSensitive = false,
        public readonly bool $cycled = false,
        public readonly bool $disabled = false,
        public readonly ?\Closure $procedure = null,
        /** @var \Closure(string): bool|null cheap skip check, see Gate */
        public readonly ?\Closure $gate = null,
    ) {
    }
}
