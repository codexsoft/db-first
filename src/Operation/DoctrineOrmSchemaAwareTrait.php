<?php

namespace CodexSoft\DatabaseFirst\Operation;

use CodexSoft\DatabaseFirst\DatabaseFirstConfig;

trait DoctrineOrmSchemaAwareTrait
{
    protected DatabaseFirstConfig $doctrineOrmSchema;

    /**
     * @param DatabaseFirstConfig $doctrineOrmSchema
     *
     * @return static
     */
    public function setDoctrineOrmSchema(DatabaseFirstConfig $doctrineOrmSchema): self
    {
        $this->doctrineOrmSchema = $doctrineOrmSchema;
        return $this;
    }

}
