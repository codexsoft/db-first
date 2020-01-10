<?php /** @noinspection PhpUnused */

namespace CodexSoft\DatabaseFirst\Orm;

use Doctrine\ORM\Events;
use Doctrine\Common\EventSubscriber;

class DoctrineEntityManagerLifecycleEventSubscriber implements EventSubscriber
{
    /**
     * @return array|string[]
     * todo: maybe make it configurable?
     */
    public function getSubscribedEvents()
    {
        return [
            Events::loadClassMetadata,
            Events::onClassMetadataNotFound,
            Events::preFlush,
            Events::onFlush,
            Events::postFlush,
            Events::onClear,
        ];
    }

    public function loadClassMetadata(\Doctrine\ORM\Event\LoadClassMetadataEventArgs $args)
    {
        //$args->getObjectManager();
        //$args->getEntityManager();
        //$args->getClassMetadata();
    }

    public function onClassMetadataNotFound(\Doctrine\ORM\Event\OnClassMetadataNotFoundEventArgs $args)
    {
        //$args->getClassName();
        //$args->getFoundMetadata();
        //$args->getObjectManager();
        //$args->setFoundMetadata();
    }

    public function preFlush(\Doctrine\ORM\Event\PreFlushEventArgs $args)
    {
        //$args->getEntityManager();
    }

    public function onFlush(\Doctrine\ORM\Event\OnFlushEventArgs $args)
    {
        //$args->getEntityManager();
    }

    public function postFlush(\Doctrine\ORM\Event\PostFlushEventArgs $args)
    {
        //$args->getEntityManager();
    }

    public function onClear(\Doctrine\ORM\Event\OnClearEventArgs $args): void
    {
        //$args->getEntityClass();
        //$args->getEntityManager();
        //$args->clearsAllEntities();
    }

}
