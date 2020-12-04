<?php


namespace CodexSoft\DatabaseFirst\Console;

use CodexSoft\DatabaseFirst\DatabaseFirstConfig;
use CodexSoft\DatabaseFirst\Operation\RemoveNotExistingInMappingEntitiesOperation;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveNotMappedCommand extends Command
{
    protected static $defaultName = 'dbf:remove-not-mapped';
    protected DatabaseFirstConfig $databaseFirstConfig;

    public function __construct(DatabaseFirstConfig $databaseFirstConfig, string $name = null)
    {
        parent::__construct($name);
        $this->databaseFirstConfig = $databaseFirstConfig;
        $this->setDescription('Remove entity and repository for not existed in mapping');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        (new RemoveNotExistingInMappingEntitiesOperation(new ConsoleLogger($output)))
            ->setDatabaseFirstConfig($this->databaseFirstConfig)
            ->execute();

        return 0;
    }
}
