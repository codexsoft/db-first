<?php

namespace CodexSoft\DatabaseFirst\Orm;

use Doctrine\ORM\EntityManagerInterface;

interface KnownEntityManagerRouterInterface
{
    public static function getEntityManagerFor(string $entityClass): ?EntityManagerInterface;
}
