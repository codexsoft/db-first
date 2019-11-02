<?php

namespace CodexSoft\DatabaseFirst\Orm;

use Doctrine\ORM\Events;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use CodexSoft\DatabaseFirst\Orm\ModelInterfaces\PostPersistInterface;
use CodexSoft\DatabaseFirst\Orm\ModelInterfaces\PostLoadInterface;
use CodexSoft\DatabaseFirst\Orm\ModelInterfaces\PrePersistInterface;
use CodexSoft\DatabaseFirst\Orm\ModelInterfaces\ValidateableInterface;

class DoctrineLifecycleEventSubscriber implements EventSubscriber
{

    public function getSubscribedEvents()
    {
        return array(
            Events::preRemove,
            Events::postRemove,
            Events::prePersist,
            Events::postPersist,
            Events::preUpdate,
            Events::postUpdate,
            Events::postLoad,
            Events::loadClassMetadata,
            Events::onClassMetadataNotFound,
            Events::preFlush,
            Events::onFlush,
            Events::postFlush,
            Events::onClear,
        );
    }

    public function preRemove(LifecycleEventArgs $args){ $x=1; }
    public function postRemove(LifecycleEventArgs $args){ $x=1; }
    public function prePersist(LifecycleEventArgs $args){ $x=1; }
    public function postPersist(LifecycleEventArgs $args){ $x=1; }
    public function preUpdate(\Doctrine\ORM\Event\PreUpdateEventArgs $args){ $x=1; }
    public function postUpdate(LifecycleEventArgs $args){ $x=1; }
    public function postLoad(LifecycleEventArgs $args){ $x=1; }
    public function loadClassMetadata(\Doctrine\ORM\Event\LoadClassMetadataEventArgs $args){ $x=1; }
    public function onClassMetadataNotFound(\Doctrine\ORM\Event\OnClassMetadataNotFoundEventArgs $args){ $x=1; }
    public function preFlush(\Doctrine\ORM\Event\PreFlushEventArgs $args){ $x=1; }
    public function onFlush(\Doctrine\ORM\Event\OnFlushEventArgs $args){ $x=1; }
    public function postFlush(\Doctrine\ORM\Event\PostFlushEventArgs $args){ $x=1; }
    public function onClear(\Doctrine\ORM\Event\OnClearEventArgs $args){ $x=1; }






    public function getSubscribedEvents()
    {
        return array(
            Events::prePersist,
            Events::postLoad,
            Events::postPersist,
        );
    }

    /**
     * using postLoad, as Doctrine does not call entity __constructors when hydrating objects from DB
     * http://www.culttt.com/2014/08/04/understanding-doctrine-2-lifecycle-events/
     * https://www.doctrine-project.org/2010/03/21/doctrine-2-give-me-my-constructor-back.html
     * https://stackoverflow.com/questions/6555237/how-does-doctrine-2-retrieve-entities-without-calling-the-entitys-constructor
     *
     * @param LifecycleEventArgs $args
     */
    public function postLoad(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();
        if ($entity instanceof PostLoadInterface) {
            $entity->onPostLoad();
        }
    }

    public function prePersist(LifecycleEventArgs $args): void
    {

        $entity = $args->getObject();

        if ( $entity instanceof ValidateableInterface ) {
            $entity->validate();
        }

        if ( $entity instanceof PrePersistInterface ) {
            $entity->onPrePersist();
        }

    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ( $entity instanceof PostPersistInterface ) {
            $entity->onPostPersist();
        }
    }

}
