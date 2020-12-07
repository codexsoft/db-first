<?php


namespace CodexSoft\DatabaseFirst;


class TypeData
{
    private string $doctrineTypeName;
    private string $typeClass;
    private string $hint;
    private string $phpDocTypehint;

    /** @var string[] */
    private array $dbTypes;

    public function __construct(
        string $doctrineType,
        string $typeClass,
        string $hint,
        string $phpDocTypehint = '',
        array $aliases = []
    )
    {
        $this->doctrineTypeName = $doctrineType;
        $this->typeClass = $typeClass;
        $this->hint = $hint;
        $this->phpDocTypehint = $phpDocTypehint;
        $this->dbTypes = $aliases;
    }

    /**
     * @return string
     */
    public function getDoctrineTypeName(): string
    {
        return $this->doctrineTypeName;
    }

    /**
     * @return string
     */
    public function getTypeClass(): string
    {
        return $this->typeClass;
    }

    /**
     * @return string
     */
    public function getHint(): string
    {
        return $this->hint;
    }

    /**
     * @return string[]
     */
    public function getDbTypes(): array
    {
        return $this->dbTypes;
    }

    /**
     * @return string
     */
    public function getPhpDocTypehint(): string
    {
        return $this->phpDocTypehint;
    }
}
