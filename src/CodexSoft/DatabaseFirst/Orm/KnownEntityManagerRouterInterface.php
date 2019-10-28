<?php

namespace CodexSoft\DatabaseFirst\Orm;

use Doctrine\ORM\EntityManager;

interface KnownEntityManagerRouterInterface
{
    public static function getEntityManagerFor(string $entityClass): ?EntityManager;
}
