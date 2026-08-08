<?php
declare(strict_types=1);

namespace EMT\Engine\Rules;

use EMT\Engine\Ir\IrDocument;
use EMT\Engine\Support\TagBuilder;

/**
 * Per-run state a rule closure can reach: the document, the group's settings
 * (legacy tret ->settings / ->is_on()), and the tag builder (legacy
 * $this->tag()). One context per group per run.
 */
final class RuleContext
{
    /** @var list<array{info: string, text: string}> */
    public array $errors = [];

    /** @param array<string, mixed> $settings */
    public function __construct(
        public readonly IrDocument $doc,
        public readonly TagBuilder $tags,
        private array $settings = [],
        /** @var array<string, string> group CSS classes (legacy tret ->classes) */
        public readonly array $classes = [],
        /** @var array<string, string> legacy tret ->class_names */
        public readonly array $classNames = [],
    ) {
    }

    /** Legacy EMT_Tret::is_on() semantics, verbatim. */
    public function isOn(string $key): bool
    {
        if (!isset($this->settings[$key])) {
            return false;
        }
        $kk = $this->settings[$key];
        return (is_string($kk) && strtolower($kk) === 'on') || $kk === '1' || $kk === true || $kk === 1;
    }

    /** Legacy EMT_Tret::ss() semantics. */
    public function setting(string $key): string
    {
        return isset($this->settings[$key]) ? strval($this->settings[$key]) : '';
    }

    public function set(string $key, mixed $value): void
    {
        $this->settings[$key] = $value;
    }

    /**
     * Legacy EMT_Tret::tag(): build a protected tag around $content and return
     * the IR string (placeholders + content). The nowrap-class downgrade to a
     * bare <nobr> is preserved.
     *
     * @param array<string, string> $attribute
     */
    public function tag(string $content, string $tag = 'span', array $attribute = []): string
    {
        return $this->tags->tag($this, $content, $tag, $attribute);
    }

    public function error(string $info, string $text = ''): void
    {
        $this->errors[] = ['info' => $info, 'text' => $text];
    }
}
