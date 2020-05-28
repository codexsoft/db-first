<?php

namespace CodexSoft\DatabaseFirst\Orm;

use CodexSoft\Code\Classes\Classes;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;

/**
 * todo: previous implementation was expected single entity manager per domain,
 * so in repo(), byId() and other methods $em argument was absent, and it was handy.
 * should not we have 2 traits for both cases?
 */
trait RepoStaticAccessTrait
{

    use KnownEntityManagerTrait;

    /**
     * @param EntityManagerInterface $em
     *
     * @return \Doctrine\ORM\EntityRepository
     */
    public static function repo(EntityManagerInterface $em = null)
    {
        return static::knownEntityManager($em)->getRepository(static::class);
    }

    /**
     * @param int|string|static $id
     *
     * @param EntityManagerInterface $em
     *
     * @return static
     */
    public static function byId($id, EntityManagerInterface $em = null)
    {
        $foundEntity = static::byIdOrNull($id, $em);
        if (!static::isInstanceOrProxy($foundEntity)) {
            throw new \RuntimeException(Classes::short(__CLASS__).' with ID='.$id.' not found!');
        }

        return $foundEntity;
    }

    /**
     * @param int[] $ids
     *
     * @param EntityManagerInterface $em
     *
     * @return static[]
     */
    public static function byIds(array $ids, EntityManagerInterface $em = null)
    {
        $repository = static::repo($em);
        if (method_exists($repository,'getByIdsIndexedById')) {
            return $repository->getByIdsIndexedById($ids);
        }
        return $repository->findBy([static::_id() => $ids]);
    }

    /**
     * Checks that $entity is instance of this class or
     * @param $entity
     *
     * @return bool
     */
    private static function isInstanceOrProxy(object $entity): bool
    {
        if ($entity instanceof static) {
            return true;
        }
        $entityRealClass = ClassUtils::getClass($entity);
        $staticRealClass = ClassUtils::getRealClass(static::class);
        return ($entityRealClass === $staticRealClass) || \is_subclass_of($entityRealClass, $staticRealClass);
    }

    /**
     * @param int|string|static $id
     *
     * @param EntityManagerInterface $em
     *
     * @return static|null
     */
    public static function byIdOrNull($id, EntityManagerInterface $em = null)
    {
        if (\is_object($id) && static::isInstanceOrProxy($id)) {
            return $id;
        }

        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return self::repo($em)->find((int) $id);
    }

    /**
     * Выдаст ID, если передать ID
     *
     * В некоторых случаях, чтобы не делать лишние запросы в БД, имея на руках ID
     * или сущность, можно пользоваться этим методом.
     *
     * @param int|string|static $idOrEntity
     *
     * @return int
     * @throws \RuntimeException
     */
    public static function extractId($idOrEntity): int
    {
        $id = static::extractIdOrNull($idOrEntity);
        if ($id === null) {
            throw new \RuntimeException("ID '$idOrEntity' is not entity nor numeric value");
        }

        if ($id < 1) {
            throw new \RuntimeException("invalid ID '$idOrEntity' extracted");
        }

        return $id;
    }

    /**
     * @param int|string|static $idOrEntity
     *
     * @return int|null
     */
    public static function extractIdOrNull($idOrEntity): ?int
    {
        if (\is_string($idOrEntity) && \is_numeric($idOrEntity)) {
            return (int) $idOrEntity;
        }

        if (\is_int($idOrEntity)) {
            return $idOrEntity;
        }

        if (\is_object($idOrEntity) && static::isInstanceOrProxy($idOrEntity)) {
            // todo: is entity always has method getId()? Maybe check that it has getter?
            if ($idOrEntity->getId() === null) {
                throw new \RuntimeException('Entity of class '.static::class.' has not ID yet!');
            }
            return $idOrEntity->getId();
        }

        return null;
    }

    /**
     * @param EntityManagerInterface|null $em
     *
     * @return int|null
     * @throws \Doctrine\DBAL\DBALException
     *
     * todo: all models should have method _db_table_()
     */
    public static function randomId(EntityManagerInterface $em = null): ?int
    {
        $table = static::_db_table_();
        /** @noinspection SqlResolve */
        $stmt = self::knownEntityManager($em)->getConnection()->query('SELECT id FROM '.$table.' ORDER BY RANDOM() LIMIT 1');
        $id = $stmt->fetchColumn();
        return (int) $id;
    }

    /**
     * @param EntityManagerInterface|null $em
     *
     * @return static|null
     * @throws \Doctrine\DBAL\DBALException
     */
    public static function randomEntity(EntityManagerInterface $em = null): ?self
    {
        $id = static::randomId();
        return static::byId($id, $em);
    }

}
