<?php

namespace CodexSoft\DatabaseFirst\Operation;

use CodexSoft\DatabaseFirst\DoctrineOrmSchema;

trait DoctrineOrmSchemaAwareTrait
{

    /** @var DoctrineOrmSchema */
    private $doctrineOrmSchema;

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