<?php

use CodexSoft\DatabaseFirst\DatabaseFirst;
use Symfony\Component\Console\Input\ArgvInput;

require_once __DIR__.'/findautoloader.php';
//$input = new ArgvInput();
//$configFile = $input->getFirstArgument();
$configFile = (new ArgvInput())->getFirstArgument();
$cli = DatabaseFirst::createApplication($configFile, __FILE__);

$args = $_SERVER['argv'];
\array_shift($args);

$cli->run(new ArgvInput($args));
