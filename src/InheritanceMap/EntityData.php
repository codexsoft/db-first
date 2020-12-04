<?php

namespace CodexSoft\DatabaseFirst\InheritanceMap;

class EntityData
{
    public bool $isSubclass;
    public string $table;
    public string $descriminatorColumnName;
    public string $descriminatorColumnType;
    public array $delegatedColumnNamesMap = [];
}
