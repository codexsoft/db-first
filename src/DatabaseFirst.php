<?php

namespace CodexSoft\DatabaseFirst;

use CodexSoft\Cli\Command\ExecuteClosureCommand;
use CodexSoft\Cli\Command\ExecuteShellCommand;
use CodexSoft\DatabaseFirst\Console\GenerateMappingCommand;
use CodexSoft\DatabaseFirst\Console\GenerateModelsCommand;
use CodexSoft\DatabaseFirst\Console\GenerateReposCommand;
use CodexSoft\DatabaseFirst\Console\RemoveNotMappedCommand;
use CodexSoft\DatabaseFirst\Helpers\Database;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * todo: should avoid executing shell, when cli applications doctrine orm&migrations can be executed
 * via constructing them in code?
 *
 * https://symfony.com/doc/current/console/command_in_controller.html
 */
class DatabaseFirst
{

    /**
     * @param string $dfConfigFile
     *
     * @param string $cliFile
     *
     * @param string|null $cliDir
     *
     * @return Application
     * @throws \Exception
     */
    public static function createApplication(
        string $dfConfigFile,
        string $cliFile,
        string $cliDir = null
    ): Application
    {
        $ormSchema = DatabaseFirstConfig::getFromConfigFile($dfConfigFile);

        $cliDir = $cliDir ?: dirname($cliFile);
        $console = new Application('CodexSoft Database-first CLI');

        $console->addCommands([
            new GenerateMappingCommand($ormSchema, 'mapping'),
            new GenerateModelsCommand($ormSchema, 'models'),
            new GenerateReposCommand($ormSchema, 'repos'),
            new RemoveNotMappedCommand($ormSchema, 'remove-not-mapped'),
        ]);
        //$console->add(new GenerateMappingCommand($ormSchema, 'mapping'));
        //$console->add(new GenerateModelsCommand($ormSchema, 'models'));
        //$console->add(new GenerateReposCommand($ormSchema, 'repos'));
        //$console->add(new RemoveNotMappedCommand($ormSchema, 'remove-not-mapped'));

        /**
         * todo: write concrete commands for this
         */

        //$commandList = [
        //    'check' => (new ExecuteShellCommand([
        //        'php '.$cliDir.'/doctrine.orm.php '.$dfConfigFile.' orm:validate-schema --skip-sync',
        //    ]))->setDescription('Validate doctrine schema'),
        //
        //    'review' => (new ExecuteShellCommand([
        //        'php '.$cliFile.' '.$dfConfigFile.' mapping',
        //        'php '.$cliFile.' '.$dfConfigFile.' models',
        //        'php '.$cliFile.' '.$dfConfigFile.' repos',
        //    ]))->setDescription('Execute commands mapping, models, repos'),
        //
        //    'db-clean' => (new ExecuteClosureCommand(function(Command $cmd, InputInterface $input, OutputInterface $output) use ($ormSchema) {
        //        Database::deleteAllUserTables($ormSchema->getEntityManager()->getConnection());
        //    }))->setDescription('Delete all not system tables'),
        //
        //    'db-truncate' => (new ExecuteClosureCommand(function(Command $cmd, InputInterface $input, OutputInterface $output) use ($ormSchema) {
        //        Database::truncateAllUserTables($ormSchema->getEntityManager()->getConnection());
        //    }))->setDescription('Truncate all not system tables'),
        //];
        //
        //foreach ($commandList as $command => $commandClass) {
        //    try {
        //
        //        if ($commandClass instanceof Command) {
        //            $commandInstance = $commandClass;
        //        } else {
        //            $commandInstance = new $commandClass($command);
        //        }
        //        $console->add($commandInstance->setName($command));
        //
        //    } catch ( \Throwable $e ) {
        //        echo "\nSomething went wrong: ".$e->getMessage();
        //    };
        //
        //}

        return $console;
    }

}
