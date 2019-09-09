<?php

namespace CodexSoft\DatabaseFirst\Helpers;

use App\Domain\Config\EntityManagerBuilder;
use function App\app;

/**
 * Created by PhpStorm.
 * User: dx
 * Date: 25.12.17
 * Time: 17:57
 */

class Database
{

    /**
     * Возвращает очередное значение из заданной последовательности (SEQUENCE).
     *
     * @param string $sequenceName
     * @return int
     * @throws \Doctrine\DBAL\DBALException
     */
    public static function getSequenceNextVal(string $sequenceName): int
    {
        $entityManager = app()->getEntityManager();
        $dbConnection = $entityManager->getConnection();
        $nextvalQuery = $dbConnection->getDatabasePlatform()->getSequenceNextValSQL($sequenceName);

        return (int) $dbConnection->fetchColumn($nextvalQuery);
    }

    /**
     * @param string $sql
     * @param array $params
     *
     * @return \Doctrine\DBAL\Driver\Statement
     * @throws \Doctrine\DBAL\DBALException
     */
    public static function prepareSql(string $sql, array $params = []): \Doctrine\DBAL\Driver\Statement
    {
        $em = app()->getEntityManager();
        $connection = $em->getConnection();
        $query = $connection->prepare($sql);
        foreach ($params as $paramName => $paramValue) {
            $query->bindParam($paramName,$paramValue);
        }
        return $query;
    }

    /**
     * @param string $sql
     * @param array $params
     *
     * @return bool
     * @throws \Doctrine\DBAL\DBALException
     */
    public static function runSql(string $sql, array $params = []): bool
    {
        return self::prepareSql($sql, $params)->execute();
    }

    /**
     * This truncates all table in database using custom "clear_tables" routine.
     * ! Set up database schema via migrations before running database-based tests
     * ! Add clear_tables routine before running tests, using database
     */
    public static function truncateAllTables(): void
    {
        Doctrine::truncateAllTables(
            app()->getEntityManager()->getConnection()
        );
    }

    /**
     * to improve performance, add optional attribute to specify tables to truncate
     * @param string|string[] $tables
     */
    public static function truncateSpecificTables( $tables ): void
    {
        Doctrine::truncateSpecificTables(
            app()->getEntityManager()->getConnection(),
            $tables
        );
    }

    /**
     * Deletes all tables in database using delete_tables routine
     */
    public static function deleteAllTablesAndDomains(): void
    {
        Doctrine::deleteAllTablesAndDomains(
            app()->getEntityManager()->getConnection(),
            array_keys(EntityManagerBuilder::CUSTOM_DOMAINS)
        );
    }

    /**
     * Deletes all tables in database using delete_tables routine
     *
     * @param \Doctrine\DBAL\Connection $connection
     * @param array $customDomainsList
     */
    public static function deleteAllUserTables(\Doctrine\DBAL\Connection $connection): void
    {
        //$connection->getDatabasePlatform()->getListTablesSQL();
        $tablesList = $connection->getDriver()->getSchemaManager($connection)->listTableNames();
        foreach ($tablesList as $tableName) {
            $connection->getWrappedConnection()->prepare('DROP TABLE IF EXISTS "'.$tableName.'" CASCADE;')->execute();
        }
    }

    public static function truncateAllUserTables(\Doctrine\DBAL\Connection $connection): void
    {
        //$connection->getDatabasePlatform()->getListTablesSQL();
        $tablesList = $connection->getDriver()->getSchemaManager($connection)->listTableNames();
        foreach ($tablesList as $tableName) {
            $connection->getWrappedConnection()->prepare('TRUNCATE TABLE "'.$tableName.'" CASCADE;')->execute();
            $connection->getWrappedConnection()->prepare('ALTER SEQUENCE IF EXISTS "'.$tableName.'_id_seq" RESTART WITH 1;')->execute();
        }
    }

}
