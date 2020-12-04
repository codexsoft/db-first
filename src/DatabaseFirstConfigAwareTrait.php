<?php

namespace CodexSoft\DatabaseFirst;

trait DatabaseFirstConfigAwareTrait
{
    protected DatabaseFirstConfig $databaseFirstConfig;

    /**
     * @param DatabaseFirstConfig $databaseFirstConfig
     *
     * @return static
     */
    public function setDatabaseFirstConfig(DatabaseFirstConfig $databaseFirstConfig): self
    {
        $this->databaseFirstConfig = $databaseFirstConfig;
        return $this;
    }

}
