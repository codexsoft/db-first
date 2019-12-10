<?php /** @noinspection PhpUnhandledExceptionInspection */

use CodexSoft\Cli\Cli;
use CodexSoft\DatabaseFirst\DoctrineOrmSchema;
use Doctrine\ORM\Tools\Console\ConsoleRunner;

require_once __DIR__.'/findautoloader.php';
$ormConfigFile = Cli::getFirstArgumentOrDie();
$ormSchema = DoctrineOrmSchema::getFromConfigFile($ormConfigFile);

// is there way to say exporter to sk
//ClassMetadataExporter::registerExportDriver(DoctrineOrmSchema::CUSTOM_CODEXSOFT_BUILDER, (new DoctrineMappingExporter)->setDbConfig($ormSchema));
//ClassMetadataExporter::registerExportDriver(DoctrineOrmSchema::CUSTOM_CODEXSOFT_BUILDER, DoctrineMappingExporter::class);

$helperSet = ConsoleRunner::createHelperSet($ormSchema->getEntityManager());
$cli = ConsoleRunner::createApplication($helperSet);
$cli->run();
