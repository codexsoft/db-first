<?php

namespace CodexSoft\DatabaseFirst\Orm;

use Doctrine\ORM\EntityManagerInterface;

/**
 * This class can be used to provide entity know its default entityManager
 * todo: This is very dirty solution and should be refactored ASAP. Maybe via smth like mapper?
 */
abstract class KnownEntityManagerContainer implements KnownEntityManagerContainerInterface
{
    protected static ?EntityManagerInterface $entityManager = null;

    public static function getEntityManager(): ?EntityManagerInterface
    {
        return static::$entityManager;
    }

    public static function setEntityManager(EntityManagerInterface $entityManager): void
    {
        static::$entityManager = $entityManager;
    }

}
