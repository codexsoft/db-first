<?php

namespace CodexSoft\DatabaseFirst\Queries;

trait PaginatableQueryTrait
{
    private int $perPage = 10;
    private int $page = 1;

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
