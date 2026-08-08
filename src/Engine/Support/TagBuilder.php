<?php
declare(strict_types=1);

namespace EMT\Engine\Support;

use EMT\EMT_Lib;
use EMT\Engine\Rules\RuleContext;

/**
 * v3 counterpart of EMT_Tret::tag() + EMT_Lib::build_safe_tag(): builds the
 * final HTML tag immediately (no base64 round-trip) and registers it in the
 * document's token table, returning "<openPH>content<closePH>" in IR space.
 * The tag markup is therefore invisible to later rules except as a
 * placeholder, while the content stays rule-visible — exactly the legacy
 * contract.
 */
final class TagBuilder
{
    public function __construct(
        public int $layout = EMT_Lib::LAYOUT_STYLE,
        /** false = no prefix; string prefix for class layout (legacy class_layout_prefix) */
        public string|false $classLayoutPrefix = false,
    ) {
    }

    /** @param array<string, string> $attribute */
    public function tag(RuleContext $ctx, string $content, string $tag = 'span', array $attribute = []): string
    {
        // EMT_Tret::tag() preamble, verbatim.
        if (isset($attribute['class'])) {
            $classname = $attribute['class'];
            if ($classname === 'nowrap' && !$ctx->isOn('nowrap')) {
                $tag = 'nobr';
                $attribute = [];
                $classname = '';
            }
            if ($classname !== '' && isset($ctx->classes[$classname])) {
                $styleInline = $ctx->classes[$classname];
                if ($styleInline) {
                    $attribute['__style'] = $styleInline;
                }
            }
            if ($classname !== '') {
                $classname = $ctx->classNames[$classname] ?? $classname;
                $classname = ($this->classLayoutPrefix ?: '') . $classname;
                $attribute['class'] = $classname;
            }
        }

        // EMT_Lib::build_safe_tag() body, minus the base64 encoding.
        $htmlTag = $tag;
        $classname = '';
        if (count($attribute)) {
            if ($this->layout & EMT_Lib::LAYOUT_STYLE) {
                if (isset($attribute['__style']) && $attribute['__style']) {
                    if (isset($attribute['style']) && $attribute['style']) {
                        $st = trim($attribute['style']);
                        if (mb_substr($st, -1) !== ';') {
                            $st .= ';';
                        }
                        $st .= $attribute['__style'];
                        $attribute['style'] = $st;
                    } else {
                        $attribute['style'] = $attribute['__style'];
                    }
                    unset($attribute['__style']);
                }
            }
            foreach ($attribute as $attr => $value) {
                if ($attr === '__style') {
                    continue;
                }
                if ($attr === 'class') {
                    $classname = "$value";
                    continue;
                }
                $htmlTag .= " $attr=\"$value\"";
            }
        }
        if (($this->layout & EMT_Lib::LAYOUT_CLASS) && $classname) {
            $htmlTag .= " class=\"$classname\"";
        }

        $open  = $ctx->doc->createTag("<$htmlTag>", strtolower($tag), false);
        $close = $ctx->doc->createTag("</$tag>", strtolower($tag), true);
        return $open . $content . $close;
    }
}
