<?php
/**
 * Created by PhpStorm.
 * User: dx
 * Date: 22.08.17
 * Time: 19:25
 */

namespace CodexSoft\DatabaseFirst\Orm;

use CodexSoft\DatabaseFirst\Exceptions\UnableToLockEntityException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * todo: previous implementation was expected single entity manager per domain,
 * so in repo(), byId() and other methods $em argument was absent, and it was handy.
 * should not we have 2 traits for both cases?
 */
trait LockableEntityTrait
{

    use KnownEntityManagerTrait;

    /**
     * @param EntityManagerInterface|null $em
     *
     * @return static
     * @throws UnableToLockEntityException
     */
    public function lockForUpdate(EntityManagerInterface $em = null)
    {
        try {
            //return $this->lockForUpdateViaLock();
            return $this->lockForUpdateViaFind($em);
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
    public function lockForUpdateViaLock(EntityManagerInterface $em = null)
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        static::knownEntityManager($em)->lock($this, LockMode::PESSIMISTIC_WRITE);
        return $this;
    }

    /**
     * @param EntityManagerInterface|\Doctrine\ORM\EntityManager $em
     *
     * @return static|object locked entity
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     *
     * todo: IS IT REALLY ALWAYS REFRESH ENTITY? It seems to be conditional: vendor/doctrine/orm/lib/Doctrine/ORM/EntityManager.php:424
     * todo: models should have method getId()
     */
    public function lockForUpdateViaFind(EntityManagerInterface $em = null)
    {
        $locked = static::knownEntityManager($em)
            ->find(static::class, $this->getId(), LockMode::PESSIMISTIC_WRITE);

        return $locked;
    }

}
