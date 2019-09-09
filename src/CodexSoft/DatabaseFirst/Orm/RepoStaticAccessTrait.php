<?php
/**
 * Created by PhpStorm.
 * User: dx
 * Date: 22.08.17
 * Time: 19:25
 */

namespace CodexSoft\DatabaseFirst\Orm;

use App\Domain\Model\Traits\HasIdTrait;
use CodexSoft\DatabaseFirst\Exceptions\UnableToLockEntityException;
use CodexSoft\Code\Helpers\Classes;
use Doctrine\ORM\EntityManagerInterface;

/**
 * todo: previous implementation was expected single entity manager per domain,
 * so in repo(), byId() and other methods $em argument was absent, and it was handy.
 * should not we have 2 traits for both cases?
 */
trait RepoStaticAccessTrait
{

    /**
     * @param EntityManagerInterface $em
     *
     * @return \Doctrine\ORM\EntityRepository
     */
    public static function repo(EntityManagerInterface $em)
    {

        //if ($em === null) {
        //    $em = AbstractDomain::fromContext()->getEntityManager();
        //}

        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $em->getRepository(static::class);
    }

    /**
     * @param int|string|static $id
     *
     * @param EntityManagerInterface $em
     *
     * @return static
     */
    public static function byId($id, EntityManagerInterface $em)
    {

        if ($id instanceof static) {
            return $id;
        }

        $model = self::repo($em)->find((int) $id);
        if (!$model instanceof static) {
            throw new \RuntimeException(Classes::short(__CLASS__).' with ID='.$id.' not found!');
        }

        return $model;

    }

    /**
     * @param array $ids
     *
     * @param EntityManagerInterface $em
     *
     * @return static[]
     */
    public static function byIds(array $ids, EntityManagerInterface $em)
    {
        $repository = static::repo($em);
        if (method_exists($repository,'getByIdsIndexedById')) {
            return $repository->getByIdsIndexedById($ids);
        }
        //logger()->notice('called '.static::class.'::byIds and repo method getByIdsIndexedById is not defined');
        return $repository->findBy([static::_id() => $ids]);
    }

    /**
     * @param int|string|static $id
     *
     * @param EntityManagerInterface $em
     *
     * @return static|null
     */
    public static function byIdOrNull($id, EntityManagerInterface $em)
    {

        if ($id instanceof static) {
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
     * todo: проверить что число >0 ?
     *
     * @param int|string|static $idOrEntity
     *
     * @return int
     * @throws \RuntimeException
     */
    public static function extractId($idOrEntity): int
    {

        if (\is_string($idOrEntity) && \is_numeric($idOrEntity)) {
            return (int) $idOrEntity;
        }

        if (\is_int($idOrEntity)) {
            return $idOrEntity;
        }

        if ($idOrEntity instanceof static) {
            // todo: is entity always implements HasIdTrait? Maybe check that it has getter?
            /** @var HasIdTrait $idOrEntity */
            if ($idOrEntity->getId() === null) {
                throw new \RuntimeException('Entity of class '.static::class.' has not ID yet!');
            }
            return $idOrEntity->getId();
        }

        throw new \RuntimeException('ID is not entity nor numeric value');

    }

    /**
     * @param int|string|static $idOrEntity
     *
     * @return int|null
     */
    public static function extractIdOrNull($idOrEntity): ?int
    {
        try {
            $id = self::extractId($idOrEntity);
        } catch (\RuntimeException $e) {
            return null;
        }
        return $id;
    }


    /**
     * @return static
     * @throws UnableToLockEntityException
     */
    public function lockForUpdate()
    {
        try {
            //return $this->lockForUpdateViaLock();
            return $this->lockForUpdateViaFind();
        } catch (\Throwable $e) {
            throw new UnableToLockEntityException('Failed to lock entity for update',0,$e);
        }
    }

    /** @noinspection PhpDocMissingThrowsInspection */
    /**
     * @param EntityManagerInterface $em
     *
     * @return static
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\PessimisticLockException
     */
    public function lockForUpdateViaLock(EntityManagerInterface $em)
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $em->lock($this,\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
        return $this;
    }

    /**
     * @param EntityManagerInterface|\Doctrine\ORM\EntityManager $em
     *
     * @return static|object
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function lockForUpdateViaFind(EntityManagerInterface $em)
    {
        //$em = AbstractDomain::fromContext()->getEntityManager();
        // todo: IS IT REALLY ALWAYS REFRESH ENTITY? It seems to be conditional: vendor/doctrine/orm/lib/Doctrine/ORM/EntityManager.php:424
        // todo: models should have method getId()
        $locked = $em->find(static::class, $this->getId(), \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
        return $locked;
    }

    public static function randomId(EntityManagerInterface $em): ?int
    {
        // todo: models should have method _db_table_()
        $table = static::_db_table_();
        /** @noinspection SqlResolve */
        //$stmt = AbstractDomain::fromContext()->getEntityManager()->getConnection()->query("SELECT id FROM $table ORDER BY RANDOM()");
        $stmt = $em->getConnection()->query("SELECT id FROM $table ORDER BY RANDOM()");
        $stmt->execute();
        $id = $stmt->fetchColumn();
        return (int) $id;
    }

    /**
     * @return static|null
     */
    public static function randomEntity(EntityManagerInterface $em): ?self
    {
        $id = static::randomId();
        return static::byId($id, $em);
    }

}