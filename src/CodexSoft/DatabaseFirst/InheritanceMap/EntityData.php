<?php

namespace CodexSoft\DatabaseFirst\InheritanceMap;

class EntityData
{
    public $isSubclass;
    public $table;
    public $descriminatorColumnName;
    public $descriminatorColumnType;
    public $delegatedColumnNamesMap = [];
}
