<?php

namespace CodexSoft\DatabaseFirst\Queries;

use CodexSoft\Code\Helpers\DateAndTime;
use CodexSoft\Code\Constants;

trait FilterableQueryTrait
{

    /** @var array */
    private $filters = [];

    /**
     * @param array $filters
     *
     * @return static
     */
    public function setFilters( array $filters ): self
    {
        $this->filters = $filters;
        return $this;
    }

    private function normalizeFilters(): void
    {
        $normalizedFilters = [];
        foreach ($this->filters as $filter => $value) {
            if ($value instanceof \DateTime) {
                $normalizedFilters[$filter] = DateAndTime::convertUtcDateTimeToLocalString($value, Constants::TZ_UTC);
            } else {
                $normalizedFilters[$filter] = $value;
            }
        }
        $this->filters = $normalizedFilters;
    }

}