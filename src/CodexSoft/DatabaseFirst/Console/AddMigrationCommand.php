<?php


namespace CodexSoft\DatabaseFirst\Console;

use CodexSoft\DatabaseFirst\DoctrineOrmSchema;
use CodexSoft\DatabaseFirst\Operation;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AddMigrationCommand extends Command
{
    protected static $defaultName = 'add-migration';
    protected DoctrineOrmSchema $ormSchema;

    public function __construct(DoctrineOrmSchema $ormSchema, string $name = null)
    {
        parent::__construct($name);
        $this->ormSchema = $ormSchema;
        $this->setDescription('Create new migration');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        (new Operation\GenerateMigrationOperation())
            ->setDoctrineOrmSchema($this->ormSchema)
            ->execute();

        return 0;
    }
}
