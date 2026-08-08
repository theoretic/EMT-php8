<?php
declare(strict_types=1);

namespace EMT\Engine\Lexer;

final class Token
{
    public function __construct(
        public readonly TokenType $type,
        /** Exact input bytes; concatenating all tokens' raw reproduces the input. */
        public readonly string $raw,
        /** Lowercase tag name for Tag tokens (null for `<?...>`), null otherwise. */
        public readonly ?string $name = null,
        /** True for closing tags (`</name>`). */
        public readonly bool $closing = false,
    ) {
    }
}
