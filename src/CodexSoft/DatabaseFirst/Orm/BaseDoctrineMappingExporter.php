<?php

namespace CodexSoft\DatabaseFirst\Orm;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Mapping\Builder\ClassMetadataBuilder;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Tools\Export\Driver\AbstractExporter;

use const CodexSoft\Shortcut\TAB;

/**
 * Created by PhpStorm.
 * User: dx
 * Date: 10.12.17
 * Time: 5:26
 */

/**
 * @deprecated mapping is generated now via mapping command (should be rewrited or deleted)?
 */
abstract class BaseDoctrineMappingExporter extends AbstractExporter
{

    protected const LS = "\n"; // todo: maybe PHP_EOL or "\n\r"

    const JOIN_DEFAULTS = [
        'name' => '',
        'referencedColumnName' => '',
        'nullable' => true,
        'unique' => false,
        'onDelete' => null,
        'columnDefinition' => null,
    ];

    const DISCRIMINATOR_COLUMN_DEFAULTS = [
        'name' => '',
        'type' => 'string',
        'length' => 255,
    ];

    const DOCTRINE_TYPES_MAP = [
        'array' => 'TARRAY',
        'simple_array' => 'SIMPLE_ARRAY',
        'json_array' => 'JSON_ARRAY',
        'json' => 'JSON',
        'bigint' => 'BIGINT',
        'boolean' => 'BOOLEAN',
        'datetime' => 'DATETIME',
        'datetime_immutable' => 'DATETIME_IMMUTABLE',
        'datetimetz' => 'DATETIMETZ',
        'datetimetz_immutable' => 'DATETIMETZ_IMMUTABLE',
        'date' => 'DATE',
        'date_immutable' => 'DATE_IMMUTABLE',
        'time' => 'TIME',
        'time_immutable' => 'TIME_IMMUTABLE',
        'decimal' => 'DECIMAL',
        'integer' => 'INTEGER',
        'object' => 'OBJECT',
        'smallint' => 'SMALLINT',
        'string' => 'STRING',
        'text' => 'TEXT',
        'binary' => 'BINARY',
        'blob' => 'BLOB',
        'float' => 'FLOAT',
        'guid' => 'GUID',
        'dateinterval' => 'DATEINTERVAL',
    ];

    /**
     * @var string
     */
    protected $_extension = '.php';

    protected function generateType(string $type) {
        if ( array_key_exists($type, self::DOCTRINE_TYPES_MAP) )
            return 'Type::'.self::DOCTRINE_TYPES_MAP[$type];
        return var_export($type,true);
    }

    protected function generateArgumentsRespectingDefaultValues( array $sendingValues, $defaultValues ) {

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

    protected function singularizer($plural) {
        return \Doctrine\Common\Inflector\Inflector::singularize($plural);
    }

    protected function _generateOutputPath(ClassMetadataInfo $metadata)
    {
        return $this->_outputDir . '/' . str_replace('\\', '.', $this->singularizer($metadata->name)) . $this->_extension;
        //return $this->_outputDir . '/' . str_replace('\\', '.', $metadata->name) . $this->_extension;
    }

    /**
     * Converts a single ClassMetadata instance to the exported format
     * and returns it.
     *
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    public function exportClassMetadata( ClassMetadataInfo $metadata ) {

        $metaVar = '$metadata';
        $builderVar = '$mapper';

        $lines = [
            '<?php',
            '',
            'use '.Type::class.';',
            'use '.ClassMetadataBuilder::class.';',
            'use '.ClassMetadataInfo::class.';',
            'use App\Domain\Model;',
            '',
            '/** @var '.$metaVar.' '.ClassMetadataInfo::class.' */',
            '',
        ];

        if ($metadata->isMappedSuperclass) {
            $lines[] = $metaVar.'->isMappedSuperclass = true;';
        }

        if ($metadata->inheritanceType) {
            $lines[] = '/** @noinspection PhpUnhandledExceptionInspection */';
            $lines[] = $metaVar.'->setInheritanceType(ClassMetadataInfo::INHERITANCE_TYPE_' . $this->_getInheritanceTypeString($metadata->inheritanceType) . ');';
        }

        if ($metadata->customRepositoryClassName) {
            $lines[] = $metaVar."->customRepositoryClassName = '" . $metadata->customRepositoryClassName . "';";
        }

        if ($metadata->discriminatorColumn) {
            $params = $this->generateArgumentsRespectingDefaultValues($metadata->discriminatorColumn,self::DISCRIMINATOR_COLUMN_DEFAULTS);
            $lines[] = $builderVar.'->setDiscriminatorColumn('.join(', ',$params).');';
        }

        // (when generating from pgsql, it is always empty, so it is not necessary for now...)
        if ($metadata->discriminatorMap) {
            foreach ((array) $metadata->discriminatorMap as $discriminatorMapName => $discriminatorMapClass )
                $lines[] = $builderVar.'->addDiscriminatorMapClass('.var_export($discriminatorMapName,true).", \\".$this->singularizer($discriminatorMapClass).'::class );';
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

        if ( ! $metadata->isIdentifierComposite && $generatorType = $this->_getIdGeneratorTypeString($metadata->generatorType)) {
            $lines[] = $metaVar.'->setIdGeneratorType(ClassMetadataInfo::GENERATOR_TYPE_' . $generatorType . ');';
        }

        $lines[] = '';
        $lines[] = $builderVar.' = new ClassMetadataBuilder('.$metaVar.');';
        $lines[] = $builderVar."->setTable('{$metadata->table['name']}');";

        foreach ($metadata->fieldMappings as $field) {
            $lines[] = '';
            //$lines[] = $builderVar."->createField('".$field['fieldName']."', '".$field['type']."')";
            $lines[] = $builderVar."->createField('".$field['fieldName']."', ".$this->generateType($field['type']).')';

            $lines[] = TAB."->columnName('".$field['columnName']."')";

            if (array_key_exists('columnDefinition',$field) && $field['columnDefinition'] ) {
                $lines[] = TAB.'->columnDefinition('.var_export($field['columnDefinition'],true).')';
            }

            if (array_key_exists('nullable',$field) && $field['nullable'] === true ) {
                $lines[] = TAB.'->nullable()';
            }

            if (array_key_exists('unique',$field) && $field['unique'] === true ) {
                $lines[] = TAB.'->unique()';
            }

            if (array_key_exists('id',$field) && $field['id'] === true ) {
                $lines[] = TAB.'->makePrimaryKey()';
            }

            if (array_key_exists('length',$field) && $field['length'] ) {
                $lines[] = TAB.'->length('.var_export($field['length'],true).')';
            }

            if (array_key_exists('precision',$field) && $field['precision'] ) {
                $lines[] = TAB.'->precision('.var_export($field['precision'],true).')';
            }

            if (array_key_exists('scale',$field) && $field['scale'] ) {
                $lines[] = TAB.'->scale('.var_export($field['scale'],true).')';
            }

            if (array_key_exists('options',$field)) {
                foreach ((array) $field['options'] as $option => $value) {
                    $lines[] = TAB."->option('".$option."',".var_export($value,true).')';
                }
            }

            $lines[] = TAB.'->build();';

        }

        foreach ($metadata->associationMappings as $associationMapping) {

            // cascades...

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
                if ( \in_array( 'remove', $cascade, true ) ) $cascadeLines[] = TAB.'->cascadeRemove()';
                if ( \in_array( 'persist', $cascade, true ) ) $cascadeLines[] = TAB.'->cascadePersist()';
                if ( \in_array( 'refresh', $cascade, true ) ) $cascadeLines[] = TAB.'->cascadeRefresh()';
                if ( \in_array( 'merge', $cascade, true ) ) $cascadeLines[] = TAB.'->cascadeMerge()';
                if ( \in_array( 'detach', $cascade, true ) ) $cascadeLines[] = TAB.'->cascadeDetach()';
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

            $lines[] = '';

            if ($associationMapping['type'] & ClassMetadataInfo::TO_ONE) {

                $lines[] = $builderVar."->createOneToOne('".$associationMapping['fieldName']."',\\".$this->singularizer($associationMapping['targetEntity']).'::class)';

                if ( $associationMapping['inversedBy'] ) {
                    $lines[] = TAB."->inversedBy('".$associationMapping['inversedBy']."')";
                }

                if ( $associationMapping['isOwningSide'] && array_key_exists('joinColumns',$associationMapping) ) {
                    foreach( (array) $associationMapping['joinColumns'] as $joinColumn ) {

                        //$params = $this->joinColumnParametersHelper( $joinColumn );
                        $params = $this->generateArgumentsRespectingDefaultValues($joinColumn,self::JOIN_DEFAULTS);
                        $lines[] = TAB.'->addJoinColumn('.join(', ',$params).')';

                    }
                }

                if ( $associationMapping['mappedBy'] ) {
                    $lines[] = TAB."->mappedBy('".$associationMapping['mappedBy']."')";
                }

                if ( $associationMapping['orphanRemoval'] ) {
                    $lines[] = TAB.'->orphanRemoval()';
                }

                foreach((array) $cascadeLines as $cascadeLine) {
                    $lines[] = $cascadeLine;
                }

                foreach((array) $fetchLines as $fetchLine) {
                    $lines[] = $fetchLine;
                }

                $lines[] = TAB.'->build();';

            } elseif ($associationMapping['type'] === ClassMetadataInfo::ONE_TO_MANY) {

                $lines[] = $builderVar."->createOneToMany('".$associationMapping['fieldName']."',\\".$this->singularizer($associationMapping['targetEntity']).'::class)';

                if ( array_key_exists('orderBy',$associationMapping) && $associationMapping['orderBy'] ) {
                    $lines[] = TAB.'->setOrderBy('.var_export($associationMapping['orderBy'],true).')';
                }

                if ( $associationMapping['mappedBy'] ) {
                    $lines[] = TAB."->mappedBy('".$associationMapping['mappedBy']."')";
                }

                if ( $associationMapping['orphanRemoval'] ) {
                    $lines[] = TAB.'->orphanRemoval()';
                }

                foreach((array) $cascadeLines as $cascadeLine) {
                    $lines[] = $cascadeLine;
                }

                foreach((array) $fetchLines as $fetchLine) {
                    $lines[] = $fetchLine;
                }

                $lines[] = TAB.'->cascadePersist()';

                $lines[] = TAB.'->build();';

            } elseif ($associationMapping['type'] === ClassMetadataInfo::MANY_TO_MANY) {

                //$lines[] = var_export($associationMapping,true).';'; // debug...

                $lines[] = $builderVar."->createManyToMany('".$associationMapping['fieldName']."',\\".$this->singularizer($associationMapping['targetEntity']).'::class)';

                if ( array_key_exists('joinTable',$associationMapping) && $associationMapping['joinTable'] ) {

                    $joinTable = $associationMapping['joinTable'];
                    $joinTableName = $joinTable['name'];
                    $lines[] = TAB."->setJoinTable('$joinTableName')";

                    if ( array_key_exists('inverseJoinColumns',$joinTable) ) {
                        foreach ((array) $joinTable['inverseJoinColumns'] as $joinColumn) {

                            //$params = $this->joinColumnParametersHelper( $joinColumn );
                            $params = $this->generateArgumentsRespectingDefaultValues($joinColumn,self::JOIN_DEFAULTS);
                            $lines[] = TAB.'->addInverseJoinColumn('.implode(', ',$params).')';

                        }
                    }

                    if ( array_key_exists('joinColumns',$joinTable) ) {
                        foreach ((array) $joinTable['joinColumns'] as $joinColumn) {

                            //$params = $this->joinColumnParametersHelper( $joinColumn );
                            $params = $this->generateArgumentsRespectingDefaultValues($joinColumn,self::JOIN_DEFAULTS);
                            $lines[] = TAB.'->addJoinColumn('.implode(', ',$params).')';

                        }
                    }

                    if ( array_key_exists('orderBy',$associationMapping) && $associationMapping['orderBy'] ) {
                        $lines[] = TAB.'->setOrderBy('.var_export($associationMapping['orderBy'],true).')';
                    }

                    if ( $associationMapping['mappedBy'] ) {
                        $lines[] = TAB."->mappedBy('".$associationMapping['mappedBy']."')";
                    }

                    foreach((array) $cascadeLines as $cascadeLine) {
                        $lines[] = $cascadeLine;
                    }

                    foreach((array) $fetchLines as $fetchLine) {
                        $lines[] = $fetchLine;
                    }

                }

                $lines[] = TAB.'->build();';

            }

        }

        return implode(self::LS, $lines);
    }
}
