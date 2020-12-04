<?php
/**
 * Created by PhpStorm.
 * User: dx
 * Date: 17.04.18
 * Time: 19:09
 */

namespace CodexSoft\DatabaseFirst\Orm\Postgres\Functions;


use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

class EarthDistance extends FunctionNode
{

    /** @var Node */
    private $latitude;

    /** @var Node */
    private $longitude;

    /**
     * @param SqlWalker $sqlWalker
     *
     * @return string
     * @throws \Doctrine\ORM\Query\AST\ASTException
     */
    public function getSql(SqlWalker $sqlWalker)
    {
        return 'earth_distance(' . $this->latitude->dispatch($sqlWalker) . ', ' . $this->longitude->dispatch($sqlWalker) . ')';
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

        $this->latitude = $parser->ArithmeticPrimary();
        $parser->match(Lexer::T_COMMA);

        $this->longitude = $parser->ArithmeticPrimary();
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }
}