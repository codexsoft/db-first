<?php

namespace CodexSoft\DatabaseFirst\Orm\Postgres;

use CodexSoft\DatabaseFirst\DoctrineOrmSchema;
use CodexSoft\DatabaseFirst\Orm\DoctrineEntityLifecycleEventSubscriber;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Configuration;
use \MartinGeorgiev\Doctrine\DBAL\Types as MartinGeorgievTypes;
use CodexSoft\DatabaseFirst\Orm\Postgres\Types\BigIntCastingToIntType;
use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\Cache\VoidCache;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Setup;
use Ramsey\Uuid\Doctrine\UuidType;

abstract class AbstractPgSqlEntityManagerBuilder
{

    public const CUSTOM_DOMAINS = [

        // while reverse-engineering, this types were detected. in normal mode it still exists?
        'smallint[]' => 'smallint[]',
        'integer[]' => 'integer[]',
        'bigint[]' => 'bigint[]',
        'text[]' => 'text[]',
        '_int2' => 'smallint[]',
        '_int4' => 'integer[]',
        '_int8' => 'bigint[]',
        '_text' => 'text[]',
        //'jsonb' => 'jsonb',
        //'jsonb[]' => 'jsonb[]',
        //'_jsonb' => 'jsonb[]',
    ];

    protected bool $isDevMode = true;

    /** @var CacheProvider */
    protected $cache;

    /** @var string  */
    protected string $proxyDir;

    /** @var string[] */
    protected array $mappingDirectories = [];

    /** @var array */
    protected array $databaseConfig = [];

    /** @var Configuration if no configuration provided, new one will be created */
    protected ?Configuration $configuration = null;

    /**
     * If you have an existing Connection instance, use it
     * otherwise use setDatabaseConfig(array), array can be builded via ConnectionBuilder
     * @var Connection
     */
    protected ?Connection $connection = null;

    /** @var \Doctrine\Common\EventManager if no event manager provided, new one will be created */
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

    public function tuneConfiguration(Configuration $config): void
    {
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
    }

    /**
     * @return EntityManagerInterface|EntityManager
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public function build(): EntityManagerInterface
    {
        static::addCustomTypes();

        if ($this->configuration instanceof Configuration) {
            $config = $this->configuration;
        } else {
            $config = Setup::createConfiguration($this->isDevMode, $this->proxyDir, $this->cache);;
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

        $this->tuneConfiguration($config);
        $this->tuneEventManager($eventManager);

        $entityManager = EntityManager::create($connection, $config, $eventManager);
        $this->tuneEntityManager($entityManager);

        return $entityManager;
    }

    /**
     * @param EntityManagerInterface $entityManager
     *
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Exception
     */
    public function tuneEntityManager(EntityManagerInterface $entityManager): void
    {
        $platform = $entityManager->getConnection()->getDatabasePlatform();

        if ($platform === null) {
            throw new \Exception('Database platform is not detected!');
        }

        foreach( static::CUSTOM_DOMAINS as $domainName => $domainType ) {
            $platform->registerDoctrineTypeMapping($domainName, $domainType);
        }
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param string $typeName
     * @param string $typeClass
     *
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Exception
     */
    public function addCustomType(EntityManagerInterface $entityManager, string $typeName, string $typeClass)
    {
        Type::addType($typeName, $typeClass);
        $platform = $entityManager->getConnection()->getDatabasePlatform();

        if ($platform === null) {
            throw new \Exception('Database platform is not detected!');
        }

        $platform->registerDoctrineTypeMapping($typeName, $typeName);
    }

    public function tuneEventManager(EventManager $eventManager): void
    {
        $eventManager->addEventSubscriber(new DoctrineEntityLifecycleEventSubscriber);
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     */
    public static function addCustomTypes(): void
    {
        $types = [
            'smallint[]' => MartinGeorgievTypes\SmallIntArray::class,
            'integer[]' => MartinGeorgievTypes\IntegerArray::class,
            'bigint[]' => MartinGeorgievTypes\BigIntArray::class,
            'jsonb[]' => MartinGeorgievTypes\JsonbArray::class,
            'text[]' => MartinGeorgievTypes\TextArray::class,
            'varchar[]'  => MartinGeorgievTypes\TextArray::class,
        ];

        foreach ($types as $typeName => $typeClass) {

            if (\is_int($typeName)) {
                // this will trigger PHP deprecated call non-static method statically
                /** @var \MartinGeorgiev\Doctrine\DBAL\Types\BaseType $typeName */
                $typeName = $typeClass::TYPE_NAME;
                //$typeName = $typeClass::getName();
            }

            if (!Type::hasType($typeName)) {
                Type::addType( $typeName, $typeClass );
            } else {
                Type::overrideType( $typeName, $typeClass );
            }

        }

        if (!Type::hasType(UuidType::NAME)) {
            Type::addType(UuidType::NAME, UuidType::class);
        } else {
            Type::overrideType(UuidType::NAME, UuidType::class);
        }

        Type::overrideType( Types::BIGINT, BigIntCastingToIntType::class );
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
