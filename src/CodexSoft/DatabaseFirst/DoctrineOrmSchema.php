<?php

namespace CodexSoft\DatabaseFirst;

use CodexSoft\Code\AbstractModuleSchema;
use CodexSoft\Code\Helpers\Strings;
use CodexSoft\DatabaseFirst\Operation\EntityManagerAwareTrait;
use Doctrine\ORM\EntityManager;

class DoctrineOrmSchema extends AbstractModuleSchema
{

    use EntityManagerAwareTrait; // todo: decide: EntityManagerAwareTrait or private $entityManager!

    public const CUSTOM_CODEXSOFT_BUILDER = 'codexsoft_builder';

    private $namespaceRepositories;
    private $namespaceRepositoriesTraits;
    private $namespaceModels;
    private $namespaceModelsTraits;
    private $namespaceModelsBuilders;
    private $namespaceMigrations;
    private $namespaceMapping;
    private $namespaceMappingGenerated;
    private $namespaceMappingExtra;

    /** @var string|null */
    private $migrationBaseClass;

    private $baseMigrationClass;

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

    /**
     * todo: this should be used when generating mapping, repos and models!
     * @var string[] while generating entities, entities for these tables should be skipped
     */
    private $skipTables = [];

    public function __construct(string $databaseNamespace = null)
    {
        if ($databaseNamespace) {
            $this->namespaceBase = $databaseNamespace;
        }
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
        return $this->migrationBaseClass;
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
     * @return string[]
     */
    public function getSkipTables(): array
    {
        return $this->skipTables;
    }

}