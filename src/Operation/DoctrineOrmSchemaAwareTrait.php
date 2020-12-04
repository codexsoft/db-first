<?php

namespace CodexSoft\DatabaseFirst\Operation;

use CodexSoft\DatabaseFirst\DoctrineOrmSchema;

trait DoctrineOrmSchemaAwareTrait
{
    protected DoctrineOrmSchema $doctrineOrmSchema;

    /**
     * @param DoctrineOrmSchema $doctrineOrmSchema
     *
     * @return static
     */
    public function setDoctrineOrmSchema(DoctrineOrmSchema $doctrineOrmSchema): self
    {
        $this->doctrineOrmSchema = $doctrineOrmSchema;
        return $this;
    }

}
