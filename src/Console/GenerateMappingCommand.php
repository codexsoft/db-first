<?php


namespace CodexSoft\DatabaseFirst\Console;

use CodexSoft\DatabaseFirst\Operation;
use CodexSoft\DatabaseFirst\DoctrineOrmSchema;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateMappingCommand extends Command
{
    protected static $defaultName = 'dbf:mapping';
    protected DoctrineOrmSchema $ormSchema;

    public function __construct(DoctrineOrmSchema $ormSchema, string $name = null)
    {
        parent::__construct($name);
        $this->ormSchema = $ormSchema;
        $this->setDescription('Generate doctrine mapping for postgres db');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        (new Operation\GenerateMappingFromPostgresDbOperation())
            ->setLogger(new ConsoleLogger($output))
            ->setDoctrineOrmSchema($this->ormSchema)
            ->execute();

        return 0;
    }
}
