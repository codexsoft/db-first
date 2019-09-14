<?php

namespace CodexSoft\DatabaseFirst;

use CodexSoft\Code\Traits\Loggable;
use CodexSoft\DatabaseFirst\Operation;
use CodexSoft\OperationsSystem\OperationsProcessor;
use CodexSoft\OperationsSystem\Traits\OperationsProcessorAwareTrait;
use Psr\Log\NullLogger;

class DatabaseFirstDomain
{

    use Operation\DoctrineOrmSchemaAwareTrait;
    use OperationsProcessorAwareTrait;
    use Loggable;

    public function __construct(DoctrineOrmSchema $doctrineOrmSchema)
    {
        $this->doctrineOrmSchema = $doctrineOrmSchema;
        $this->logger = new NullLogger;
        $this->operationsProcessor = new OperationsProcessor;
    }

    /**
     * @return Operation\GenerateEntitiesOperation
     */
    public function generateEntities(): Operation\GenerateEntitiesOperation
    {
        return (new Operation\GenerateEntitiesOperation)
            ->setLogger($this->logger)
            ->setOperationsProcessor($this->operationsProcessor)
            ->setDoctrineOrmSchema($this->doctrineOrmSchema);
    }

    /**
     * @return Operation\GenerateMigrationOperation
     */
    public function generateMigration(): Operation\GenerateMigrationOperation
    {
        return (new Operation\GenerateMigrationOperation)
            ->setLogger($this->logger)
            ->setOperationsProcessor($this->operationsProcessor)
            ->setDoctrineOrmSchema($this->doctrineOrmSchema);
    }

    /**
     * @return Operation\GenerateReposOperation
     */
    public function generateRepositories(): Operation\GenerateReposOperation
    {
        return (new Operation\GenerateReposOperation)
            ->setLogger($this->logger)
            ->setOperationsProcessor($this->operationsProcessor)
            ->setDoctrineOrmSchema($this->doctrineOrmSchema);
    }

    public function generateMapping(): Operation\GenerateMappingFromPostgresDbOperation
    {
        return (new Operation\GenerateMappingFromPostgresDbOperation)
            ->setLogger($this->logger)
            ->setOperationsProcessor($this->operationsProcessor)
            ->setDoctrineOrmSchema($this->doctrineOrmSchema);
    }

}