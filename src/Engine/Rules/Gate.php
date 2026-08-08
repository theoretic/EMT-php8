<?php
declare(strict_types=1);

namespace EMT\Engine\Rules;

/**
 * Cheap pre-checks that let the engine skip a rule's preg pass entirely.
 *
 * SOUNDNESS RULE: a gate literal must be *implied by the pattern* — present in
 * every possible match, with case handled (str_contains for case-exact atoms,
 * stripos only for ASCII /i atoms). A wrong gate silently changes output, so
 * when in doubt a rule stays ungated; the golden matrix and shadow tests are
 * the safety net.
 */
final class Gate
{
    /** Rule runs if ANY needle is present (case-sensitive). */
    public static function any(string ...$needles): \Closure
    {
        return static function (string $text) use ($needles): bool {
            foreach ($needles as $needle) {
                if (str_contains($text, $needle)) {
                    return true;
                }
            }
            return false;
        };
    }

    /** Rule runs if ANY needle is present, ASCII case-insensitive. */
    public static function anyCI(string ...$needles): \Closure
    {
        return static function (string $text) use ($needles): bool {
            foreach ($needles as $needle) {
                if (stripos($text, $needle) !== false) {
                    return true;
                }
            }
            return false;
        };
    }

    /** Rule runs if the text contains any decimal digit. */
    public static function digits(): \Closure
    {
        return static fn(string $text): bool => strpbrk($text, '0123456789') !== false;
    }

    /** Rule runs if the text contains any of the given single-byte chars. */
    public static function bytes(string $chars): \Closure
    {
        return static fn(string $text): bool => strpbrk($text, $chars) !== false;
    }
}
