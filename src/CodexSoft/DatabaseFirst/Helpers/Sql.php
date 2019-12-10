<?php


namespace CodexSoft\DatabaseFirst\Helpers;


use CodexSoft\DatabaseFirst\Helpers\SqlParser;

class Sql
{

    /**
     * @param string $sqlCode
     *
     * @return array
     */
    public static function parseToStatements(string $sqlCode): array
    {
        return SqlParser::parseString($sqlCode);
    }
}
