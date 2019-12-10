<?php

namespace CodexSoft\DatabaseFirst\Operation;

use CodexSoft\Code\Classes\Classes;
use CodexSoft\DatabaseFirst\Helpers\Doctrine;
use CodexSoft\DatabaseFirst\Orm\Dql;
use CodexSoft\OperationsSystem\Operation;
use CodexSoft\OperationsSystem\Exception\OperationException;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Console\MetadataFilter;
use Doctrine\ORM\Tools\DisconnectedClassMetadataFactory;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Generate repository classes and method stubs from your mapping information.
 * @method void execute()
 */
class GenerateReposOperation extends Operation
{

    use DoctrineOrmSchemaAwareTrait;

    const ID = '490383fb-4a65-48c8-8ba9-0eb85dc040e1';

    private const LS = "\n";

    /**
     * @var array comments for all columns in db, in format [ <table>.<column> => <comment> ]
     * @internal
     */
    private $columnComments = [];

    /** @var string Which interface repo implements */
    private $repoInterface;

    /**
     * @throws OperationException
     */
    protected function validateInputData(): void
    {
        $this->assert(\class_exists($this->doctrineOrmSchema->dqlHelperClass),
            $this->doctrineOrmSchema->dqlHelperClass.' provided as Dql-helper class does not exists');

        $this->assert(\is_subclass_of($this->doctrineOrmSchema->dqlHelperClass, Dql::class),
            $this->doctrineOrmSchema->dqlHelperClass.' provided as Dql-helper class does not extend '.Dql::class);
    }

    /**
     * @return void
     * @throws OperationException
     */
    protected function handle()
    {
        $em = $this->doctrineOrmSchema->getEntityManager();
        $this->columnComments = Doctrine::getAllColumnsComments($em->getConnection());
        $fs = new Filesystem();

        //$this->repoNamespace = $this->doctrineOrmSchema->getNamespaceRepositories();

        $cmf = new DisconnectedClassMetadataFactory();
        $cmf->setEntityManager( $em );
        $metadatas = $cmf->getAllMetadata();
        $metadatas = MetadataFilter::filter($metadatas, $this->doctrineOrmSchema->metadataFilter);
        /** @var ClassMetadata[] $metadatas */

        //$reposPath = realpath($this->reposPath);
        $reposPath = $this->doctrineOrmSchema->getPathToRepositories();
        $reposTraitPath = $reposPath.'/Generated'; // todo: get from config?

        $parentClass = $this->doctrineOrmSchema->parentRepositoryClass;
        //$repoNamespace = $this->repoNamespace;
        $repoNamespace = $this->doctrineOrmSchema->getNamespaceRepositories();
        $repoInterface = $this->repoInterface;

        // todo: try to create?

        if (!file_exists($reposPath)) {
            $fs->mkdir($reposPath);
        }

        if (!file_exists($reposPath)) {
            throw $this->exception(self::ERROR_CODE_INVALID_INPUT_DATA, "Repos destination directory $reposPath does not exist.");
        }

        if (!is_writable($reposPath)) {
            throw $this->exception(self::ERROR_CODE_INVALID_INPUT_DATA, "Repos destination directory $reposPath does not have write permissions.");
        }

        if (!count($metadatas)) {
            throw $this->genericException('No Metadata Classes to process.');
        }

        foreach ($metadatas as $metadata) {
            $tableComment = Doctrine::getCommentForTable($em->getConnection(), $metadata->table['name']);
            $this->getLogger()->info("Processing entity {$metadata->name}");

            $collectionShortClass = Classes::short($metadata->name).'Repository';
            $repoTraitFileName = $reposTraitPath.'/'.$collectionShortClass.'Trait.php';

            if ($this->doctrineOrmSchema->generateRepoTraits) {
                $this->getLogger()->info('Generating repo trait...');
                $content = $this->generateRepositoryBaseTrait($metadata, $collectionShortClass);
                $fs->dumpFile($repoTraitFileName,$content);
            }

            $repoFilename = $reposPath.'/'.$collectionShortClass.'.php';
            if (file_exists($repoFilename)) {
                $this->getLogger()->warning("Repo file $repoFilename already exists, skipped...");
                continue;
            }

            $implementsUse = $repoInterface ? "use {$repoInterface};" : '';
            $implementsInt = $repoInterface ? ' implements '.Classes::short($repoInterface) : '';

            $repoCode = [
                '<?php',
                '',
                "namespace {$repoNamespace};",
                '',
                "use {$parentClass};",
                $implementsUse,
                '',
                '/**',
                ' * '.$tableComment,
                ' * @method \\'.$metadata->name.'|null find($id, $lockMode = null, $lockVersion = null)',
                ' * @method \\'.$metadata->name.'|null findOneBy(array $criteria, array $orderBy = null)',
                ' * @method \\'.$metadata->name.'[] findAll()',
                ' * @method \\'.$metadata->name.'[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)',
                ' */',
                'class '.$collectionShortClass.' extends '.Classes::short($parentClass).$implementsInt,
                '{',
                '    use \\'.$this->doctrineOrmSchema->getNamespaceRepositoriesTraits().'\\'.$collectionShortClass.'Trait;',
                //'    use \\'.Constants::NAMESPACE_REPOSITORIES_TRAITS.'\\'.$collectionShortClass.'Trait;',
                '}',
            ];

            if ($this->doctrineOrmSchema->overwriteRepoClasses || !file_exists($repoFilename)) {
                file_put_contents($repoFilename, implode(self::LS, $repoCode));
            }

            if ($repoInterface) {
                $interfaceCode = [
                    '<?php',
                    '',
                    "namespace {$repoNamespace};",
                    '',
                    'interface '.$collectionShortClass.'Interface',
                    '{',
                    '}',
                ];

                file_put_contents($reposPath.'/'.$collectionShortClass.'Interface.php', implode(self::LS, $interfaceCode));
            }

        }

    }

    private function generateRepositoryBaseTrait(ClassMetadata $metadata, string $repoClass)
    {

        \ksort($metadata->fieldMappings);
        \ksort($metadata->associationMappings);

        $result = '';
        $code = [
            '<?php',
            '',
            'namespace '.$this->doctrineOrmSchema->getNamespaceRepositoriesTraits().';',
            '',
            'use '.$this->doctrineOrmSchema->getNamespaceModels().';',
            'use '.\Doctrine\ORM\Query\Expr::class.';',
            'use '.$this->doctrineOrmSchema->dqlHelperClass.';',
            '',
            '/**',
            ' */',
            'trait '.$repoClass.'Trait',
            '{',
        ];

        $importedDqlHelperClass = Classes::short($this->doctrineOrmSchema->dqlHelperClass);

        $result .= implode(self::LS, $code);

        $shortName = Classes::short($metadata->name);
        $lowerName = lcfirst($shortName);
        $importedEntityNamespace = Classes::short($this->doctrineOrmSchema->getNamespaceModels());
        $namespacedShortName = $importedEntityNamespace.'\\'.$shortName;

        foreach ($metadata->fieldMappings as $key => $mapping) {
            $methodName = 'getOneBy'.\ucfirst($key);
            $fieldComment = $mapping['options']['comment'] ?? '';
            $exceptionMessageVar = ($key === 'exceptionMessage') ? '$message' : '$exceptionMessage';

            $code = [
                '',
                '/**',
                ' * @param $'.$key,
                ' * '.$fieldComment,
                ' *',
                ' * @return null|'.$namespacedShortName,
                ' */',
                'public function '.$methodName.'($'.$key.'): ?'.$namespacedShortName,
                '{',
                '    return $this->findOneBy(['.$namespacedShortName.'::_'.$key.'() => $'.$key.']);',
                '}',
                '',
            ];
            $result .= implode(self::LS.'    ',$code);

            $code = [
                '',
                '/**',
                ' * @noinspection PhpDocMissingThrowsInspection',
                ' * @param $'.$key,
                " * @param string|\\Exception|null $exceptionMessageVar a message for exception (or exception instance) that will raise if query fails",
                ' * '.$fieldComment,
                ' *',
                ' * @return '.$namespacedShortName,
                ' */',
                'public function '.$methodName.'OrFail($'.$key.', '.$exceptionMessageVar.' = null): '.$namespacedShortName,
                '{',
                '    $'.$lowerName.' = $this->'.$methodName.'($'.$key.');',
                '    if (!$'.$lowerName.' instanceof '.$namespacedShortName.') {',
                '',
                "        if ($exceptionMessageVar instanceof \\Exception) {",
                '            /** @noinspection PhpUnhandledExceptionInspection */',
                '            /** @var \RuntimeException '.$exceptionMessageVar.' */',
                "            throw $exceptionMessageVar;",
                '        }',
                '',
                "        if (\is_string($exceptionMessageVar)) {",
                '            /** @noinspection PhpUnhandledExceptionInspection */',
                "            throw new \\".\DomainException::class."($exceptionMessageVar);",
                '        }',
                '',
                '        /** @noinspection PhpUnhandledExceptionInspection */',
                "        throw new \\".\DomainException::class."('Failed to find $shortName by $key');",
                '    }',
                '    return $'.$lowerName.';',
                '}',
                '',
            ];
            $result .= implode(self::LS.'    ',$code);

            $code = [
                '',
                '/**',
                ' * @param $'.$key,
                ' * '.$fieldComment,
                ' *',
                ' * @return '.$namespacedShortName.'[]',
                ' */',
                'public function getBy'.\ucfirst($key).'($'.$key.'): array',
                '{',
                '    return $this->findBy(['.$namespacedShortName.'::_'.$key.'() => $'.$key.']);',
                '}',
                '',
            ];
            $result .= implode(self::LS.'    ',$code);

        }

        foreach ($metadata->associationMappings as $associationMappingName => $associationMapping) {

            $assocComment = Doctrine::getCommentForField($metadata, $associationMappingName, $this->columnComments);
            $exceptionMessageVar = ($associationMappingName === 'exceptionMessage') ? '$message' : '$exceptionMessage';

            if ($associationMapping['type'] & ClassMetadataInfo::TO_ONE) {
                $nameOrId = $associationMappingName.'OrId';
                $targetEntity = $associationMapping['targetEntity'];
                $targetEntityShort = Classes::short($targetEntity);
                $targetEntityShortNamespaced = $importedEntityNamespace.'\\'.$targetEntityShort;
                $code = [
                    '',
                    '/**',
                    ' * @param int|'.$importedEntityNamespace.'\\'.$targetEntityShort.' $'.$nameOrId,
                    ' * '.$assocComment,
                    ' *',
                    ' * @return '.$namespacedShortName.'[]',
                    ' */',
                    'public function getBy'.\ucfirst($associationMappingName).'($'.$nameOrId.'): array',
                    '{',
                    '    $'.$associationMappingName.'Id = '.$targetEntityShortNamespaced.'::extractId($'.$nameOrId.');',
                    '    return $this->findBy(['.$namespacedShortName.'::_'.$associationMappingName.'() => $'.$associationMappingName.'Id]);',
                    '}',
                    '',
                ];
                $result .= implode(self::LS.'    ',$code);

                // getOneFirstBy

                $methodName = 'getOneRandomBy'.\ucfirst($associationMappingName);
                $code = [
                    '',
                    '/**',
                    ' * @noinspection PhpDocMissingThrowsInspection',
                    ' * '.$assocComment,
                    ' * Получить ПЕРВУЮ ПОПАВШУЮСЯ подходящую сущность из всех подходящих, либо NULL',
                    ' * @param int|'.$targetEntityShortNamespaced.' $'.$nameOrId,
                    ' *',
                    ' * @return null|'.$namespacedShortName,
                    ' */',
                    'public function '.$methodName.'($'.$nameOrId.'): ?'.$namespacedShortName,
                    '{',
                    '    $'.$associationMappingName.'Id = '.$targetEntityShortNamespaced.'::extractId($'.$nameOrId.');',
                    '    /** @var \\'.EntityRepository::class.' $this */',
                    '    $qb = $this->createQueryBuilder(\'base\');',
                    '    $qb->innerJoin('.$targetEntityShortNamespaced.'::class, \'joined\', Expr\Join::WITH, '.$importedDqlHelperClass.'::andX($qb,[',
                    '        '.$importedDqlHelperClass.'::dql($qb,'.$namespacedShortName.'::_'.$associationMappingName.'(\'base\').\' = joined\'),',
                    '        '.$importedDqlHelperClass.'::eq($qb,'.$targetEntityShortNamespaced.'::_id(\'joined\'),$'.$associationMappingName.'Id),',
                    '    ]));',
                    '    /** @noinspection PhpUnhandledExceptionInspection */',
                    '    return $qb->setMaxResults(1)->getQuery()->getOneOrNullResult();',
                    '}',
                    '',
                ];
                $result .= implode(self::LS.'    ',$code);

                $code = [
                    '',
                    '/**',
                    ' * @noinspection PhpDocMissingThrowsInspection',
                    ' * '.$assocComment,
                    ' * Получить ПЕРВУЮ ПОПАВШУЮСЯ подходящую сущность из всех подходящих, либо бросить исключение',
                    ' * @param int|'.$targetEntityShortNamespaced.' $'.$nameOrId,
                    ' * @param string|\\Exception|null '.$exceptionMessageVar.' a message for exception (or exception instance) that will raise if query fails',
                    ' *',
                    ' * @return '.$namespacedShortName,
                    ' */',
                    'public function '.$methodName.'OrFail($'.$nameOrId.', '.$exceptionMessageVar.' = null): '.$namespacedShortName,
                    '{',
                    '    $'.$associationMappingName.' = $this->'.$methodName.'($'.$nameOrId.');',

                    '    if (!$'.$associationMappingName.' instanceof '.$namespacedShortName.') {',
                    '',
                    "        if ($exceptionMessageVar instanceof \\Exception) {",
                    '            /** @noinspection PhpUnhandledExceptionInspection */',
                    '            /** @var \RuntimeException '.$exceptionMessageVar.' */',
                    "            throw $exceptionMessageVar;",
                    '        }',
                    '',
                    "        if (\is_string($exceptionMessageVar)) {",
                    '            /** @noinspection PhpUnhandledExceptionInspection */',
                    "            throw new \\".\DomainException::class."($exceptionMessageVar);",
                    '        }',
                    '',
                    '        /** @noinspection PhpUnhandledExceptionInspection */',
                    "        throw new \\".\DomainException::class."('Failed to find $shortName by $associationMappingName');",
                    '    }',
                    '    return $'.$associationMappingName.';',
                    '}',
                    '',
                ];
                $result .= implode(self::LS.'    ',$code);

                // getOneBy

                $methodName = 'getOneBy'.\ucfirst($associationMappingName);
                $code = [
                    '',
                    '/**',
                    ' * '.$assocComment,
                    ' * @param int|'.$targetEntityShortNamespaced.' $'.$nameOrId,
                    ' *',
                    ' * @return null|'.$namespacedShortName,
                    ' * @throws \\'.NonUniqueResultException::class,
                    ' */',
                    'public function '.$methodName.'($'.$nameOrId.'): ?'.$namespacedShortName,
                    '{',
                    '    $'.$associationMappingName.'Id = '.$targetEntityShortNamespaced.'::extractId($'.$nameOrId.');',
                    '    /** @var \\'.EntityRepository::class.' $this */',
                    '    $qb = $this->createQueryBuilder(\'base\');',
                    '    $qb->innerJoin('.$targetEntityShortNamespaced.'::class, \'joined\', Expr\Join::WITH, '.$importedDqlHelperClass.'::andX($qb,[',
                    '        '.$importedDqlHelperClass.'::dql($qb,'.$namespacedShortName.'::_'.$associationMappingName.'(\'base\').\' = joined\'),',
                    '        '.$importedDqlHelperClass.'::eq($qb,'.$targetEntityShortNamespaced.'::_id(\'joined\'),$'.$associationMappingName.'Id),',
                    '    ]));',
                    '    return $qb->getQuery()->getOneOrNullResult();',
                    '}',
                    '',
                ];
                $result .= implode(self::LS.'    ',$code);

                $code = [
                    '',
                    '/**',
                    ' * @noinspection PhpDocMissingThrowsInspection',
                    ' * '.$assocComment,
                    ' * @param int|'.$targetEntityShortNamespaced.' $'.$nameOrId,
                    ' * @param string|\\Exception|null '.$exceptionMessageVar.' a message for exception (or exception instance) that will raise if query fails',
                    ' *',
                    ' * @return '.$namespacedShortName,
                    ' * @throws \\'.NonUniqueResultException::class,
                    ' */',
                    'public function '.$methodName.'OrFail($'.$nameOrId.', '.$exceptionMessageVar.' = null): '.$namespacedShortName,
                    '{',
                    '    $'.$associationMappingName.' = $this->'.$methodName.'($'.$nameOrId.');',

                    '    if (!$'.$associationMappingName.' instanceof '.$namespacedShortName.') {',
                    '',
                    "        if ($exceptionMessageVar instanceof \\Exception) {",
                    '            /** @noinspection PhpUnhandledExceptionInspection */',
                    '            /** @var \RuntimeException '.$exceptionMessageVar.' */',
                    "            throw $exceptionMessageVar;",
                    '        }',
                    '',
                    "        if (\is_string($exceptionMessageVar)) {",
                    '            /** @noinspection PhpUnhandledExceptionInspection */',
                    "            throw new \\".\DomainException::class."($exceptionMessageVar);",
                    '        }',
                    '',
                    '        /** @noinspection PhpUnhandledExceptionInspection */',
                    "        throw new \\".\DomainException::class."('Failed to find $shortName by $associationMappingName');",
                    '    }',
                    '    return $'.$associationMappingName.';',
                    '}',
                    '',
                ];
                $result .= implode(self::LS.'    ',$code);

            }
        }

        $code = [
            '',
            '/**',
            ' * Получить коллекцию, проиндексированную по ID, по массиву ID',
            ' * @param int[] $ids',
            ' *',
            ' * @return '.$namespacedShortName.'[]',
            ' * @throws \\'.QueryException::class,
            ' */',
            'public function getByIdsIndexedById(array $ids): array',
            '{',
            '    if (\count($ids) === 0) {',
            '        return [];',
            '    }',
            '',
            '    /** @var \\'.QueryBuilder::class.' $qb */',
            '    $qb = $this->createQueryBuilder(\'base\');',
            '    $qb->indexBy(\'base\', \'base.id\');',
            '    '.$importedDqlHelperClass.'::requireAll($qb, [',
            '        '.$importedDqlHelperClass.'::in($qb, \'base.id\', $ids),',
            '    ]);',
            '    return $qb->getQuery()->getResult();',
            '}',
            '',
            '',
        ];

        $result .= implode(self::LS.'    ',$code);

        $result .= '}';
        return $result;

    }

    /**
     * @param string $repoInterface
     *
     * @return GenerateReposOperation
     */
    public function setRepoInterface(string $repoInterface): GenerateReposOperation
    {
        $this->repoInterface = $repoInterface;
        return $this;
    }

}
