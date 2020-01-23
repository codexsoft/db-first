<?php

//use Doctrine\DBAL\Migrations\Configuration\Configuration;
//use Doctrine\DBAL\Migrations\Tools\Console\ConsoleRunner;
//use Doctrine\DBAL\Migrations\Tools\Console\Helper\ConfigurationHelper;
use CodexSoft\Cli\Cli;
use CodexSoft\DatabaseFirst\DoctrineOrmSchema;
use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Tools\Console\ConsoleRunner;
//use Doctrine\DBAL\Tools\Console\ConsoleRunner;
use Doctrine\Migrations\Tools\Console\Helper\ConfigurationHelper;
use Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;

require_once __DIR__.'/findautoloader.php';
$ormConfigFile = Cli::getFirstArgumentOrDie();
$ormSchema = DoctrineOrmSchema::getFromConfigFile($ormConfigFile);

$connection = $ormSchema->getEntityManager()->getConnection();

$migrationsConfig = new Configuration($connection);
$migrationsConfig->setMigrationsNamespace($ormSchema->getNamespaceMigrations());
$migrationsConfig->setMigrationsDirectory($ormSchema->getPathToMigrations());

$helperSet = new HelperSet([
    'db' => new ConnectionHelper($connection),
    new QuestionHelper,
    new ConfigurationHelper($connection, $migrationsConfig),
]);

ConsoleRunner::createApplication($helperSet)->run();
