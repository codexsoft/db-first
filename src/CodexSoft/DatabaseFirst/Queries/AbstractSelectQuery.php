<?php

namespace CodexSoft\DatabaseFirst\Queries;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

abstract class AbstractSelectQuery
{

    protected $_sql = '';
    protected $_params = [];

    /** @var EntityManagerInterface */
    protected $entityManager;

    /**
     * @param EntityManagerInterface $entityManager
     *
     * @return AbstractSelectQuery
     */
    public function setEntityManager(EntityManagerInterface $entityManager): AbstractSelectQuery
    {
        $this->entityManager = $entityManager;
        return $this;
    }

    protected function generateWhere($conditions): string
    {
        if (!$conditions) {
            return '';
        }
        array_walk($conditions, function (&$condition) {
            $condition = '('.$condition.')';
        });
        return ' WHERE '.implode(' AND ',$conditions);
    }

    /**
     * $fetchMode === FetchMode::COLUMN —  Полезно чтобы получить массив ID из возвращенного набора
     * rows с единственной колонкой
     *
     * @param null $fetchMode
     *
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function executeSql($fetchMode = null): array
    {
        $connection = $this->entityManager->getConnection();
        $query = $connection->prepare($this->_sql);

        $query->execute($this->_params);
        return $query->fetchAll($fetchMode);
    }

    protected function expBuilder(): \Doctrine\ORM\Query\Expr
    {
        return $this->entityManager->getExpressionBuilder();
    }

    /**
     * @param $orderBy
     *
     * @throws InvalidArgumentException
     */
    protected function checkOrderBy( $orderBy): void
    {
        $acceptedColumnsToOrder = static::acceptedColumnsToOrder();

        if ($orderBy && !\in_array($orderBy,$acceptedColumnsToOrder,true)) {
            throw new InvalidArgumentException("provided ordering column is invalid: $orderBy provided, valid values: ".implode(', ',$acceptedColumnsToOrder));
        }
    }

    abstract public static function acceptedColumnsToOrder(): array;

    /**
     * Сгенерировать конвертер INT-кода в его текстовое представление
     * Выдаст нечто вроде
     *
     * CASE
     * WHEN (table.column = 1) THEN 'Борт'
     * WHEN (table.column = 2) THEN 'Рефрежиратор'
     * WHEN (table.column = 3) THEN 'Тент'
     * ELSE 'Неизвестный тип кузова'
     * END AS bodyTypeTitle;
     *
     * @param array $map
     * @param $column
     * @param null $defaultValue
     *
     * @return string
     */
    protected function generateCase(array $map, $column, $defaultValue = null): string
    {
        if (!$map) {
            return '';
        }
        $expression = 'CASE';
        foreach ($map as $originalValue => $convertedValue) {
            $expression .= ' WHEN ('.$column.' = '.$originalValue.') THEN \''.$convertedValue.'\'';
        }
        $expression .= ' ELSE \''.$defaultValue.'\' END';
        return $expression;
    }

    /**
     * Хэлпер для встраивания ограничений по конкретному полю в запрос
     * @param string $column
     * @param array $acceptableValues
     *
     * @return \Doctrine\ORM\Query\Expr\Comparison|\Doctrine\ORM\Query\Expr\Func|string
     */
    protected function getInOrEqualFilter(string $column, ?array $acceptableValues)
    {

        if ( $acceptableValues === null || \count($acceptableValues) === 0 ) {
            return 'TRUE';
        }

        $expBuilder = $this->expBuilder();

        if ( \count($acceptableValues) === 1 ) {
            $clientId = (int) (new ArrayCollection($acceptableValues))->first();
            return $expBuilder->eq($column,$clientId);
        }

        return $expBuilder->in($column,$acceptableValues);
    }

}