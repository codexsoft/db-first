<?php


namespace CodexSoft\DatabaseFirst\Console;

use CodexSoft\DatabaseFirst\DatabaseFirstConfig;
use CodexSoft\DatabaseFirst\Operation\GenerateEntitiesOperation;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateModelsCommand extends Command
{
    protected static $defaultName = 'dbf:models';
    protected DatabaseFirstConfig $databaseFirstConfig;

    public function __construct(DatabaseFirstConfig $databaseFirstConfig, string $name = null)
    {
        parent::__construct($name);
        $this->databaseFirstConfig = $databaseFirstConfig;
        $this->setDescription('Generate models by database schema');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        (new GenerateEntitiesOperation(new ConsoleLogger($output)))
            ->setDatabaseFirstConfig($this->databaseFirstConfig)
            ->execute();

        return 0;
    }
}
