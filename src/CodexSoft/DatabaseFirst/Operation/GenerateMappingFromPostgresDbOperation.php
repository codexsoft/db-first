<?php
namespace CodexSoft\DatabaseFirst\Operation;

use \MartinGeorgiev\Doctrine\DBAL\Types as MartinGeorgievTypes;
use CodexSoft\Code\Helpers\Classes;
use CodexSoft\Code\Shortcuts;
use CodexSoft\DatabaseFirst\DoctrineOrmSchema;
use CodexSoft\OperationsSystem\Exception\OperationException;
use CodexSoft\OperationsSystem\Operation;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Mapping\Driver\DatabaseDriver;
use Doctrine\ORM\Tools\DisconnectedClassMetadataFactory;
use Symfony\Component\Filesystem\Filesystem;
use function CodexSoft\Code\str;
use const CodexSoft\Code\TAB;

/**
 * Class GenerateMappingOperation
 * todo: Write description — what this operation for
 * @method void execute() todo: change void to handle() method return type if other
 */
class GenerateMappingFromPostgresDbOperation extends Operation
{
    use DoctrineOrmSchemaAwareTrait;

    public const ID = 'e163ac5f-ec34-470e-b8ee-450eb8886453';

    protected const ERROR_PREFIX = 'GenerateMappingFromDatabaseOperation cannot be completed: ';

    private $joinDefaultArguments = [
        'name' => '',
        'referencedColumnName' => '',
        'nullable' => true,
        'unique' => false,
        'onDelete' => null,
        'columnDefinition' => null,
    ];

    /**
     * @var string
     * todo: it is just a proxy, maybe remove?
     */
    private $metaVar = '$metadata';

    /**
     * @var string
     * todo: it is just a proxy, maybe remove?
     */
    private $builderVar = '$mapper';

    /**
     * @return void
     * @throws OperationException
     */
    protected function validateInputData(): void
    {
        $this->assert($this->doctrineOrmSchema instanceof DoctrineOrmSchema);
    }

    /**
     * @return void
     * @throws OperationException
     */
    protected function handle(): void
    {
        $this->builderVar = $this->doctrineOrmSchema->builderVar;
        $this->metaVar = $this->doctrineOrmSchema->metaVar;

        Shortcuts::register();
        $em = $this->doctrineOrmSchema->getEntityManager();
        $databaseDriver = new DatabaseDriver($em->getConnection()->getSchemaManager());

        $em->getConfiguration()->setMetadataDriverImpl($databaseDriver);
        $databaseDriver->setNamespace($this->doctrineOrmSchema->getNamespaceModels().'\\');

        $cmf = new DisconnectedClassMetadataFactory();
        $cmf->setEntityManager($em);
        $allMetadata = $cmf->getAllMetadata();

        if (empty($allMetadata)) {
            throw $this->genericException('No Metadata Classes to process.');
        }

        $fs = new Filesystem;
        $fs->mkdir($this->doctrineOrmSchema->getPathToMapping());

        /** @var ClassMetadata $metadata */
        foreach ($allMetadata as $metadata) {
            $singularizedModelClass = $this->singularize($metadata->name);
            $tableName = $metadata->table['name'];

            echo sprintf("\n".'Processing table "%s"', $tableName);

            if (\in_array($tableName, $this->doctrineOrmSchema->skipTables, true)) {
                $this->getLogger()->notice(sprintf("\n".'Skipping table "%s"', $tableName));
                //echo sprintf("\n".'Skipping table "%s"', $tableName);
                continue;
            }

            foreach ($this->doctrineOrmSchema->skipTables as $tableToSkip) {
                if (str($tableToSkip)->endsWith('*') && str($tableName)->startsWith((string) str($tableToSkip)->removeRight('*'))) {
                    echo sprintf("\n".'Skipping table "%s" because of %s', $tableName, $tableToSkip);
                    continue 2;
                }
            }

            echo sprintf("\n".'Entity class "%s"', $singularizedModelClass);
            $file = $this->generateOutputFilePath($metadata);
            echo sprintf("\n".'Mapping file "%s"', $file);

            $customRepoClass = $this->doctrineOrmSchema->getNamespaceRepositories().'\\'.Classes::short($singularizedModelClass).'Repository';
            $metadata->customRepositoryClassName = $customRepoClass;

            //if ($metadata->customRepositoryClassName) {
            //    $code[] = $metaVar."->customRepositoryClassName = '" . $metadata->customRepositoryClassName . "';";
            //}

            //if ($metadata->discriminatorColumn) {
            //    $params = $this->generateArgumentsRespectingDefaultValues($metadata->discriminatorColumn,self::DISCRIMINATOR_COLUMN_DEFAULTS);
            //    $code[] = $this->builderVar.'->setDiscriminatorColumn('.implode(', ',$params).');';
            //}

            if (!$metadata->isIdentifierComposite && $generatorType = $this->_getIdGeneratorTypeString($metadata->generatorType)) {
                $code[] = $this->metaVar.'->setIdGeneratorType(ClassMetadataInfo::GENERATOR_TYPE_' . $generatorType . ');';
            }

            $builderShortClass = Classes::short($this->doctrineOrmSchema->metadataBuilderClass);

            $code = [
                '<?php',
                '',
                'use '.\Doctrine\DBAL\Types\Type::class.';',
                'use '.\Doctrine\ORM\Mapping\ClassMetadataInfo::class.';',
                "use \\{$this->doctrineOrmSchema->metadataBuilderClass};",
                '',
                //"/** @var \$metadata \\$builderShortClass */",
                '/** @var $metadata '.\Doctrine\ORM\Mapping\ClassMetadataInfo::class.' */',
                '',
                '/** @noinspection PhpUnhandledExceptionInspection */',
                "{$this->metaVar}->setInheritanceType(ClassMetadataInfo::INHERITANCE_TYPE_NONE);", // inheritance now is not supported
                "{$this->metaVar}->setIdGeneratorType(ClassMetadataInfo::GENERATOR_TYPE_SEQUENCE);",
                '',
                "{$this->builderVar} = new $builderShortClass({$this->metaVar});",
                "{$this->builderVar}->setCustomRepositoryClass('$customRepoClass');",
                "{$this->builderVar}->setTable('$tableName');",
            ];

            foreach ($metadata->fieldMappings as $field) {

                $fieldColumnName = $metadata->getColumnName($field['fieldName']);
                if (\in_array($tableName.'.'.$fieldColumnName, $this->doctrineOrmSchema->skipColumns, true)) {
                    continue;
                }

                //$fieldCode = $this->generateFieldCode($field);
                array_push($code, ...$this->generateFieldCode($field));
            }

            foreach ($metadata->associationMappings as $associationMappingName => $associationMapping) {

                echo "\nfound $associationMappingName";

                if (\in_array($tableName.'.'.$associationMappingName, $this->doctrineOrmSchema->skipColumns, true)) {
                    echo "\nskipped $tableName.$associationMappingName";
                    continue;
                }

                if ($associationMapping['type'] & ClassMetadataInfo::TO_ONE) {
                    try {
                        $associationColumnName = $metadata->getSingleAssociationJoinColumnName($associationMappingName);
                        if (\in_array($tableName.'.'.$associationColumnName, $this->doctrineOrmSchema->skipColumns, true)) {
                            continue;
                        }
                    } catch (\Doctrine\ORM\Mapping\MappingException $e) {
                        // log?
                    }
                }

                array_push($code, ...$this->generateAssociationCode($associationMappingName, $associationMapping, $tableName));
                //$this->generateAssociationCode($associationMappingName, $associationMapping, $tableName);
            }

            array_push($code, ...[
                '',
                "if (file_exists(\$_extraMappingInfoFile = __DIR__.'/../Extra/'.basename(__FILE__))) {",
                '    /** @noinspection PhpIncludeInspection */ include $_extraMappingInfoFile;',
                '}',
            ]);

            $fs->dumpFile($file, implode("\n", $code));
        }

        //if (($namespace = $input->getOption('namespace')) !== null) {
        //    $databaseDriver->setNamespace($namespace);
        //}
    }

    protected function generateFieldCode(array $field)
    {
        $code = [];

        $code[] = '';
        //$code[] = $this->builderVar."->createField('".$field['fieldName']."', '".$field['type']."')";
        $code[] = $this->builderVar."->createField('".$field['fieldName']."', ".$this->generateType($field['type']).')';

        $code[] = TAB."->columnName('".$field['columnName']."')";

        if (array_key_exists('columnDefinition',$field) && $field['columnDefinition'] ) {
            $code[] = TAB.'->columnDefinition('.var_export($field['columnDefinition'],true).')';
        }

        if (array_key_exists('nullable',$field) && $field['nullable'] === true ) {
            $code[] = TAB.'->nullable()';
        }

        if (array_key_exists('unique',$field) && $field['unique'] === true ) {
            $code[] = TAB.'->unique()';
        }

        if (array_key_exists('id',$field) && $field['id'] === true ) {
            $code[] = TAB.'->makePrimaryKey()';
        }

        if (array_key_exists('length',$field) && $field['length'] ) {
            $code[] = TAB.'->length('.var_export($field['length'],true).')';
        }

        if (array_key_exists('precision',$field) && $field['precision'] ) {
            $code[] = TAB.'->precision('.var_export($field['precision'],true).')';
        }

        if (array_key_exists('scale',$field) && $field['scale'] ) {
            $code[] = TAB.'->scale('.var_export($field['scale'],true).')';
        }

        if (array_key_exists('options',$field)) {
            foreach ((array) $field['options'] as $option => $value) {

                if ( $option === 'default' ) {

                    switch( $field['type'] ) {

                        case Type::SMALLINT:
                        case Type::INTEGER:
                        case Type::BIGINT:
                            $code[] = TAB."->option('".$option."',".( (int) $value ).')';
                            break;

                        case Type::DATE:
                        case Type::DATETIME:
                            //$fieldLines[] = TAB."->option('".$option."',".( (int) $value ).')';
                            break;

                        case Type::FLOAT:
                            $code[] = TAB."->option('".$option."',".var_export((float) $value,true).')';
                            break;

                        case Type::JSON:
                        case Type::JSON_ARRAY:
                        case Type::SIMPLE_ARRAY:
                        case Type::TARRAY:
                        case MartinGeorgievTypes\BigIntArray::TYPE_NAME:
                        case MartinGeorgievTypes\SmallIntArray::TYPE_NAME:
                        case MartinGeorgievTypes\IntegerArray::TYPE_NAME:
                        case MartinGeorgievTypes\JsonbArray::TYPE_NAME:
                        case MartinGeorgievTypes\TextArray::TYPE_NAME:
                        case 'varchar[]':
                        case '_int2':
                        case '_int4':
                        case '_int8':
                        case '_text':
                            if ($value === [] || $value === '{}' || $value === '[]') {
                                $code[] = TAB."->option('".$option."', [])";
                            } else {
                                $code[] = TAB."->option('".$option."',".var_export( $value, true ).')';
                            }
                            break;

                        default:
                            $code[] = TAB."->option('".$option."',".var_export($value,true).')';
                    }

                } else {
                    $code[] = TAB."->option('".$option."',".var_export($value,true).')';
                }

            }
        }

        $code[] = TAB.'->build();';

        return $code;
    }

    protected function generateAssociationCode(string $associationMappingName, array $associationMapping, string $fieldTableName): array
    {
        //$fieldTableName = $metadata->getTableName();

        // is cascading persist by default?
        if ($this->doctrineOrmSchema->cascadePersistAllRelationships) {
            $associationMapping['isCascadePersist'] = true;
        }

        // is cascading refresh by default?
        if ($this->doctrineOrmSchema->cascadeRefreshAllRelationships) {
            $associationMapping['isCascadeRefresh'] = true;
        }

        $cascadeLines = [];

        $cascade = array('remove', 'persist', 'refresh', 'merge', 'detach');
        foreach ($cascade as $key => $value) {
            if ( ! $associationMapping['isCascade'.ucfirst($value)]) {
                unset($cascade[$key]);
            }
        }

        if ( \count($cascade) === 5) {
            $cascadeLines[] = TAB.'->cascadeAll()';
        } else {
            if (\in_array('remove',$cascade,true)) {
                $cascadeLines[] = TAB.'->cascadeRemove()';
            }
            if (\in_array('persist',$cascade,true)) {
                $cascadeLines[] = TAB.'->cascadePersist()';
            }
            if (\in_array('refresh',$cascade,true)) {
                $cascadeLines[] = TAB.'->cascadeRefresh()';
            }
            if (\in_array('merge',$cascade,true)) {
                $cascadeLines[] = TAB.'->cascadeMerge()';
            }
            if (\in_array('detach',$cascade,true)) {
                $cascadeLines[] = TAB.'->cascadeDetach()';
            }
        }

        // fetches...

        $fetchLines = [];

        if ( isset($associationMapping['fetch']) ) {
            switch ($associationMapping['fetch']) {
                case ClassMetadata::FETCH_LAZY:
                    $fetchLines[] = TAB.'->fetchLazy()';
                    break;
                case ClassMetadata::FETCH_EAGER:
                    $fetchLines[] = TAB.'->fetchEager()';
                    break;
                case ClassMetadata::FETCH_EXTRA_LAZY:
                    $fetchLines[] = TAB.'->fetchExtraLazy()';
                    break;
            }
        }

        $fieldLines[] = '';

        if ($associationMapping['type'] & ClassMetadataInfo::TO_ONE) {

            // FOR OUR PROJECTS ALMOST ALL ASSOCIATIONS ARE MANYTOONE
            $importingEntityClass = $this->singularize($associationMapping['targetEntity']);

            //$importingEntityClass = $this->simplifyFullQualifiedModelName( $importingEntityClass );

            $fieldLines[] = $this->builderVar."->createManyToOne('".$associationMapping['fieldName']."', \\".$importingEntityClass.'::class)';

            if ( $associationMapping['inversedBy'] ) {
                $fieldLines[] = TAB."->inversedBy('".$associationMapping['inversedBy']."')";
            }

            if ( $associationMapping['isOwningSide'] && \array_key_exists('joinColumns',$associationMapping) ) {
                foreach( (array) $associationMapping['joinColumns'] as $joinColumn ) {

                    //$params = $this->joinColumnParametersHelper( $joinColumn );
                    //$params = $this->generateArgumentsRespectingDefaultValues($joinColumn,self::JOIN_DEFAULTS);
                    $params = $this->generateArgumentsRespectingDefaultValues($joinColumn, $this->joinDefaultArguments);
                    $fieldLines[] = TAB.'->addJoinColumn('.implode(', ',$params).')';

                }
            }

            if ( $associationMapping['mappedBy'] ) {
                $fieldLines[] = TAB."->mappedBy('".$associationMapping['mappedBy']."')";
            }

            if ( $associationMapping['orphanRemoval'] ) {
                $fieldLines[] = TAB.'->orphanRemoval()';
            }

            foreach((array) $cascadeLines as $cascadeLine) {
                $fieldLines[] = $cascadeLine;
            }

            foreach((array) $fetchLines as $fetchLine) {
                $fieldLines[] = $fetchLine;
            }

            $fieldLines[] = TAB.'->build();';

        } elseif ($associationMapping['type'] === ClassMetadataInfo::ONE_TO_MANY) {

            $fieldLines[] = $this->builderVar."->createOneToMany('".$associationMapping['fieldName']."',\\".$this->singularize($associationMapping['targetEntity']).'::class)';

            if ( array_key_exists('orderBy',$associationMapping) && $associationMapping['orderBy'] ) {
                $fieldLines[] = TAB.'->setOrderBy('.var_export($associationMapping['orderBy'],true).')';
            }

            if ( $associationMapping['mappedBy'] ) {
                $fieldLines[] = TAB."->mappedBy('".$associationMapping['mappedBy']."')";
            }

            if ( $associationMapping['orphanRemoval'] ) {
                $fieldLines[] = TAB.'->orphanRemoval()';
            }

            foreach((array) $cascadeLines as $cascadeLine) {
                $fieldLines[] = $cascadeLine;
            }

            foreach((array) $fetchLines as $fetchLine) {
                $fieldLines[] = $fetchLine;
            }

            $fieldLines[] = TAB.'->build();';

        } elseif ($associationMapping['type'] === ClassMetadataInfo::MANY_TO_MANY) {

            //$fieldLines[] = var_export($associationMapping,true).';'; // debug...

            $fieldLines[] = $this->builderVar."->createManyToMany('".$associationMapping['fieldName']."',\\".$this->singularize($associationMapping['targetEntity']).'::class)';

            if ( array_key_exists('joinTable',$associationMapping) && $associationMapping['joinTable'] ) {

                $joinTable = $associationMapping['joinTable'];
                $joinTableName = $joinTable['name'];
                $fieldLines[] = TAB."->setJoinTable('$joinTableName')";

                if ( array_key_exists('inverseJoinColumns',$joinTable) ) {
                    foreach ((array) $joinTable['inverseJoinColumns'] as $joinColumn) {

                        //$params = $this->joinColumnParametersHelper( $joinColumn );
                        //$params = $this->generateArgumentsRespectingDefaultValues($joinColumn,self::JOIN_DEFAULTS);
                        $params = $this->generateArgumentsRespectingDefaultValues($joinColumn, $this->joinDefaultArguments);
                        $fieldLines[] = TAB.'->addInverseJoinColumn('.implode(', ',$params).')';

                    }
                }

                if ( array_key_exists('joinColumns',$joinTable) ) {
                    foreach ((array) $joinTable['joinColumns'] as $joinColumn) {

                        //$params = $this->joinColumnParametersHelper( $joinColumn );
                        //$params = $this->generateArgumentsRespectingDefaultValues($joinColumn,self::JOIN_DEFAULTS);
                        $params = $this->generateArgumentsRespectingDefaultValues($joinColumn, $this->joinDefaultArguments);
                        $fieldLines[] = TAB.'->addJoinColumn('.implode(', ',$params).')';

                    }
                }

                if ( array_key_exists('orderBy',$associationMapping) && $associationMapping['orderBy'] ) {
                    $fieldLines[] = TAB.'->setOrderBy('.var_export($associationMapping['orderBy'],true).')';
                }

                if ( $associationMapping['mappedBy'] ) {
                    $fieldLines[] = TAB."->mappedBy('".$associationMapping['mappedBy']."')";
                }

                foreach((array) $cascadeLines as $cascadeLine) {
                    $fieldLines[] = $cascadeLine;
                }

                foreach((array) $fetchLines as $fetchLine) {
                    $fieldLines[] = $fetchLine;
                }

            }

            $fieldLines[] = TAB.'->build();';

        }

        return $fieldLines;
    }

    protected function generateType(string $type)
    {

        if (\array_key_exists($type, $this->doctrineOrmSchema->doctrineTypesMap)) {
            return 'Type::'.$this->doctrineOrmSchema->doctrineTypesMap[$type];
        }

        //if (\array_key_exists($type, self::DOCTRINE_TYPES_MAP)) {
        //    return 'Type::'.self::DOCTRINE_TYPES_MAP[$type];
        //}

        return var_export($type,true);
    }

    protected function generateOutputFilePath(ClassMetadataInfo $metadata)
    {
        return $this->doctrineOrmSchema->getPathToMapping().'/'.str_replace('\\', '.', $this->singularize($metadata->name)).'.php';
    }

    protected function singularize($plural) {
        return \Doctrine\Common\Inflector\Inflector::singularize($plural);
    }

    protected function _getIdGeneratorTypeString($type)
    {
        switch ($type) {
            case ClassMetadataInfo::GENERATOR_TYPE_AUTO:
                return 'AUTO';

            case ClassMetadataInfo::GENERATOR_TYPE_SEQUENCE:
                return 'SEQUENCE';

            case ClassMetadataInfo::GENERATOR_TYPE_TABLE:
                return 'TABLE';

            case ClassMetadataInfo::GENERATOR_TYPE_IDENTITY:
                return 'IDENTITY';

            case ClassMetadataInfo::GENERATOR_TYPE_UUID:
                return 'UUID';

            case ClassMetadataInfo::GENERATOR_TYPE_CUSTOM:
                return 'CUSTOM';
        }
    }

    protected function generateArgumentsRespectingDefaultValues( array $sendingValues, $defaultValues )
    {

        $values = [];

        foreach( $defaultValues as $existingValue => $defaultValue ) {
            $values[$existingValue] = array_key_exists($existingValue,$sendingValues)
                ? $sendingValues[$existingValue]
                : $defaultValue;
        }

        $reversedKeys = array_reverse(array_keys($defaultValues));

        foreach( $reversedKeys as $key ) {
            if ($values[$key] === $defaultValues[$key])
                unset($values[$key]);
        }

        $exportValues = [];
        foreach ($values as $value) {
            $exportValues[] = var_export($value,true);
        }

        return $exportValues;
    }

}