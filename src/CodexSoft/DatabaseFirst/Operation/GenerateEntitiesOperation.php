<?php


namespace CodexSoft\DatabaseFirst\Operation;

use CodexSoft\Code\Helpers\Classes;
use CodexSoft\Code\Shortcuts;
use CodexSoft\DatabaseFirst\Helpers\Doctrine;
use CodexSoft\OperationsSystem\Operation;
use Doctrine\Common\Inflector\Inflector;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\Tools\Console\MetadataFilter;
use Doctrine\ORM\Tools\DisconnectedClassMetadataFactory;
use Symfony\Component\Filesystem\Filesystem;
use function CodexSoft\Code\str;
use const CodexSoft\Code\TAB;

/**
 * @method void execute()
 */
class GenerateEntitiesOperation extends Operation
{

    use DoctrineOrmSchemaAwareTrait;

    const ID = '91922eda-b329-423b-aaa9-7832f3d7c6dd';

    private $generateWithRepoAccess = true;

    /**
     * Visibility of the field
     *
     * @var string
     */
    protected $fieldVisibility = 'private';

    /** @var bool  */
    protected $overwriteModelClasses = false;

    /** @var array comments for all columns in db, in format [ <table>.<column> => <comment> ] */
    protected $columnComments = [];

    /** @var EntityManager */
    protected $em;

    protected $typeAlias = [
        'json'             => 'array',
        'json[]'           => 'array',
        'jsonb'            => 'array',
        'jsonb[]'          => 'array',
        'text[]'           => 'string[]',
        'smallint[]'       => 'integer[]',
        'integer[]'        => 'integer[]',
        'bigint[]'         => 'integer[]',
        'varchar[]'        => 'string[]',
        Type::DATETIMETZ   => '\DateTime',
        Type::DATETIME     => '\DateTime',
        Type::DATE         => '\DateTime',
        Type::TIME         => '\DateTime',
        Type::OBJECT       => '\stdClass',
        Type::BIGINT       => 'integer',
        Type::SMALLINT     => 'integer',
        Type::TEXT         => 'string',
        Type::BLOB         => 'string',
        Type::DECIMAL      => 'string',
        Type::JSON_ARRAY   => 'array',
        Type::SIMPLE_ARRAY => 'array',
        Type::GUID         => 'string',
    ];

    /** @var string A string pattern used to match entities that should be processed. */
    private $metadataFilter;

    /**
     * @param string $metadataFilter
     *
     * @return GenerateEntitiesOperation
     */
    public function setMetadataFilter(string $metadataFilter): GenerateEntitiesOperation
    {
        $this->metadataFilter = $metadataFilter;
        return $this;
    }

    /**
     * @param bool $overwriteModelClasses
     *
     * @return GenerateEntitiesOperation
     */
    public function setOverwriteModelClasses(bool $overwriteModelClasses): GenerateEntitiesOperation
    {
        $this->overwriteModelClasses = $overwriteModelClasses;
        return $this;
    }

    /**
     * @return void
     * @throws \CodexSoft\OperationsSystem\Exception\OperationException
     */
    protected function handle()
    {
        Shortcuts::register();
        $this->em = $this->doctrineOrmSchema->getEntityManager();

        $cmf = new DisconnectedClassMetadataFactory();
        $cmf->setEntityManager($this->em);
        $metadatas = $cmf->getAllMetadata();
        $metadatas = MetadataFilter::filter($metadatas, $this->metadataFilter);

        if (!count($metadatas)) {
            throw $this->genericException('No Metadata Classes to process.');
        }

        $ds = $this->doctrineOrmSchema;
        //$destPath = $ds->getPathToModels();

        $fs = new Filesystem;

        // todo: if dir not exsists and no write permissions?
        if (!file_exists($ds->getPathToModels())) {
            $fs->mkdir($ds->getPathToModels());
        }

        if (!file_exists($ds->getPathToModelsTraits())) {
            $fs->mkdir($ds->getPathToModelsTraits());
        }

        $this->columnComments = Doctrine::getAllColumnsComments($this->em->getConnection());
        foreach ($metadatas as $metadata) {
            $fs->dumpFile(
                $ds->getPathToModelsTraits().'/'.$this->getClassName($metadata).'Trait.php',
                $this->generateEntityTraitClassCode($metadata)
            );

            $modelClassFile = $ds->getPathToModels().'/'.$this->getClassName($metadata).'.php';
            if ($this->overwriteModelClasses || !file_exists($modelClassFile)) {
                $fs->dumpFile($modelClassFile, $this->generateEntityClassCode($metadata));
            }
        }
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    protected function getClassName(ClassMetadataInfo $metadata)
    {
        return ($pos = strrpos($metadata->name, '\\'))
            ? substr($metadata->name, $pos + 1, strlen($metadata->name)) : $metadata->name;
    }

    private function generateEntityClassCode(ClassMetadata $metadata): string
    {
        $ds = $this->doctrineOrmSchema;
        //$lines = [];

        $entityClassName = $this->getClassName($metadata);
        $tableComment = Doctrine::getCommentForTable($this->em->getConnection(), $metadata->table['name']);
        $customRepoClass = $this->doctrineOrmSchema->getNamespaceRepositories().'\\'.$entityClassName.'Repository';

        $lines = [
            '<?php',
            '',
            'namespace '.$ds->getNamespaceModels().';',
            '/**',
            " * {$tableComment}",
            ' * '.$entityClassName,
            $this->generateWithRepoAccess ? ' * @method static \\'.$customRepoClass.' repo(\\'.EntityManagerInterface::class.' $em = null)' : ' *',
            ' * @Doctrine\ORM\Mapping\Entity(repositoryClass="'.$customRepoClass.'")',
            ' */',
            "class $entityClassName",
            '{',
            TAB.'use \\'.$ds->getNamespaceModelsTraits().'\\'.$entityClassName.'Trait;',
            '}',
        ];

        return implode("\n", $lines);

    }

    private function generateEntityTraitClassCode(ClassMetadata $metadata): string
    {
        $ds = $this->doctrineOrmSchema;
        $entityClassName = $this->getClassName($metadata);

        $lines = [
            '<?php',
            '',
            'namespace '.$ds->getNamespaceModelsTraits().';',
            '',
            "trait {$entityClassName}Trait",
            '{',
            TAB,
            TAB.'use \\'.\CodexSoft\DatabaseFirst\Orm\RepoStaticAccessTrait::class.';',
            '',
            $this->generateEntityFieldMappingProperties($metadata),
            $this->generateEntityAssociationMappingProperties($metadata),
            $this->generateEntityStubMethods($metadata),
            $this->generateDqlFieldNameHelpers($metadata),
            $this->generateSqlFieldNameHelpers($metadata),
            '}',

        ];

        return implode("\n", $lines);
    }

    protected function generateFieldMappingPropertyDocBlock(array $fieldMapping, ClassMetadataInfo $metadata): string
    {
        $fieldComment = $this->getCommentForField($metadata,$fieldMapping['fieldName']);
        //$fieldComment = Doctrine::getCommentForField($metadata, $fieldMapping['fieldName'], $this->columnComments);
        $lines = [];
        $lines[] = TAB.'/**';
        if ($fieldComment) {
            $lines[] = TAB.' * '.$fieldComment;
        }
        $lines[] = TAB.' * @var '
            .$this->getType($fieldMapping['type'])
            .($this->nullableFieldExpression($fieldMapping) ? '|null' : '');
        $lines[] = TAB.' */';

        return implode("\n", $lines);
    }

    /**
     * @param string $type
     *
     * @return string
     */
    protected function getType($type): string
    {
        if (isset($this->typeAlias[$type])) {
            return $this->typeAlias[$type];
        }

        return $type;
    }

    /**
     * @param array $fieldMapping
     *
     * @return string|null
     */
    private function nullableFieldExpression(array $fieldMapping): ?string
    {
        if (isset($fieldMapping['nullable']) && true === $fieldMapping['nullable']) {
            return 'null';
        }

        return null;
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    protected function generateEntityFieldMappingProperties(ClassMetadataInfo $metadata)
    {
        $lines = [];

        foreach ($metadata->fieldMappings as $fieldMapping) {
            //if (isset($fieldMapping['declaredField'], $metadata->embeddedClasses[$fieldMapping['declaredField']]) ||
            //    $this->hasProperty($fieldMapping['fieldName'], $metadata) ||
            //    $metadata->isInheritedField($fieldMapping['fieldName'])
            //) {
            //    continue;
            //}

            $lines[] = $this->generateFieldMappingPropertyDocBlock($fieldMapping, $metadata);
            $lines[] = TAB.$this->fieldVisibility.' $'.$fieldMapping['fieldName']
                .(isset($fieldMapping['options']['default']) ? ' = '.var_export($fieldMapping['options']['default'], true) : null) . ";\n";
        }

        return implode("\n", $lines);
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    protected function generateEntityAssociationMappingProperties(ClassMetadataInfo $metadata)
    {
        $lines = [];

        foreach ($metadata->associationMappings as $associationMapping) {
            //if ($this->hasProperty($associationMapping['fieldName'], $metadata)) {
            //    continue;
            //}

            $lines[] = $this->generateAssociationMappingPropertyDocBlock($associationMapping, $metadata);
            $lines[] = TAB.$this->fieldVisibility.' $'.$associationMapping['fieldName']
                .($associationMapping['type'] === 'manyToMany' ? ' = array()' : null).";\n";
        }

        return implode("\n", $lines);
    }

    /**
     * @param array             $associationMapping
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    protected function generateAssociationMappingPropertyDocBlock(array $associationMapping, ClassMetadataInfo $metadata)
    {
        $fieldComment = $this->getCommentForField($metadata, $associationMapping['fieldName']);
        //$fieldComment = Doctrine::getCommentForField($metadata, $associationMapping['fieldName'], $this->columnComments);

        $lines = [];
        $lines[] = TAB . '/**';
        if ($fieldComment) {
            $lines[] = TAB.' * '.$fieldComment;
        }

        if ($associationMapping['type'] & ClassMetadataInfo::TO_MANY) {
            $lines[] = TAB . ' * @var \Doctrine\Common\Collections\Collection';
        } else {
            $lines[] = TAB . ' * @var \\' . ltrim($associationMapping['targetEntity'], '\\');
        }

        $lines[] = TAB . ' */';

        return implode("\n", $lines);
    }

    protected function generateDqlFieldNameHelpers(ClassMetadataInfo $metadata): string
    {
        $lines = ['','// to avoid human mistakes when using entity DQL properties names...',''];

        $filteredAssociationMappings = [];
        foreach ($metadata->associationMappings as $associationMappingName => $associationMapping) {
            if ($associationMapping['type'] & ClassMetadataInfo::TO_ONE) {
                $filteredAssociationMappings[] = $associationMappingName;
            }
        }

        $methodNames = \array_merge(
            \array_keys($metadata->fieldMappings),
            \array_keys($metadata->embeddedClasses),
            $filteredAssociationMappings
        );

        //$methodNames = \array_values(\array_unique($methodNames));
        $methodNames = \array_unique($methodNames);

        foreach ($methodNames as $methodName) {
            $uMethodName = '_'.$methodName;
            $comment = $this->getCommentForField($metadata, $methodName);
            $lines[] = '';
            $lines[] = '/**';
            if ($comment) {
                $lines[] = ' * '.$comment;
            }
            $lines[] = ' * @param string|null $alias';
            $lines[] = ' * @return string';
            $lines[] = ' */';
            $lines[] = "public static function $uMethodName(?string \$alias = null): string { return \is_string(\$alias) ? \$alias.'.$methodName' : '$methodName'; }";
        }

        $lines[] = '';

        return implode("\n".TAB, $lines);

    }

    protected function generateSqlFieldNameHelpers(ClassMetadataInfo $metadata): string
    {
        $lines = ['','// to avoid human mistakes when using SQL table and columns names...',''];
        //$lines[] = "public const TABLE = '".$metadata->getTableName()."';"; // traits cannot have constants
        $lines[] = 'public static function _db_table_($doubleQuoted = true): string { return $doubleQuoted ? \'"'.$metadata->getTableName().'"\' : \''.$metadata->getTableName().'\'; }';
        $lines[] = '';

        $methodNames = [];

        foreach ($metadata->fieldMappings as $fieldName => $fieldData) {
            $methodNames[] = $metadata->getColumnName($fieldName);
        }

        foreach ($metadata->embeddedClasses as $fieldName => $fieldData) {
            $methodNames[] = str($fieldName)->underscored(); // can be buggy (act_1c_no => act1c_no)
        }

        foreach ($metadata->associationMappings as $associationMappingName => $associationMapping) {
            if ($associationMapping['type'] & ClassMetadataInfo::TO_ONE) {
                try {
                    $methodNames[] = $metadata->getSingleAssociationJoinColumnName($associationMappingName);
                } catch (MappingException $e) {
                    // log?
                }
            }
        }

        $methodNames = \array_unique($methodNames);

        foreach ($methodNames as $methodName) {
            $uMethodName = $methodName;
            $comment = $this->columnComments[$metadata->getTableName().'.'.$uMethodName];
            $lines[] = '';
            $lines[] = '/**';
            if ($comment) {
                $lines[] = ' * '.$comment;
            }
            $lines[] = ' * @param string|null $alias';
            $lines[] = ' * @return string';
            $lines[] = ' */';
            $lines[] = 'public static function _db_'.$uMethodName.'(?string $alias = null): string { return \is_string($alias) ? \'"\'.$alias.\'"."'.$uMethodName.'"\' : \'"'.$uMethodName.'"\'; }';
        }

        $lines[] = '';

        return implode("\n".TAB, $lines);

    }

    /**
     * комментарий для скалярного поля берем из fieldMappings, тогда как для ассоциации немного сложнее
     * @param ClassMetadataInfo $metadata
     * @param string $fieldName
     *
     * @return string
     */
    protected function getCommentForField(ClassMetadataInfo $metadata, string $fieldName): string
    {
        return Doctrine::getCommentForField($metadata,$fieldName,$this->columnComments);
    }

    private function generateGetMethod(ClassMetadataInfo $metadata, string $fieldName, $typeHint = null, $defaultValue = null)
    {

        $fieldComment = $this->getCommentForField($metadata,$fieldName);
        $methodName = 'get'.Inflector::classify($fieldName);
        $variableName = Inflector::camelize($fieldName);

        $methodTypeHint = null;
        $types          = Type::getTypesMap();
        $variableType   = $typeHint ? $this->getType($typeHint) : null;

        if ($typeHint && !isset($types[$typeHint])) {
            $variableType   =  '\\' . ltrim($variableType, '\\');
            $methodTypeHint =  '\\' . $typeHint . ' ';
        }

        //if ( ClassMetadata::ONE_TO_MANY === $metadata->associationMappings[$fieldName]['type'] ) {
        //
        //    $mappedBy = null;
        //    $inversedBy = null;
        //    $targetEntity = null;
        //    $sourceEntity = null;
        //
        //    if ( array_key_exists($fieldName,$metadata->associationMappings) ) {
        //        $variableExtraType = '|\\'.$targetEntity.'[] ';
        //    }
        //
        //}

        $variableExtraType = null;

        if (\array_key_exists($fieldName,$metadata->associationMappings) || ($variableType === '\\'.\DateTime::class) ) {
            $entityClassName = $this->getClassName( $metadata );
            $exceptionMessageVar = ($fieldName === 'message') ? '$msg' : '$message';
            $getMethodTemplate = [
                '/**',
                ' * Get '.$variableName,
                ' * '.$fieldComment,
                ' *',
                ' * @return '.($defaultValue === 'null' ? $variableType.'|null' : $variableType).$variableExtraType,
                ' */',
                "public function $methodName(): ?".$variableType,
                '{',
                TAB."return \$this->$fieldName;",
                '}',
                '',
                '/**',
                ' * @noinspection PhpDocMissingThrowsInspection',
                ' * Get not-null '.$variableName.' or throw exception',
                ' * '.$fieldComment,
                ' *',
                " * @param string|\\Exception|null $exceptionMessageVar a message for exception (or exception instance) that will raise if $fieldName is null",
                ' * @return '.$variableType.$variableExtraType,
                ' */',
                "public function {$methodName}OrFail($exceptionMessageVar = null): $variableType",
                '{',
                TAB."if (!\$this->$fieldName instanceof $variableType) {",
                TAB.TAB."if ($exceptionMessageVar instanceof \\Exception) {",
                TAB.TAB.TAB.'/** @noinspection PhpUnhandledExceptionInspection */',
                TAB.TAB.TAB.'/** @var \RuntimeException '.$exceptionMessageVar.' */',
                TAB.TAB.TAB."throw $exceptionMessageVar;",
                TAB.TAB.'}',
                '',
                TAB.TAB."if ($exceptionMessageVar === null) {",
                TAB.TAB.TAB."$exceptionMessageVar = '{$entityClassName}.{$fieldName} is null, but {$variableType} was expected.';",
                TAB.TAB.'}',
                '',
                TAB.TAB.'/** @noinspection PhpUnhandledExceptionInspection */',
                TAB.TAB.'if (!$this->$fieldName instanceof \\'.$variableType.') {',
                TAB.TAB.TAB."throw new \\Exception('{$exceptionMessageVar}');",
                TAB.TAB.'}',
                //TAB.TAB.'\\'.Assertion::class."::isInstanceOf(\$this->$fieldName,{$variableType}::class, $exceptionMessageVar);",
                TAB.'}',
                TAB."return \$this->$fieldName;",
                '}',
                '',
            ];
            return TAB.implode("\n".TAB, $getMethodTemplate);
        }

        // scalar
        $lines = [
            '/**',
            ' * Get '.$variableName,
            ' * '.$fieldComment,
            ' * ',
            ' * @return '.$variableType,
            ' */',
            'public function '.$methodName.'()',
            '{',
            //TAB.'$this->'.$fieldName.' = $'.$variableName.';',
            TAB.'return $this->'.$fieldName.';',
            '}',
        ];

        return TAB.implode("\n".TAB, $lines);

        //$x = '/**
        //* <description>
        //* <fieldComment>
        //*
        //* @return <variableType><variableExtraType>
        //*/
        //public function <methodName>()
        //{
        //<spaces>return $this-><fieldName>;
        //}';
    }

    private function generateSetMethod(ClassMetadataInfo $metadata, string $fieldName, $typeHint = null, $defaultValue = null)
    {
        $fieldComment = $this->getCommentForField($metadata,$fieldName);
        $variableDefault = ($defaultValue !== null ) ? ' = '.$defaultValue : '';
        $methodName = 'set'.Inflector::classify($fieldName);
        $variableName = Inflector::camelize($fieldName);

        $methodTypeHint = null;
        $types          = Type::getTypesMap();
        $variableType   = $typeHint ? $this->getType($typeHint) : null;

        if ($typeHint && !isset($types[$typeHint])) {
            $variableType   =  '\\' . ltrim($variableType, '\\');
            $methodTypeHint =  '\\' . $typeHint . ' ';
        }

        //$mappedBy = null;
        //$inversedBy = null;
        //$targetEntity = null;
        //$sourceEntity = null;

        // association?
        if (array_key_exists($fieldName,$metadata->associationMappings)) {
            $mappedBy = $metadata->associationMappings[$fieldName]['mappedBy'];
            $inversedBy = $metadata->associationMappings[$fieldName]['inversedBy'];
            $targetEntity = $metadata->associationMappings[$fieldName]['targetEntity'];
            $sourceEntity = $metadata->associationMappings[$fieldName]['sourceEntity'];

            $associationType = $metadata->associationMappings[$fieldName]['type'];

            // collection association
            if ($targetEntity && $inversedBy) {

                $sync = '// there will be mapper for '.$targetEntity.($inversedBy ? ' inversedBy: '.$inversedBy : '')
                    .($mappedBy ? ' mappedBy: '.$mappedBy : '');

                $collectionField = ucfirst($inversedBy);
                $collectionItem = ucfirst(Inflector::singularize($inversedBy));

                $lines = [];

                if ( ClassMetadata::MANY_TO_ONE === $associationType ) {
                    $lines = [
                        '// synchronizing many-to-one association',
                        '$prev = $this->'.$variableName.';',
                        '$this->'.$variableName.' = $'.$variableName.';',
                        '',
                        '// synchronizing with inversed side '.$inversedBy,
                        'if ( $'.$variableName.' instanceof \\'.$targetEntity.' && !$'.$variableName.'->get'.$collectionField.'()->contains($this) ) {',
                        TAB.'/** @var \\'.$sourceEntity.' $this */',
                        TAB.'$'.$variableName.'->add'.$collectionItem.'($this);',
                        '} elseif ( $'.$variableName.' === null && $prev instanceof \\'.$targetEntity.' && $prev->get'.$collectionField.'()->contains($this) ) {',
                        TAB.'/** @var \\'.$sourceEntity.' $this */',
                        TAB.'$prev->remove'.$collectionItem.'($this);',
                        '}',
                    ];
                } elseif ( ClassMetadata::ONE_TO_ONE === $associationType ) {
                    $lines = [
                        '// synchronizing one-to-one association',
                        '$this->'.$variableName.' = $'.$variableName.';',
                        'if ($'.$variableName.' instanceof \\'.$targetEntity.' && $'.$variableName.'->get'.$collectionField.'() !== $this) {',
                        TAB.'/** @noinspection PhpParamsInspection */',
                        TAB.'$'.$variableName.'->set'.$collectionField.'($this);',
                        '}',
                    ];
                }

                $sync = implode("\n".TAB,$lines);

            } elseif ($targetEntity) {
                $sync = "\n".TAB."// There can be auto-sync with {$targetEntity} collection but no inversion info provided"
                    ."\n".TAB.'// In other words, '.Classes::short($targetEntity).' knows nothing about '.Classes::short($sourceEntity)
                ;
            }

        }

        //if (!array_key_exists($fieldName, $metadata->associationMappings)) {
        //    logger()->error( 'Not properly configured mapping for '.$fieldName );
        //}

        $lines = [
            '/**',
            ' * Set '.$variableName,
            ' * '.$fieldComment,
            ' * ',
            ' * @param '.($defaultValue === 'null' ? $variableType.'|null' : $variableType).' $'.$variableName,
            ' * @return static',
            ' */',
            'public function '.$methodName.'('.$methodTypeHint.' $'.$variableName.$variableDefault.')',
            '{',
            TAB.'$this->'.$fieldName.' = $'.$variableName.';',
            TAB.'return $this;',
            '}',
        ];

        return TAB.implode("\n".TAB, $lines);
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    protected function generateEntityStubMethods(ClassMetadataInfo $metadata)
    {
        $methods = [];

        foreach ($metadata->fieldMappings as $fieldMapping) {
            if (isset($fieldMapping['declaredField'], $metadata->embeddedClasses[$fieldMapping['declaredField']])) {
                continue;
            }

            $nullableField = $this->nullableFieldExpression($fieldMapping);

            // generating setter for field
            if ((!$metadata->isEmbeddedClass)
                && (!isset($fieldMapping['id']) || !$fieldMapping['id'] || $metadata->generatorType === ClassMetadataInfo::GENERATOR_TYPE_NONE)
            ) {
                $methods[] = $this->generateSetMethod($metadata, $fieldMapping['fieldName'], $fieldMapping['type'], $nullableField);
                //$methods[] = $code;
            }

            // generating getter for field
            $methods[] = $this->generateGetMethod($metadata, $fieldMapping['fieldName'], $fieldMapping['type'], $nullableField);
            //if ($code = $this->generateEntityStubMethod($metadata, 'get', $fieldMapping['fieldName'], $fieldMapping['type'], $nullableField)) {
            //    $methods[] = $code;
            //}
        }

        //foreach ($metadata->embeddedClasses as $fieldName => $embeddedClass) {
        //    if (isset($embeddedClass['declaredField'])) {
        //        continue;
        //    }
        //
        //    if ( ! $metadata->isEmbeddedClass || ! $this->embeddablesImmutable) {
        //        if ($code = $this->generateEntityStubMethod($metadata, 'set', $fieldName, $embeddedClass['class'])) {
        //            $methods[] = $code;
        //        }
        //    }
        //
        //    if ($code = $this->generateEntityStubMethod($metadata, 'get', $fieldName, $embeddedClass['class'])) {
        //        $methods[] = $code;
        //    }
        //}

        foreach ($metadata->associationMappings as $associationMapping) {
            if ($associationMapping['type'] & ClassMetadataInfo::TO_ONE) {

                // generating setter for association
                $nullable = $this->isAssociationIsNullable($associationMapping) ? 'null' : null;

                $methods[] = $this->generateSetMethod($metadata, $associationMapping['fieldName'], $associationMapping['targetEntity'], $nullable);
                $methods[] = $this->generateGetMethod($metadata, $associationMapping['fieldName'], $associationMapping['targetEntity'], $nullable);

                //if ($code = $this->generateEntityStubMethod($metadata, 'set', $associationMapping['fieldName'], $associationMapping['targetEntity'], $nullable)) {
                //    $methods[] = $code;
                //}
                //if ($code = $this->generateEntityStubMethod($metadata, 'get', $associationMapping['fieldName'], $associationMapping['targetEntity'], $nullable)) {
                //    $methods[] = $code;
                //}
            } elseif ($associationMapping['type'] & ClassMetadataInfo::TO_MANY) {
                // todo: not supported

                //if ($code = $this->generateEntityStubMethod($metadata, 'add', $associationMapping['fieldName'], $associationMapping['targetEntity'])) {
                //    $methods[] = $code;
                //}
                //if ($code = $this->generateEntityStubMethod($metadata, 'remove', $associationMapping['fieldName'], $associationMapping['targetEntity'])) {
                //    $methods[] = $code;
                //}
                //if ($code = $this->generateEntityStubMethod($metadata, 'get', $associationMapping['fieldName'], Collection::class)) {
                //    $methods[] = $code;
                //}
            }
        }

        return implode("\n\n", $methods);
    }

    /**
     * @param array $associationMapping
     *
     * @return bool
     */
    protected function isAssociationIsNullable(array $associationMapping)
    {
        if (isset($associationMapping['id']) && $associationMapping['id']) {
            return false;
        }

        if (isset($associationMapping['joinColumns'])) {
            $joinColumns = $associationMapping['joinColumns'];
        } else {
            //@todo there is no way to retrieve targetEntity metadata
            $joinColumns = [];
        }

        foreach ($joinColumns as $joinColumn) {
            if (isset($joinColumn['nullable']) && !$joinColumn['nullable']) {
                return false;
            }
        }

        return true;
    }

}