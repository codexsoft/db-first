<?php

namespace CodexSoft\DatabaseFirst\Orm;

use CodexSoft\Code\Helpers\Classes;
use CodexSoft\Code\Helpers\Strings;
use CodexSoft\DatabaseFirst\DoctrineOrmSchema;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Mapping\Builder\ClassMetadataBuilder;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

class ModelMetadataInheritanceBuilder extends ClassMetadataBuilder
{

    public const DISCRIMINATOR_COLUMN = 'type';
    public const HELPER_CREATION = 'trackCreation';
    public const HELPER_DELETES = 'trackDeletes';
    public const HELPER_UPDATES = 'trackUpdates';
    public const HELPER_ID = 'hasId';
    // todo: maybe remove these HELPER_* helpers?

    /**
     * while generating entities, entities for these tables should be skipped
     */
    public const SKIP_TABLES = [
        'migration_versions', // migrations are not entities
    ];

    /**
     * While generating entities, these columns will not be mapped.
     * List of strings in format <table_name>.<column_name>
     * @example [
     *   'registered_operations.type',
     *   'transport_orders.order_id',
     * ]
     */
    public const SKIP_COLUMNS = [
    ];

    /**
     * while review/regenerate, DB is reverse-engineered, and some basic column configurations are
     * detected and replaced with trait usages. This is not very useful, so this behaviour should be
     * removed.
     * todo: helper traits should became separate classes or deleted
     */
    public const HELPER_TO_TRAIT = [
        //self::HELPER_CREATION => Traits\CreatedByTrait::class,
        //self::HELPER_DELETES  => Traits\DeletedByTrait::class,
        //self::HELPER_UPDATES  => Traits\UpdatedByTrait::class,
        //self::HELPER_ID       => Traits\HasIdTrait::class,
    ];

    /**
     * Some old Doctrine2 Class Table Inheritance stuff
     */
    public const MAPPING = [
    ];

    public const INHERITANCE = [
    ];

    static public $skipDescriminatorMapInfo = false;

    /** @var DoctrineOrmSchema */
    private $dbConfig;

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

    public static function getDiscriminationMapForClass($className) {
        if (array_key_exists($className,self::MAPPING)) {
            return self::MAPPING[$className];
        }
        return null;
    }

    public function setDiscriminatorColumn($name, $type = 'string', $length = 255) {
        if ( self::$skipDescriminatorMapInfo ) {
            return $this;
        }
        return parent::setDiscriminatorColumn($name, $type, $length);
    }

    public function addDiscriminatorMapClass($name, $class) {
        if ( self::$skipDescriminatorMapInfo ) {
            return $this;
        }
        return parent::addDiscriminatorMapClass($name, $class);
    }

    /**
     * @param $fieldName
     * @param $newType
     *
     * @return \Doctrine\ORM\Mapping\Builder\FieldBuilder
     */
    public function overrideField( $fieldName, $newType) {
        $metadata = $this->getClassMetadata();
        $columnName = $metadata->fieldMappings[$fieldName]['columnName'];
        unset( $metadata->fieldMappings[$fieldName] );
        unset( $metadata->fieldNames[$fieldName] );
        if (\property_exists($metadata,'columnNames')) {
            unset( $metadata->columnNames[$columnName] );
        }
        return $this->createField($fieldName,$newType);
    }

    /**
     * @param $fieldName
     *
     * @return ModelMetadataInheritanceBuilder
     */
    public function removeAssociation( $fieldName) {
        unset( $this->getClassMetadata()->associationMappings[$fieldName] );
        return $this;
    }

    /**
     *
     * @param string $fieldName
     * @param string $inversedField
     *
     * @return static
     */
    public function setMappedBy($fieldName, $inversedField) {

        $metadata = $this->getClassMetadata();
        $metadata->associationMappings[$fieldName]['mappedBy'] = $inversedField;

        return $this;
    }

    /**
     *
     * @param string $fieldName
     * @param string $inversedField
     *
     * @return static
     */
    public function setInversedBy($fieldName, $inversedField) {

        $metadata = $this->getClassMetadata();
        $metadata->associationMappings[$fieldName]['inversedBy'] = $inversedField;

        return $this;
    }

    /**
     * @param string|string[] $fieldNames
     * @param bool $bool
     *
     * @return static
     */
    public function setCascadePersisting($fieldNames, $bool = true) {

        $fieldNames = (array) $fieldNames;
        $metadata = $this->getClassMetadata();

        foreach( $fieldNames as $fieldName ) {

            if (!array_key_exists($fieldName,$metadata->associationMappings)) {
                continue;
            }
                //throw new \Exception('Tried to set cascading for field '.$fieldName.' that does not exists!');

            if ($bool) {
                $metadata->associationMappings[$fieldName]['cascade'][] = 'persist';
            } else {
                $metadata->associationMappings[$fieldName]['cascade']
                    = array_filter($metadata->associationMappings[$fieldName]['cascade'], function($k,$v) {
                    return $v !== 'persist';
                }, ARRAY_FILTER_USE_BOTH);
            }

            $this->getClassMetadata()->associationMappings[$fieldName]['isCascadePersist'] = $bool;

        }


        return $this;
    }

    public static function calculateParentClasses($entityClass) {
        $parentClasses = [];
        if ( self::hasParentEntity($entityClass) ) {
            $parentClass = self::INHERITANCE[$entityClass];
            $parentClasses[] = $parentClass;
            $parentClasses = array_merge( $parentClasses, self::calculateParentClasses($parentClass) );
        }
        return $parentClasses;
    }

    static public function calculateChildClasses($entityClass) {
        $childClasses = [];
        foreach (self::INHERITANCE as $subClass => $parentClass) {
            if ( $entityClass === $parentClass )
                $childClasses[] = $subClass;
        }
        return $childClasses;
    }

    public static function hasParentEntity( $class) {
        //echo "\nChecking if {$class} has parent";
        return \array_key_exists($class,self::INHERITANCE);
    }

    public static function hasChildEntities( $class) {
        //echo "\nChecking if {$class} has children";
        return array_search( $class, self::INHERITANCE, true );
    }

    /**
     * todo: custom classes must be calculated!
     * @param $entityClass
     *
     * @return mixed|string
     * @throws \ReflectionException
     */
    protected function getRepositoryClassFor($entityClass)
    {
        if (class_exists($entityClass)) {
            return $this->domainSchema->getNamespaceRepositories().'\\'.Classes::short($entityClass).'Repository';
        }

        return basename(Strings::bs2s($entityClass)); // todo: .Repository?
    }

    /**
     * ModelMetadataBuilder constructor.
     *
     * @param ClassMetadataInfo $cm
     *
     * @throws \ReflectionException
     */
    //public function __construct(ClassMetadataInfo $cm, string $customRepo)
    public function __construct(ClassMetadataInfo $cm)
    {

        parent::__construct($cm);
        $entityClassName = $cm->getName();

        //$this->setCustomRepositoryClass($this->getRepositoryClassFor($entityClassName));

        // applying discriminator mapping
        if ( $map = self::getDiscriminationMapForClass($entityClassName) ) {

            $this->setDiscriminatorColumn('type',Type::SMALLINT);
            foreach( $map as $value => $modelClass ) {
                $this->addDiscriminatorMapClass($value, $modelClass );
            }

        }
    }

    /**
     * @return static
     */
    public function hasId()
    {

        $this->addUsedHelper(self::HELPER_ID);

        $this->createField('id', Type::BIGINT)
            ->columnName('id')
            ->makePrimaryKey()
            ->option('unsigned',false)
            //->option('comment','Целочисленный идентификатор Агента')
            ->build();

        return $this;

    }

    protected function addUsedHelper($helper) {
        $metadata = $this->getClassMetadata();
        /** @noinspection MissingIssetImplementationInspection */
        if ( !isset($metadata->dbfirst_mapping_helpers) )
            $metadata->dbfirst_mapping_helpers = [];

        $metadata->dbfirst_mapping_helpers[] = $helper;
    }

}
