<?php


namespace CodexSoft\DatabaseFirst;


use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class TypesManager
{
    private const DBAL_TYPES_CONSTANTS = [
        'array' => 'ARRAY',
        'bigint' => 'BIGINT',
        'binary' => 'BINARY',
        'blob' => 'BLOB',
        'boolean' => 'BOOLEAN',
        'date' => 'DATE_MUTABLE',
        'date_immutable' => 'DATE_IMMUTABLE',
        'dateinterval' => 'DATEINTERVAL',
        'datetime' => 'DATETIME_MUTABLE',
        'datetime_immutable' => 'DATETIME_IMMUTABLE',
        'datetimetz' => 'DATETIMETZ_MUTABLE',
        'datetimetz_immutable' => 'DATETIMETZ_IMMUTABLE',
        'decimal' => 'DECIMAL',
        'float' => 'FLOAT',
        'guid' => 'GUID',
        'integer' => 'INTEGER',
        'json' => 'JSON',
        'json_array' => 'JSON',
        'object' => 'OBJECT',
        'simple_array' => 'SIMPLE_ARRAY',
        'smallint' => 'SMALLINT',
        'string' => 'STRING',
        'text' => 'TEXT',
        'time' => 'TIME_MUTABLE',
        'time_immutable' => 'TIME_IMMUTABLE',
        //'array' => 'TARRAY',
        //'date' => 'DATE',
        //'datetime' => 'DATETIME',
        //'datetimetz' => 'DATETIMETZ',
        //'json_array' => 'JSON_ARRAY',
        //'time' => 'TIME',
    ];

    public function getTypesConstantName(string $typeName): ?string
    {
        return self::DBAL_TYPES_CONSTANTS[$typeName] ?? null;
    }

    /** @var TypeData[] */
    private array $types = [];

    private AbstractPlatform $platform;

    public function __construct(AbstractPlatform $platform)
    {
        $this->platform = $platform;
    }

    public function hasType(string $typeName): bool
    {
        return \array_key_exists($typeName, $this->types);
    }

    public function getType(string $typeName): ?TypeData
    {
        if ($this->hasType($typeName)) {
            return $this->types[$typeName];
        }

        return null;
    }

    public function addType(TypeData $typeData): self
    {
        $this->types[$typeData->getDoctrineTypeName()] = $typeData;
        return $this;
    }

    /**
     * @param TypeData[] $typesData
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function installTypesData(array $typesData): void
    {
        foreach ($typesData as $typeData) {
            $this->installTypeData($typeData);
        }
    }

    /**
     * @param TypeData $typeData
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function installTypeData(TypeData $typeData): void
    {
        //self::installType($typeData->getDoctrineTypeName(), $typeData->getTypeClass());

        $typeName = $typeData->getDoctrineTypeName();
        $this->types[$typeName] = $typeData;

        if (!Type::hasType($typeName)) {
            Type::addType($typeName, $typeData->getTypeClass());
        } else {
            Type::overrideType($typeName, $typeData->getTypeClass());
        }

        $this->platform->registerDoctrineTypeMapping($typeName, $typeName);
        foreach ($typeData->getDbTypes() as $alias) {
            $this->platform->registerDoctrineTypeMapping($alias, $typeName);
        }
    }

    public static function installType(
        string $typeName,
        string $typeClass
        //bool $overrideIfExists = true,
        //bool $throwExceptionIfClassNotFound = false
    ): void
    {
        //if (!\class_exists($typeClass)) {
        //    if ($throwExceptionIfClassNotFound) {
        //        throw new \RuntimeException("Failed to install Doctrine type: $typeClass is not exist");
        //    }
        //    return;
        //}

        if (!Type::hasType($typeName)) {
            Type::addType($typeName, $typeClass);
        //} elseif ($overrideIfExists) {
        } else {
            Type::overrideType($typeName, $typeClass);
        }

        $platform->registerDoctrineTypeMapping($typeName, $typeName);
    }

    public static function installTypeOld(
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
}
