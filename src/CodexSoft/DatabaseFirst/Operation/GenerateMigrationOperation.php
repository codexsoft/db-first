<?php

namespace CodexSoft\DatabaseFirst\Operation;

use CodexSoft\Code\Helpers\Classes;
use CodexSoft\OperationsSystem\Operation;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Generates new blank migration class
 * @method void execute()
 */
class GenerateMigrationOperation extends Operation
{

    use DoctrineOrmSchemaAwareTrait;

    protected const ID = 'd1c7d39c-fbcb-49c1-a198-2e39009e30b2';

    /**
     * @return void
     */
    protected function handle()
    {
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
                'class '.$baseMigrationClassShort.' extends \\'.\CodexSoft\DatabaseFirst\Migration\BaseMigration::class,
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
        $fs->dumpFile($fileName.'.sql', '-- write migration SQL code here');

        $this->getLogger()->info("Generated new migration class to $fileName");
    }

    private function generateVersionNumber(\DateTimeInterface $now = null): string
    {
        $now = $now ?: new \DateTime('now', new \DateTimeZone('UTC'));
        return $now->format('YmdHis');
    }
}
