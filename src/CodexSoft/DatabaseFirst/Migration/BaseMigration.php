<?php

namespace CodexSoft\DatabaseFirst\Migration;

use CodexSoft\Code\Arrays\Arrays;
use CodexSoft\Code\Classes\Classes;
use CodexSoft\DatabaseFirst\Helpers\SqlParser;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use function Stringy\create as str;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
abstract class BaseMigration extends AbstractMigration
{

    private const SQL_STATEMENTS_AUTODETECT = 1;
    private const SQL_STATEMENTS_MANUAL = 2;

    protected const SQL_MANUAL_STATEMENT_TOKEN_OPEN = '--@statement';
    protected const SQL_MANUAL_STATEMENT_TOKEN_CLOSE = '--@/statement';

    abstract public static function getContainingDirectory(): string;

    protected function getSqlStatements(string $migrationSqlFile): array
    {
        //$migrationSqlFile = static::getContainingDirectory().'/'.Classes::short(static::class).'.sql';
        if (!\file_exists($migrationSqlFile)) {
            throw new \RuntimeException('Migration file does not exists! '.$migrationSqlFile);
        }

        $auto_detect_line_endings_prev_value = \ini_get('auto_detect_line_endings');
        \ini_set('auto_detect_line_endings', true);

        $migrationSqlFileHandler = \fopen($migrationSqlFile, 'rb');
        if (!$migrationSqlFileHandler) {
            \ini_set('auto_detect_line_endings', $auto_detect_line_endings_prev_value);
            throw new \RuntimeException('Failed to open migration file '.$migrationSqlFile);
        }

        $buffer = '';
        $statements = [];
        $sqlBufferMode = self::SQL_STATEMENTS_AUTODETECT;

        while (($sqlLine = fgets($migrationSqlFileHandler, 4096)) !== false) {

            if ($sqlLine === '') {
                continue;
            }

            $trimmedSqlLine = str($sqlLine)->trimLeft();

            if ($trimmedSqlLine->startsWith(static::SQL_MANUAL_STATEMENT_TOKEN_OPEN)) {

                if ($buffer) {
                    if ($sqlBufferMode === self::SQL_STATEMENTS_AUTODETECT) {
                        Arrays::push($statements, ...SqlParser::parseString($buffer));
                    } else {
                        $statements[] = $buffer;
                    }
                    $buffer = '';
                }

                $sqlBufferMode = self::SQL_STATEMENTS_MANUAL;
                continue;
            }

            if ($trimmedSqlLine->startsWith(static::SQL_MANUAL_STATEMENT_TOKEN_CLOSE)) {
                if ($buffer && ($sqlBufferMode === self::SQL_STATEMENTS_MANUAL)) {
                    $statements[] = $buffer;
                    $buffer = '';
                }

                $sqlBufferMode = self::SQL_STATEMENTS_AUTODETECT;
                continue;
            }

            if ($trimmedSqlLine->startsWith('--')) {
                continue;
            }

            $buffer .= $sqlLine;
        }

        if ($buffer) {
            if ($sqlBufferMode === self::SQL_STATEMENTS_AUTODETECT) {
                Arrays::push($statements, ...SqlParser::parseString($buffer));
            } else {
                $statements[] = $buffer;
            }
        }

        if (!feof($migrationSqlFileHandler)) {
            throw new \RuntimeException('Migration file probably has problems with reading EOF! '.$migrationSqlFile);
        }

        \fclose($migrationSqlFileHandler);

        \ini_set('auto_detect_line_endings', $auto_detect_line_endings_prev_value);
        return $statements;
    }

    /**
     * Of course, instead we can override up() method and use Schema $schema.
     * @param Schema $schema
     *
     * @throws \Exception
     */
    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->abortIf(!$platform || ($platform->getName() !== 'postgresql'), 'Migration can only be executed safely on \'postgresql\'.');

        $migrationSqlFile = static::getContainingDirectory().'/'.Classes::short(static::class).'.sql';
        $statemets = $this->getSqlStatements($migrationSqlFile);

        $this->addSql('BEGIN');
        foreach($statemets as $statement) {
            $this->addSql($statement);
        }
        $this->addSql('COMMIT');
    }

    /**
     * Of course, instead we can override down() method and use Schema $schema.
     * @param Schema $schema
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->abortIf(!$platform || ($platform->getName() !== 'postgresql'), 'Migration can only be executed safely on \'postgresql\'.');

        $migrationSqlFile = static::getContainingDirectory().'/'.Classes::short(static::class).'down.sql';
        if (!\file_exists($migrationSqlFile)) {
            $this->throwIrreversibleMigrationException();
        }
        $statemets = $this->getSqlStatements($migrationSqlFile);

        $this->addSql('BEGIN');
        foreach($statemets as $statement) {
            $this->addSql($statement);
        }
        $this->addSql('COMMIT');
    }

}
