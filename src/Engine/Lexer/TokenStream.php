<?php
declare(strict_types=1);

namespace EMT\Engine\Lexer;

/**
 * @implements \IteratorAggregate<int, Token>
 */
final class TokenStream implements \IteratorAggregate, \Countable
{
    /** @param list<Token> $tokens */
    public function __construct(
        public readonly array $tokens,
    ) {
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->tokens);
    }

    public function count(): int
    {
        return count($this->tokens);
    }

    /** Reassemble the exact original input. */
    public function raw(): string
    {
        $out = '';
        foreach ($this->tokens as $token) {
            $out .= $token->raw;
        }
        return $out;
    }
}
