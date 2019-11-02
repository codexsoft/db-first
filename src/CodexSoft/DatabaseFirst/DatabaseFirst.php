<?php

namespace CodexSoft\DatabaseFirst;

use CodexSoft\Code\Traits\Loggable;
use CodexSoft\DatabaseFirst\Operation;
use Psr\Log\NullLogger;

class DatabaseFirst
{

    use Operation\DoctrineOrmSchemaAwareTrait;
    use Loggable;

    public function __construct(DoctrineOrmSchema $doctrineOrmSchema)
    {
        $this->doctrineOrmSchema = $doctrineOrmSchema;
        $this->logger = new NullLogger;
    }

    /**
     * @return Operation\GenerateEntitiesOperation
     */
    public function generateEntities(): Operation\GenerateEntitiesOperation
    {
        return (new Operation\GenerateEntitiesOperation)
            ->setLogger($this->logger)
            ->setDoctrineOrmSchema($this->doctrineOrmSchema);
    }

    /**
     * @return Operation\GenerateMigrationOperation
     */
    public function generateMigration(): Operation\GenerateMigrationOperation
    {
        return (new Operation\GenerateMigrationOperation)
            ->setLogger($this->logger)
            ->setDoctrineOrmSchema($this->doctrineOrmSchema);
    }

    /**
     * @return Operation\GenerateReposOperation
     */
    public function generateRepositories(): Operation\GenerateReposOperation
    {
        return (new Operation\GenerateReposOperation)
            ->setLogger($this->logger)
            ->setDoctrineOrmSchema($this->doctrineOrmSchema);
    }

    public function generateMapping(): Operation\GenerateMappingFromPostgresDbOperation
    {
        return (new Operation\GenerateMappingFromPostgresDbOperation)
            ->setLogger($this->logger)
            ->setDoctrineOrmSchema($this->doctrineOrmSchema);
    }

    public function removeEntitiesAndReposNotExistingInMapping(): Operation\RemoveNotExistingInMappingEntitiesOperation
    {
        return (new Operation\RemoveNotExistingInMappingEntitiesOperation)
            ->setLogger($this->logger)
            ->setDoctrineOrmSchema($this->doctrineOrmSchema);
    }

}
