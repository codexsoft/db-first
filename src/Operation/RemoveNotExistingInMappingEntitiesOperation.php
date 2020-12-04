<?php


namespace CodexSoft\DatabaseFirst\Operation;

use CodexSoft\Code\Files\Files;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Tools\Console\MetadataFilter;
use Doctrine\ORM\Tools\DisconnectedClassMetadataFactory;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

use function Stringy\create as str;

class RemoveNotExistingInMappingEntitiesOperation extends AbstractBaseOperation
{
    protected EntityManager $em;

    public function execute(): void
    {
        if (!isset($this->doctrineOrmSchema)) {
            throw new \InvalidArgumentException('Required doctrineOrmSchema is not provided');
        }

        $this->em = $this->doctrineOrmSchema->getEntityManager();

        $cmf = new DisconnectedClassMetadataFactory();
        $cmf->setEntityManager($this->em);
        $metadatas = $cmf->getAllMetadata();

        if ($this->doctrineOrmSchema->metadataFilter) {
            $metadatas = MetadataFilter::filter($metadatas, $this->doctrineOrmSchema->metadataFilter);
        }

        if (!\count($metadatas)) {
            throw new \InvalidArgumentException('No Metadata Classes to process.');
        }

        $ds = $this->doctrineOrmSchema;

        $fs = new Filesystem;
        $mappingFiles = Files::listFilesWithPath($ds->getPathToMapping());
        $this->logger->info("Found mapping files");
        foreach ($mappingFiles as $mappingFile) {
            echo "\n$mappingFile";
        }
        echo "\n";

        foreach ($metadatas as $metadata) {

            if (!str($metadata->name)->startsWith('remove_')) {
                $this->logger->debug('Skip entity '.$this->getClassName($metadata).' — mapping exists');
                continue;
            }

            $filesToDelete = [
                $ds->getPathToMapping().'/'.str_replace('\\', '.', $metadata->name).'.php',
                $ds->getPathToModelsTraits().'/'.$this->getClassName($metadata).'Trait.php',
                $ds->getPathToModels().'/'.$this->getClassName($metadata).'.php',
                $ds->getPathToModelAwareTraits().'/'.$this->getClassName($metadata).'AwareTrait.php',
                $ds->getPathToRepositories().'/'.$this->getClassName($metadata).'Repository.php',
                $ds->getPathToRepositories().'/'.$this->getClassName($metadata).'Interface.php',
                $ds->getPathToRepositories().'/Generated/'.$this->getClassName($metadata).'RepositoryTrait.php',
            ];

            $this->logger->info('Removing entity '.$this->getClassName($metadata).' files ');
            foreach ($filesToDelete as $fileToDelete) {
                if ($fs->exists($fileToDelete) && \is_file($fileToDelete)) {
                    try {
                        $fs->remove($fileToDelete);
                        $this->logger->debug('Removed file '.$fileToDelete);
                    } catch (IOException $e) {
                        $this->logger->warning($e->getMessage());
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
    protected function getClassName(ClassMetadataInfo $metadata): string
    {
        return ($pos = strrpos($metadata->name, '\\'))
            ? substr($metadata->name, $pos + 1, strlen($metadata->name)) : $metadata->name;
    }

}
