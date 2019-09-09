<?php

namespace CodexSoft\DatabaseFirst\Migration;

use CodexSoft\Code\Helpers\Classes;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
abstract class BaseMigration extends AbstractMigration
{

    abstract public static function getContainingDirectory(): string;

    /**
     * @return string[]
     * @throws \RuntimeException
     */
    protected function getSqlStatements(): array
    {
        $simpleStatements = $this->getSqlStatementsFromFileWithSimpleSyntaxOrFail();
        $complexStatements = $this->getSqlStatementsFromFileWithComplexSyntax();
        return array_merge($simpleStatements,$complexStatements);
    }

    /**
     * @return string[]
     * @throws \RuntimeException
     */
    protected function getSqlStatementsFromFileWithSimpleSyntaxOrFail(): array
    {
        $migrationSqlFile = static::getContainingDirectory().'/'.Classes::short(static::class).'.sql';
        if ( !file_exists($migrationSqlFile) ) {
            throw new \RuntimeException( 'Migration file does not exists! '.$migrationSqlFile );
        }

        // to allow multiple statemets via PDO, we use parser
        return SqlParser::parseFile($migrationSqlFile);
    }

    /**
     * @return string[]
     * @throws \RuntimeException
     */
    protected function getSqlStatementsFromFileWithComplexSyntax(): array
    {
        // some complex statements, like routines (functions) should not be parsed, for that cases
        // we use R postfix in another sql file, statements separated by --@@@
        $migrationSqlFile = static::getContainingDirectory().'/'.Classes::short(static::class).'R.sql';
        if ( file_exists($migrationSqlFile) ) {
            $contents = file_get_contents($migrationSqlFile);
            return preg_split('/\r\n\-\-\@\@\@|\r\-\-\@\@\@|\n\-\-\@\@\@/', $contents);
            //return explode(PHP_EOL.'--@@@'.PHP_EOL,$contents);
        }
        return [];

    }

    /**
     * @param Schema $schema
     *
     * @throws \Exception
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $statemets = $this->getSqlStatements();

        $this->addSql('BEGIN');
        foreach( $statemets as $statement ) {
            $this->addSql( $statement );
        }
        $this->addSql('COMMIT');

        // of course, instead we can use Schema $schema.
    }

    /**
     * @param Schema $schema
     *
     * @throws \Doctrine\DBAL\Migrations\AbortMigrationException
     * @throws \Doctrine\DBAL\Migrations\IrreversibleMigrationException
     * @throws \Doctrine\DBAL\DBALException
     */
    public function down(Schema $schema)
    {

        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->throwIrreversibleMigrationException();

    }

}
