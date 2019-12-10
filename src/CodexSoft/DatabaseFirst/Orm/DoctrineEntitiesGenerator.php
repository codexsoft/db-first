<?php

namespace CodexSoft\DatabaseFirst\Orm;

use CodexSoft\Code\Classes\Classes;
use CodexSoft\Code\Traits\Loggable;
use CodexSoft\DatabaseFirst\DoctrineOrmSchema;
use CodexSoft\DatabaseFirst\Helpers\Doctrine;
use Doctrine\Common\Inflector\Inflector;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\Tools\EntityGenerator;
use function Stringy\create as str;

use const CodexSoft\Shortcut\TAB;

/**
 * todo table comment (it seems it will be very hacky in doctrine...)
 * todo association comment (it seems it will be very hacky in doctrine...)
 * todo: consider split MODE_SINGLE_CLASS, MODE_CLASS_USING_TRAIT, MODE_TRAIT_FOR_CLASS into 3 commands
 * @deprecated entities are now generated via `models` command
 */
class DoctrineEntitiesGenerator extends EntityGenerator
{

    use Loggable;

    public const MODE_SINGLE_CLASS = 1;
    public const MODE_CLASS_USING_TRAIT = 2;
    public const MODE_TRAIT_FOR_CLASS = 3;

    protected $regenerateEntityIfExists = true;
    protected $updateEntityIfExists = false;

    protected $writeDefaultNulls = false;

    /**
     * Used for Class-table inheritance feature
     * @var array
     */
    protected $inheritanceMap = [];

    protected $generateAsTraits = false;
    protected $generateWithRepoAccess = true;
    protected $mode = self::MODE_SINGLE_CLASS;

    protected $em;

    /** @var array comments for all columns in db, in format [ <table>.<column> => <comment> ] */
    protected $columnComments = [];

    /** @var DoctrineOrmSchema */
    private $domainSchema;

    /**
     * @param DoctrineOrmSchema $domainSchema
     *
     * @return static
     */
    public function setDomainSchema(DoctrineOrmSchema $domainSchema): self
    {
        $this->domainSchema = $domainSchema;
        return $this;
    }

    /**
     * @param array $columnComments
     *
     * @return DoctrineEntitiesGenerator
     */
    public function setColumnComments(array $columnComments): DoctrineEntitiesGenerator
    {
        $this->columnComments = $columnComments;
        return $this;
    }

    /**
     * @return EntityManager
     */
    public function getEm(): EntityManager
    {
        return $this->em;
    }

    /**
     * @param EntityManager $em
     */
    public function setEm(EntityManager $em): void
    {
        $this->em = $em;
    }

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

    /**
     * @var string
     */
    protected static $getMethodTemplate =
'/**
 * <description>
 * <fieldComment>
 *
 * @return <variableType><variableExtraType>
 */
public function <methodName>()
{
<spaces>return $this-><fieldName>;
}';

    /**
     * @var string
     */
    protected static $setMethodTemplate =
'/**
 * <description>
 * <fieldComment>
 *
 * @param <variableType> $<variableName>
 * @return static
 */
public function <methodName>(<methodTypeHint>$<variableName><variableDefault>)
{
<spaces><sync>
<spaces>$this-><fieldName> = $<variableName>;
<spaces>return $this;
}';

    /**
     * @var string
     */
    protected static $addMethodTemplate =
'/**
 * <description>
 *
 * @param <variableType> $<variableName>
 *
 * @return static
 */
public function <methodName>(<methodTypeHint>$<variableName>)
{
<spaces>$this-><fieldName>[] = $<variableName>;
<spaces><sync>
<spaces>return $this;
}';

    /**
     * @var string
     */
    protected static $removeMethodTemplate =
'/**
 * <description>
 *
 * @param <variableType> $<variableName>
 * 
 * @return static
 */
public function <methodName>(<methodTypeHint>$<variableName>)
{
<spaces>$this-><fieldName>->removeElement($<variableName>);
<spaces><sync>
<spaces>return $this;
}';

    /**
     * @param array $inheritanceMap
     *
     * @return DoctrineEntitiesGenerator
     */
    public function setInheritanceMap( array $inheritanceMap ): DoctrineEntitiesGenerator {
        $this->inheritanceMap = $inheritanceMap;
        return $this;
    }

    /**
     * @param int $mode
     *
     * @return DoctrineEntitiesGenerator
     */
    public function setMode( int $mode ): DoctrineEntitiesGenerator
    {
        $this->mode = $mode;

        switch ($this->mode) {

            case self::MODE_TRAIT_FOR_CLASS:
                $this->generateAsTraits = true;
                $this->extension = 'Trait.php';
                $this->regenerateEntityIfExists = true;
                $this->updateEntityIfExists = true;
                break;

            case self::MODE_CLASS_USING_TRAIT:
                $this->generateAsTraits = false;
                $this->extension = '.php';
                $this->regenerateEntityIfExists = false;
                $this->updateEntityIfExists = false;
                break;

            case self::MODE_SINGLE_CLASS:
            default:
                $this->generateAsTraits = false;
                $this->extension = '.php';
                $this->regenerateEntityIfExists = false;
                $this->updateEntityIfExists = false;

        }

        return $this;
    }

    /**
     * @param bool $writeDefaultNulls
     *
     * @return DoctrineEntitiesGenerator
     */
    public function setWriteDefaultNulls( bool $writeDefaultNulls ): DoctrineEntitiesGenerator
    {
        $this->writeDefaultNulls = $writeDefaultNulls;
        return $this;
    }

    /**
     * @return bool
     */
    public function isWriteDefaultNulls(): bool
    {
        return $this->writeDefaultNulls;
    }

    /**
     * @param array             $associationMapping
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    protected function generateAssociationMappingPropertyDocBlock(array $associationMapping, ClassMetadataInfo $metadata)
    {
        $result = parent::generateAssociationMappingPropertyDocBlock( $associationMapping, $metadata );
        $fieldComment = $this->getCommentForField($metadata,$associationMapping['fieldName']);
        if ($fieldComment) {
            $lines = explode( "\n", $result );
            $line1 = array_shift( $lines );
            $line2 = array_shift( $lines );
            array_unshift( $lines, '     * '.$fieldComment );
            array_unshift( $lines, $line2 );
            array_unshift( $lines, $line1 );
            $result = implode( "\n", $lines );
        }

        return $result;
    }

    /**
     * adds field comments
     *
     * @param array $fieldMapping
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    protected function generateFieldMappingPropertyDocBlock( array $fieldMapping, ClassMetadataInfo $metadata ) {

        $result = parent::generateFieldMappingPropertyDocBlock( $fieldMapping, $metadata );
        $fieldComment = $this->getCommentForField($metadata,$fieldMapping['fieldName']);
        if ($fieldComment) {
            $lines = explode( "\n", $result );
            $line1 = array_shift( $lines );
            $line2 = array_shift( $lines );
            array_unshift( $lines, '     * '.$fieldComment );
            array_unshift( $lines, $line2 );
            array_unshift( $lines, $line1 );
            $result = implode( "\n", $lines );
        }

        return $result;
    }

    protected function shortClass( $NamespacedClassName ): string
    {
        return Classes::short($NamespacedClassName);
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    protected function generateEntityClassName( ClassMetadataInfo $metadata )
    {
        $className = $this->getClassName( $metadata );

        if ( $this->generateAsTraits ) {
            return 'trait '.$this->getClassName($metadata).'Trait';
        }

        $namespacedClassName = $this->getNamespace( $metadata ).'\\'.$className;

        $export = 'class '.$this->getClassName( $metadata );

        if ( !array_key_exists( $namespacedClassName, $this->inheritanceMap ) ) {
            return $export;
        }

        $parentClassName = $this->inheritanceMap[$namespacedClassName];
        return 'class '.$className.' extends '.Classes::short($parentClassName);
    }

    protected function generateDqlFieldNameHelpers(ClassMetadataInfo $metadata): string
    {
        $lines = ['','// to avoid human mistakes when using entity properties names...',''];

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
        $lines = ['','// to avoid human mistakes when using table and columns names...',''];
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
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     * @throws \Exception
     */
    protected function generateEntityBody(ClassMetadataInfo $metadata)
    {

        $sortedMetadata = clone $metadata;

        if (!ksort($sortedMetadata->fieldMappings)) {
            throw new \RuntimeException("Failed to sort fieldMappings for entity $sortedMetadata->name");
        }

        if (!ksort($sortedMetadata->associationMappings)) {
            throw new \RuntimeException("Failed to sort associationMappings for entity $sortedMetadata->name");
        }

        $filteredMetadata = clone $sortedMetadata;

        $code = [];

        if ( $this->mode !== self::MODE_CLASS_USING_TRAIT ) {

            echo "\n TRAIT/SINGLE ".$filteredMetadata->name;

            // если колонка-дискриминатор присутствует в исследуемой сущности...
            if ( \array_key_exists(ModelMetadataInheritanceBuilder::DISCRIMINATOR_COLUMN, $filteredMetadata->fieldMappings) ) {

                // если среди корней CTI-иерархий имеется исследуемая сущность
                if ( \array_key_exists($filteredMetadata->name,ModelMetadataInheritanceBuilder::MAPPING) ) {

                    // убираем из списка полей для генерации трейта поле дискриминатора
                    unset($filteredMetadata->fieldMappings[ModelMetadataInheritanceBuilder::DISCRIMINATOR_COLUMN]);

                }

            }

            //if ( array_key_exists(ModelMetadataBuilder::DISCRIMINATOR_COLUMN, $filteredMetadata->fieldMappings) ) {
            //    if ( array_key_exists($filteredMetadata->name,ModelMetadataBuilder::DISCRIMINATORS) )
            //        unset($filteredMetadata->fieldMappings[ModelMetadataBuilder::DISCRIMINATOR_COLUMN]);
            //}

            /**
             * using helper traits if appliable
             * todo: these helpers should be removed or made optional
             */
            /** @noinspection MissingIssetImplementationInspection */
            if (isset($filteredMetadata->dbfirst_mapping_helpers)) {

                $code[] = '';

                if ($this->generateWithRepoAccess) {
                    $code[] = TAB.'use \\'.RepoStaticAccessTrait::class.';';
                }

                /** @noinspection PhpUndefinedFieldInspection */
                $filteredMetadata->dbfirst_mapping_helpers = array_unique($filteredMetadata->dbfirst_mapping_helpers);

                /** @noinspection PhpUndefinedFieldInspection */
                foreach( (array) $filteredMetadata->dbfirst_mapping_helpers as $helper) {

                    $code[] = TAB.'use \\'.ModelMetadataInheritanceBuilder::HELPER_TO_TRAIT[$helper].';';

                    switch($helper) {
                        case ModelMetadataInheritanceBuilder::HELPER_CREATION:
                            unset($filteredMetadata->fieldMappings['createdAt']);
                            unset($filteredMetadata->associationMappings['createdBy']);
                            break;
                        case ModelMetadataInheritanceBuilder::HELPER_UPDATES:
                            unset($filteredMetadata->fieldMappings['updatedAt']);
                            unset($filteredMetadata->associationMappings['updatedBy']);
                            break;
                        case ModelMetadataInheritanceBuilder::HELPER_DELETES:
                            unset($filteredMetadata->fieldMappings['deletedAt']);
                            unset($filteredMetadata->associationMappings['deletedBy']);
                            break;
                        case ModelMetadataInheritanceBuilder::HELPER_ID:
                            unset($filteredMetadata->fieldMappings['id']);
                            unset($filteredMetadata->associationMappings['id']);
                            break;
                    }

                }

            }

            $code[] = '';

            $fieldMappingProperties = $this->generateEntityFieldMappingProperties($filteredMetadata);
            $embeddedProperties = $this->generateEntityEmbeddedProperties($filteredMetadata);
            $associationMappingProperties = $this->generateEntityAssociationMappingProperties($filteredMetadata);
            $stubMethods = $this->generateEntityStubMethods ? $this->generateEntityStubMethods($filteredMetadata) : null;
            $lifecycleCallbackMethods = $this->generateEntityLifecycleCallbackMethods($filteredMetadata);

            if ($fieldMappingProperties) {
                $code[] = $fieldMappingProperties;
            }

            if ($embeddedProperties) {
                $code[] = $embeddedProperties;
            }

            if ($associationMappingProperties) {
                $code[] = $associationMappingProperties;
            }

            $code[] = $this->generateEntityConstructor($filteredMetadata);

            if ($stubMethods) {
                $code[] = $stubMethods;
            }

            if ($lifecycleCallbackMethods) {
                $code[] = $lifecycleCallbackMethods;
            }

            $code[] = $this->generateDqlFieldNameHelpers($sortedMetadata);
            $code[] = $this->generateSqlFieldNameHelpers($sortedMetadata);

        } else {
            $code[] = '';
            if ($this->mode === self::MODE_CLASS_USING_TRAIT ) {
                $code[] = TAB.'use \\'.$filteredMetadata->namespace.'\\Generated\\'.Classes::short($filteredMetadata->name).'Trait;';
            }
        }

        return implode("\n", $code);
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    protected function generateEntityConstructor(ClassMetadataInfo $metadata)
    {

        $collections = [];

        foreach ($metadata->associationMappings as $mapping) {
            if ($mapping['type'] & ClassMetadataInfo::TO_MANY) {
                $collections[] = '$this->'.$mapping['fieldName'].' = new \\'.\Doctrine\Common\Collections\ArrayCollection::class.'();';
            }
        }

        return $this->prefixCodeWithSpaces(str_replace( '<collections>', implode("\n".$this->spaces, $collections), 'public function constructor'.$this->getClassName( $metadata ).'Trait()
{
<spaces><collections>
}
'));

    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function generateEntityDocBlock(ClassMetadataInfo $metadata)
    {
        if ($this->generateAsTraits) {
            return '';
        }

        $tableComment = Doctrine::getCommentForTable($this->getEm()->getConnection(),$metadata->table['name']);

        //$customRepoClass = Constants::NAMESPACE_REPOSITORIES.'\\'.$this->getClassName($metadata).'Repository';
        $customRepoClass = $this->domainSchema->getNamespaceRepositories().'\\'.$this->getClassName($metadata).'Repository';

        $lines = [];
        $lines[] = '/**';
        $lines[] = ' * '.$tableComment;
        $lines[] = ' * ' . $this->getClassName($metadata);

        if ($this->generateWithRepoAccess)
            $lines[] = ' * @method static \\'.$customRepoClass.' repo(\\'.EntityManagerInterface::class.' $em = null)';

        $lines[] = ' * @Doctrine\ORM\Mapping\Entity(repositoryClass="'.$customRepoClass.'")';

        if ($this->generateAnnotations) {
            $lines[] = ' *';

            $methods = [
                'generateTableAnnotation',
                'generateInheritanceAnnotation',
                'generateDiscriminatorColumnAnnotation',
                'generateDiscriminatorMapAnnotation',
                'generateEntityAnnotation',
            ];

            foreach ($methods as $method) {
                $code = $this->$method($metadata);
                if ($code) {
                    $lines[] = ' * ' . $code;
                }
            }

            if (isset($metadata->lifecycleCallbacks) && $metadata->lifecycleCallbacks) {
                $lines[] = ' * @' . $this->annotationsPrefix . 'HasLifecycleCallbacks';
            }
        }

        $lines[] = ' */';

        return implode("\n", $lines);
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    protected function generateEntityNamespace(ClassMetadataInfo $metadata)
    {
        if ($this->hasNamespace($metadata)) {
            if ($this->generateAsTraits) {
                return 'namespace ' . $this->getNamespace($metadata) .'\\Generated;';
            }
            return 'namespace ' . $this->getNamespace($metadata) .';';
        }

        return null;

    }

    /**
     * Generates and writes entity class to disk for the given ClassMetadataInfo instance.
     *
     * @param ClassMetadataInfo $metadata
     * @param string            $outputDirectory
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    public function writeEntityClass(ClassMetadataInfo $metadata, $outputDirectory)
    {

        $path1 = str_replace('\\', '/', $metadata->namespace).'/Generated/'.Classes::short($metadata->name);
        $traitFilePath = $outputDirectory . '/' . $path1 . $this->extension;
        $modelFilePath = $outputDirectory . '/' . str_replace('\\', DIRECTORY_SEPARATOR, $metadata->name) . '.php';
        unset($path1);

        if ($this->generateAsTraits) {
            $path = $traitFilePath;
        } else {
            $path = $modelFilePath;
        }


        $dir = \dirname($path);

        if ( !mkdir( $dir, 0775, true ) && !is_dir( $dir ) ) {
            throw new \RuntimeException( sprintf( 'Directory "%s" was not created', $dir ) );
        }

        $this->isNew = !file_exists($path) || (file_exists($path) && $this->regenerateEntityIfExists);

        if ( $this->isNew ) {
            $this->staticReflection[$metadata->name] = ['properties' => [], 'methods' => []];
        } else {
            $this->parseTokensInEntityFile(file_get_contents($path));
        }

        if ($this->backupExisting && file_exists($path)) {
            $backupPath = \dirname($path) . DIRECTORY_SEPARATOR . basename($path) .'~';
            if (!copy($path, $backupPath)) {
                throw new \RuntimeException( 'Attempt to backup overwritten entity file but copy operation failed.' );
            }
        }

        /**
         * While generating trait, somewhere in internals doctrine tries to autoload Entity class.
         * If it is exists, and entity class already uses this trait, fatal error occures.
         * So we moving entity class file, if it exists, to temp directory.
         */

        // If entity doesn't exist or we're re-generating the entities entirely
        if ($this->isNew) {
            file_put_contents($path, $this->generateEntityClass($metadata));
            // If entity exists and we're allowed to update the entity class
        } elseif ( ! $this->isNew && $this->updateEntityIfExists) {
            file_put_contents($path, $this->generateUpdatedEntityClass($metadata, $path));
        }
        chmod($path, 0664);

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

    /**
     * @param ClassMetadataInfo $metadata
     * @param string            $type
     * @param string            $fieldName
     * @param string|null       $typeHint
     * @param string|null       $defaultValue
     *
     * @return string
     */
    protected function generateEntityStubMethod(ClassMetadataInfo $metadata, $type, $fieldName, $typeHint = null, $defaultValue = null)
    {
        $fieldComment = $this->getCommentForField($metadata,$fieldName);

        $methodName = $type . Inflector::classify($fieldName);
        $variableName = Inflector::camelize($fieldName);
        if ( \in_array($type, ['add', 'remove'] )) {
            $methodName = Inflector::singularize($methodName);
            $variableName = Inflector::singularize($variableName);
        }

        // todo: maybe this is reason why in some cases trait generates as empty???
        if ($this->hasMethod($methodName, $metadata)) {
            return '';
        }
        $this->staticReflection[$metadata->name]['methods'][] = strtolower($methodName);

        $var = sprintf('%sMethodTemplate', $type);
        $template = static::$$var;

        $methodTypeHint = null;
        $types          = Type::getTypesMap();
        $variableType   = $typeHint ? $this->getType($typeHint) : null;

        if ($typeHint && ! isset($types[$typeHint])) {
            $variableType   =  '\\' . ltrim($variableType, '\\');
            $methodTypeHint =  '\\' . $typeHint . ' ';
        }

        $sync = '';
        $variableExtraType = '';

        // enable auto-sync for inversed-mapped fields
        if ( $type === 'add' ) {

            $mappedBy = $metadata->associationMappings[$fieldName]['mappedBy'];
            if ($mappedBy) {
                $sync = "if (\${$variableName}->get".ucfirst($mappedBy)."() !== \$this) {\n".TAB.TAB."/** @noinspection PhpParamsInspection */\n".TAB.TAB."\${$variableName}->set".ucfirst($mappedBy)."(\$this); \n".TAB.'}';
            }

        } elseif ($type === 'remove') {

            $mappedBy = $metadata->associationMappings[$fieldName]['mappedBy'];
            if ($mappedBy) {
                $lines = [
                    'if ($'.$variableName.'->get'.ucfirst($mappedBy).'() === $this) $'.$variableName.'->set'.ucfirst($mappedBy).'(null);'
                ];
                $sync = 'if ($'.$variableName.'->get'.ucfirst($mappedBy).'() === $this) $'.$variableName.'->set'.ucfirst($mappedBy).'(null);';
            }

        } elseif (str($methodName)->startsWith('set')) {

            $mappedBy = null;
            $inversedBy = null;
            $targetEntity = null;
            $sourceEntity = null;

            if ( array_key_exists($fieldName,$metadata->associationMappings) ) {
                $mappedBy = $metadata->associationMappings[$fieldName]['mappedBy'];
                $inversedBy = $metadata->associationMappings[$fieldName]['inversedBy'];
                $targetEntity = $metadata->associationMappings[$fieldName]['targetEntity'];
                $sourceEntity = $metadata->associationMappings[$fieldName]['sourceEntity'];
            }

            if (!array_key_exists($fieldName,$metadata->associationMappings)) {
                $this->logger->error( 'Not properly configured mapping for '.$fieldName );
            }

            $associationType = $metadata->associationMappings[$fieldName]['type'];

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
                    ."\n".TAB."// In other words, {$this->shortClass($targetEntity)} knows nothing about {$this->shortClass($sourceEntity)}"
                ;
            }

        } elseif ( str($methodName)->startsWith('get')) {

            if ( ClassMetadata::ONE_TO_MANY === $metadata->associationMappings[$fieldName]['type'] ) {

                $mappedBy = null;
                $inversedBy = null;
                $targetEntity = null;
                $sourceEntity = null;

                if ( array_key_exists($fieldName,$metadata->associationMappings) ) {
                    $variableExtraType = '|\\'.$targetEntity.'[] ';
                }

            }

        }

        if (($type === 'get') && (\array_key_exists($fieldName,$metadata->associationMappings) || $variableType === '\\'.\DateTime::class) ) {
            $entityClassName = $this->getClassName( $metadata );
            $exceptionMessageVar = ($fieldName === 'message') ? '$msg' : '$message';
            $getMethodTemplate = [
                '/**',
                ' * '.ucfirst($type).' '.$variableName,
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
                ' * '.ucfirst($type).' not-null '.$variableName.' or throw exception',
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
            return $this->prefixCodeWithSpaces(implode("\n",$getMethodTemplate));
        }

        if ($this->writeDefaultNulls) {
            $variableDefault = ($defaultValue !== null ) ? ' = '.$defaultValue : '';
        } else {
            $variableDefault = \in_array($defaultValue,[null,'null'],true) ? '' : ' = '.$defaultValue;
            $methodTypeHint = ($defaultValue === 'null' && ($methodTypeHint !== '' && $methodTypeHint !== null) ) ? '?'.$methodTypeHint : $methodTypeHint;
        }


        $replacements = [
            '<description>'       => ucfirst( $type ).' '.$variableName,
            '<entity>'            => $this->getClassName( $metadata ),
            '<fieldComment>'      => $fieldComment,
            '<fieldName>'         => $fieldName,
            '<methodName>'        => $methodName,
            '<methodTypeHint>'    => $methodTypeHint,
            '<sync>'              => $sync,
            '<variableDefault>'   => $variableDefault,
            '<variableExtraType>' => $variableExtraType,
            '<variableName>'      => $variableName,
            '<variableType>'      => $defaultValue === 'null' ? $variableType.'|null' : $variableType,
        ];

        $method = str_replace(
            \array_keys($replacements),
            \array_values($replacements),
            $template
        );

        return $this->prefixCodeWithSpaces($method);
    }

}
