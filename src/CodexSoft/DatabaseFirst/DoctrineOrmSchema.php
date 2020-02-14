<?php

namespace CodexSoft\DatabaseFirst;

use CodexSoft\Code\Strings\Strings;
use CodexSoft\DatabaseFirst\Orm\Dql;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\Builder\ClassMetadataBuilder;

class DoctrineOrmSchema
{
    private $namespaceRepositories;
    private $namespaceRepositoriesTraits;
    private $namespaceModels;
    private $namespaceModelsTraits;
    private $namespaceModelsBuilders;
    private $namespaceMigrations;
    private $namespaceMapping;
    private $namespaceMappingGenerated;
    private $namespaceMappingExtra;
    private $namespaceModelsAwareTraits;

    /** @var string|null */
    private $migrationBaseClass;

    /** @var string */
    private $pathToMigrations;

    /** @var string */
    private $pathToModels;

    /** @var string */
    private $pathToRepositories;

    /** @var string */
    private $pathToModelsTraits;

    /** @var string */
    private $pathToMapping;

    /** @var EntityManager */
    private $entityManager;

    /** @var string */
    private $pathToModelAwareTraits;

    /** @var string[]  */
    public $dbToPhpType = [
        'json[]'                    => 'array',
        'jsonb'                     => 'array',
        'jsonb[]'                   => 'array',
        'text[]'                    => 'string[]',
        'smallint[]'                => 'integer[]',
        'integer[]'                 => 'integer[]',
        'bigint[]'                  => 'integer[]',
        'varchar[]'                 => 'string[]',
        Types::DATETIMETZ_MUTABLE   => '\DateTime',
        Types::DATETIMETZ_IMMUTABLE => '\DateTime',
        Types::DATETIME_IMMUTABLE   => '\DateTime',
        Types::DATETIME_MUTABLE     => '\DateTime',
        Types::DATE_IMMUTABLE       => '\DateTime',
        Types::DATE_MUTABLE         => '\DateTime',
        Types::TIME_IMMUTABLE       => '\DateTime',
        Types::TIME_MUTABLE         => '\DateTime',
        Types::OBJECT               => '\stdClass',
        Types::BIGINT               => 'integer',
        Types::SMALLINT             => 'integer',
        Types::TEXT                 => 'string',
        Types::BLOB                 => 'string',
        Types::DECIMAL              => 'string',
        Types::JSON                 => 'array',
        Types::JSON_ARRAY           => 'array',
        Types::SIMPLE_ARRAY         => 'array',
        Types::GUID                 => 'string',
        'ltree'                     => 'array',
    ];

    /**
     * @var string[] while generating entities, entities for these tables should be skipped
     * supports wildcard ending like doctrine_*
     */
    public $skipTables = [
        'doctrine_migration_versions'
    ];

    /**
     * While generating entities, these columns will not be mapped.
     * List of strings in format <table_name>.<column_name>
     * @example [
     *   'registered_operations.type',
     *   'transport_orders.order_id',
     * ]
     * @var string[]
     */
    public $skipColumns = [];

    /** @var bool  */
    public $cascadePersistAllRelationships = true;

    /** @var bool  */
    public $cascadeRefreshAllRelationships = true;

    /** @var bool */
    public $generateModelAwareTraits = true;

    /** @var bool */
    public $overwriteModelAwareTraits = false;

    /** @var bool */
    public $generateSetMethodForModelAwareTraits = false;

    /** @var bool */
    public $generateSetOrIdMethodForModelAwareTraits = true;

    /** @var bool */
    public $overwriteModelClasses = false;

    /**
     * Visibility of the field in generated model traits
     *
     * @var string
     */
    public $modelTraitFieldVisibility = 'private';

    /**
     * @var bool
     */
    public $generateModelWithRepoAccess = true;

    /**
     * @var bool
     */
    public $generateModelWithLockHelpers = false;

    /** @var string A string pattern used to match entities that should be processed. */
    public $metadataFilter;

    /**
     * @var string
     * OR \CodexSoft\DatabaseFirst\Orm\ModelMetadataInheritanceBuilder::class
     */
    public $metadataBuilderClass = ClassMetadataBuilder::class;

    /** @var string  */
    public $metaVar = '$metadata';

    /** @var string  */
    public $builderVar = '$mapper';

    public $doctrineTypesMap = [
        'array' => 'TARRAY',
        'simple_array' => 'SIMPLE_ARRAY',
        'json_array' => 'JSON_ARRAY',
        'json' => 'JSON',
        'bigint' => 'BIGINT',
        'boolean' => 'BOOLEAN',
        'datetime' => 'DATETIME',
        'datetime_immutable' => 'DATETIME_IMMUTABLE',
        'datetimetz' => 'DATETIMETZ',
        'datetimetz_immutable' => 'DATETIMETZ_IMMUTABLE',
        'date' => 'DATE',
        'date_immutable' => 'DATE_IMMUTABLE',
        'time' => 'TIME',
        'time_immutable' => 'TIME_IMMUTABLE',
        'decimal' => 'DECIMAL',
        'integer' => 'INTEGER',
        'object' => 'OBJECT',
        'smallint' => 'SMALLINT',
        'string' => 'STRING',
        'text' => 'TEXT',
        'binary' => 'BINARY',
        'blob' => 'BLOB',
        'float' => 'FLOAT',
        'guid' => 'GUID',
        'dateinterval' => 'DATEINTERVAL',
    ];

    /** @var string A parent class for repository. */
    public $parentRepositoryClass = EntityRepository::class;

    /** @var bool  */
    public $generateRepoTraits = true;

    /** @var bool  */
    public $overwriteRepoClasses = false;

    /** @var string */
    public $knownEntityManagerContainerClass;

    /** @var string */
    public $knownEntityManagerRouterClass;

    /** @var string */
    public $dqlHelperClass = Dql::class;

    protected $namespaceBase = 'App\\Domain';

    /** @var string */
    protected $pathToPsrRoot = '/src';

    /** @var array */
    public $singleTableInheritance = [];

    /**
     * @param string $domainConfigFile
     *
     * @return static
     * @throws \Exception
     */
    public static function getFromConfigFile(string $domainConfigFile): self
    {
        ob_start();
        $domainSchema = include $domainConfigFile;
        ob_end_clean();

        if (!$domainSchema instanceof static) {
            throw new \Exception("File $domainConfigFile does not return valid ".static::class."!\n");
        }

        return $domainSchema;
    }

    /**
     * @return string
     */
    public function getPathToPsrRoot(): string
    {
        return $this->pathToPsrRoot;
    }

    /**
     * @param string $pathToPsrRoot
     *
     * @return static
     */
    public function setPathToPsrRoot(string $pathToPsrRoot): self
    {
        $this->pathToPsrRoot = $pathToPsrRoot;
        return $this;
    }

    /**
     * @return string
     */
    public function getNamespaceBase(): string
    {
        return $this->namespaceBase;
    }

    /**
     * @param string $namespaceBase
     *
     * @return static
     */
    public function setNamespaceBase(string $namespaceBase): self
    {
        $this->namespaceBase = $namespaceBase;
        return $this;
    }


    public function __construct(string $databaseNamespace = null)
    {
        if ($databaseNamespace) {
            $this->namespaceBase = $databaseNamespace;
        }
    }

    /**
     * @param bool $overwriteModelAwareTraits
     *
     * @return static
     */
    public function setOverwriteModelAwareTraits(bool $overwriteModelAwareTraits): self
    {
        $this->overwriteModelAwareTraits = $overwriteModelAwareTraits;
        return $this;
    }

    /**
     * @param array $dbToPhpType
     *
     * @return static
     */
    public function setDbToPhpType(array $dbToPhpType): self
    {
        $this->dbToPhpType = $dbToPhpType;
        return $this;
    }

    /**
     * @return string
     */
    public function getNamespaceRepositories(): string
    {
        return $this->namespaceRepositories ?: $this->getNamespaceBase().'\\Repository';
    }

    /**
     * @param string $namespaceRepositories
     *
     * @return DoctrineOrmSchema
     */
    public function setNamespaceRepositories(string $namespaceRepositories): DoctrineOrmSchema
    {
        $this->namespaceRepositories = $namespaceRepositories;
        return $this;
    }

    /**
     * @return string
     */
    public function getNamespaceRepositoriesTraits(): string
    {
        return $this->namespaceRepositoriesTraits ?: $this->getNamespaceRepositories().'\\Generated';
    }

    /**
     * @param string $namespaceRepositoriesTraits
     *
     * @return DoctrineOrmSchema
     */
    public function setNamespaceRepositoriesTraits(string $namespaceRepositoriesTraits): DoctrineOrmSchema
    {
        $this->namespaceRepositoriesTraits = $namespaceRepositoriesTraits;
        return $this;
    }

    /**
     * @return string
     */
    public function getNamespaceModels(): string
    {
        return $this->namespaceModels ?: $this->getNamespaceBase().'\\Model';
    }

    /**
     * @param string $namespaceModels
     *
     * @return DoctrineOrmSchema
     */
    public function setNamespaceModels(string $namespaceModels): DoctrineOrmSchema
    {
        $this->namespaceModels = $namespaceModels;
        return $this;
    }

    /**
     * @return string
     */
    public function getNamespaceModelsTraits(): string
    {
        return $this->namespaceModelsTraits ?: $this->getNamespaceModels().'\\Generated';
    }

    /**
     * @param string $namespaceModelsTraits
     *
     * @return DoctrineOrmSchema
     */
    public function setNamespaceModelsTraits(string $namespaceModelsTraits): DoctrineOrmSchema
    {
        $this->namespaceModelsTraits = $namespaceModelsTraits;
        return $this;
    }

    /**
     * @return string
     */
    public function getNamespaceModelsBuilders(): string
    {
        return $this->namespaceModelsBuilders ?: $this->getNamespaceModels().'\\Builders';
    }

    /**
     * @param string $namespaceModelsBuilders
     *
     * @return DoctrineOrmSchema
     */
    public function setNamespaceModelsBuilders(string $namespaceModelsBuilders): DoctrineOrmSchema
    {
        $this->namespaceModelsBuilders = $namespaceModelsBuilders;
        return $this;
    }

    /**
     * @return string
     */
    public function getNamespaceMigrations(): string
    {
        return $this->namespaceMigrations ?: $this->getNamespaceBase().'\\Migrations';
    }

    /**
     * @param string $namespaceMigrations
     *
     * @return DoctrineOrmSchema
     */
    public function setNamespaceMigrations(string $namespaceMigrations): DoctrineOrmSchema
    {
        $this->namespaceMigrations = $namespaceMigrations;
        return $this;
    }

    /**
     * @return string
     */
    public function getNamespaceMapping(): string
    {
        return $this->namespaceMapping ?: $this->getNamespaceBase().'\\Mapping';
    }

    /**
     * @param string $namespaceMapping
     *
     * @return DoctrineOrmSchema
     */
    public function setNamespaceMapping(string $namespaceMapping): DoctrineOrmSchema
    {
        $this->namespaceMapping = $namespaceMapping;
        return $this;
    }

    /**
     * @return string
     */
    public function getNamespaceMappingGenerated(): string
    {
        return $this->namespaceMappingGenerated ?: $this->getNamespaceMapping().'\\Generated';
    }

    /**
     * @param string $namespaceMappingGenerated
     *
     * @return DoctrineOrmSchema
     */
    public function setNamespaceMappingGenerated(string $namespaceMappingGenerated): DoctrineOrmSchema
    {
        $this->namespaceMappingGenerated = $namespaceMappingGenerated;
        return $this;
    }

    /**
     * @return string
     */
    public function getNamespaceMappingExtra(): string
    {
        return $this->namespaceMappingExtra ?: $this->getNamespaceMapping().'\\Extra';
    }

    /**
     * @param string $namespaceMappingExtra
     *
     * @return DoctrineOrmSchema
     */
    public function setNamespaceMappingExtra(string $namespaceMappingExtra): DoctrineOrmSchema
    {
        $this->namespaceMappingExtra = $namespaceMappingExtra;
        return $this;
    }

    /**
     * @param EntityManager $entityManager
     *
     * @return DoctrineOrmSchema
     */
    public function setEntityManager(EntityManager $entityManager): DoctrineOrmSchema
    {
        $this->entityManager = $entityManager;
        return $this;
    }

    /**
     * @return EntityManager
     */
    public function getEntityManager(): EntityManager
    {
        return $this->entityManager;
    }

    /**
     * @param string|null $migrationBaseClass
     *
     * @return DoctrineOrmSchema
     */
    public function setMigrationBaseClass(?string $migrationBaseClass): DoctrineOrmSchema
    {
        $this->migrationBaseClass = $migrationBaseClass;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getMigrationBaseClass(): ?string
    {
        return $this->migrationBaseClass ?: $this->getNamespaceMigrations().'\\AbstractBaseMigration';
    }

    /**
     * @param string $pathToMigrations
     *
     * @return DoctrineOrmSchema
     */
    public function setPathToMigrations(string $pathToMigrations): DoctrineOrmSchema
    {
        $this->pathToMigrations = $pathToMigrations;
        return $this;
    }

    /**
     * @return string
     */
    public function getPathToMigrations(): string
    {
        return $this->pathToMigrations ?: $this->pathToPsrRoot.'/'.Strings::bs2s($this->getNamespaceMigrations());
    }

    /**
     * @param mixed $pathToModels
     *
     * @return DoctrineOrmSchema
     */
    public function setPathToModels($pathToModels)
    {
        $this->pathToModels = $pathToModels;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPathToModels()
    {
        return $this->pathToModels ?: $this->pathToPsrRoot.'/'.Strings::bs2s($this->getNamespaceModels());
    }

    /**
     * @param string $pathToModelsTraits
     *
     * @return DoctrineOrmSchema
     */
    public function setPathToModelsTraits(string $pathToModelsTraits): DoctrineOrmSchema
    {
        $this->pathToModelsTraits = $pathToModelsTraits;
        return $this;
    }

    /**
     * @return string
     */
    public function getPathToModelsTraits(): string
    {
        return $this->pathToModelsTraits ?: $this->pathToPsrRoot.'/'.Strings::bs2s($this->getNamespaceModelsTraits());
    }

    /**
     * @param mixed $pathToRepositories
     *
     * @return DoctrineOrmSchema
     */
    public function setPathToRepositories($pathToRepositories)
    {
        $this->pathToRepositories = $pathToRepositories;
        return $this;
    }

    /**
     * @return string
     */
    public function getPathToRepositories(): string
    {
        return $this->pathToRepositories ?: $this->pathToPsrRoot.'/'.Strings::bs2s($this->getNamespaceRepositories());
    }

    /**
     * @param string $pathToMapping
     *
     * @return DoctrineOrmSchema
     */
    public function setPathToMapping(string $pathToMapping): DoctrineOrmSchema
    {
        $this->pathToMapping = $pathToMapping;
        return $this;
    }

    /**
     * @return string
     */
    public function getPathToMapping(): string
    {
        return $this->pathToMapping ?: $this->pathToPsrRoot.'/'.Strings::bs2s($this->getNamespaceMapping());
    }

    /**
     * @param string[] $skipTables
     *
     * @return DoctrineOrmSchema
     */
    public function setSkipTables(array $skipTables): DoctrineOrmSchema
    {
        $this->skipTables = $skipTables;
        return $this;
    }

    /**
     * @param mixed $namespaceModelsAwareTraits
     *
     * @return DoctrineOrmSchema
     */
    public function setNamespaceModelsAwareTraits($namespaceModelsAwareTraits)
    {
        $this->namespaceModelsAwareTraits = $namespaceModelsAwareTraits;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getNamespaceModelsAwareTraits()
    {
        return $this->namespaceModelsAwareTraits ?: $this->getNamespaceModels().'\\AwareTraits';
    }

    /**
     * @param string $pathToModelAwareTraits
     *
     * @return DoctrineOrmSchema
     */
    public function setPathToModelAwareTraits(string $pathToModelAwareTraits): DoctrineOrmSchema
    {
        $this->pathToModelAwareTraits = $pathToModelAwareTraits;
        return $this;
    }

    /**
     * @return string
     */
    public function getPathToModelAwareTraits(): string
    {
        return $this->pathToModelAwareTraits ?: $this->pathToPsrRoot.'/'.Strings::bs2s($this->getNamespaceModelsAwareTraits());
    }

    /**
     * @param bool $generateModelAwareTraits
     *
     * @return static
     */
    public function setGenerateModelAwareTraits(bool $generateModelAwareTraits): self
    {
        $this->generateModelAwareTraits = $generateModelAwareTraits;
        return $this;
    }

    /**
     * @param bool $overwriteModelClasses
     *
     * @return static
     */
    public function setOverwriteModelClasses(bool $overwriteModelClasses): self
    {
        $this->overwriteModelClasses = $overwriteModelClasses;
        return $this;
    }

    /**
     * @param string $modelTraitFieldVisibility
     *
     * @return DoctrineOrmSchema
     */
    public function setModelTraitFieldVisibility(string $modelTraitFieldVisibility): DoctrineOrmSchema
    {
        $this->modelTraitFieldVisibility = $modelTraitFieldVisibility;
        return $this;
    }

    /**
     * @param bool $generateModelWithRepoAccess
     *
     * @return DoctrineOrmSchema
     */
    public function setGenerateModelWithRepoAccess(bool $generateModelWithRepoAccess): DoctrineOrmSchema
    {
        $this->generateModelWithRepoAccess = $generateModelWithRepoAccess;
        return $this;
    }

    /**
     * @param string $metadataFilter
     *
     * @return static
     */
    public function setMetadataFilter(string $metadataFilter): self
    {
        $this->metadataFilter = $metadataFilter;
        return $this;
    }

    /**
     * @param string $metadataBuilderClass
     *
     * @return DoctrineOrmSchema
     */
    public function setMetadataBuilderClass(string $metadataBuilderClass): DoctrineOrmSchema
    {
        $this->metadataBuilderClass = $metadataBuilderClass;
        return $this;
    }

    /**
     * @param string $metaVar
     *
     * @return DoctrineOrmSchema
     */
    public function setMetaVar(string $metaVar): DoctrineOrmSchema
    {
        $this->metaVar = $metaVar;
        return $this;
    }

    /**
     * @param string $builderVar
     *
     * @return DoctrineOrmSchema
     */
    public function setBuilderVar(string $builderVar): DoctrineOrmSchema
    {
        $this->builderVar = $builderVar;
        return $this;
    }

    /**
     * @param array $doctrineTypesMap
     *
     * @return DoctrineOrmSchema
     */
    public function setDoctrineTypesMap(array $doctrineTypesMap): DoctrineOrmSchema
    {
        $this->doctrineTypesMap = $doctrineTypesMap;
        return $this;
    }

    /**
     * @param string[] $skipColumns
     *
     * @return DoctrineOrmSchema
     */
    public function setSkipColumns(array $skipColumns): DoctrineOrmSchema
    {
        $this->skipColumns = $skipColumns;
        return $this;
    }

    /**
     * @param bool $cascadePersistAllRelationships
     *
     * @return DoctrineOrmSchema
     */
    public function setCascadePersistAllRelationships(bool $cascadePersistAllRelationships): DoctrineOrmSchema
    {
        $this->cascadePersistAllRelationships = $cascadePersistAllRelationships;
        return $this;
    }

    /**
     * @param bool $cascadeRefreshAllRelationships
     *
     * @return DoctrineOrmSchema
     */
    public function setCascadeRefreshAllRelationships(bool $cascadeRefreshAllRelationships): DoctrineOrmSchema
    {
        $this->cascadeRefreshAllRelationships = $cascadeRefreshAllRelationships;
        return $this;
    }

    /**
     * @param string $parentRepositoryClass
     *
     * @return DoctrineOrmSchema
     */
    public function setParentRepositoryClass(string $parentRepositoryClass): DoctrineOrmSchema
    {
        $this->parentRepositoryClass = $parentRepositoryClass;
        return $this;
    }

    /**
     * @param bool $overwriteRepoClasses
     *
     * @return DoctrineOrmSchema
     */
    public function setOverwriteRepoClasses(bool $overwriteRepoClasses): DoctrineOrmSchema
    {
        $this->overwriteRepoClasses = $overwriteRepoClasses;
        return $this;
    }

    /**
     * @param bool $generateRepoTraits
     *
     * @return DoctrineOrmSchema
     */
    public function setGenerateRepoTraits(bool $generateRepoTraits): DoctrineOrmSchema
    {
        $this->generateRepoTraits = $generateRepoTraits;
        return $this;
    }

    /**
     * @param bool $generateModelWithLockHelpers
     *
     * @return DoctrineOrmSchema
     */
    public function setGenerateModelWithLockHelpers(bool $generateModelWithLockHelpers): DoctrineOrmSchema
    {
        $this->generateModelWithLockHelpers = $generateModelWithLockHelpers;
        return $this;
    }

    /**
     * @param string $knownEntityManagerContainerClass
     *
     * @return DoctrineOrmSchema
     */
    public function setKnownEntityManagerContainerClass(string $knownEntityManagerContainerClass): DoctrineOrmSchema
    {
        $this->knownEntityManagerContainerClass = $knownEntityManagerContainerClass;
        return $this;
    }

    /**
     * @param string $knownEntityManagerRouterClass
     *
     * @return DoctrineOrmSchema
     */
    public function setKnownEntityManagerRouterClass(string $knownEntityManagerRouterClass): DoctrineOrmSchema
    {
        $this->knownEntityManagerRouterClass = $knownEntityManagerRouterClass;
        return $this;
    }

    /**
     * @param bool $generateSetOrIdMethodForModelAwareTraits
     *
     * @return DoctrineOrmSchema
     */
    public function setGenerateSetOrIdMethodForModelAwareTraits(bool $generateSetOrIdMethodForModelAwareTraits): DoctrineOrmSchema
    {
        $this->generateSetOrIdMethodForModelAwareTraits = $generateSetOrIdMethodForModelAwareTraits;
        return $this;
    }

    /**
     * @param bool $generateSetMethodForModelAwareTraits
     *
     * @return DoctrineOrmSchema
     */
    public function setGenerateSetMethodForModelAwareTraits(bool $generateSetMethodForModelAwareTraits): DoctrineOrmSchema
    {
        $this->generateSetMethodForModelAwareTraits = $generateSetMethodForModelAwareTraits;
        return $this;
    }

    /**
     * @param string $dqlHelperClass
     *
     * @return DoctrineOrmSchema
     */
    public function setDqlHelperClass(string $dqlHelperClass): DoctrineOrmSchema
    {
        $this->dqlHelperClass = $dqlHelperClass;
        return $this;
    }

    /**
     * @param array $singleTableInheritance
     *
     * @return DoctrineOrmSchema
     */
    public function setSingleTableInheritance(array $singleTableInheritance): DoctrineOrmSchema
    {
        $this->singleTableInheritance = $singleTableInheritance;
        return $this;
    }

}
