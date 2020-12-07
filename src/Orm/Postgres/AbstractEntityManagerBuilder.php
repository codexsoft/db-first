<?php

namespace CodexSoft\DatabaseFirst\Orm\Postgres;

use CodexSoft\DatabaseFirst\Orm\DoctrineEntityLifecycleEventSubscriber;
use CodexSoft\DatabaseFirst\TypeData;
use CodexSoft\DatabaseFirst\TypesManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Configuration;
use CodexSoft\DatabaseFirst\Orm\Postgres\Types\BigIntCastingToIntType;
use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\Cache\VoidCache;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Setup;

abstract class AbstractEntityManagerBuilder
{
    protected bool $isDevMode = true;

    /** @var CacheProvider */
    protected $cache;

    /** @var string */
    protected string $proxyDir;

    /** @var string[] */
    protected array $mappingDirectories = [];

    /** @var array */
    protected array $databaseConfig = [];

    /** @var Configuration|null if no configuration provided, new one will be created */
    protected ?Configuration $configuration = null;

    /**
     * If you have an existing Connection instance, use it
     * otherwise use setDatabaseConfig(array), array can be builded via ConnectionBuilder
     *
     * @var Connection|null
     */
    protected ?Connection $connection = null;

    /** @var EventManager|null if no event manager provided, new one will be created */
    protected ?EventManager $eventManager = null;

    public function __construct() {
        $this->cache = new VoidCache();
    }

    /**
     * Here custom string DQL functions can be resistered, this function can be overrided, like:
     * <code>
     * retun [
     *     'ALL_OF' => \MartinGeorgiev\Doctrine\ORM\Query\AST\Functions\All::class,
     * ]
     * </code>
     * @return array
     */
    protected function getStringFunctions(): array
    {
        return [];
    }

    /**
     * Here custom numeric DQL functions can be resistered, this function can be overrided, like:
     * <code>
     * retun [
     *     'LEAST' => \MartinGeorgiev\Doctrine\ORM\Query\AST\Functions\Least::class,
     * ]
     * </code>
     * @return array
     */
    protected function getNumericFunctions(): array
    {
        return [];
    }

    /**
     * @return EntityManagerInterface|EntityManager
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public function build(): EntityManagerInterface
    {
        if ($this->configuration instanceof Configuration) {
            $config = $this->configuration;
        } else {
            $config = Setup::createConfiguration($this->isDevMode, $this->proxyDir, $this->cache);
        }

        $connection = ($this->connection instanceof Connection)
            ? $this->connection
            : $this->databaseConfig;

        /*
         * if event manager is not set manually, trying to get it from connection
         */
        if (!($this->eventManager instanceof EventManager) && $this->connection instanceof Connection) {
            $this->eventManager = $this->connection->getEventManager();
        }

        /*
         * if event manager still not set, creating new
         */
        if ($this->eventManager instanceof EventManager) {
            $eventManager = $this->eventManager;
        } else {
            $eventManager = new EventManager();
        }

        $config->setCustomStringFunctions($this->getStringFunctions());
        $config->setCustomNumericFunctions($this->getNumericFunctions());
        $config->setMetadataDriverImpl(new \Doctrine\Persistence\Mapping\Driver\PHPDriver($this->getMappingDirectories()));

        ///**
        // * todo: this should be fixed someday
        // * Somewhy \Doctrine\DBAL\Schema\AbstractSchemaManager::filterAssetNames() ignores
        // * configured filterSchemaAssetsExpressionCallable
        // */
        //$config->setSchemaAssetsFilter(function($tableName) {
        //    return false === DoctrineOrmSchema::tableShouldBeSkipped(
        //        $tableName, $this->doctrineOrmSchema->skipTables
        //    );
        //});

        $eventManager->addEventSubscriber(new DoctrineEntityLifecycleEventSubscriber());

        $entityManager = EntityManager::create($connection, $config, $eventManager);

        /**
         * Registering types
         */
        $platform = $entityManager->getConnection()->getDatabasePlatform();
        static::installTypes($platform);
        foreach (static::getDoctrineTypeMapping() as $domainName => $domainType) {
            $platform->registerDoctrineTypeMapping($domainName, $domainType);
        }

        $this->tuneConfiguration($config);
        $this->tuneEventManager($eventManager);
        $this->tuneEntityManager($entityManager);

        return $entityManager;
    }

    protected static function getDoctrineTypeMapping(): array
    {
        /*
         * while reverse-engineering, this types were detected. in normal mode it still exists?
         */
        return [
            'smallint[]' => 'smallint[]',
            'integer[]' => 'integer[]',
            'bigint[]' => 'bigint[]',
            'text[]' => 'text[]',
            '_int2' => 'smallint[]',
            '_int4' => 'integer[]',
            '_int8' => 'bigint[]',
            '_text' => 'text[]',
        ];
    }

    /**
     * Use this hook to modify EntityManager after creation
     * @param EntityManagerInterface $entityManager
     */
    public function tuneEntityManager(EntityManagerInterface $entityManager): void
    {
    }

    /**
     * Use this hook to modify Configuration after creation
     * @param Configuration $config
     */
    public function tuneConfiguration(Configuration $config): void
    {
    }

    /**
     * Use this hook to modify EventManager after creation
     * @param EventManager $eventManager
     */
    public function tuneEventManager(EventManager $eventManager): void
    {
    }

    /**
     * @param AbstractPlatform $platform
     * @param string $typeName
     * @param string $typeClass
     * @param bool $overrideIfExists
     *
     * @param bool $throwExceptionIfClassNotFound
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public static function installType(
        AbstractPlatform $platform,
        string $typeName,
        string $typeClass,
        bool $overrideIfExists = true,
        bool $throwExceptionIfClassNotFound = false
    ): void
    {
        if (!\class_exists($typeClass)) {
            if ($throwExceptionIfClassNotFound) {
                throw new \RuntimeException("Failed to install Doctrine type: $typeClass is not exist");
            }
            return;
        }

        if (!Type::hasType($typeName)) {
            Type::addType($typeName, $typeClass);
        } elseif ($overrideIfExists) {
            Type::overrideType($typeName, $typeClass);
        }

        $platform->registerDoctrineTypeMapping($typeName, $typeName);
    }

    /**
     * Most common/popular types
     * @return array|string[]
     */
    protected static function defaultTypesToInstall(): array
    {
        $defaultTypes = [
            Types::BIGINT => BigIntCastingToIntType::class,
        ];

        if (\class_exists(\MartinGeorgiev\Doctrine\DBAL\Types\SmallIntArray::class)) {
            \array_merge($defaultTypes, [
                'smallint[]' => \MartinGeorgiev\Doctrine\DBAL\Types\SmallIntArray::class,
                'integer[]' => \MartinGeorgiev\Doctrine\DBAL\Types\IntegerArray::class,
                'bigint[]' => \MartinGeorgiev\Doctrine\DBAL\Types\BigIntArray::class,
                'jsonb[]' => \MartinGeorgiev\Doctrine\DBAL\Types\JsonbArray::class,
                'text[]' => \MartinGeorgiev\Doctrine\DBAL\Types\TextArray::class,
                'varchar[]' => \MartinGeorgiev\Doctrine\DBAL\Types\TextArray::class,
            ]);
        }

        if (\class_exists(\Ramsey\Uuid\Doctrine\UuidType::class)) {
            \array_merge($defaultTypes, [
                'uuid' => \Ramsey\Uuid\Doctrine\UuidType::class,
            ]);
        }

        return $defaultTypes;
    }

    /**
     * @return array|string[]
     */
    protected static function getTypesToInstall(): array
    {
        return static::defaultTypesToInstall();
    }

    /**
     * Because of Doctrine's global type mapping settings
     *
     * @param AbstractPlatform $platform
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    protected static function installTypes(AbstractPlatform $platform): void
    {
        foreach (static::getTypesToInstall() as $typeName => $typeClass) {
            static::installType($platform, $typeName, $typeClass);
        }
    }

    /**
     * @param array $config
     *
     * @return $this
     */
    public function setDatabaseConfig(array $config): self
    {
        $this->databaseConfig = $config;
        return $this;
    }

    public function getDatabaseConfig(): array
    {
        return $this->databaseConfig;
    }

    /**
     * @param mixed $proxyDir
     *
     * @return static
     */
    public function setProxyDir( $proxyDir ): self
    {
        $this->proxyDir = $proxyDir;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getProxyDir() {
        return $this->proxyDir;
    }

    /**
     * @param CacheProvider $cache
     *
     * @return static
     */
    public function setCache( CacheProvider $cache ): self
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * @return CacheProvider
     */
    public function getCache(): CacheProvider
    {
        return $this->cache;
    }

    /**
     * @param bool $isDevMode
     *
     * @return static
     */
    public function setIsDevMode( bool $isDevMode ): self
    {
        $this->isDevMode = $isDevMode;
        return $this;
    }

    /**
     * @param string[] $mappingDirectories
     *
     * @return static
     */
    public function setMappingDirectories( array $mappingDirectories ): self
    {
        $this->mappingDirectories = $mappingDirectories;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getMappingDirectories(): array {
        return $this->mappingDirectories;
    }

    /**
     * @param Connection $connection
     *
     * @return static
     */
    public function setConnection( Connection $connection ): self
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * @param EventManager $eventManager
     *
     * @return static
     */
    public function setEventManager( EventManager $eventManager ): self
    {
        $this->eventManager = $eventManager;
        return $this;
    }

    /**
     * @return EventManager
     */
    public function getEventManager(): EventManager
    {
        return $this->eventManager;
    }

    /**
     * @param Configuration $configuration
     *
     * @return static
     */
    public function setConfiguration( Configuration $configuration ): self
    {
        $this->configuration = $configuration;
        return $this;
    }

    /**
     * @return Configuration
     */
    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }
}
