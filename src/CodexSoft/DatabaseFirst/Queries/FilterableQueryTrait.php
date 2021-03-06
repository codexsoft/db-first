<?php

namespace CodexSoft\DatabaseFirst\Queries;

use CodexSoft\DateAndTime\DateAndTime;

trait FilterableQueryTrait
{

    private array $filters = [];
    private string $filterDateTimeZone = 'UTC';

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

    /**
     * @param string $filterDateTimeZone
     *
     * @return static
     */
    public function setFilterDateTimeZone(string $filterDateTimeZone): self
    {
        $this->filterDateTimeZone = $filterDateTimeZone;
        return $this;
    }

    private function normalizeFilters(): void
    {
        $normalizedFilters = [];
        foreach ($this->filters as $filter => $value) {
            if ($value instanceof \DateTime) {
                $normalizedFilters[$filter] = DateAndTime::convertUtcDateTimeToLocalString($value, $this->filterDateTimeZone);
            } else {
                $normalizedFilters[$filter] = $value;
            }
        }
        $this->filters = $normalizedFilters;
    }

}
