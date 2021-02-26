<?php

namespace CodexSoft\DatabaseFirst\Orm;

use Doctrine\ORM\EntityManagerInterface;

trait KnownEntityManagerTrait
{
    /**
     * @param EntityManagerInterface|null $em
     *
     * @return EntityManagerInterface
     */
    private static function knownEntityManager(EntityManagerInterface $em = null): EntityManagerInterface
    {
        if ($em instanceof EntityManagerInterface) {
            return $em;
        }

        if (\property_exists(static::class, 'knownEntityManagerContainerClass')) {
            /** @noinspection PhpUndefinedFieldInspection */
            $emContainerClass = static::$knownEntityManagerContainerClass;

            /** @var KnownEntityManagerContainerInterface $emContainerClass */
            $knownEm = $emContainerClass::getEntityManager();

            if ($knownEm instanceof EntityManagerInterface) {
                return $knownEm;
            }
        }

        if (\property_exists(static::class, 'knownEntityManagerRouterClass')) {
            $emRouterClass = static::$knownEntityManagerRouterClass;

            /** @var KnownEntityManagerRouterInterface $emRouterClass */
            $knownEm = $emRouterClass::getEntityManagerFor(static::class);

            if ($knownEm instanceof EntityManagerInterface) {
                return $knownEm;
            }
        }

        throw new \RuntimeException('EntityManager is not defined for entity '.static::class);
    }

}
