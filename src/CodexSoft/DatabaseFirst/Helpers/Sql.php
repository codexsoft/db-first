<?php


namespace CodexSoft\DatabaseFirst\Helpers;


use CodexSoft\DatabaseFirst\Migration\SqlParser;

class Sql
{
    public static function parseToStatements(string $sqlCode): array
    {
        return SqlParser::parseString($sqlCode);
    }
}