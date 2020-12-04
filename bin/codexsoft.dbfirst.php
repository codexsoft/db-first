<?php

use CodexSoft\Cli\Cli;
use CodexSoft\DatabaseFirst\DatabaseFirst;

require_once __DIR__.'/findautoloader.php';
$ormConfigFile = Cli::getFirstArgumentOrDie();
DatabaseFirst::createApplication($ormConfigFile, __FILE__)->run();
