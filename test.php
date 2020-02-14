<?php

use CodexSoft\DatabaseFirst\DoctrineOrmSchema;

include __DIR__.'/vendor/autoload.php';

$ormSchema = DoctrineOrmSchema::getFromConfigFile(__DIR__.'/config/orm.php');
//$migrationsRepo = \TestDomain20190914\Model\DoctrineMigrationVersion::repo($ormSchema->getEntityManager());
$migrationsRepo = \TestDomain20190914\Model\DoctrineMigrationVersion::repo($ormSchema->getEntityManager());
//$migrations = $migrationsRepo->findAll();
$migrations = $migrationsRepo->getByVersion('asd');
$x=1;

// this is test release
