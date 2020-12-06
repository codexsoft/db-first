<?php

use CodexSoft\DatabaseFirst\DatabaseFirstConfig;
use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Symfony\Component\Console\Input\ArgvInput;

require_once __DIR__.'/findautoloader.php';

//$input = new ArgvInput();
//$configFile = $input->getFirstArgument();
//$configFile = (new ArgvInput())->getFirstArgument();
//$config = DatabaseFirstConfig::getFromConfigFile($configFile);
$config = DatabaseFirstConfig::getFromConfigFile((new ArgvInput())->getFirstArgument());

ConsoleRunner::createApplication(
    ConsoleRunner::createHelperSet($config->getEntityManager())
)->run();
