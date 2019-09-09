<?php

namespace CodexSoft\DatabaseFirst\Queries;

trait PaginatableQueryTrait
{

    /** @var int */
    private $perPage = 10;

    /** @var int */
    private $page = 1;

    /**
     * @param int $perPage
     *
     * @return static
     */
    public function setPerPage( int $perPage ): self
    {
        $this->perPage = $perPage;
        return $this;
    }

    /**
     * @param int $page
     *
     * @return static
     */
    public function setPage( int $page ): self
    {
        $this->page = $page;
        return $this;
    }

}