<?php


namespace CodexSoft\DatabaseFirst\Console;

use CodexSoft\DatabaseFirst\Operation;
use CodexSoft\DatabaseFirst\DoctrineOrmSchema;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateModelsCommand extends Command
{
    protected static $defaultName = 'models';
    protected DoctrineOrmSchema $ormSchema;

    public function __construct(DoctrineOrmSchema $ormSchema, string $name = null)
    {
        parent::__construct($name);
        $this->ormSchema = $ormSchema;
        $this->setDescription('Generate models by database schema');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        (new Operation\GenerateEntitiesOperation())
            ->setDoctrineOrmSchema($this->ormSchema)
            ->execute();

        return 0;
    }
}
