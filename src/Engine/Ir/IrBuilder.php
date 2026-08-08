<?php
declare(strict_types=1);

namespace EMT\Engine\Ir;

use EMT\EMT_Lib;
use EMT\Engine\Lexer\TokenStream;
use EMT\Engine\Lexer\TokenType;

/**
 * Linearizes a TokenStream into an IrDocument.
 *
 * Text tokens are PUA-escaped and (unless disabled) entity-normalized with the
 * same character table the legacy engine uses (EMT_Lib::clear_special_chars),
 * keeping the entity intermediate representation the 106 rules are written
 * against. Non-text tokens become placeholders; their raw bytes go to the
 * token table untouched.
 */
final class IrBuilder
{
    public function __construct(
        private readonly bool $normalize = true,
    ) {
    }

    public function build(TokenStream $stream): IrDocument
    {
        $table = [];
        $text = '';
        $index = 0;

        foreach ($stream as $token) {
            if ($token->type === TokenType::Text) {
                $text .= Placeholder::escapeText($token->raw);
                continue;
            }
            $table[$index] = $token;
            $text .= Placeholder::forToken($token, $index);
            $index++;
        }

        if ($this->normalize) {
            // One strtr over the whole IR string instead of one per text
            // token: no normalization key contains a PUA codepoint, so
            // placeholders are opaque to the map and the result is identical.
            $text = EMT_Lib::clear_special_chars($text);
        }

        return new IrDocument($table, $text);
    }
}
