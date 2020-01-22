<?php

namespace CodexSoft\DatabaseFirst\Orm;

use CodexSoft\Code\Classes\Classes;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Some helpers for access repository directly from entity class, for example:
 *
 * Product::repo() => ProductRepository
 * Product::byId() => Product
 * Product::byIdOrNull() => ?Product
 * Product::byIds() => Product[]
 * Product::randomId() => int
 * Product::randomEntity() => Product
 *
 * All these methods expect ?EntityManagerInterface $em parameter, OR statically configured
 * entity-repo map (via KnownEntityManagerContainer::setEntityManager($em)) and
 * (new DoctrineOrmSchema)->setKnownEntityManagerContainerClass(KnownEntityManagerContainer::class)
 *
 * KnownEntityManagerContainer can be child (neccessary when multiple orm schemas and/or entity managers are used).
 *
 * todo: previous implementation was expected single entity manager per domain,
 * so in repo(), byId() and other methods $em argument was absent, and it was handy.
 * should not we have 2 traits for both cases?
 */
trait RepoStaticAccessTrait
{
    use KnownEntityManagerTrait;
    use EntityExtractIdTrait;

    /**
     * Caution! If entityManager argument is omitted, knownEntityManager must be properly configured
     * @param EntityManagerInterface $em
     *
     * @return \Doctrine\ORM\EntityRepository
     */
    public static function repo(?EntityManagerInterface $em = null)
    {
        return static::knownEntityManager($em)->getRepository(static::class);
    }

    /**
     * Caution! If entityManager argument is omitted, knownEntityManager must be properly configured
     * @param int|string|static $id
     *
     * @param EntityManagerInterface $em
     *
     * @return static
     */
    public static function byId($id, ?EntityManagerInterface $em = null)
    {
        $foundEntity = static::byIdOrNull($id, $em);
        if (!$foundEntity instanceof static) {
            throw new \RuntimeException(Classes::short(__CLASS__).' with ID='.$id.' not found!');
        }

        return $foundEntity;
    }

    /**
     * Caution! If entityManager argument is omitted, knownEntityManager must be properly configured
     * @param int[] $ids
     *
     * @param EntityManagerInterface $em
     *
     * @return static[]
     */
    public static function byIds(array $ids, ?EntityManagerInterface $em = null)
    {
        $repository = static::repo($em);
        if (method_exists($repository,'getByIdsIndexedById')) {
            return $repository->getByIdsIndexedById($ids);
        }
        // todo: what if id is not present?
        //logger()->notice('called '.static::class.'::byIds and repo method getByIdsIndexedById is not defined');
        return $repository->findBy([static::_id() => $ids]);
    }

    /**
     * Caution! If entityManager argument is omitted, knownEntityManager must be properly configured
     * @param int|string|static $id
     *
     * @param EntityManagerInterface $em
     *
     * @return static|null
     */
    public static function byIdOrNull($id, ?EntityManagerInterface $em = null)
    {
        if ($id instanceof static) {
            return $id;
        }

        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return self::repo($em)->find((int) $id);
    }

    /**
     * Caution! If entityManager argument is omitted, knownEntityManager must be properly configured
     * @param EntityManagerInterface|null $em
     *
     * @return int|null
     * @throws \Doctrine\DBAL\DBALException
     *
     * todo: all models should have method _db_table_()
     */
    public static function randomId(?EntityManagerInterface $em = null): ?int
    {
        $table = static::_db_table_();
        /** @noinspection SqlResolve */
        //$stmt = AbstractDomain::fromContext()->getEntityManager()->getConnection()->query("SELECT id FROM $table ORDER BY RANDOM()");
        $stmt = self::knownEntityManager($em)->getConnection()->query('SELECT id FROM :tableName ORDER BY RANDOM() LIMIT 1');
        $stmt->execute([
            ':tableName' => $table,
        ]);
        $id = $stmt->fetchColumn();
        return (int) $id;
    }

    /**
     * Caution! If entityManager argument is omitted, knownEntityManager must be properly configured
     * @param EntityManagerInterface|null $em
     *
     * @return static|null
     * @throws \Doctrine\DBAL\DBALException
     */
    public static function randomEntity(?EntityManagerInterface $em = null): ?self
    {
        $id = static::randomId();
        return static::byId($id, $em);
    }

}
