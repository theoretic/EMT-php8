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
                $chunk = Placeholder::escapeText($token->raw);
                if ($this->normalize) {
                    $chunk = EMT_Lib::clear_special_chars($chunk);
                }
                $text .= $chunk;
                continue;
            }
            $table[$index] = $token;
            $text .= Placeholder::forToken($token, $index);
            $index++;
        }

        return new IrDocument($table, $text);
    }
}
