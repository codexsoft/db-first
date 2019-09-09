<?php

namespace CodexSoft\DatabaseFirst\Orm;

use Doctrine\ORM\Events;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use CodexSoft\DatabaseFirst\Orm\ModelInterfaces\PostPersistInterface;
use CodexSoft\DatabaseFirst\Orm\ModelInterfaces\PostLoadingInterface;
use CodexSoft\DatabaseFirst\Orm\ModelInterfaces\PrePersistInterface;
use CodexSoft\DatabaseFirst\Orm\ModelInterfaces\ValidateableInterface;

class DoctrineLifecycleEventSubscriber implements EventSubscriber
{
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
        if ($entity instanceof PostLoadingInterface) {
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
            $entity->prePersist();
        }

    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ( $entity instanceof PostPersistInterface ) {
            $entity->postPersist();
        }
    }

}