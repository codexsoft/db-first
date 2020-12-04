<?php /** @noinspection SlowArrayOperationsInLoopInspection */

namespace CodexSoft\DatabaseFirst\Helpers;

use CodexSoft\DatabaseFirst\Orm\Postgres\AbstractPgSqlEntityManagerBuilder;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\QueryBuilder;
use CodexSoft\Code\Arrays\Arrays;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function Stringy\create as str;

class Doctrine
{

    private const FORMAT_YMD_HIS = 'Y-m-d H:i:s';

    public const SQL_FORMATTER_SIMPLE = 1;
    public const SQL_FORMATTER_COMPLEX = 2;
    public const SQL_FORMATTER_DEFAULT = self::SQL_FORMATTER_SIMPLE;

    private static ?LoggerInterface $logger = null;

    private static function getLogger(): LoggerInterface
    {
        if (!self::$logger instanceof LoggerInterface) {
            self::$logger = new NullLogger();
        }

        return self::$logger;
    }

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

    /**
     * @param EntityManagerInterface $em
     * @param $object
     * @param array $attributes
     *
     * @return array
     * @throws \Doctrine\ORM\Mapping\MappingException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function prepareAttributes(EntityManagerInterface $em, $object, array $attributes)
    {
        foreach ($attributes as $fieldName => &$fieldValue) {
            if (!$em->getClassMetadata(get_class($object))->hasAssociation($fieldName)) {
                continue;
            }

            $association = $em->getClassMetadata(get_class($object))
                ->getAssociationMapping($fieldName);

            if ($fieldValue === null) {
                continue;
            }

            $fieldValue = $em->getReference($association['targetEntity'], $fieldValue);

            unset($fieldValue);
        }

        return $attributes;
    }

    /**
     * @param EntityManagerInterface $em
     * @param $object
     * @param array $attributes
     *
     * @throws \Doctrine\ORM\Mapping\MappingException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function fillEntityFromArray(EntityManagerInterface $em, &$object, array $attributes)
    {
        $attributes = self::prepareAttributes($em, $object, $attributes);

        foreach ($attributes as $name => $value) {
            $methodName = 'set'.str($name)->upperCamelize();
            if (\method_exists($object, $methodName)) {
                $object->{$methodName}($value);
            }
        }
    }

    public static function dqlExplain(QueryBuilder $qb, int $formatter = self::SQL_FORMATTER_DEFAULT): string
    {

        $query = $qb->getQuery();
        $dql = $query->getDQL();
        $sql = $query->getSQL();

        switch ($formatter) {

            case self::SQL_FORMATTER_COMPLEX:
                $sqlFormatted = SqlFormatter::format($sql, false);
                $dqlFormatted = SqlFormatter::format($dql, false);
                break;

            case self::SQL_FORMATTER_SIMPLE:
            default:
                $sqlFormatted = self::simplyFormatSQL($sql);
                $dqlFormatted = self::simplyFormatSQL($dql);
                break;
        }

        $dqlParams = Doctrine::showDQLParams($qb);

        return \implode("\n", [
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
     * @param int $formatter
     *
     * @noinspection PhpUnusedLocalVariableInspection
     * @noinspection OnlyWritesOnParameterInspection
     */
    public static function dqlForXDebug(QueryBuilder $qb, int $formatter = self::SQL_FORMATTER_DEFAULT): void
    {

        if (false === (bool) \getenv('DQL_XDEBUG_MODE')) {
            return;
        }

        $query = $qb->getQuery();
        $dqlData = self::dqlExplain($qb, $formatter);
        try {
            $result = $query->getResult();
        } catch (\Throwable $exception) {
            $result = $exception->getMessage();
            $trace = $exception->getTraceAsString();
            if ($prev = $exception->getPrevious()) {
                $trace .= $prev->getMessage()."\n".$prev->getTraceAsString();
            }
        }

    } // tip: set debug stop point here

    private static function simplyFormatSQL($sql): string
    {
        return \strtr($sql, [
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
     * @param int $formatter
     */
    public static function dqlExplainAndDie(QueryBuilder $qb, int $formatter = self::SQL_FORMATTER_DEFAULT): void
    {
        if (false === (bool) \getenv('DQL_DEBUG_MODE')) {
            return;
        }

        die(self::dqlExplain($qb, $formatter));
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
                    $stringifiedValue .= ' ('.$value->format(self::FORMAT_YMD_HIS).' '.$value->getTimezone()->getName().')';
                }
            } elseif (\is_array($value)) {
                $stringifiedValue = \print_r($value, true);
            } else {
                $stringifiedValue = '('.\gettype($value).') '.\var_export($value, true);
            }

            $string .= "\n".++$i.'. '.$parameter->getName().' = '.$stringifiedValue;
        }

        return $string;

    }

    public static function getIds(QueryBuilder $qb, string $alias): array
    {
        return \array_column($qb->select($alias.'.id')->getQuery()->getScalarResult(), 'id');
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
     * to improve performance, add optional attribute to specify tables to truncate
     *
     * @param Connection $connection
     * @param string|string[] $tables
     */
    public static function truncateSpecificTables(Connection $connection, $tables): void
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
     * @param Connection $connection
     *
     * @return array
     */
    public static function getAllColumnsComments(Connection $connection): array
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
            self::getLogger()->warning('Failed to get column comments from database: '.$e->getMessage());
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

    public static function getCommentForTable(Connection $connection, string $tableName): string
    {
        try {
            /** @var array $pgTableDescriptionArray */
            $pgTableDescriptionArray = $connection->fetchAssociative("SELECT obj_description('".$tableName."'::regclass)");
            return $pgTableDescriptionArray['obj_description'] ?? '';
        } catch (\Doctrine\DBAL\Exception $e) {
            return '';
        }
    }

    public static function reopenEntityManager(EntityManagerInterface $em, AbstractPgSqlEntityManagerBuilder $builder, bool $onlyIfClosed = false): EntityManagerInterface
    {
        if ($em->isOpen()) {
            $em->clear();
            if ($onlyIfClosed) {
                return $em;
            }

            $em->close();
        }

        $entityManager = $builder
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
