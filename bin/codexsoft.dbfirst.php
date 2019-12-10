<?php

use CodexSoft\Cli\Cli;
use CodexSoft\DatabaseFirst\ConsoleRunner;
use CodexSoft\DatabaseFirst\DoctrineOrmSchema;

require_once __DIR__.'/findautoloader.php';
$ormConfigFile = Cli::getFirstArgumentOrDie();
$ormSchema = DoctrineOrmSchema::getFromConfigFile($ormConfigFile);

//$console = ConsoleRunner::createApplication($ormSchema, $ormConfigFile, __FILE__, dirname(__DIR__).'/vendor/bin');
$console = ConsoleRunner::createApplication($ormSchema, $ormConfigFile, __FILE__);
/** @noinspection PhpUnhandledExceptionInspection */
$console->run();
