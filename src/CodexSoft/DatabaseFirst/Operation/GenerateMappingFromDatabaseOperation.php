<?php
namespace CodexSoft\DatabaseFirst\Operation;

use CodexSoft\Code\Helpers\Classes;
use CodexSoft\DatabaseFirst\DoctrineOrmSchema;
use CodexSoft\OperationsSystem\Exception\OperationException;
use CodexSoft\OperationsSystem\Operation;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Mapping\Driver\DatabaseDriver;
use Doctrine\ORM\Tools\DisconnectedClassMetadataFactory;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class GenerateMappingOperation
 * todo: Write description — what this operation for
 * @method void execute() todo: change void to handle() method return type if other
 */
class GenerateMappingFromDatabaseOperation extends Operation
{
    use DoctrineOrmSchemaAwareTrait;

    public const ID = 'e163ac5f-ec34-470e-b8ee-450eb8886453';
    protected const ERROR_PREFIX = 'GenerateMappingFromDatabaseOperation cannot be completed: ';

    /** @var string[] */
    private $skipTables = [];

    /** @var bool */
    private $overwriteExisting = true;

    /**
     * @return void
     * @throws OperationException
     */
    protected function validateInputData(): void
    {
        $this->assert($this->doctrineOrmSchema instanceof DoctrineOrmSchema);
    }

    /**
     * @return void
     * @throws OperationException
     */
    protected function handle(): void
    {
        $em = $this->doctrineOrmSchema->getEntityManager();
        $databaseDriver = new DatabaseDriver($em->getConnection()->getSchemaManager());

        $em->getConfiguration()->setMetadataDriverImpl($databaseDriver);
        $databaseDriver->setNamespace($this->doctrineOrmSchema->getNamespaceModels().'\\');

        $cmf = new DisconnectedClassMetadataFactory();
        $cmf->setEntityManager($em);
        $metadata = $cmf->getAllMetadata();

        if (empty($metadata)) {
            throw $this->genericException('No Metadata Classes to process.');
        }

        $fs = new Filesystem;
        $fs->mkdir($this->doctrineOrmSchema->getPathToMapping());

        /** @var ClassMetadata $class */
        foreach ($metadata as $class) {
            $singularizedModelClass = $this->singularize($class->name);
            $tableName = $class->table['name'];
            echo sprintf("\n".'Processing table "%s"', $tableName);
            //echo sprintf("\n".'Processing entity "%s"', $class->name);
            echo sprintf("\n".'Processing entity "%s"', $singularizedModelClass);
            $file = $this->generateOutputFilePath($class);
            echo sprintf("\n".'File path "%s"', $file);

            $customRepoClass = $this->doctrineOrmSchema->getNamespaceRepositories().'\\'.Classes::short($singularizedModelClass).'Repository';

            $code = [
                '<?php',
                '',
                'use '.\Doctrine\DBAL\Types\Type::class.';',
                'use '.\Doctrine\ORM\Mapping\ClassMetadataInfo::class.';',
                'use '.\CodexSoft\DatabaseFirst\Orm\ModelMetadataBuilder::class.';',
                '',
                '/** @var $metadata '.\Doctrine\ORM\Mapping\ClassMetadataInfo::class.' */',
                '',
                '/** @noinspection PhpUnhandledExceptionInspection */',
                '$metadata->setInheritanceType(ClassMetadataInfo::INHERITANCE_TYPE_NONE);',
                '$metadata->setIdGeneratorType(ClassMetadataInfo::GENERATOR_TYPE_SEQUENCE);',
                '',
                '$mapper = new ModelMetadataBuilder($metadata);',
                '$mapper->setCustomRepositoryClass(\''.$customRepoClass.'\');',
                '$mapper->setTable(\''.$tableName.'\');',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                "if (file_exists(\$_extraMappingInfoFile = __DIR__.'/../Extra/'.basename(__FILE__))) {",
                '    /** @noinspection PhpIncludeInspection */ include $_extraMappingInfoFile;',
                '}',
                '',
                '',
                '',
                '',
                // $mapper->createField('version', Type::STRING) ...
            ];

            $fs->dumpFile($file, implode("\n", $code));
        }

        //if (($namespace = $input->getOption('namespace')) !== null) {
        //    $databaseDriver->setNamespace($namespace);
        //}
    }

    protected function generateOutputFilePath(ClassMetadataInfo $metadata)
    {
        return $this->doctrineOrmSchema->getPathToMapping().'/MAPPING.'.str_replace('\\', '.', $this->singularize($metadata->name)).'.php';
    }

    protected function singularize($plural) {
        return \Doctrine\Common\Inflector\Inflector::singularize($plural);
    }

    /**
     * @param bool $overwriteExisting
     *
     * @return GenerateMappingFromDatabaseOperation
     */
    public function setOverwriteExisting(bool $overwriteExisting): GenerateMappingFromDatabaseOperation
    {
        $this->overwriteExisting = $overwriteExisting;
        return $this;
    }

    /**
     * @param string[] $skipTables
     *
     * @return GenerateMappingFromDatabaseOperation
     */
    public function setSkipTables(array $skipTables): GenerateMappingFromDatabaseOperation
    {
        $this->skipTables = $skipTables;
        return $this;
    }

}