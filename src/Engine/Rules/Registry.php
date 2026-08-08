<?php
declare(strict_types=1);

namespace EMT\Engine\Rules;

use EMT\Engine\Rules\Groups;

/**
 * Rule-group registry. Group order matches EMTypograph::$trets exactly —
 * sequential rule semantics are observable (groups feed each other), so the
 * order is part of the parity contract.
 *
 * All 12 groups are registered; the legacy engine remains the default until
 * the Phase 5 cutover.
 */
final class Registry
{
    /** Execution order, mirroring EMTypograph::$trets. */
    public const ORDER = [
        'Quote', 'Dash', 'Symbol', 'Punctmark', 'Number', 'Space',
        'Abbr', 'Nobr', 'Date', 'OptAlign', 'Etc', 'Text',
    ];

    /** @var array<string, class-string> */
    private const PROVIDERS = [
        'Quote'     => Groups\QuoteRules::class,
        'OptAlign'  => Groups\OptAlignRules::class,
        'Text'      => Groups\TextRules::class,
        'Symbol'    => Groups\SymbolRules::class,
        'Punctmark' => Groups\PunctmarkRules::class,
        'Number'    => Groups\NumberRules::class,
        'Date'      => Groups\DateRules::class,
        'Space'     => Groups\SpaceRules::class,
        'Abbr'      => Groups\AbbrRules::class,
        'Dash'      => Groups\DashRules::class,
        'Nobr'      => Groups\NobrRules::class,
        'Etc'       => Groups\EtcRules::class,
    ];

    public static function has(string $group): bool
    {
        return isset(self::PROVIDERS[$group]);
    }

    /** @return list<string> migrated groups in execution order */
    public static function migratedInOrder(): array
    {
        return array_values(array_filter(self::ORDER, self::has(...)));
    }

    /** @return list<Rule> */
    public static function rulesFor(string $group): array
    {
        $provider = self::PROVIDERS[$group] ?? throw new \InvalidArgumentException("unknown group: $group");
        return $provider::rules();
    }

    /** @return array<string, string> */
    public static function classesFor(string $group): array
    {
        $provider = self::PROVIDERS[$group] ?? throw new \InvalidArgumentException("unknown group: $group");
        return method_exists($provider, 'classes') ? $provider::classes() : [];
    }

    /** @return array<string, string> */
    public static function classNamesFor(string $group): array
    {
        $provider = self::PROVIDERS[$group] ?? throw new \InvalidArgumentException("unknown group: $group");
        return method_exists($provider, 'classNames') ? $provider::classNames() : [];
    }
}
