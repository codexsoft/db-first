<?php

namespace CodexSoft\DatabaseFirst;

use CodexSoft\Code\Command\ExecuteClosureCommand;
use CodexSoft\Code\Command\ExecuteShellCommand;
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

            'repos' => new ExecuteOperationCommand($dbFirst->generateRepositories()),
            'models' => new ExecuteOperationCommand($dbFirst->generateEntities()),
            'add-migration' => new ExecuteOperationCommand($dbFirst->generateMigration()),
            'mapping' => new ExecuteOperationCommand($dbFirst->generateMapping()),

            //'mapping-old' => new ExecuteShellCommand([
            //    'php '.$cliDir.'/doctrine.orm.php '.$ormConfigFile.' orm:convert-mapping '
            //    .DoctrineOrmSchema::CUSTOM_CODEXSOFT_BUILDER.' '
            //    .$ormSchema->getPathToMapping().' '
            //    .'--force --from-database --namespace='.$ormSchema->getNamespaceModels().'\\'
            //]),

            'migrate' => new ExecuteShellCommand([
                'php '.$cliDir.'/doctrine.migrate.php '.$ormConfigFile.' migrations:migrate',
            ]),

            'check' => new ExecuteShellCommand([
                'php '.$cliDir.'/doctrine.orm.php '.$ormConfigFile.' orm:validate-schema --skip-sync',
            ]),

            'review' => new ExecuteShellCommand([
                'php '.$cliFile.' '.$ormConfigFile.' mapping',
                'php '.$cliFile.' '.$ormConfigFile.' models',
                'php '.$cliFile.' '.$ormConfigFile.' repos',
            ]),

            'regenerate' => new ExecuteShellCommand([
                'php '.$cliFile.' '.$ormConfigFile.' db-clean',
                'php '.$cliDir.'/doctrine.migrate.php '.$ormConfigFile.' migrations:migrate --no-interaction',
                'php '.$cliFile.' '.$ormConfigFile.' review',
                'php '.$cliFile.' '.$ormConfigFile.' check',
            ]),

            'db-clean' => new ExecuteClosureCommand(function(Command $cmd, InputInterface $input, OutputInterface $output) use ($ormSchema) {
                Database::deleteAllUserTables($ormSchema->getEntityManager()->getConnection());
            }),

            'db-truncate' => new ExecuteClosureCommand(function(Command $cmd, InputInterface $input, OutputInterface $output) use ($ormSchema) {
                Database::truncateAllUserTables($ormSchema->getEntityManager()->getConnection());
            }),
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
