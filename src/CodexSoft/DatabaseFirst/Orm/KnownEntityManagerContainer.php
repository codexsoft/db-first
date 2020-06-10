<?php

namespace CodexSoft\DatabaseFirst\Orm;

use Doctrine\ORM\EntityManager;

/**
 * This class can be used to provide entity know its default entityManager
 * todo: This is very dirty solution and should be refactored ASAP. Maybe via smth like mapper?
 */
abstract class KnownEntityManagerContainer implements KnownEntityManagerContainerInterface
{
    protected static ?EntityManager $entityManager = null;

    public static function getEntityManager(): ?EntityManager
    {
        return static::$entityManager;
    }

    public static function setEntityManager(EntityManager $entityManager): void
    {
        static::$entityManager = $entityManager;
    }

}
