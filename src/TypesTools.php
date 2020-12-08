<?php


namespace CodexSoft\DatabaseFirst;


use Doctrine\DBAL\Types\ArrayType;
use Doctrine\DBAL\Types\AsciiStringType;
use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\BooleanType;
use Doctrine\DBAL\Types\DateImmutableType;
use Doctrine\DBAL\Types\DateIntervalType;
use Doctrine\DBAL\Types\DateTimeImmutableType;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\DateTimeTzImmutableType;
use Doctrine\DBAL\Types\DateTimeTzType;
use Doctrine\DBAL\Types\DateType;
use Doctrine\DBAL\Types\DecimalType;
use Doctrine\DBAL\Types\FloatType;
use Doctrine\DBAL\Types\GuidType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\JsonArrayType;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\DBAL\Types\ObjectType;
use Doctrine\DBAL\Types\SimpleArrayType;
use Doctrine\DBAL\Types\SmallIntType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Types\TimeImmutableType;
use Doctrine\DBAL\Types\TimeType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

class TypesTools
{

    /**
     * typeName to Types constant name
     * @var array|string[]
     */
    public array $doctrineTypesMap = [
        'array' => 'ARRAY',
        'bigint' => 'BIGINT',
        'binary' => 'BINARY',
        'blob' => 'BLOB',
        'boolean' => 'BOOLEAN',
        'date' => 'DATE_MUTABLE',
        'date_immutable' => 'DATE_IMMUTABLE',
        'dateinterval' => 'DATEINTERVAL',
        'datetime' => 'DATETIME_MUTABLE',
        'datetime_immutable' => 'DATETIME_IMMUTABLE',
        'datetimetz' => 'DATETIMETZ_MUTABLE',
        'datetimetz_immutable' => 'DATETIMETZ_IMMUTABLE',
        'decimal' => 'DECIMAL',
        'float' => 'FLOAT',
        'guid' => 'GUID',
        'integer' => 'INTEGER',
        'json' => 'JSON',
        'json_array' => 'JSON',
        'object' => 'OBJECT',
        'simple_array' => 'SIMPLE_ARRAY',
        'smallint' => 'SMALLINT',
        'string' => 'STRING',
        'text' => 'TEXT',
        'time' => 'TIME_MUTABLE',
        'time_immutable' => 'TIME_IMMUTABLE',
        //'array' => 'TARRAY',
        //'date' => 'DATE',
        //'datetime' => 'DATETIME',
        //'datetimetz' => 'DATETIMETZ',
        //'json_array' => 'JSON_ARRAY',
        //'time' => 'TIME',
    ];

    /**
     * Is used to generate parameter hints
     * EntityGenerator.$typeAlias
     * @var string[]
     */
    protected $typeAlias = [
        Type::DATETIMETZ    => '\DateTime',
        Type::DATETIME      => '\DateTime',
        Type::DATE          => '\DateTime',
        Type::TIME          => '\DateTime',
        Type::OBJECT        => '\stdClass',
        Type::INTEGER       => 'int',
        Type::BIGINT        => 'int',
        Type::SMALLINT      => 'int',
        Type::TEXT          => 'string',
        Type::BLOB          => 'string',
        Type::DECIMAL       => 'string',
        Type::GUID          => 'string',
        Type::JSON_ARRAY    => 'array',
        Type::SIMPLE_ARRAY  => 'array',
        Type::BOOLEAN       => 'bool',
    ];

    public array $dbToPhpType = [
        'json[]'                    => 'array',
        'jsonb'                     => 'array',
        'jsonb[]'                   => 'array',
        'text[]'                    => 'string[]',
        'smallint[]'                => 'integer[]',
        'integer[]'                 => 'integer[]',
        'bigint[]'                  => 'integer[]',
        'varchar[]'                 => 'string[]',
        Types::DATETIMETZ_MUTABLE   => '\DateTime',
        Types::DATETIMETZ_IMMUTABLE => '\DateTimeImmutable',
        Types::DATETIME_IMMUTABLE   => '\DateTimeImmutable',
        Types::DATETIME_MUTABLE     => '\DateTime',
        Types::DATE_IMMUTABLE       => '\DateTimeImmutable',
        Types::DATE_MUTABLE         => '\DateTime',
        Types::TIME_IMMUTABLE       => '\DateTimeImmutable',
        Types::TIME_MUTABLE         => '\DateTime',
        Types::OBJECT               => '\stdClass',
        Types::BIGINT               => 'integer',
        Types::SMALLINT             => 'integer',
        Types::TEXT                 => 'string',
        Types::BLOB                 => 'string',
        Types::DECIMAL              => 'string',
        Types::JSON                 => 'array',
        Types::JSON_ARRAY           => 'array',
        Types::SIMPLE_ARRAY         => 'array',
        Types::GUID                 => 'string',
        'ltree'                     => 'array',
    ];

    /**
     * PostgreSqlPlatform.initializeDoctrineTypeMappings
     */
    private const POSTGRES_DBTYPE_MAP = [
        'smallint'      => 'smallint',
        'int2'          => 'smallint',
        'serial'        => 'integer',
        'serial4'       => 'integer',
        'int'           => 'integer',
        'int4'          => 'integer',
        'integer'       => 'integer',
        'bigserial'     => 'bigint',
        'serial8'       => 'bigint',
        'bigint'        => 'bigint',
        'int8'          => 'bigint',
        'bool'          => 'boolean',
        'boolean'       => 'boolean',
        'text'          => 'text',
        'tsvector'      => 'text',
        'varchar'       => 'string',
        'interval'      => 'string',
        '_varchar'      => 'string',
        'char'          => 'string',
        'bpchar'        => 'string',
        'inet'          => 'string',
        'date'          => 'date',
        'datetime'      => 'datetime',
        'timestamp'     => 'datetime',
        'timestamptz'   => 'datetimetz',
        'time'          => 'time',
        'timetz'        => 'time',
        'float'         => 'float',
        'float4'        => 'float',
        'float8'        => 'float',
        'double'        => 'float',
        'double precision' => 'float',
        'real'          => 'float',
        'decimal'       => 'decimal',
        'money'         => 'decimal',
        'numeric'       => 'decimal',
        'year'          => 'date',
        'uuid'          => 'guid',
        'bytea'         => 'blob',
    ];

    /**
     * AbstractPgSqlEntityManagerBuilder.getDoctrineTypeMapping
     * while reverse-engineering, this types were detected. in normal mode it still exists?
     */
    const YYY = [
        'smallint[]' => 'smallint[]',
        'integer[]' => 'integer[]',
        'bigint[]' => 'bigint[]',
        'text[]' => 'text[]',
        '_int2' => 'smallint[]',
        '_int4' => 'integer[]',
        '_int8' => 'bigint[]',
        '_text' => 'text[]',
    ];

    const XXX = [
        [Types::ARRAY, ArrayType::class],
        [Types::DATETIME_IMMUTABLE, DateTimeImmutableType::class, \DateTimeImmutable::class],
    ];

    /**
     * This is in Doctrine\DBAL\Types\Type
     */
    private const BUILTIN_TYPES_MAP = [
        Types::ARRAY                => ArrayType::class,
        Types::ASCII_STRING         => AsciiStringType::class,
        Types::BIGINT               => BigIntType::class,
        Types::BINARY               => BinaryType::class,
        Types::BLOB                 => BlobType::class,
        Types::BOOLEAN              => BooleanType::class,
        Types::DATE_MUTABLE         => DateType::class,
        Types::DATE_IMMUTABLE       => DateImmutableType::class,
        Types::DATEINTERVAL         => DateIntervalType::class,
        Types::DATETIME_MUTABLE     => DateTimeType::class,
        Types::DATETIME_IMMUTABLE   => DateTimeImmutableType::class,
        Types::DATETIMETZ_MUTABLE   => DateTimeTzType::class,
        Types::DATETIMETZ_IMMUTABLE => DateTimeTzImmutableType::class,
        Types::DECIMAL              => DecimalType::class,
        Types::FLOAT                => FloatType::class,
        Types::GUID                 => GuidType::class,
        Types::INTEGER              => IntegerType::class,
        Types::JSON                 => JsonType::class,
        Types::JSON_ARRAY           => JsonArrayType::class,
        Types::OBJECT               => ObjectType::class,
        Types::SIMPLE_ARRAY         => SimpleArrayType::class,
        Types::SMALLINT             => SmallIntType::class,
        Types::STRING               => StringType::class,
        Types::TEXT                 => TextType::class,
        Types::TIME_MUTABLE         => TimeType::class,
        Types::TIME_IMMUTABLE       => TimeImmutableType::class,
    ];
}
