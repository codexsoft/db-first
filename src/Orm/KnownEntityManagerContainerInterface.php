<?php

namespace CodexSoft\DatabaseFirst\Orm;

use Doctrine\ORM\EntityManagerInterface;

interface KnownEntityManagerContainerInterface
{
    public static function getEntityManager(): ?EntityManagerInterface;
    public static function setEntityManager(EntityManagerInterface $entityManager): void;
}
