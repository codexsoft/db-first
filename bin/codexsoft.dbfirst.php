<?php

use CodexSoft\Code\Helpers\Cli;
use CodexSoft\DatabaseFirst\ConsoleRunner;
use CodexSoft\DatabaseFirst\DoctrineOrmSchema;

require_once __DIR__.'/findautoloader.php';
$ormConfigFile = Cli::getFirstArgumentOrDie();
$ormSchema = DoctrineOrmSchema::getFromConfigFile($ormConfigFile);

$console = ConsoleRunner::createApplication($ormSchema, $ormConfigFile, __FILE__);
/** @noinspection PhpUnhandledExceptionInspection */
$console->run();
