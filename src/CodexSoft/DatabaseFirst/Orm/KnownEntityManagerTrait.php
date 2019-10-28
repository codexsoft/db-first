<?php

namespace CodexSoft\DatabaseFirst\Orm;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;

trait KnownEntityManagerTrait
{

    /**
     * @param EntityManagerInterface|null $em
     *
     * @return EntityManager
     */
    private static function knownEntityManager(EntityManagerInterface $em = null): EntityManager
    {
        if ($em instanceof EntityManager) {
            return $em;
        }

        if (\property_exists(static::class, 'knownEntityManagerContainerClass')) {
            /** @noinspection PhpUndefinedFieldInspection */
            $emContainerClass = static::$knownEntityManagerContainerClass;

            /** @var KnownEntityManagerContainerInterface $emContainerClass */
            $knownEm = $emContainerClass::getEntityManager();

            if ($knownEm instanceof EntityManager) {
                return $knownEm;
            }
        }

        if (\property_exists(static::class, 'knownEntityManagerRouterClass')) {
            /** @noinspection PhpUndefinedFieldInspection */
            $emRouterClass = static::$knownEntityManagerRouterClass;

            /** @var KnownEntityManagerRouterInterface $emRouterClass */
            $knownEm = $emRouterClass::getEntityManagerFor(static::class);

            if ($knownEm instanceof EntityManager) {
                return $knownEm;
            }
        }

        throw new \RuntimeException('EntityManager is not defined for entity '.static::class);
    }

}
