<?php

namespace CodexSoft\DatabaseFirst\Orm\Postgres\Functions;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

class EarthBox extends FunctionNode
{
    private Node $earth;
    private Node $radius;

    /**
     * @param SqlWalker $sqlWalker
     *
     * @return string
     * @throws \Doctrine\ORM\Query\AST\ASTException
     */
    public function getSql(SqlWalker $sqlWalker)
    {
        return 'earth_box(' . $this->earth->dispatch($sqlWalker) . ', ' . $this->radius->dispatch($sqlWalker) . ')';
    }

    /**
     * @param Parser $parser
     *
     * @throws \Doctrine\ORM\Query\QueryException
     */
    public function parse( Parser $parser)
    {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);

        $this->earth = $parser->StringPrimary();
        $parser->match(Lexer::T_COMMA);

        $this->radius = $parser->ArithmeticPrimary();
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }
}
