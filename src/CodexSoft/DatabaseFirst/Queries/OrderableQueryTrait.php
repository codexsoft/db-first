<?php

namespace CodexSoft\DatabaseFirst\Queries;

use Doctrine\Common\Collections\Criteria;
use InvalidArgumentException;

trait OrderableQueryTrait
{

    /** @var string|null */
    private $orderBy;

    /** @var string */
    private $orderDirection = Criteria::ASC;

    /**
     * @param null|string $orderBy
     *
     * @return static
     */
    public function setOrderBy( ?string $orderBy ): self
    {
        $this->orderBy = $orderBy;
        return $this;
    }

    /**
     * @param string $orderDirection
     *
     * @return static
     */
    public function setOrderDirection( string $orderDirection ): self
    {
        $this->orderDirection = $orderDirection;
        return $this;
    }

    /**
     * @param $orderDirection
     *
     * @return string
     * @throws InvalidArgumentException
     */
    protected function normalizeOrderDirection(string $orderDirection): string
    {
        $orderDirection = \mb_strtoupper($orderDirection);
        if (!\in_array($orderDirection,[Criteria::ASC,Criteria::DESC],true)) {
            throw new InvalidArgumentException("provided ordering direction is invalid: $orderDirection provided, valid values: ".Criteria::ASC.' '.Criteria::DESC);
        }
        return $orderDirection;
    }

}