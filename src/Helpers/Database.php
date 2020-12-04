<?php /** @noinspection PhpUnused */

namespace CodexSoft\DatabaseFirst\Helpers;

use Doctrine\DBAL\Connection;

use Doctrine\DBAL\Driver\Statement;
use Doctrine\ORM\EntityManagerInterface;

class Database
{

    /**
     * Возвращает очередное значение из заданной последовательности (SEQUENCE).
     *
     * @param EntityManagerInterface $entityManager
     * @param string $sequenceName
     *
     * @return int
     * @throws \Doctrine\DBAL\DBALException
     */
    public static function getSequenceNextVal(EntityManagerInterface $entityManager, string $sequenceName): int
    {
        $dbConnection = $entityManager->getConnection();
        $nextvalQuery = $dbConnection->getDatabasePlatform()->getSequenceNextValSQL($sequenceName);

        return (int) $dbConnection->fetchColumn($nextvalQuery);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param string $sql
     * @param array $params
     *
     * @return Statement
     * @throws \Doctrine\DBAL\DBALException
     */
    public static function prepareSql(EntityManagerInterface $entityManager, string $sql, array $params = []): Statement
    {
        $connection = $entityManager->getConnection();
        $query = $connection->prepare($sql);
        foreach ($params as $paramName => $paramValue) {
            $query->bindParam($paramName,$paramValue);
        }
        return $query;
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param string $sql
     * @param array $params
     *
     * @return bool
     * @throws \Doctrine\DBAL\DBALException
     */
    public static function runSql(EntityManagerInterface $entityManager, string $sql, array $params = []): bool
    {
        return self::prepareSql($entityManager, $sql, $params)->execute();
    }

    /**
     * to improve performance, add optional attribute to specify tables to truncate
     *
     * @param EntityManagerInterface $entityManager
     * @param string|string[] $tables
     */
    public static function truncateSpecificTables(EntityManagerInterface $entityManager, array $tables): void
    {
        Doctrine::truncateSpecificTables($entityManager->getConnection(), $tables);
    }

    /**
     * Deletes all tables in database using delete_tables routine
     *
     * @param Connection $connection
     */
    public static function deleteAllUserTables(Connection $connection): void
    {
        //$connection->getDatabasePlatform()->getListTablesSQL();
        $tablesList = $connection->getDriver()->getSchemaManager($connection)->listTableNames();
        foreach ($tablesList as $tableName) {
            $connection->getWrappedConnection()->prepare('DROP TABLE IF EXISTS "'.$tableName.'" CASCADE;')->execute();
        }
    }

    public static function truncateAllUserTables(Connection $connection): void
    {
        //$connection->getDatabasePlatform()->getListTablesSQL();
        $tablesList = $connection->getDriver()->getSchemaManager($connection)->listTableNames();
        foreach ($tablesList as $tableName) {
            $connection->getWrappedConnection()->prepare('TRUNCATE TABLE "'.$tableName.'" CASCADE;')->execute();
            $connection->getWrappedConnection()->prepare('ALTER SEQUENCE IF EXISTS "'.$tableName.'_id_seq" RESTART WITH 1;')->execute();
        }
    }

}
