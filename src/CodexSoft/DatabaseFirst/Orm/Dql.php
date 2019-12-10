<?php

namespace CodexSoft\DatabaseFirst\Orm;

use Carbon\Carbon;
use CodexSoft\DateAndTime\DateAndTime;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\Query\Expr\Func;
use Doctrine\ORM\Query\Expr\Literal;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr\Comparison;

abstract class Dql
{

    /** @var string DQL has not NULL, so this is hacky replacement */
    public const NULL = 'CASE WHEN 1=1 THEN :null ELSE :null END';

    private const FORMAT_YMD_HIS = DateAndTime::FORMAT_YMD_HIS;

    /** @var int */
    static private $counter = 0;

    /**
     * @param null $varName
     *
     * @return string
     */
    private static function generateParamName($varName = null): string
    {
        $slug = $varName ? '_'.preg_replace('/\W+/', '', str_replace('.','_',$varName)) : '';

        self::$counter++;
        return 'var'.$slug.'_'.uniqid('',false).self::$counter;
    }

    /**
     * todo: should be able to set parameters types
     * @param QueryBuilder $qb
     * @param string $expression
     * @param array $params
     *
     * @return string
     */
    public static function dql( QueryBuilder $qb, string $expression, array $params = []): string
    {
        //$qb->setParameters($params);
        foreach ( $params as $key => $val ) {
            $qb->setParameter($key,$val);
        }
        return $expression;
    }

    /**
     * @param $expressions
     * todo: import strings as DQL expressions?..
     * @return array
     */
    private static function normalizeExpressions($expressions): array
    {
        $normailzed = [];
        foreach ($expressions as $expression) {

            // importing expressions set
            if (\is_array($expression)) {

                foreach ($expression as $exp) {
                    if (\is_array($exp)) {
                        $normailzed[] = self::normalizeExpressions($exp);
                    } else {
                        (null !== $exp) && $normailzed[] = $exp;
                    }

                }

            } else {
                (null !== $expression) && $normailzed[] = $expression;
            }
        }
        return $normailzed;
    }

    /**
     * @param QueryBuilder $qb
     * @param array $conditions
     *
     * @return Andx
     */
    public static function andX( QueryBuilder $qb, array $conditions): Andx
    {
        $andX = $qb->expr()->andX();
        $conditions = self::normalizeExpressions($conditions);
        $conditions && $andX->addMultiple($conditions);
        return $andX;
    }

    /**
     * @param QueryBuilder $qb
     * @param array $conditions
     *
     * @return Orx
     */
    public static function orX( QueryBuilder $qb, array $conditions): Orx
    {
        $orX = $qb->expr()->orX();
        $conditions = self::normalizeExpressions($conditions);
        $conditions && $orX->addMultiple($conditions);
        return $orX;
    }

    /**
     * @param QueryBuilder $qb
     * @param array $conditions
     *
     * @return Andx
     */
    public static function requireAll( QueryBuilder $qb, array $conditions): Andx
    {
        $andX = Dql::andX($qb,$conditions);
        $andX->count() && $qb->where($andX);
        return $andX;
    }

    /**
     * @param QueryBuilder $qb
     * @param array $conditions
     *
     * @return Orx
     */
    public static function requireAny( QueryBuilder $qb, array $conditions): Orx
    {
        $orX = Dql::orX($qb,$conditions);
        $orX->count() && $qb->where($orX);
        return $orX;
    }

    /**
     * todo: equality for non-variable
     * @param QueryBuilder $qb
     * @param string $var
     * @param $value
     * @param string|null $type PDO::PARAM_* or \Doctrine\DBAL\Types\Type::* constant
     *
     * @return Comparison
     */
    public static function eq(QueryBuilder $qb, string $var, $value, $type = null): Comparison
    {
        $paramName = self::generateParamName($var);
        $qb->setParameter($paramName,$value,$type);
        return $qb->expr()->eq($var,':'.$paramName);
    }

    public static function set(QueryBuilder $qb, string $var, $value, $type = null): QueryBuilder
    {
        $paramName = self::generateParamName($var);
        $qb->setParameter($paramName,$value,$type);
        return $qb->set($var,':'.$paramName);
    }

    /**
     * todo: equality for non-variable
     * @param QueryBuilder $qb
     * @param string $var
     * @param $value
     * @param string|null $type PDO::PARAM_* or \Doctrine\DBAL\Types\Type::* constant
     *
     * @return Comparison
     */
    public static function neq(QueryBuilder $qb, string $var, $value, $type = null): Comparison
    {
        $paramName = self::generateParamName($var);
        $qb->setParameter($paramName,$value,$type);
        return $qb->expr()->neq($var,':'.$paramName);
    }

    /**
     * @param QueryBuilder $qb
     * @param string $var
     * @param $value
     *
     * @param null $type
     *
     * @return Comparison
     */
    public static function lt(QueryBuilder $qb, string $var, $value, $type = null): Comparison
    {
        $paramName = self::generateParamName($var);
        $qb->setParameter($paramName,$value,$type);
        return $qb->expr()->lt($var,':'.$paramName);
    }

    /**
     * @param QueryBuilder $qb
     * @param string $var
     * @param $value
     *
     * @param null $type
     *
     * @return Comparison
     */
    public static function lte(QueryBuilder $qb, string $var, $value, $type = null): Comparison
    {
        $paramName = self::generateParamName($var);
        $qb->setParameter($paramName,$value,$type);
        return $qb->expr()->lte($var,':'.$paramName);
    }

    /**
     * @param QueryBuilder $qb
     * @param string $var
     * @param $value
     *
     * @param null $type
     *
     * @return Comparison
     */
    public static function gt(QueryBuilder $qb, string $var, $value, $type = null): Comparison
    {
        $paramName = self::generateParamName($var);
        $qb->setParameter($paramName,$value,$type);
        return $qb->expr()->gt($var,':'.$paramName);
    }

    /**
     * @param QueryBuilder $qb
     * @param string $var
     * @param $value
     *
     * @param null $type
     *
     * @return Comparison
     */
    public static function gte(QueryBuilder $qb, string $var, $value, $type = null): Comparison
    {
        $paramName = self::generateParamName($var);
        $qb->setParameter($paramName,$value,$type);
        return $qb->expr()->gte($var,':'.$paramName);
    }

    /**
     * todo: allow non-variable params (literal?)
     *
     * @param QueryBuilder $qb
     * @param string $var
     * @param $min
     * @param $max
     *
     * @param null $type
     *
     * @return Func
     */
    public static function between(QueryBuilder $qb, string $var, $min, $max, $type = null): string
    {

        // should provide ability to set $var, $min, $max either in-sql value like a.state or fixed scalar/object like "desiredValue"
        // use special wrapper for it?
        $minVar = self::generateParamName('min');
        $maxVar = self::generateParamName('max');
        $qb->setParameter($minVar,$min,$type);
        $qb->setParameter($maxVar,$max,$type);

        return $qb->expr()->between($var,':'.$minVar,':'.$maxVar);
    }

    /**
     * @param QueryBuilder $qb
     * @param string $var
     * @param \mixed $value
     *
     * @return Func
     */
    public static function in(QueryBuilder $qb, string $var, $value): Func
    {
        $value = (array) $value;
        //$value = Doctrine::getArrayOfIdsFromArrayOfEntities($value);
        return $qb->expr()->in($var,$value);
    }

    /**
     * @param QueryBuilder $qb
     * @param string $var
     * @param $value
     *
     * @return Func
     */
    public static function notIn(QueryBuilder $qb, string $var, $value): Func
    {
        return $qb->expr()->notIn($var,$value);
    }

    /**
     * @param QueryBuilder $qb
     * @param string $var
     *
     * @return string
     */
    public static function isNull(QueryBuilder $qb, string $var): string
    {
        return $qb->expr()->isNull($var);
    }

    /**
     * @param QueryBuilder $qb
     * @param string $var
     *
     * @return string
     */
    public static function isNotNull(QueryBuilder $qb, string $var): string
    {
        return $qb->expr()->isNotNull($var);
    }

    /**
     * @param QueryBuilder $qb
     * @param null $limit
     * @param int $offset
     */
    public static function paginateResult( QueryBuilder $qb, $limit = null, $offset = 0): void
    {

        $limit = (int) $limit;
        $offset = (int) $offset;

        if ($limit > 0) {
            $qb->setFirstResult($offset);
            $qb->setMaxResults($limit);
        }

    }

    /**
     * @param QueryBuilder $qb
     * @param string $var
     * @param $value
     *
     * @param null $type
     *
     * @return Comparison
     */
    public static function like(QueryBuilder $qb, string $var, $value, $type = null): Comparison
    {
        $paramName = self::generateParamName($var);
        $qb->setParameter($paramName,$value,$type);
        return $qb->expr()->like($var,':'.$paramName);
    }

    /**
     * @param QueryBuilder $qb
     * @param string $var
     * @param $value
     *
     * @param null $type
     *
     * @return Comparison
     */
    public static function notLike(QueryBuilder $qb, string $var, $value, $type = null): Comparison
    {
        $paramName = self::generateParamName($var);
        $qb->setParameter($paramName,$value,$type);
        return $qb->expr()->notLike($var,':'.$paramName);
    }

    /**
     * @param QueryBuilder $qb
     * @param $subQuery
     *
     * @param bool $importParameters
     *
     * @return Func
     */
    public static function exists(QueryBuilder $qb, $subQuery, bool $importParameters = false): Func
    {
        $result = $qb->expr()->exists($subQuery);
        if ($importParameters && ($subQuery instanceof QueryBuilder)) {
            self::importParameters($qb,$subQuery);
        }
        return $result;
    }

    /**
     * @param QueryBuilder $qb
     * @param $expression
     *
     * @return Func
     */
    public static function not(QueryBuilder $qb, $expression): Func
    {
        return $qb->expr()->not($expression);
    }

    /**
     * @param QueryBuilder $qb
     * @param $expression
     *
     * @return Func
     */
    public static function abs(QueryBuilder $qb, $expression): Func
    {
        return $qb->expr()->abs($expression);
    }

    /**
     * @param QueryBuilder $qb
     * @param $subQuery
     *
     * @param bool $importParameters
     *
     * @return Func
     */
    public static function notExists(QueryBuilder $qb, $subQuery, bool $importParameters = false): Func
    {
        return self::not($qb,self::exists($qb,$subQuery,$importParameters));
    }

    /**
     * merge parameters from subquery
     *
     * @param QueryBuilder $targetQb
     * @param QueryBuilder[] $sourceQbs
     */
    public static function importParameters(QueryBuilder $targetQb, QueryBuilder ...$sourceQbs): void
    {
        foreach ( $sourceQbs as $sourceQb ) {
            /** @var \Doctrine\ORM\Query\Parameter $param */
            foreach ( $sourceQb->getParameters() as $param ) {
                $targetQb->setParameter($param->getName(),$param->getValue(),$param->getType());
            }
        }

    }

    /**
     * @param QueryBuilder $qb
     * @param string $var
     * @param $value
     *
     * @return Comparison
     */
    public static function isInstanceOf(QueryBuilder $qb, string $var, $value): Comparison
    {
        $paramName = self::generateParamName($var);
        $qb->setParameter($paramName,$value);
        return $qb->expr()->isInstanceOf($var,':'.$paramName);
    }

    /**
     * @param QueryBuilder $qb
     *
     * @return Expr
     */
    public static function expr( QueryBuilder $qb): Expr
    {
        return $qb->expr();
    }

    /**
     * @param QueryBuilder $qb
     * @param $value
     *
     * @return Func
     */
    public static function count( QueryBuilder $qb, $value): Func
    {
        return $qb->expr()->count($value);
    }

    /**
     * @param QueryBuilder $qb
     * @param $value
     *
     * @return string
     */
    public static function countDistinct( QueryBuilder $qb, $value): string
    {
        return $qb->expr()->countDistinct($value);
    }

    /**
     * @param QueryBuilder $qb
     * @param $value
     *
     * @return Func
     */
    public static function avg( QueryBuilder $qb, $value): Func
    {
        return $qb->expr()->avg($value);
    }

    /**
     * @param QueryBuilder $qb
     * @param $value
     *
     * @return Func
     */
    public static function max( QueryBuilder $qb, $value): Func
    {
        return $qb->expr()->max($value);
    }

    /**
     * @param QueryBuilder $qb
     * @param $value
     *
     * @return Func
     */
    public static function min( QueryBuilder $qb, $value): Func
    {
        return $qb->expr()->min($value);
    }

    /**
     * @param QueryBuilder $qb
     * @param $value
     *
     * @return Func
     */
    public static function lower( QueryBuilder $qb, $value): Func
    {
        return $qb->expr()->lower($value);
    }

    /**
     * @param QueryBuilder $qb
     * @param $value
     *
     * @return Func
     */
    public static function upper( QueryBuilder $qb, $value): Func
    {
        return $qb->expr()->upper($value);
    }

    /**
     * @param QueryBuilder $qb
     * @param $value
     *
     * @return Func
     */
    public static function trim( QueryBuilder $qb, $value): Func
    {
        return $qb->expr()->trim($value);
    }

    /**
     * @param QueryBuilder $qb
     * @param $value
     *
     * @return Func
     */
    public static function length( QueryBuilder $qb, $value): Func
    {
        return $qb->expr()->length($value);
    }

    /**
     * @param QueryBuilder $qb
     * @param $value
     *
     * @return Literal
     */
    public static function literal( QueryBuilder $qb, $value): Literal
    {
        return $qb->expr()->literal($value);
    }

    /**
     * Важно: тут НЕ сколько получится секунд если вычислить большее МИНУС меньшее
     * тут сколько секунд ОТ первого момента ДО второго
     *
     * @param QueryBuilder $qb
     * @param $value1
     * @param $value2
     *
     * @param string $part
     *
     * @return string
     */
    private static function timeStampDiff( QueryBuilder $qb, $value1, $value2, $part = 'SECOND'): string
    {
        if ($value1 instanceof \DateTime) {
            $value1 = "'".Carbon::instance($value1)->format(self::FORMAT_YMD_HIS)."'";
        }

        if ($value2 instanceof \DateTime) {
            $value2 = "'".Carbon::instance($value2)->format(self::FORMAT_YMD_HIS)."'";
        }

        return Dql::dql($qb,"TIMESTAMPDIFF($part, $value1, $value2)");
    }

    /**
     * сколько ОТ первого момента ДО второго
     *
     * @param QueryBuilder $qb
     * @param $a
     * @param $b
     *
     * @param string $part
     *
     * @return string
     */
    private static function timestampFromAToB( QueryBuilder $qb, $a, $b, $part = 'SECOND'): string
    {
        return self::timeStampDiff($qb,$a,$b,$part);
    }

    public static function secondsFromAToB( QueryBuilder $qb, $a, $b): string
    {
        return self::timestampFromAToB($qb,$a,$b,'SECOND');
    }

    public static function minutesFromAToB( QueryBuilder $qb, $a, $b): string
    {
        return self::timestampFromAToB($qb,$a,$b,'MINUTE');
    }

    public static function hoursFromAToB( QueryBuilder $qb, $a, $b): string
    {
        return self::timestampFromAToB($qb,$a,$b,'HOUR');
    }

    public static function daysFromAToB( QueryBuilder $qb, $a, $b): string
    {
        return self::timestampFromAToB($qb,$a,$b,'DAY');
    }

    public static function weeksFromAToB( QueryBuilder $qb, $a, $b): string
    {
        return self::timestampFromAToB($qb,$a,$b,'WEEK');
    }

    public static function monthsFromAToB( QueryBuilder $qb, $a, $b): string
    {
        return self::timestampFromAToB($qb,$a,$b,'MONTH');
    }

    public static function quartersFromAToB( QueryBuilder $qb, $a, $b): string
    {
        return self::timestampFromAToB($qb,$a,$b,'QUARTER');
    }

    public static function yearsFromAToB( QueryBuilder $qb, $a, $b): string
    {
        return self::timestampFromAToB($qb,$a,$b,'YEAR');
    }

    /**
     * первый момент МИНУС второй момент
     *
     * @param QueryBuilder $qb
     * @param $a
     * @param $b
     *
     * @param string $part
     *
     * @return string
     */
    private static function timestampAMinusB( QueryBuilder $qb, $a, $b, $part = 'SECOND'): string
    {
        return self::timeStampDiff($qb,$b,$a,$part);
    }

    public static function secondsAMinusB( QueryBuilder $qb, $a, $b): string
    {
        return self::timestampAMinusB($qb,$a,$b,'SECOND');
    }

    public static function minutesAMinusB( QueryBuilder $qb, $a, $b): string
    {
        return self::timestampAMinusB($qb,$a,$b,'MINUTE');
    }

    public static function hoursAMinusB( QueryBuilder $qb, $a, $b): string
    {
        return self::timestampAMinusB($qb,$a,$b,'HOUR');
    }

    public static function daysAMinusB( QueryBuilder $qb, $a, $b): string
    {
        return self::timestampAMinusB($qb,$a,$b,'DAY');
    }

    public static function weeksAMinusB( QueryBuilder $qb, $a, $b): string
    {
        return self::timestampAMinusB($qb,$a,$b,'WEEK');
    }

    public static function monthsAMinusB( QueryBuilder $qb, $a, $b): string
    {
        return self::timestampAMinusB($qb,$a,$b,'MONTH');
    }

    public static function quartersAMinusB( QueryBuilder $qb, $a, $b): string
    {
        return self::timestampAMinusB($qb,$a,$b,'QUARTER');
    }

    public static function yearsAMinusB( QueryBuilder $qb, $a, $b): string
    {
        return self::timestampAMinusB($qb,$a,$b,'YEAR');
    }

    /**
     * calculate distance in meters
     *
     * @param string|float $latA latitude of point A
     * @param string|float $lonA longitude of point A
     * @param string|float $latB latitude of point B
     * @param string|float $lonB longitude of point B
     *
     * Warning! This function does not set parameters to query, so it should be used to produce
     * expressions like this one (no numeric values, that should come through parameters):
     * EARTH_DISTANCE(LL_TO_EARTH(b.foo, b.baz), LL_TO_EARTH(a.foo, a.baz)
     *
     * @return string
     */
    public static function distance($latA, $lonA, $latB, $lonB): string
    {
        $latA = is_numeric($latA) ? self::formatCoord((float)$latA) : $latA;
        $lonA = is_numeric($lonA) ? self::formatCoord((float)$lonA) : $lonA;
        $latB = is_numeric($latB) ? self::formatCoord((float)$latB) : $latB;
        $lonB = is_numeric($lonB) ? self::formatCoord((float)$lonB) : $lonB;

        return 'EARTH_DISTANCE( LL_TO_EARTH(' . $latA . ', ' . $lonA . '), LL_TO_EARTH(' . $latB . ', ' . $lonB . ') )';
    }

    /**
     * @param float $coord
     * @return string
     */
    public static function formatCoord(float $coord) :string
    {
        return number_format($coord, 6, '.', '');
    }

    /**
     * @uses \MartinGeorgiev\Doctrine\ORM\Query\AST\Functions\IsContainedBy
     * @param QueryBuilder $qb
     * @param $left
     * @param $right
     *
     * @return string
     */
    public static function isContainedBy(QueryBuilder $qb, $left, $right): string
    {
        return Dql::dql($qb, 'IS_CONTAINED_BY('.$left.', '.$right.') = TRUE');
    }

    /**
     * @uses \MartinGeorgiev\Doctrine\ORM\Query\AST\Functions\IsContainedBy
     * @param QueryBuilder $qb
     * @param $left
     * @param $right
     *
     * @return string
     */
    public static function isNotContainedBy(QueryBuilder $qb, $left, $right): string
    {
        return Dql::dql($qb, 'IS_CONTAINED_BY('.$left.', '.$right.') = FALSE');
    }

    /**
     * @uses \MartinGeorgiev\Doctrine\ORM\Query\AST\Functions\Contains
     * @param QueryBuilder $qb
     * @param $left
     * @param $right
     *
     * @return string
     */
    public static function contains(QueryBuilder $qb, $left, $right): string
    {
        return Dql::dql($qb, 'CONTAINS('.$left.', '.$right.') = TRUE');
    }

    /**
     * @uses \MartinGeorgiev\Doctrine\ORM\Query\AST\Functions\Contains
     * @param QueryBuilder $qb
     * @param $left
     * @param $right
     *
     * @return string
     */
    public static function notContains(QueryBuilder $qb, $left, $right): string
    {
        return Dql::dql($qb, 'CONTAINS('.$left.', '.$right.') = FALSE');
    }

}
