<?php
declare(strict_types=1);

namespace EMT\Engine\Lexer;

enum TokenType
{
    /** Plain text, including literal `<` that does not start markup. */
    case Text;
    /** An HTML tag: `<name ...>`, `</name>`, or `<? ... >`. */
    case Tag;
    /** `<!-- ... -->` including delimiters. */
    case Comment;
    /** `<![CDATA[ ... ]]>` including delimiters. */
    case Cdata;
    /** `<!DOCTYPE ...>` or any other `<! ... >` construct. */
    case Doctype;
    /**
     * Content that rules must never touch: the body between a raw-text /
     * safe tag pair (pre, script, style, notg, user-added), or a full
     * user-defined delimiter block. Delimiting tags are separate Tag tokens;
     * delimiter blocks include their delimiters in the raw string.
     */
    case Protected;
}
