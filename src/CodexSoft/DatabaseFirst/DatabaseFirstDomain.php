<?php

namespace CodexSoft\DatabaseFirst;

use CodexSoft\DatabaseFirst\Operation;
use CodexSoft\Domain\AbstractDomainApp;

class DatabaseFirstDomain extends AbstractDomainApp
{

    use Operation\DoctrineOrmSchemaAwareTrait;

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
    public function generateMigrations(): Operation\GenerateMigrationOperation
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

}