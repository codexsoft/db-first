<?php /** @noinspection SlowArrayOperationsInLoopInspection */

namespace CodexSoft\DatabaseFirst\Helpers;

use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\QueryBuilder;
use CodexSoft\Code\Helpers\Arrays;
use CodexSoft\Code\Helpers\Strings;
use CodexSoft\Code\Helpers\Traits;
use CodexSoft\Code\Constants;
use App\Domain\Model\Traits\HasIdTrait;
use CodexSoft\Domain\AbstractDomain;
use function App\logger;
use function CodexSoft\Code\str;

class Doctrine
{

    public const NULL = 'CASE WHEN 1=1 THEN :null ELSE :null END';

    /**
     * @param array|Collection $collectionA
     * @param array|Collection $collectionB
     *
     * @return bool
     */
    public static function areEntityCollectionsEquals($collectionA, $collectionB): bool
    {

        $arrayA = $collectionA;
        $arrayB = $collectionB;

        if ($arrayA instanceof Collection)
            $arrayA = $arrayA->getValues();

        if ($arrayB instanceof Collection)
            $arrayB = $arrayB->getValues();

        if (!( \count($arrayA) === \count($arrayB) ))
            return false;

        $diff = array_udiff($arrayA, $arrayB, function ($a, $b) {
            return $a === $b;
        });

        return empty($diff);

    }

    public static function prepareAttributes(EntityManagerInterface $em, $object, array $attributes)
    {
        foreach ($attributes as $fieldName => &$fieldValue) {
            if (!$em->getClassMetadata(get_class($object))->hasAssociation($fieldName)) {
                continue;
            }

            $association = $em->getClassMetadata(get_class($object))
                ->getAssociationMapping($fieldName);

            if (is_null($fieldValue)) {
                continue;
            }

            $fieldValue = $em->getReference($association['targetEntity'], $fieldValue);

            unset($fieldValue);
        }

        return $attributes;
    }

    public static function fillEntityFromArray(EntityManagerInterface $em, &$object, array $attributes)
    {
        $attributes = self::prepareAttributes($em, $object, $attributes);

        foreach ($attributes as $name => $value) {
            $methodName = 'set'.str($name)->upperCamelize();
            if (method_exists($object, $methodName)) {
                $object->{$methodName}($value);
            }
        }
    }

    public static function dqlExplain(QueryBuilder $qb): string
    {

        $query = $qb->getQuery();
        $dql = $query->getDQL();
        $sql = $query->getSQL();

        $sqlFormatted = class_exists('SqlFormatter') ? \SqlFormatter::format($sql, false) : self::simplyFormatSQL($sql);
        $dqlFormatted = class_exists('SqlFormatter') ? \SqlFormatter::format($dql, false) : self::simplyFormatSQL($dql);
        $dqlParams = Doctrine::showDQLParams($qb);

        return implode("\n", [
            '- DQL - - - - - - - - - - - - - - - - - - - - - - - - - - - ',
            '',
            $dqlFormatted,
            '',
            '- Using parameters  - - - - - - - - - - - - - - - - - - - - ',
            '',
            $dqlParams,
            '',
            '- SQL - - - - - - - - - - - - - - - - - - - - - - - - - - - ',
            '',
            $sqlFormatted,
        ]);

    }

    /**
     * for debug purposes, acts if DQL_DEBUG_MODE=true in .env
     *
     * @param QueryBuilder $qb
     */
    public static function dqlForXDebug(QueryBuilder $qb): void
    {

        if (false === (bool) \getenv('DQL_XDEBUG_MODE')) {
            return;
        }

        $query = $qb->getQuery();
        $dqlData = self::dqlExplain($qb);
        try {
            $result = $query->getResult();
        } catch (\Throwable $exception) {
            $result = $exception->getMessage();
            $trace = $exception->getTraceAsString();
            if ($prev = $exception->getPrevious()) {
                $trace .= $prev->getMessage()."\n".$prev->getTraceAsString();
            }
        }

    } // set debug stop point here

    private static function simplyFormatSQL($sql): string
    {
        return Strings::replacePlaceholders($sql, [
            'FROM'       => "\nFROM",
            'WHERE'      => "\nWHERE\n",
            'UNION'      => "\nUNION\n",
            'INNER JOIN' => "\nINNER JOIN",
            'LEFT JOIN'  => "\nLEFT JOIN",
            'RIGHT JOIN' => "\nRIGHT JOIN",
            'ORDER BY'   => "\nORDER BY",
            'GROUP BY'   => "\nGROUP BY",
            'AND'        => "AND\n",
            'OR'         => "OR\n",
        ]);
    }

    /**
     * for debug purposes, acts if DQL_DEBUG_MODE=true in .env
     *
     * @param QueryBuilder $qb
     */
    public static function dqlExplainAndDie(QueryBuilder $qb): void
    {
        if (false === (bool) \getenv('DQL_DEBUG_MODE')) {
            return;
        }
        die(self::dqlExplain($qb));
    }

    public static function showDQLParams(QueryBuilder $qb): string
    {

        /** @var \Doctrine\ORM\Query\Parameter[] $parameters */
        $parameters = $qb->getParameters();

        if (!\count($parameters)) {
            return '  ( no parameters used )';
        }

        //$string = $qb->getQuery()->getDQL()."\n- - - - - - - - - - - - ";
        $string = '';

        $i = 0;
        foreach ($parameters as $parameter) {
            $value = $parameter->getValue();

            if (\is_object($value)) {
                $stringifiedValue = \get_class($value);
                if (\method_exists($value, 'getId')) {
                    $stringifiedValue .= ' (id = '.\var_export($value->getId(), true).')';
                } elseif ($value instanceof \DateTime) {
                    $stringifiedValue .= ' ('.$value->format(Constants::FORMAT_YMD_HIS).' '.$value->getTimezone()->getName().')';
                }
            } elseif (\is_array($value)) {
                $stringifiedValue = \print_r($value, true);
            } else {
                $stringifiedValue = '('.\gettype($value).') '.\var_export($value, true);
            }

            $string .= "\n".++$i.'. '.$parameter->getName().' = '.$stringifiedValue;
        }
        //$string .= "\n";

        return $string;

    }

    public static function getIds(QueryBuilder $qb, string $alias): array
    {
        return \array_column($qb->select($alias.'.id')->getQuery()->getScalarResult(), 'id');
    }

    public static function ensureEntityHasId(...$objects): void
    {
        $domain = AbstractDomain::fromContext();

        $entitiesToPersist = [];

        foreach ($objects as $object) {
            if (!\is_object($object)) {
                continue;
            }

            /** @var HasIdTrait $object */
            if (Traits::isUsedBy($object, HasIdTrait::class) && !$object->getId()) {
                $entitiesToPersist[] = $object;
            }

        }

        if (!\count($entitiesToPersist)) {
            return;
        }

        $domain->persistAndFlushArray($entitiesToPersist);

    }

    /**
     * @param $array
     *
     * @return int[]
     */
    public static function getArrayOfIdsFromArrayOfEntities(array $array): array
    {
        $result = [];
        foreach ($array as $value) {
            if (!\is_object($value) || !\method_exists($value, 'getId')) {
                continue;
            }
            $result[] = (int) $value->getId();
        }
        return $result;
    }

    /**
     * Преобразует полученный из запроса массив объектов
     * в сгруппированный массив вида
     * [
     *  0 => [
     *      'alias1' => Entity object,
     *      'alias2' => Entity object,
     *      ...
     *      'aliasN' => Entity object,
     *  ],
     *  1 => [
     *      'alias1' => Entity object,
     *      'alias2' => Entity object,
     *      ...
     *      'aliasN' => Entity object,
     *  ],
     * ]
     * причем алиасы и их последовательность берется из переданного QueryBuilder-а
     *
     * $saveSelectOrder
     *
     * @param array $rows
     *
     * @param QueryBuilder $qb
     *
     * @param bool $forceSaveOriginalSelectOrder
     *
     * @return array
     */
    public static function parseRows(array $rows, QueryBuilder $qb, bool $forceSaveOriginalSelectOrder = false): array
    {

        $rowCount = \count($rows);

        $parts = $qb->getDQLParts();
        $resultMeta = [];

        $fromParts = $parts['from'];
        foreach ($fromParts as $fromPart) {
            /** @var \Doctrine\ORM\Query\Expr\From $fromPart */
            $resultMeta[] = [$fromPart->getFrom(), $fromPart->getAlias()];
        }

        $joinParts = $parts['join'];
        foreach ($joinParts as $joinRootEntity) {
            /** @var \Doctrine\ORM\Query\Expr\Join $join */
            foreach ($joinRootEntity as $join) {
                $resultMeta[] = [$join->getJoin(), $join->getAlias()];
            }
        }

        $desiredOrder = [];
        $selectParts = $parts['select'];
        foreach ($selectParts as $selectPart) {
            /** @var \Doctrine\ORM\Query\Expr\Select $selectPart */
            $desiredOrder = \array_merge($desiredOrder, $selectPart->getParts());
        }

        $result = [];

        $i = 0;
        while ($i < $rowCount) {
            $parsed = [];
            foreach ($resultMeta as $resultMetaItem) {
                if (!\in_array($resultMetaItem[1], $desiredOrder, true)) {
                    continue;
                }
                $parsed[$resultMetaItem[1]] = $rows[$i];
                $i++;
            }
            $result[] = $parsed;
        }

        if (false === $forceSaveOriginalSelectOrder) {
            return $result;
        }

        $sortedResult = [];
        foreach ($result as $resultItem) {
            $sortedRow = [];
            foreach ($desiredOrder as $desiredOrderItem) {
                $sortedRow[$desiredOrderItem] = $resultItem[$desiredOrderItem];
            }
            $sortedResult[] = $sortedRow;
        }

        return $sortedResult;

    }

    /**
     * This truncates all table in database using custom "clear_tables" routine.
     * ! Set up database schema via migrations before running database-based tests
     * ! Add clear_tables routine before running tests, using database
     *
     * @param \Doctrine\DBAL\Connection $connection
     */
    public static function truncateAllTables(\Doctrine\DBAL\Connection $connection): void
    {

        $userName = $connection->getUsername();

        $connection->getWrappedConnection()
            ->prepare('SELECT clear_tables(:userName);')
            ->execute(['userName' => $userName]);

    }

    /**
     * to improve performance, add optional attribute to specify tables to truncate
     *
     * @param \Doctrine\DBAL\Connection $connection
     * @param string|string[] $tables
     */
    public static function truncateSpecificTables(\Doctrine\DBAL\Connection $connection, $tables): void
    {

        if (!$tables) {
            return;
        }

        $tables = (array) $tables;
        $pdo = $connection->getWrappedConnection();

        foreach ($tables as $table) {

            $pdo->prepare('TRUNCATE TABLE '.$table.' CASCADE;')->execute();
            $pdo->prepare('ALTER SEQUENCE IF EXISTS '.$table.'_id_seq RESTART WITH 1;')->execute();

        }

    }

    /**
     * Deletes all tables in database using delete_tables routine
     *
     * @param \Doctrine\DBAL\Connection $connection
     * @param array $customDomainsList
     */
    public static function deleteAllTablesAndDomains(\Doctrine\DBAL\Connection $connection, array $customDomainsList = []): void
    {

        $userName = $connection->getUsername();

        $pdo = $connection->getWrappedConnection();
        $pdo->prepare("SELECT delete_tables('{$userName}');")->execute();

        if (!\count($customDomainsList)) {
            return;
        }

        foreach ($customDomainsList as $domainName) {
            $pdo->prepare("DROP DOMAIN IF EXISTS public.{$domainName};")->execute();
        }

    }

    /**
     * Проиндексировать массив сущностей по Id
     *
     * @param array $entities
     *
     * @return array
     */
    public static function indexByIdArrayOfEntities(array $entities)
    {
        return Arrays::indexByClosure($entities, function ($item) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $item->getId();
        });
    }

    /**
     * Вернет все комментарии всех полей БД
     * [
     *   users.id => Целочисленный идентификатор Пользователя
     * ]
     *
     * @param \Doctrine\DBAL\Connection $connection
     *
     * @return array
     */
    public static function getAllColumnsComments(\Doctrine\DBAL\Connection $connection): array
    {
        $dbName = $connection->getDatabase();
        $sql = "SELECT
  (cols.table_name || '.' || cols.column_name) as col_name,
  pg_catalog.col_description(c.oid, cols.ordinal_position::int) as col_desc
FROM pg_catalog.pg_class c, information_schema.columns cols
WHERE
    cols.table_catalog = '$dbName' AND
    cols.table_schema = 'public' AND
    cols.table_name = c.relname;";
        try {
            //return $connection->fetchAssoc($sql);
            $rows = $connection->fetchAll($sql);
            $result = [];
            foreach ($rows as $row) {
                $result[$row['col_name']] = $row['col_desc'];
            }
            return $result;
        } catch (\Throwable $e) {
            logger()->warning('Failed to get column comments from database: '.$e->getMessage());
            return [];
        }
    }

    /**
     * комментарий для скалярного поля берем из fieldMappings, тогда как для ассоциации немного
     * сложнее
     *
     * @param ClassMetadataInfo $metadata
     * @param string $fieldName
     *
     * @param array $columnComments
     *
     * @return string
     */
    public static function getCommentForField(ClassMetadataInfo $metadata, string $fieldName, array $columnComments): string
    {
        $fieldComment = '';
        if (\array_key_exists($fieldName, $metadata->fieldMappings)) {
            //echo "\n SEE: ".var_export($metadata->fieldMappings[$fieldName], true);
            $fieldComment = $metadata->fieldMappings[$fieldName]['options']['comment'] ?? '';
        } elseif (\array_key_exists($fieldName, $metadata->associationMappings)) {
            $associationMapping = $metadata->associationMappings[$fieldName];
            if ($associationMapping['type'] & ClassMetadataInfo::TO_ONE) {
                $sqlFieldName = $associationMapping['joinColumns'][0]['name'];
                $fieldComment = $columnComments[$metadata->table['name'].'.'.$sqlFieldName] ?? '';
            }
        }
        return $fieldComment;
    }

    public static function getCommentForTable(\Doctrine\DBAL\Connection $connection, string $tableName): string
    {
        try {
            /** @var array $pgTableDescriptionArray */
            $pgTableDescriptionArray = $connection->fetchAssoc("SELECT obj_description('".$tableName."'::regclass)");
            return $pgTableDescriptionArray['obj_description'] ?? '';
        } catch (DBALException $e) {
            return '';
        }
    }

    public static function reopenEntityManager(EntityManagerInterface $em, bool $onlyIfClosed = false): EntityManagerInterface
    {
        if ($em->isOpen()) {
            $em->clear();
            if ($onlyIfClosed) {
                return $em;
            }

            $em->close();
        }

        $entityManager = (new EntityManagerBuilder)
            ->setConnection($em->getConnection())
            ->setProxyDir($em->getConfiguration()->getProxyDir())
            ->build();
        return $entityManager;
    }

    public static function resultEntities(?array $result, $className)
    {
        if ($result === null) {
            return null;
        }

        $return = [];
        foreach ($result as $obj) {
            if ($obj instanceof $className) {
                $return[] = $obj;
            }
        }

        return $return;
    }

}
