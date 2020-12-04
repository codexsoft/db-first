<?php

namespace CodexSoft\DatabaseFirst\Operation;

use CodexSoft\Code\Classes\Classes;
use CodexSoft\DatabaseFirst\Migration\BaseMigration;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Generates new blank migration class
 */
class GenerateMigrationOperation extends AbstractBaseOperation
{
    public function execute(): void
    {
        if (!isset($this->doctrineOrmSchema)) {
            throw new \InvalidArgumentException('Required doctrineOrmSchema is not provided');
        }

        $dbConfig = $this->doctrineOrmSchema;
        $version = $this->generateVersionNumber();
        $fileName = $dbConfig->getPathToMigrations().'/Version'.$version;
        $fs = new Filesystem;

        $baseMigrationClass = $dbConfig->getMigrationBaseClass();
        $baseMigrationClassShort = Classes::short($baseMigrationClass);

        $baseMigrationFile = $dbConfig->getPathToMigrations().'/'.$baseMigrationClassShort.'.php';
        if (!file_exists($baseMigrationFile)) {
            $fs->dumpFile($baseMigrationFile, implode("\n", [
                '<?php',
                '',
                'namespace '.$dbConfig->getNamespaceMigrations().';',
                '',
                'class '.$baseMigrationClassShort.' extends \\'.BaseMigration::class,
                '{',
                '    public static function getContainingDirectory(): string',
                '    {',
                '        return __DIR__;',
                '    }',
                '}',
            ]));
        }

        $code = [
            '<?php',
            '',
            '/**',
            ' * Auto-generated Migration: Please modify to your needs!',
            ' */',
            'namespace '.$dbConfig->getNamespaceMigrations().';',
            '',
            'class Version'.$version.' extends \\'.$baseMigrationClass,
            '{',
            '}',
        ];

        $fs->dumpFile($fileName.'.php', implode("\n", $code));
        $fs->dumpFile($fileName.'.sql', '-- write UP migration SQL code here');
        $fs->dumpFile($fileName.'down.sql', '-- write DOWN migration SQL code here (it should completely revert UP migration)');

        $this->logger->info("Generated new migration class to $fileName");
    }

    private function generateVersionNumber(\DateTimeInterface $now = null): string
    {
        $now = $now ?: new \DateTime('now', new \DateTimeZone('UTC'));
        return $now->format('YmdHis');
    }
}
