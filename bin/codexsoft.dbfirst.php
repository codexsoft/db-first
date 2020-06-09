<?php

use CodexSoft\Cli\Cli;
use CodexSoft\DatabaseFirst\DatabaseFirst;
use CodexSoft\DatabaseFirst\DoctrineOrmSchema;

require_once __DIR__.'/findautoloader.php';
$ormConfigFile = Cli::getFirstArgumentOrDie();
$ormSchema = DoctrineOrmSchema::getFromConfigFile($ormConfigFile);

/** @noinspection PhpUnhandledExceptionInspection */
DatabaseFirst::createApplication($ormSchema, $ormConfigFile, __FILE__)->run();
