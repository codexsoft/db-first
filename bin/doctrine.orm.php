<?php

use CodexSoft\Code\Helpers\Cli;
use CodexSoft\DatabaseFirst\DoctrineOrmSchema;
use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Doctrine\ORM\Tools\Export\ClassMetadataExporter;
use CodexSoft\DatabaseFirst\Orm\DoctrineMappingExporter;

require_once __DIR__.'/findautoloader.php';
$ormConfigFile = Cli::getFirstArgumentOrDie();
$ormSchema = DoctrineOrmSchema::getFromConfigFile($ormConfigFile);

\CodexSoft\Code\Shortcuts::register();

// is there way to say exporter to sk
ClassMetadataExporter::registerExportDriver(DoctrineOrmSchema::CUSTOM_CODEXSOFT_BUILDER, DoctrineMappingExporter::class);

$helperSet = ConsoleRunner::createHelperSet($ormSchema->getEntityManager());
$cli = ConsoleRunner::createApplication($helperSet);
/** @noinspection PhpUnhandledExceptionInspection */
$cli->run();