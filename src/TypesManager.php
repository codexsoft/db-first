<?php


namespace CodexSoft\DatabaseFirst;


use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class TypesManager
{
    /** @var TypeData[] */
    private array $types = [];

    private AbstractPlatform $platform;

    public function __construct(AbstractPlatform $platform)
    {
        $this->platform = $platform;
    }

    public function addType(TypeData $typeData)
    {
        $this->types[$typeData->getDoctrineTypeName()] = $typeData;
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

    public function installTypeData(TypeData $typeData): void
    {
        //self::installType($typeData->getDoctrineTypeName(), $typeData->getTypeClass());

        $typeName = $typeData->getDoctrineTypeName();

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
