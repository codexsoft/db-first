<?php

$autoloaderFound = false;
foreach ([
             __DIR__.'/../../..',
             __DIR__.'/../vendor',
         ] as $dir) {
    $autoloadFile = $dir.'/autoload.php';
    if (\file_exists($autoloadFile)) {
        require_once $autoloadFile;
        $autoloaderFound = true;
        break;
    }
}

if (!$autoloaderFound) {
    die("Autoload not found.\n");
}
