<?php

namespace CodexSoft\DatabaseFirst;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\Builder\ClassMetadataBuilder;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function Stringy\create as str;

class DatabaseFirstConfig
{
    private ?string $namespaceEntities = null;
    private ?string $namespaceEntityTraits = null;
    private ?string $namespaceEntityAwareTraits = null;
    private ?string $namespaceRepositories = null;
    private ?string $namespaceRepositoriesTraits = null;
    private ?string $namespaceMapping = null;

    private ?string $pathToEntities = null;
    private ?string $pathToEntityTraits = null;
    private ?string $pathToEntityAwareTraits = null;
    private ?string $pathToRepositories = null;
    private ?string $pathToRepositoriesTraits = null;
    private ?string $pathToMapping = null;

    private EntityManagerInterface $entityManager;
    private TypesManager $typesManager;

    /**
     * @var string[]
     * @deprecated todo: rework this
     */
    public array $dbToPhpType = [
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

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->typesManager = new TypesManager($entityManager->getConnection()->getDatabasePlatform());
    }

    /**
     * @var string[] while generating entities, entities for these tables should be skipped
     * supports wildcard ending like doctrine_*
     */
    public array $skipTables = [
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
    public array $skipColumns = [];

    //public const OPTION_CASCADE_PERSIST_ALL_RELATIONSHIPS = 1;
    //public const OPTION_CASCADE_REFRESH_ALL_RELATIONSHIPS = 2;

    public bool $optionMappingCascadePersistAllRelationships = false;
    public bool $optionMappingRefreshAllRelationships = false;
    public bool $optionEntityAwareTraitsGenerate = false;
    public bool $optionEntityAwareTraitsOverwriteExisting = false;
    public bool $optionEntityAwareTraitsGenerateSetMethod = false;
    public bool $optionEntityAwareTraitsGenerateSetOrIdMethod = false;
    public bool $optionEntityOverwriteExistingClasses = false;
    public bool $optionEntityTraitAssociationIdGettersGenerate = false;
    public bool $optionEntityTraitStaticDqlFieldNamesGenerate = false;
    public bool $optionEntityTraitStaticSqlColumnNamesGenerate = false;
    public bool $optionRepoTraitsGenerate = false;
    public bool $optionRepoOverwriteClasses = false;
    public bool $optionEntityTraitGenerateWithRepoAccess = false;
    public bool $optionEntityTraitGenerateWithLockHelpers = false;

    public ?string $knownEntityManagerContainerClass = null;
    public ?string $knownEntityManagerRouterClass = null;

    /**
     * Visibility of the field in generated model traits
     */
    public string $modelTraitFieldVisibility = 'private';

    /** @var string|string[] A string pattern used to match entities that should be processed (IS WHITELIST). */
    public $metadataFilter;

    /** @var string A parent class for repository. */
    public string $parentRepositoryClass = EntityRepository::class;

    public string $metadataBuilderClass = ClassMetadataBuilder::class;
    public string $metaVar = '$metadata';
    public string $builderVar = '$mapper';

    /**
     * @var array|string[]
     * @deprecated
     * todo: review this
     */
    public array $doctrineTypesMap = [
        'array' => 'TARRAY',
        'bigint' => 'BIGINT',
        'binary' => 'BINARY',
        'blob' => 'BLOB',
        'boolean' => 'BOOLEAN',
        'date' => 'DATE',
        'date_immutable' => 'DATE_IMMUTABLE',
        'dateinterval' => 'DATEINTERVAL',
        'datetime' => 'DATETIME',
        'datetime_immutable' => 'DATETIME_IMMUTABLE',
        'datetimetz' => 'DATETIMETZ',
        'datetimetz_immutable' => 'DATETIMETZ_IMMUTABLE',
        'decimal' => 'DECIMAL',
        'float' => 'FLOAT',
        'guid' => 'GUID',
        'integer' => 'INTEGER',
        'json' => 'JSON',
        'json_array' => 'JSON_ARRAY',
        'object' => 'OBJECT',
        'simple_array' => 'SIMPLE_ARRAY',
        'smallint' => 'SMALLINT',
        'string' => 'STRING',
        'text' => 'TEXT',
        'time' => 'TIME',
        'time_immutable' => 'TIME_IMMUTABLE',
    ];

    public array $singleTableInheritance = [];
    public ?InheritanceMap $inheritanceMap = null;

    /**
     * @param string $domainConfigFile
     *
     * @return static
     * @throws \Exception
     */
    public static function getFromConfigFile(string $domainConfigFile): self
    {
        if (!\file_exists($domainConfigFile)) {
            throw new \Exception("Provided config File $domainConfigFile not exists!");
        }

        ob_start();
        $domainSchema = include $domainConfigFile;
        ob_end_clean();

        if (!$domainSchema instanceof static) {
            throw new \Exception("File $domainConfigFile does not return valid ".static::class."!\n");
        }

        return $domainSchema;
    }

    /**
     * @param bool $optionEntityAwareTraitsOverwriteExisting
     *
     * @return static
     */
    public function setOptionEntityAwareTraitsOverwriteExisting(bool $optionEntityAwareTraitsOverwriteExisting): self
    {
        $this->optionEntityAwareTraitsOverwriteExisting = $optionEntityAwareTraitsOverwriteExisting;
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
        return $this->namespaceRepositories;
    }

    /**
     * @param string $namespaceRepositories
     *
     * @return DatabaseFirstConfig
     */
    protected function setNamespaceRepositories(string $namespaceRepositories): DatabaseFirstConfig
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
     * @return DatabaseFirstConfig
     */
    protected function setNamespaceRepositoriesTraits(string $namespaceRepositoriesTraits): DatabaseFirstConfig
    {
        $this->namespaceRepositoriesTraits = $namespaceRepositoriesTraits;
        return $this;
    }

    /**
     * @return string
     */
    public function getNamespaceEntities(): string
    {
        return $this->namespaceEntities;
    }

    /**
     * @param string $namespaceEntities
     *
     * @return DatabaseFirstConfig
     */
    protected function setNamespaceEntities(string $namespaceEntities): DatabaseFirstConfig
    {
        $this->namespaceEntities = $namespaceEntities;
        return $this;
    }

    /**
     * @return string
     */
    public function getNamespaceEntityTraits(): string
    {
        return $this->namespaceEntityTraits ?: $this->getNamespaceEntities().'\\Generated';
    }

    /**
     * @param string $namespaceEntityTraits
     *
     * @return DatabaseFirstConfig
     */
    protected function setNamespaceEntityTraits(string $namespaceEntityTraits): DatabaseFirstConfig
    {
        $this->namespaceEntityTraits = $namespaceEntityTraits;
        return $this;
    }

    /**
     * @return string
     */
    public function getNamespaceMapping(): string
    {
        return $this->namespaceMapping;
    }

    /**
     * @param string $namespaceMapping
     *
     * @return DatabaseFirstConfig
     */
    protected function setNamespaceMapping(string $namespaceMapping): DatabaseFirstConfig
    {
        $this->namespaceMapping = $namespaceMapping;
        return $this;
    }

    /**
     * @param EntityManager $entityManager
     *
     * @return DatabaseFirstConfig
     */
    public function setEntityManager(EntityManager $entityManager): DatabaseFirstConfig
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
     * @param mixed $pathToEntities
     *
     * @return DatabaseFirstConfig
     */
    protected function setPathToEntities($pathToEntities)
    {
        $this->pathToEntities = $pathToEntities;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPathToEntities()
    {
        return $this->pathToEntities;
    }

    /**
     * @param string $pathToEntityTraits
     *
     * @return DatabaseFirstConfig
     */
    protected function setPathToEntityTraits(string $pathToEntityTraits): DatabaseFirstConfig
    {
        $this->pathToEntityTraits = $pathToEntityTraits;
        return $this;
    }

    /**
     * @return string
     */
    public function getPathToEntityTraits(): string
    {
        return $this->pathToEntityTraits;
    }

    /**
     * @param mixed $pathToRepositories
     *
     * @return DatabaseFirstConfig
     */
    protected function setPathToRepositories($pathToRepositories)
    {
        $this->pathToRepositories = $pathToRepositories;
        return $this;
    }

    /**
     * @return string
     */
    public function getPathToRepositories(): string
    {
        return $this->pathToRepositories;
    }

    /**
     * @param string $pathToMapping
     *
     * @return DatabaseFirstConfig
     */
    protected function setPathToMapping(string $pathToMapping): DatabaseFirstConfig
    {
        $this->pathToMapping = $pathToMapping;
        return $this;
    }

    /**
     * @return string
     */
    public function getPathToMapping(): string
    {
        return $this->pathToMapping;
    }

    /**
     * @param string[] $skipTables
     *
     * @return DatabaseFirstConfig
     */
    public function setSkipTables(array $skipTables): DatabaseFirstConfig
    {
        $this->skipTables = $skipTables;
        return $this;
    }

    /**
     * @param mixed $namespaceEntityAwareTraits
     *
     * @return DatabaseFirstConfig
     */
    protected function setNamespaceEntityAwareTraits($namespaceEntityAwareTraits)
    {
        $this->namespaceEntityAwareTraits = $namespaceEntityAwareTraits;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getNamespaceEntityAwareTraits()
    {
        return $this->namespaceEntityAwareTraits ?: $this->getNamespaceEntities().'\\AwareTraits';
    }

    /**
     * @param string $pathToEntityAwareTraits
     *
     * @return DatabaseFirstConfig
     */
    protected function setPathToEntityAwareTraits(string $pathToEntityAwareTraits): DatabaseFirstConfig
    {
        $this->pathToEntityAwareTraits = $pathToEntityAwareTraits;
        return $this;
    }

    /**
     * @return string
     */
    public function getPathToEntityAwareTraits(): string
    {
        return $this->pathToEntityAwareTraits;
    }

    /**
     * @param bool $optionEntityAwareTraitsGenerate
     *
     * @return static
     */
    public function setOptionEntityAwareTraitsGenerate(bool $optionEntityAwareTraitsGenerate): self
    {
        $this->optionEntityAwareTraitsGenerate = $optionEntityAwareTraitsGenerate;
        return $this;
    }

    /**
     * @param bool $optionEntityOverwriteExistingClasses
     *
     * @return static
     */
    public function setOptionEntityOverwriteExistingClasses(bool $optionEntityOverwriteExistingClasses): self
    {
        $this->optionEntityOverwriteExistingClasses = $optionEntityOverwriteExistingClasses;
        return $this;
    }

    /**
     * @param string $modelTraitFieldVisibility
     *
     * @return DatabaseFirstConfig
     */
    public function setModelTraitFieldVisibility(string $modelTraitFieldVisibility): DatabaseFirstConfig
    {
        $this->modelTraitFieldVisibility = $modelTraitFieldVisibility;
        return $this;
    }

    /**
     * @param bool $optionEntityTraitGenerateWithRepoAccess
     *
     * @return DatabaseFirstConfig
     */
    public function setOptionEntityTraitGenerateWithRepoAccess(bool $optionEntityTraitGenerateWithRepoAccess): DatabaseFirstConfig
    {
        $this->optionEntityTraitGenerateWithRepoAccess = $optionEntityTraitGenerateWithRepoAccess;
        return $this;
    }

    /**
     * @param string|string[] $metadataFilter
     *
     * @return static
     */
    public function setMetadataFilter($metadataFilter): self
    {
        $this->metadataFilter = $metadataFilter;
        return $this;
    }

    /**
     * @param string $metadataBuilderClass
     *
     * @return DatabaseFirstConfig
     */
    public function setMetadataBuilderClass(string $metadataBuilderClass): DatabaseFirstConfig
    {
        $this->metadataBuilderClass = $metadataBuilderClass;
        return $this;
    }

    /**
     * @param string $metaVar
     *
     * @return DatabaseFirstConfig
     */
    public function setMetaVar(string $metaVar): DatabaseFirstConfig
    {
        $this->metaVar = $metaVar;
        return $this;
    }

    /**
     * @param string $builderVar
     *
     * @return DatabaseFirstConfig
     */
    public function setBuilderVar(string $builderVar): DatabaseFirstConfig
    {
        $this->builderVar = $builderVar;
        return $this;
    }

    /**
     * @param array $doctrineTypesMap
     *
     * @return DatabaseFirstConfig
     */
    public function setDoctrineTypesMap(array $doctrineTypesMap): DatabaseFirstConfig
    {
        $this->doctrineTypesMap = $doctrineTypesMap;
        return $this;
    }

    /**
     * @param string[] $skipColumns
     *
     * @return DatabaseFirstConfig
     */
    public function setSkipColumns(array $skipColumns): DatabaseFirstConfig
    {
        $this->skipColumns = $skipColumns;
        return $this;
    }

    /**
     * @param bool $optionMappingCascadePersistAllRelationships
     *
     * @return DatabaseFirstConfig
     */
    public function setOptionMappingCascadePersistAllRelationships(bool $optionMappingCascadePersistAllRelationships): DatabaseFirstConfig
    {
        $this->optionMappingCascadePersistAllRelationships = $optionMappingCascadePersistAllRelationships;
        return $this;
    }

    /**
     * @param bool $optionMappingRefreshAllRelationships
     *
     * @return DatabaseFirstConfig
     */
    public function setOptionMappingRefreshAllRelationships(bool $optionMappingRefreshAllRelationships): DatabaseFirstConfig
    {
        $this->optionMappingRefreshAllRelationships = $optionMappingRefreshAllRelationships;
        return $this;
    }

    /**
     * @param string $parentRepositoryClass
     *
     * @return DatabaseFirstConfig
     */
    public function setParentRepositoryClass(string $parentRepositoryClass): DatabaseFirstConfig
    {
        $this->parentRepositoryClass = $parentRepositoryClass;
        return $this;
    }

    /**
     * @param bool $optionRepoOverwriteClasses
     *
     * @return DatabaseFirstConfig
     */
    public function setOptionRepoOverwriteClasses(bool $optionRepoOverwriteClasses): DatabaseFirstConfig
    {
        $this->optionRepoOverwriteClasses = $optionRepoOverwriteClasses;
        return $this;
    }

    /**
     * @param bool $optionRepoTraitsGenerate
     *
     * @return DatabaseFirstConfig
     */
    public function setOptionRepoTraitsGenerate(bool $optionRepoTraitsGenerate): DatabaseFirstConfig
    {
        $this->optionRepoTraitsGenerate = $optionRepoTraitsGenerate;
        return $this;
    }

    /**
     * @param bool $optionEntityTraitGenerateWithLockHelpers
     *
     * @return DatabaseFirstConfig
     */
    public function setOptionEntityTraitGenerateWithLockHelpers(bool $optionEntityTraitGenerateWithLockHelpers): DatabaseFirstConfig
    {
        $this->optionEntityTraitGenerateWithLockHelpers = $optionEntityTraitGenerateWithLockHelpers;
        return $this;
    }

    /**
     * @param string $knownEntityManagerContainerClass
     *
     * @return DatabaseFirstConfig
     */
    public function setKnownEntityManagerContainerClass(string $knownEntityManagerContainerClass): DatabaseFirstConfig
    {
        $this->knownEntityManagerContainerClass = $knownEntityManagerContainerClass;
        return $this;
    }

    /**
     * @param string $knownEntityManagerRouterClass
     *
     * @return DatabaseFirstConfig
     */
    public function setKnownEntityManagerRouterClass(string $knownEntityManagerRouterClass): DatabaseFirstConfig
    {
        $this->knownEntityManagerRouterClass = $knownEntityManagerRouterClass;
        return $this;
    }

    /**
     * @param bool $optionEntityAwareTraitsGenerateSetOrIdMethod
     *
     * @return DatabaseFirstConfig
     */
    public function setOptionEntityAwareTraitsGenerateSetOrIdMethod(bool $optionEntityAwareTraitsGenerateSetOrIdMethod): DatabaseFirstConfig
    {
        $this->optionEntityAwareTraitsGenerateSetOrIdMethod = $optionEntityAwareTraitsGenerateSetOrIdMethod;
        return $this;
    }

    /**
     * @param bool $optionEntityAwareTraitsGenerateSetMethod
     *
     * @return DatabaseFirstConfig
     */
    public function setOptionEntityAwareTraitsGenerateSetMethod(bool $optionEntityAwareTraitsGenerateSetMethod): DatabaseFirstConfig
    {
        $this->optionEntityAwareTraitsGenerateSetMethod = $optionEntityAwareTraitsGenerateSetMethod;
        return $this;
    }

    ///**
    // * @param string $dqlHelperClass
    // *
    // * @return DoctrineOrmSchema
    // */
    //public function setDqlHelperClass(string $dqlHelperClass): DoctrineOrmSchema
    //{
    //    $this->dqlHelperClass = $dqlHelperClass;
    //    return $this;
    //}

    /**
     * @param array $singleTableInheritance
     *
     * @return DatabaseFirstConfig
     */
    public function setSingleTableInheritance(array $singleTableInheritance): DatabaseFirstConfig
    {
        $this->singleTableInheritance = $singleTableInheritance;
        $this->inheritanceMap = new InheritanceMap($singleTableInheritance);
        return $this;
    }

    public static function tableShouldBeSkipped(string $tableName, array $skipTables, ?LoggerInterface $logger = null): bool
    {
        $logger = $logger ?: new NullLogger();

        if (\in_array($tableName, $skipTables, true)) {
            $logger->debug(\sprintf('Skipping table "%s"', $tableName));
            return true;
        }

        foreach ($skipTables as $tableToSkip) {
            if (str($tableToSkip)->endsWith('*') && str($tableName)->startsWith((string) str($tableToSkip)->removeRight('*'))) {
                $logger->debug(\sprintf('Skipping table "%s" because of %s', $tableName, $tableToSkip));
                return true;
            }
        }

        return false;
    }

    public function configureMapping(string $namespace, string $path): self
    {
        $this->setNamespaceMapping($namespace);
        $this->setPathToMapping($path);
        return $this;
    }

    public function configureEntities(string $namespace, string $path): self
    {
        $this->setNamespaceEntities($namespace);
        $this->setPathToEntities($path);
        return $this;
    }

    /**
     * @deprecated use configureEntitiesTraits()
     * @param string $namespace
     * @param string $path
     *
     * @return $this
     */
    public function configureModelsTraits(string $namespace, string $path): self
    {
        return $this->configureEntitiesTraits($namespace, $path);
    }

    public function configureEntitiesTraits(string $namespace, string $path): self
    {
        $this->setNamespaceEntityTraits($namespace);
        $this->setPathToEntityTraits($path);
        return $this;
    }

    /**
     * @deprecated use configureEntityAwareTraits()
     * @param string $namespace
     * @param string $path
     *
     * @return $this
     */
    public function configureModelsAwareTraits(string $namespace, string $path): self
    {
        return $this->configureEntitiesAwareTraits($namespace, $path);
    }

    public function configureEntitiesAwareTraits(string $namespace, string $path): self
    {
        $this->setNamespaceEntityAwareTraits($namespace);
        $this->setPathToEntityAwareTraits($path);
        return $this;
    }

    public function configureRepositories(string $namespace, string $path): self
    {
        $this->setNamespaceRepositories($namespace);
        $this->setPathToRepositories($path);
        return $this;
    }

    public function configureRepositoriesTraits(string $namespace, string $path): self
    {
        $this->setNamespaceRepositoriesTraits($namespace);
        $this->setPathToRepositoriesTraits($path);
        return $this;
    }

    /**
     * @param string|null $pathToRepositoriesTraits
     *
     * @return DatabaseFirstConfig
     */
    protected function setPathToRepositoriesTraits(?string $pathToRepositoriesTraits): DatabaseFirstConfig
    {
        $this->pathToRepositoriesTraits = $pathToRepositoriesTraits;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getPathToRepositoriesTraits(): string
    {
        return $this->pathToRepositoriesTraits;
    }

    /**
     * @param bool $optionEntityTraitAssociationIdGettersGenerate
     *
     * @return static
     */
    public function setOptionEntityTraitAssociationIdGettersGenerate(bool $optionEntityTraitAssociationIdGettersGenerate): self
    {
        $this->optionEntityTraitAssociationIdGettersGenerate = $optionEntityTraitAssociationIdGettersGenerate;
        return $this;
    }

    /**
     * @param bool $optionEntityTraitStaticDqlFieldNamesGenerate
     *
     * @return DatabaseFirstConfig
     */
    public function setOptionEntityTraitStaticDqlFieldNamesGenerate(
        bool $optionEntityTraitStaticDqlFieldNamesGenerate
    ): DatabaseFirstConfig {
        $this->optionEntityTraitStaticDqlFieldNamesGenerate = $optionEntityTraitStaticDqlFieldNamesGenerate;
        return $this;
    }

    /**
     * @param bool $optionEntityTraitStaticSqlColumnNamesGenerate
     *
     * @return DatabaseFirstConfig
     */
    public function setOptionEntityTraitStaticSqlColumnNamesGenerate(
        bool $optionEntityTraitStaticSqlColumnNamesGenerate
    ): DatabaseFirstConfig {
        $this->optionEntityTraitStaticSqlColumnNamesGenerate = $optionEntityTraitStaticSqlColumnNamesGenerate;
        return $this;
    }

    /**
     * @return TypesManager
     */
    public function getTypesManager(): TypesManager
    {
        return $this->typesManager;
    }

}
