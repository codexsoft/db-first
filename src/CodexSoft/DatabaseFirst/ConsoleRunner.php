<?php

namespace CodexSoft\DatabaseFirst;

use CodexSoft\Cli\Command\ExecuteClosureCommand;
use CodexSoft\Cli\Command\ExecuteShellCommand;
use CodexSoft\DatabaseFirst\Helpers\Database;
use CodexSoft\OperationsSystem\Command\ExecuteOperationCommand;
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
class ConsoleRunner
{
    /**
     * @param DoctrineOrmSchema $ormSchema
     * @param string $ormConfigFile
     *
     * @param string $cliFile
     *
     * @param string $cliDir
     *
     * @return Application
     */
    public static function createApplication(DoctrineOrmSchema $ormSchema, string $ormConfigFile, string $cliFile, string $cliDir = null): Application
    {
        $dbFirst = new DatabaseFirst($ormSchema);

        $cliDir = $cliDir ?: dirname($cliFile);
        $console = new Application('CodexSoft Database-first CLI');
        $commandList = [

            'remove-not-mapped' => (new ExecuteOperationCommand($dbFirst->removeEntitiesAndReposNotExistingInMapping()))
                ->setDescription('Remove entity and repository for not existed in mapping') ,

            'repos' => (new ExecuteOperationCommand($dbFirst->generateRepositories()))
                ->setDescription('Generate repositories by database schema'),

            'models' => (new ExecuteOperationCommand($dbFirst->generateEntities()))
                ->setDescription('Generate models by database schema') ,

            'add-migration' => (new ExecuteOperationCommand($dbFirst->generateMigration()))
                ->setDescription('Create new migration'),

            'mapping' => (new ExecuteOperationCommand($dbFirst->generateMapping()))
                ->setDescription('Generate doctrine mapping'),

            // todo: how to pass rest of input to executing shell command?
            //'migrations' => (new ExecuteShellCommand([
            //    'php '.$cliDir.'/doctrine.migrate.php '.$ormConfigFile.' migrations:migrate',
            //]))->setDescription('doctrine migrations CLI'),

            'migrate' => (new ExecuteShellCommand([
                'php '.$cliDir.'/doctrine.migrate.php '.$ormConfigFile.' migrations:migrate',
            ]))->setDescription('apply migrations'),

            'check' => (new ExecuteShellCommand([
                'php '.$cliDir.'/doctrine.orm.php '.$ormConfigFile.' orm:validate-schema --skip-sync',
            ]))->setDescription('Validate doctrine schema'),

            'review' => (new ExecuteShellCommand([
                'php '.$cliFile.' '.$ormConfigFile.' mapping',
                'php '.$cliFile.' '.$ormConfigFile.' models',
                'php '.$cliFile.' '.$ormConfigFile.' repos',
            ]))->setDescription('Execute commands mapping, models, repos'),

            'db-remake' => (new ExecuteShellCommand([
                'php '.$cliFile.' '.$ormConfigFile.' db-clean',
                'php '.$cliDir.'/doctrine.migrate.php '.$ormConfigFile.' migrations:migrate --no-interaction',
            ]))->setDescription('Remove db and apply migrations(Execute commands db-clean + migrate --no-interaction)'),

            'regenerate' => (new ExecuteShellCommand([
                'php '.$cliFile.' '.$ormConfigFile.' db-remake',
                'php '.$cliFile.' '.$ormConfigFile.' review',
                'php '.$cliFile.' '.$ormConfigFile.' check',
            ]))->setDescription('Recreate db and mapping, entity,repos (Execute commands db-remake, review, check)'),

            'db-clean' => (new ExecuteClosureCommand(function(Command $cmd, InputInterface $input, OutputInterface $output) use ($ormSchema) {
                Database::deleteAllUserTables($ormSchema->getEntityManager()->getConnection());
            }))->setDescription('Delete all not system tables'),

            'db-truncate' => (new ExecuteClosureCommand(function(Command $cmd, InputInterface $input, OutputInterface $output) use ($ormSchema) {
                Database::truncateAllUserTables($ormSchema->getEntityManager()->getConnection());
            }))->setDescription('Truncate all not system tables'),
        ];

        foreach ($commandList as $command => $commandClass) {
            try {

                if ($commandClass instanceof Command) {
                    $commandInstance = $commandClass;
                } else {
                    $commandInstance = new $commandClass($command);
                }
                $console->add($commandInstance->setName($command));

            } catch ( \Throwable $e ) {
                echo "\nSomething went wrong: ".$e->getMessage();
            };

        }

        return $console;
    }

}
