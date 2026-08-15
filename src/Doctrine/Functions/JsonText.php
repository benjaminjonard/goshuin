<?php

declare(strict_types=1);

namespace App\Doctrine\Functions;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * "JSON_TEXT" "(" StateFieldPathExpression ")"
 *
 * Reads a JSON array of strings back as one line of text, so a LIKE reaches every entry.
 */
class JsonText extends FunctionNode
{
    public Node $field;

    #[\Override]
    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $this->field = $parser->StateFieldPathExpression();

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    #[\Override]
    public function getSql(SqlWalker $sqlWalker): string
    {
        return sprintf(
            "(SELECT COALESCE(STRING_AGG(entry, ' '), '') FROM JSONB_ARRAY_ELEMENTS_TEXT(%s::jsonb) AS entry)",
            $this->field->dispatch($sqlWalker),
        );
    }
}
