<?php


namespace CodexSoft\DatabaseFirst\Operation;


use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\Console\MetadataFilter;
use Doctrine\ORM\Tools\DisconnectedClassMetadataFactory;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

abstract class AbstractBaseOperation implements LoggerAwareInterface
{
    use DoctrineOrmSchemaAwareTrait;
    protected LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    /**
     * @param LoggerInterface $logger
     *
     * @return static
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * @param EntityManager $em
     *
     * @return ClassMetadata[]
     */
    protected function getMetadata(EntityManager $em): array
    {
        $cmf = new DisconnectedClassMetadataFactory();
        $cmf->setEntityManager( $em );

        $metadatas = $cmf->getAllMetadata();
        if ($this->doctrineOrmSchema->metadataFilter) {
            $metadatas = MetadataFilter::filter($metadatas, $this->doctrineOrmSchema->metadataFilter);
        }

        if (!\count($metadatas)) {
            throw new \InvalidArgumentException('No Metadata Classes to process.');
        }

        return $metadatas;
    }

    abstract public function execute();
}
