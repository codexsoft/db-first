<?php

use CodexSoft\DatabaseFirst\DatabaseFirst;
use Symfony\Component\Console\Input\ArgvInput;

require_once __DIR__.'/findautoloader.php';
//$input = new ArgvInput();
//$configFile = $input->getFirstArgument();
$configFile = (new ArgvInput())->getFirstArgument();
DatabaseFirst::createApplication($configFile, __FILE__)->run();
