<?php


namespace CodexSoft\DatabaseFirst\Operation;

use CodexSoft\Code\Shortcuts;
use CodexSoft\OperationsSystem\Operation;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Tools\Console\MetadataFilter;
use Doctrine\ORM\Tools\DisconnectedClassMetadataFactory;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @method void execute()
 */
class RemoveNotExistingInMappingEntitiesOperation extends Operation
{

    use DoctrineOrmSchemaAwareTrait;

    const ID = '81311eaw-a311-431b-bbb5-7931c6e7c6ab';

    /** @var EntityManager */
    protected $em;

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
        $metadatas = MetadataFilter::filter($metadatas, $this->doctrineOrmSchema->metadataFilter);

        if (!count($metadatas)) {
            throw $this->genericException('No Metadata Classes to process.');
        }

        $ds = $this->doctrineOrmSchema;

        $fs = new Filesystem;

        foreach ($metadatas as $metadata) {

            /** @var string[] $filesToDelete */
            $filesToDelete = [
                $ds->getPathToModelsTraits().'/'.$this->getClassName($metadata).'Trait.php',
                $ds->getPathToModels().'/'.$this->getClassName($metadata).'.php',
                $ds->getPathToModelAwareTraits().'/'.$this->getClassName($metadata).'AwareTrait.php',
                $ds->getPathToRepositories().'/'.$this->getClassName($metadata).'Repository.php',
                $ds->getPathToRepositories().'/'.$this->getClassName($metadata).'Interface.php',
                $ds->getPathToRepositories().'/Generated/'.$this->getClassName($metadata).'RepositoryTrait.php',
            ];

            foreach ($filesToDelete as $fileToDelete) {
                if ($fs->exists($fileToDelete) && \is_file($fileToDelete)) {
                    try {
                        $fs->remove($fileToDelete);
                        $this->getLogger()->debug('Removed file '.$fileToDelete);
                    } catch (IOException $e) {
                        $this->getLogger()->warning($e->getMessage());
                    }
                }
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

}
