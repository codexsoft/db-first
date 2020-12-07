<?php


namespace CodexSoft\DatabaseFirst;


class TypeData
{
    private string $doctrineType;
    private string $typeClass;
    private string $hint;

    /** @var string[] */
    private array $aliases;

    public function __construct(
        string $doctrineType,
        string $typeClass,
        string $hint,
        array $aliases = []
    )
    {
        $this->doctrineType = $doctrineType;
        $this->typeClass = $typeClass;
        $this->hint = $hint;
        $this->aliases = $aliases;
    }

    /**
     * @return string
     */
    public function getDoctrineType(): string
    {
        return $this->doctrineType;
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
}
