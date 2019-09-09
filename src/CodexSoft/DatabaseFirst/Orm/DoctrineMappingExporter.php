<?php

namespace CodexSoft\DatabaseFirst\Orm;

use CodexSoft\DatabaseFirst\DoctrineOrmSchema;
use \MartinGeorgiev\Doctrine\DBAL\Types as MartinGeorgievTypes;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use function CodexSoft\Code\str;
use const CodexSoft\Code\TAB;

/**
 * Created by PhpStorm.
 * User: dx
 * Date: 10.12.17
 * Time: 5:26
 */

/**
 * @deprecated should be rewrited?
 */
class DoctrineMappingExporter extends BaseDoctrineMappingExporter
{

    protected $cascadePersistAllRelationships = true;
    protected $cascadeRefreshAllRelationships = true;
    protected $addCommentsToExport = false;
    protected $entityHasParent = false;
    protected $entityHasChildren = false;
    protected $singularizedEntityClassName;
    protected $helpers = [];

    /** @var string where should custom hooks for mapping be placed */
    protected $extraPath = '/../Extra'; // todo: $dbConfig must be set.

    //protected $entitiesNamespace = Constants::NAMESPACE_MODELS;
    protected $entitiesNamespace; // todo: $dbConfig must be set.
    protected $parentEntitiesNamespace;

    /** @var DoctrineOrmSchema */
    private $dbConfig; // todo: $dbConfig must be set.

    /**
     * @param DoctrineOrmSchema $dbConfig
     *
     * @return static
     */
    public function setDbConfig(DoctrineOrmSchema $dbConfig): self
    {
        $this->dbConfig = $dbConfig;
        return $this;
    }

    protected function simplifyFullQualifiedModelName( $importingEntityClass )
    {

        if ( str($importingEntityClass)->startsWith($this->entitiesNamespace.'\\')) {
            return (string) str($importingEntityClass)->removeLeft($this->parentEntitiesNamespace.'\\');
        }

        return '\\'.$importingEntityClass;

    }

    protected function shortClass( $NamespacedClassName )
    {

        $NamespacedClassName = str_replace( "\\", '/', $NamespacedClassName );
        $NamespacedClassName = basename( $NamespacedClassName );
        return basename( $NamespacedClassName );

    }

    protected function injectHelperByAssoc(ClassMetadataInfo $metadata, $currentField,$fieldCheck,$assocCheck,$helper)
    {

        if ( $currentField === $assocCheck ) {
            if (array_key_exists($fieldCheck,$metadata->fieldMappings)) {
                if ( !$this->entityHasParent ) {
                    $this->helpers[] = $helper;
                }
                return true;
            }
        }

        return false;

    }

    protected function injectHelperByField(ClassMetadataInfo $metadata, $currentField,$fieldCheck,$assocCheck,$helper)
    {

        if ( $currentField === $fieldCheck && \array_key_exists( $assocCheck, $metadata->associationMappings ) ) {
            $this->helpers[] = $helper;
            //if ( !$this->entityHasParent ) {
            //    $this->helpers[] = $helper;
            //}
            return true;
        }

        return false;

    }

    /**
     * Converts a single ClassMetadata instance to the exported format
     * and returns it.
     *
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    public function exportClassMetadata(ClassMetadataInfo $metadata)
    {

        // skip some tables
        if ( \in_array( $metadata->table['name'], ModelMetadataBuilder::SKIP_TABLES, true ) ) {
            return null;
        }

        if (!ksort($metadata->fieldMappings)) {
            throw new \RuntimeException("Failed to sort fieldMappings for entity $metadata->name");
        }

        if (!ksort($metadata->associationMappings)) {
            throw new \RuntimeException("Failed to sort associationMappings for entity $metadata->name");
        }

        $this->helpers = [];
        $this->singularizedEntityClassName = $this->singularizer($metadata->name);
        $this->entityHasParent = ModelMetadataBuilder::hasParentEntity($this->singularizedEntityClassName);
        $this->entityHasChildren = ModelMetadataBuilder::hasChildEntities($this->singularizedEntityClassName);

        //$this->entitiesNamespace = $this->domainSchema->getNamespaceModels();
        $this->entitiesNamespace = 'App\\Model'; // todo!!! how to set this?

        $this->parentEntitiesNamespace = str_replace('\\', '/', $this->entitiesNamespace);
        $this->parentEntitiesNamespace = \dirname($this->parentEntitiesNamespace);
        $this->parentEntitiesNamespace = str_replace('/','\\', $this->parentEntitiesNamespace);

        $metaVar = '$metadata';
        $builderVar = '$mapper';

        $lines = [
            '<?php',
            '',
            'use '.Type::class.';',
            'use '.ClassMetadataInfo::class.';',
            'use '.ModelMetadataBuilder::class.';',
            //'use '.$this->entitiesNamespace.';',
            '',
            '/** @var '.$metaVar.' '.ClassMetadataInfo::class.' */',
            '',
        ];

        if ( $this->entityHasParent || $this->entityHasChildren ) {
            $metadata->inheritanceType = ClassMetadataInfo::INHERITANCE_TYPE_JOINED;
        }

        if ($metadata->isMappedSuperclass) {
            $lines[] = $metaVar.'->isMappedSuperclass = true;';
        }

        if ($metadata->inheritanceType) {
            $lines[] = '/** @noinspection PhpUnhandledExceptionInspection */';
            $lines[] = $metaVar.'->setInheritanceType(ClassMetadataInfo::INHERITANCE_TYPE_' . $this->_getInheritanceTypeString($metadata->inheritanceType) . ');';
        }

        if ( ! $metadata->isIdentifierComposite && $generatorType = $this->_getIdGeneratorTypeString($metadata->generatorType)) {
            $lines[] = $metaVar.'->setIdGeneratorType(ClassMetadataInfo::GENERATOR_TYPE_' . $generatorType . ');';
        }

        if ( $this->entityHasParent ) {
            $parentClasses = ModelMetadataBuilder::calculateParentClasses($this->singularizedEntityClassName);
            $lines[] = $metaVar.'->setParentClasses([';
            foreach((array)$parentClasses as $parentClass) {
                $lines[] = TAB.$this->simplifyFullQualifiedModelName( $parentClass ).'::class,';
            }
            $lines[] = ']);';
        }

        if ( $this->entityHasChildren ) {
            $lines[] = $metaVar.'->setSubclasses([';
            foreach( (array) ModelMetadataBuilder::calculateChildClasses($this->singularizedEntityClassName) as $childClass ) {
                //$lines[] = TAB.$childClass.'::class,';
                $lines[] = TAB.$this->simplifyFullQualifiedModelName( $childClass ).'::class,';
            }
            $lines[] = ']);';

        }

        if ($metadata->customRepositoryClassName) {
            $lines[] = $metaVar."->customRepositoryClassName = '" . $metadata->customRepositoryClassName . "';";
        }

        $lines[] = '';
        $lines[] = $builderVar.' = new '.$this->shortClass( ModelMetadataBuilder::class).'('.$metaVar.');';
        $lines[] = $builderVar."->setTable('{$metadata->table['name']}');";

        //if ($metadata->discriminatorColumn) {
        //    $params = $this->generateArgumentsRespectingDefaultValues($metadata->discriminatorColumn,self::DISCRIMINATOR_COLUMN_DEFAULTS);
        //    $lines[] = $builderVar."->setDiscriminatorColumn(".join(', ',$params).");";
        //}

        if ( !$metadata->discriminatorMap ) {
            // если среди корней CTI-иерархий имеется исследуемая сущность
            if ( array_key_exists($this->singularizedEntityClassName,ModelMetadataBuilder::MAPPING) ) {
                $metadata->discriminatorMap = ModelMetadataBuilder::MAPPING[$this->singularizedEntityClassName];
            }
        }

        //if ( !$metadata->discriminatorMap ) {
        //    if ( array_key_exists($this->singularizedEntityClassName,ModelMetadataBuilder::DISCRIMINATORS) )
        //        $metadata->discriminatorMap = ModelMetadataBuilder::DISCRIMINATORS[$this->singularizedEntityClassName][0];
        //}

        // (when generating from pgsql, it is always empty, so it is not necessary for now...)
        // but using custom metadata it is possible
        if ($metadata->discriminatorMap) {
            $lines[] = '// todo: these stub lines should be reviewed and moved to your mapping hook';
            $lines[] = '// '.$builderVar.'->setDiscriminatorColumn(\''.ModelMetadataBuilder::DISCRIMINATOR_COLUMN.'\',Type::SMALLINT)';
            foreach ((array) $metadata->discriminatorMap as $discriminatorMapName => $discriminatorMapClass ) {
                $lines[] = '// '.TAB.'->addDiscriminatorMapClass('.var_export( $discriminatorMapName, true ).", \\".$this->singularizer( $discriminatorMapClass ).'::class )';
            }
            $lines[] = '// ;';
                //$lines[] = $builderVar."->addDiscriminatorMapClass(".var_export($discriminatorMapName,true).", \\".$discriminatorMapClass."::class );";

        }

        if ($metadata->changeTrackingPolicy) {
            switch ( $this->_getChangeTrackingPolicyString($metadata->changeTrackingPolicy) ) {
                case ClassMetadata::CHANGETRACKING_DEFERRED_EXPLICIT:
                    $lines[] = $builderVar.'->setChangeTrackingPolicyDeferredExplicit();';
                    break;
                case ClassMetadata::CHANGETRACKING_NOTIFY:
                    $lines[] = $builderVar.'->setChangeTrackingPolicyNotify();';
                    break;

                // CHANGETRACKING_DEFERRED_IMPLICIT is default

            }
        }

        if ($metadata->lifecycleCallbacks) {
            foreach ($metadata->lifecycleCallbacks as $event => $callbacks) {
                foreach ($callbacks as $callback) {
                    $lines[] = $builderVar."->addLifecycleEvent('$callback', '$event');";
                }
            }
        }

        $fieldLines = [];

        foreach ($metadata->fieldMappings as $field) {

            $fieldTableName = $metadata->getTableName();
            $fieldColumnName = $metadata->getColumnName($field['fieldName']);
            if (\in_array($fieldTableName.'.'.$fieldColumnName, ModelMetadataBuilder::SKIP_COLUMNS, true)) {
                continue;
            }

            // если среди корней CTI-иерархий имеется исследуемая сущность
            if ( array_key_exists($this->singularizedEntityClassName,ModelMetadataBuilder::MAPPING) ) {
                if ( $field['fieldName'] === ModelMetadataBuilder::DISCRIMINATOR_COLUMN ) {
                    continue;
                }
            }

            //if ( array_key_exists($this->singularizedEntityClassName,ModelMetadataBuilder::DISCRIMINATORS) ) {
            //    if ( $field['fieldName'] === ModelMetadataBuilder::DISCRIMINATORS[$this->singularizedEntityClassName][1] )
            //        continue;
            //}

            if($this->injectHelperByField($metadata,$field['fieldName'],'createdAt','createdBy',ModelMetadataBuilder::HELPER_CREATION)) {
                continue;
            }

            if($this->injectHelperByField($metadata,$field['fieldName'],'updatedAt','updatedBy',ModelMetadataBuilder::HELPER_UPDATES)) {
                continue;
            }

            if($this->injectHelperByField($metadata,$field['fieldName'],'deletedAt','deletedBy',ModelMetadataBuilder::HELPER_DELETES)) {
                continue;
            }

            // skip id column if extends
            if ( $field['fieldName'] === 'id' ) {
                if ( !$this->entityHasParent ) {
                    $this->helpers[] = ModelMetadataBuilder::HELPER_ID;

                } else if ($this->addCommentsToExport) {
                    $fieldLines[] = '';
                    $fieldLines[] = '// skipped id assoc, as it is has parent entity';
                }
                continue;
            }


            $fieldLines[] = '';
            //$fieldLines[] = $builderVar."->createField('".$field['fieldName']."', '".$field['type']."')";

            // TODO: hardcoded useful hack!..
            if ( ( $field['type'] === Type::STRING ) && isset($field['options']['default']) && $field['options']['default'] === '{}') {
                $field['type'] = Type::SIMPLE_ARRAY;
            }

            $fieldLines[] = $builderVar."->createField('".$field['fieldName']."', ".$this->generateType($field['type']).')';

            $fieldLines[] = TAB."->columnName('".$field['columnName']."')";

            if (array_key_exists('columnDefinition',$field) && $field['columnDefinition'] ) {
                $fieldLines[] = TAB.'->columnDefinition('.var_export($field['columnDefinition'],true).')';
            }

            if (array_key_exists('nullable',$field) && $field['nullable'] === true ) {
                $fieldLines[] = TAB.'->nullable()';
            }

            if (array_key_exists('unique',$field) && $field['unique'] === true ) {
                $fieldLines[] = TAB.'->unique()';
            }

            if (array_key_exists('id',$field) && $field['id'] === true ) {
                $fieldLines[] = TAB.'->makePrimaryKey()';
            }

            if (array_key_exists('length',$field) && $field['length'] ) {
                $fieldLines[] = TAB.'->length('.var_export($field['length'],true).')';
            }

            if (array_key_exists('precision',$field) && $field['precision'] ) {
                $fieldLines[] = TAB.'->precision('.var_export($field['precision'],true).')';
            }

            if (array_key_exists('scale',$field) && $field['scale'] ) {
                $fieldLines[] = TAB.'->scale('.var_export($field['scale'],true).')';
            }

            if (array_key_exists('options',$field)) {
                foreach ((array) $field['options'] as $option => $value) {

                    if ( $option === 'default' ) {

                        switch( $field['type'] ) {

                            case Type::SMALLINT:
                            case Type::INTEGER:
                            case Type::BIGINT:
                                $fieldLines[] = TAB."->option('".$option."',".( (int) $value ).')';
                                break;

                            case Type::DATE:
                            case Type::DATETIME:
                                //$fieldLines[] = TAB."->option('".$option."',".( (int) $value ).')';
                                break;

                            case Type::FLOAT:
                                $fieldLines[] = TAB."->option('".$option."',".var_export((float) $value,true).')';
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
                                    $fieldLines[] = TAB."->option('".$option."',[])";
                                } else {
                                    $fieldLines[] = TAB."->option('".$option."',".var_export( $value, true ).')';
                                }
                                break;

                            default:
                                $fieldLines[] = TAB."->option('".$option."',".var_export($value,true).')';
                        }

                    } else {
                        $fieldLines[] = TAB."->option('".$option."',".var_export($value,true).')';
                    }

                }
            }

            $fieldLines[] = TAB.'->build();';

        }

        foreach ($metadata->associationMappings as $associationMappingName => $associationMapping) {

            $fieldTableName = $metadata->getTableName();
            if ($associationMapping['type'] & ClassMetadataInfo::TO_ONE) {
                try {
                    $associationColumnName = $metadata->getSingleAssociationJoinColumnName($associationMappingName);
                    if (\in_array($fieldTableName.'.'.$associationColumnName, ModelMetadataBuilder::SKIP_COLUMNS, true)) {
                        continue;
                    }
                } catch (\Doctrine\ORM\Mapping\MappingException $e) {
                    // log?
                }
            }

            if($this->injectHelperByAssoc($metadata,$associationMapping['fieldName'],'createdAt','createdBy',ModelMetadataBuilder::HELPER_CREATION)) {
                continue;
            }

            if($this->injectHelperByAssoc($metadata,$associationMapping['fieldName'],'updatedAt','updatedBy',ModelMetadataBuilder::HELPER_UPDATES)) {
                continue;
            }

            if($this->injectHelperByAssoc($metadata,$associationMapping['fieldName'],'deletedAt','deletedBy',ModelMetadataBuilder::HELPER_DELETES)) {
                continue;
            }

            if ( $associationMapping['fieldName'] === 'id' ) {
                if ( !$this->entityHasParent ) {
                    $this->helpers[] = ModelMetadataBuilder::HELPER_ID;
                } else if ($this->addCommentsToExport) {
                    $fieldLines[] = '';
                    $fieldLines[] = '// skipped id assoc, as it is has parent entity';
                }
                continue;
            }

            // cascades...

            // is cascading persist by default?
            if ($this->cascadePersistAllRelationships) {
                $associationMapping['isCascadePersist'] = true;
            }

            // is cascading refresh by default?
            if ($this->cascadeRefreshAllRelationships) {
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
                $importingEntityClass = $this->singularizer($associationMapping['targetEntity']);

                $importingEntityClass = $this->simplifyFullQualifiedModelName( $importingEntityClass );

                $fieldLines[] = $builderVar."->createManyToOne('".$associationMapping['fieldName']."', ".$importingEntityClass.'::class)';

                if ( $associationMapping['inversedBy'] ) {
                    $fieldLines[] = TAB."->inversedBy('".$associationMapping['inversedBy']."')";
                }

                if ( $associationMapping['isOwningSide'] && \array_key_exists('joinColumns',$associationMapping) ) {
                    foreach( (array) $associationMapping['joinColumns'] as $joinColumn ) {

                        //$params = $this->joinColumnParametersHelper( $joinColumn );
                        $params = $this->generateArgumentsRespectingDefaultValues($joinColumn,self::JOIN_DEFAULTS);
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

                $fieldLines[] = $builderVar."->createOneToMany('".$associationMapping['fieldName']."',\\".$this->singularizer($associationMapping['targetEntity']).'::class)';

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

                $fieldLines[] = $builderVar."->createManyToMany('".$associationMapping['fieldName']."',\\".$this->singularizer($associationMapping['targetEntity']).'::class)';

                if ( array_key_exists('joinTable',$associationMapping) && $associationMapping['joinTable'] ) {

                    $joinTable = $associationMapping['joinTable'];
                    $joinTableName = $joinTable['name'];
                    $fieldLines[] = TAB."->setJoinTable('$joinTableName')";

                    if ( array_key_exists('inverseJoinColumns',$joinTable) ) {
                        foreach ((array) $joinTable['inverseJoinColumns'] as $joinColumn) {

                            //$params = $this->joinColumnParametersHelper( $joinColumn );
                            $params = $this->generateArgumentsRespectingDefaultValues($joinColumn,self::JOIN_DEFAULTS);
                            $fieldLines[] = TAB.'->addInverseJoinColumn('.implode(', ',$params).')';

                        }
                    }

                    if ( array_key_exists('joinColumns',$joinTable) ) {
                        foreach ((array) $joinTable['joinColumns'] as $joinColumn) {

                            //$params = $this->joinColumnParametersHelper( $joinColumn );
                            $params = $this->generateArgumentsRespectingDefaultValues($joinColumn,self::JOIN_DEFAULTS);
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

        }

        $this->helpers = array_unique($this->helpers);
        if ( $this->helpers ) {
            $lines[] = '';
            $lines[] = $builderVar;
            foreach( $this->helpers as $helper ) {
                $lines[] = TAB.'->'.$helper.'()';
            }
            $lines[] = ';';
        } else if ($this->addCommentsToExport) {
            $lines[] = '// no appliable helpers detected';
        }

        $lines = array_merge($lines,$fieldLines);

        $lines[] = '';
        $lines[] = 'if (file_exists($_extraMappingInfoFile = __DIR__.\''.$this->extraPath.'/\'.basename(__FILE__))) {';
        $lines[] = TAB.'/** @noinspection PhpIncludeInspection */ include $_extraMappingInfoFile;';
        $lines[] = '}';

        return implode(self::LS, $lines);
    }

    /**
     * @param string $entitiesNamespace
     *
     * @return DoctrineMappingExporter
     */
    public function setEntitiesNamespace( string $entitiesNamespace ): DoctrineMappingExporter
    {
        $this->entitiesNamespace = $entitiesNamespace;
        return $this;
    }

}