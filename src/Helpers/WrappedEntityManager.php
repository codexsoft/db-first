<?php /** @noinspection PhpUnused */

namespace CodexSoft\DatabaseFirst\Helpers;

use CodexSoft\DatabaseFirst\Orm\Postgres\AbstractPgSqlEntityManagerBuilder;
use Doctrine\DBAL\Driver\PDOConnection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;

class WrappedEntityManager
{
    protected EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Domain uses single EntityManager to tie Entites with database
     *
     * @return EntityManagerInterface|EntityManager
     */
    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * Just a shortcut to EntityManager's PDO instance
     *
     * @return PDOConnection|\Doctrine\DBAL\Driver\Connection
     */
    public function PDO()
    {
        return $this->getEntityManager()->getConnection()->getWrappedConnection();
    }

    /**
     * @param array|\Object $objects
     *
     * @throws \RuntimeException
     */
    public function persistAndFlushArray(array $objects = []): void
    {

        if (\is_object($objects)) {
            $this->persistAndFlush($objects);
            return;
        }

        if (!\is_array($objects)) {
            throw new \RuntimeException('Item for persisting must be object or array of objects');
        }

        $this->persistAndFlush(...array_values($objects));

    }

    /**
     * @noinspection PhpDocMissingThrowsInspection
     *
     * @param mixed ...$objects entities to persist and flush
     *
     * @return EntityManagerInterface
     */
    public function persistAndFlush(...$objects): EntityManagerInterface
    {
        $em = $this->getEntityManager();

        foreach ($objects as $object) {
            if (!\is_object($object)) {
                continue;
            }
            /** @noinspection PhpUnhandledExceptionInspection */
            $em->persist($object);
        }
        /** @noinspection PhpUnhandledExceptionInspection */
        $em->flush();
        return $em;

    }

    /**
     * Откатываем все "вложенные" транзакции
     *
     * @return static
     */
    public function rollbackNestedTransactions(): self
    {
        $em = $this->entityManager;
        if ($em->getConnection()->isTransactionActive()) {
            $nestedTransactionsLevel = $em->getConnection()->getTransactionNestingLevel();
            for ($i = 1; $i <= $nestedTransactionsLevel; $i++) {
                $em->rollback();
            }
        }

        return $this;
    }

    /**
     * Helper to re-open broken entityManger with same parameters (if broken)
     *
     * @param AbstractPgSqlEntityManagerBuilder $builder
     * @param bool $onlyIfClosed
     *
     * @return EntityManagerInterface
     */
    public function reopenEntityManager(AbstractPgSqlEntityManagerBuilder $builder, bool $onlyIfClosed = false): EntityManagerInterface
    {
        $reopenedEm = Doctrine::reopenEntityManager($this->entityManager, $builder, $onlyIfClosed);
        $this->entityManager = $reopenedEm;
        return $reopenedEm;
    }

}
