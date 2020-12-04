<?php

namespace CodexSoft\DatabaseFirst\Orm;

use Doctrine\ORM\EntityManager;

interface KnownEntityManagerContainerInterface
{
    public static function getEntityManager(): ?EntityManager;
    public static function setEntityManager(EntityManager $entityManager): void;
}
