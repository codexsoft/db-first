<?php

use CodexSoft\Cli\Cli;
use CodexSoft\DatabaseFirst\DatabaseFirstConfig;
use Doctrine\ORM\Tools\Console\ConsoleRunner;

require_once __DIR__.'/findautoloader.php';
$dfConfigFile = Cli::getFirstArgumentOrDie();
$dfConfig = DatabaseFirstConfig::getFromConfigFile($dfConfigFile);

ConsoleRunner::createApplication(
    ConsoleRunner::createHelperSet($dfConfig->getEntityManager())
)->run();
