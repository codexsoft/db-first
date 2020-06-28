<?php

use CodexSoft\Cli\Cli;
use CodexSoft\DatabaseFirst\DoctrineOrmSchema;
use Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;

require_once __DIR__.'/findautoloader.php';
$ormConfigFile = Cli::getFirstArgumentOrDie();
$ormSchema = DoctrineOrmSchema::getFromConfigFile($ormConfigFile);

$connection = $ormSchema->getEntityManager()->getConnection();

if (\class_exists('Doctrine\Migrations\Configuration\Configuration')) {
    $migrationsConfig = new \Doctrine\Migrations\Configuration\Configuration($connection);
} else {
    $migrationsConfig = new \Doctrine\DBAL\Migrations\Configuration\Configuration($connection); // doctrine migrations 1.x
}

$migrationsConfig->setMigrationsNamespace($ormSchema->getNamespaceMigrations());
$migrationsConfig->setMigrationsDirectory($ormSchema->getPathToMigrations());

if (\class_exists('Doctrine\Migrations\Tools\Console\ConsoleRunner')) {
    $configurationHelper = new \Doctrine\Migrations\Tools\Console\Helper\ConfigurationHelper($connection, $migrationsConfig);
} else {
    $configurationHelper = new \Doctrine\DBAL\Migrations\Tools\Console\Helper\ConfigurationHelper($connection, $migrationsConfig);
}

$helperSet = new HelperSet([
    'db' => new ConnectionHelper($connection),
    new QuestionHelper,
    $configurationHelper,
]);

if (\class_exists('Doctrine\Migrations\Tools\Console\ConsoleRunner')) {
    \Doctrine\Migrations\Tools\Console\ConsoleRunner::createApplication($helperSet)->run();
} else {
    \Doctrine\DBAL\Migrations\Tools\Console\ConsoleRunner::createApplication($helperSet)->run(); // doctrine migrations 1.x
}
