<?php

namespace CodexSoft\DatabaseFirst;

use CodexSoft\DatabaseFirst\InheritanceMap\EntityData;

/**
 * @internal
 */
class InheritanceMap
{
    private array $entityDataByTable = [];
    private array $entityDataByName = [];

    public function __construct(array $inheritanceMap)
    {
        foreach ($inheritanceMap as $table => [$descriminatorColumnName, $descriminatorColumnType, $children]) {

            $entityData = new EntityData();
            $entityData->table = $table;
            $entityData->descriminatorColumnName = $descriminatorColumnName;
            $entityData->descriminatorColumnType = $descriminatorColumnType;

            foreach ($children as [$childEntityName, $discriminatorValue, $childColumns]) {

                $childEntityData = new EntityData();

                foreach ($childColumns as $childColumn) {
                    $entityData->delegatedColumnNamesMap[$childColumn] = $childEntityName;
                }
            }

            $this->entityDataByTable[$table] = $entityData;
        }
    }

    public function getEntityDataForTable($tableName): ?EntityData
    {
        return $this->entityDataByTable[$tableName] ?? null;
    }

    public function getEntityDataForName($name): ?EntityData
    {
        return $this->entityDataByName[$name] ?? null;
    }
}
