<?php /** @noinspection PhpUnused */

namespace CodexSoft\DatabaseFirst\Orm;

use CodexSoft\DatabaseFirst\Orm\ModelInterfaces\PostRemoveInterface;
use CodexSoft\DatabaseFirst\Orm\ModelInterfaces\PostUpdateInterface;
use CodexSoft\DatabaseFirst\Orm\ModelInterfaces\PreRemoveInterface;
use CodexSoft\DatabaseFirst\Orm\ModelInterfaces\PreUpdateInterface;
use Doctrine\ORM\Events;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use CodexSoft\DatabaseFirst\Orm\ModelInterfaces\PostPersistInterface;
use CodexSoft\DatabaseFirst\Orm\ModelInterfaces\PostLoadInterface;
use CodexSoft\DatabaseFirst\Orm\ModelInterfaces\PrePersistInterface;

/**
 * executes entity callbacks if defined
 */
class DoctrineEntityLifecycleEventSubscriber implements EventSubscriber
{

    /**
     * @return array|string[]
     * todo: maybe make it configurable?
     */
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
        );
    }

    public function preRemove(LifecycleEventArgs $args)
    {
        //$args->getEntityManager();
        $entity = $args->getEntity();

        if ($entity instanceof PreRemoveInterface) {
            $entity->onPreRemove($args);
        }
    }

    public function postRemove(LifecycleEventArgs $args)
    {
        //$args->getEntityManager();
        $entity = $args->getEntity();

        if ($entity instanceof PostRemoveInterface) {
            $entity->onPostRemove($args);
        }
    }

    public function preUpdate(\Doctrine\ORM\Event\PreUpdateEventArgs $args)
    {
        //$args->getEntityManager();
        $entity = $args->getEntity();

        if ($entity instanceof PreUpdateInterface) {
            $entity->onPreUpdate($args);
        }
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        //$args->getEntityManager();
        $entity = $args->getEntity();

        if ($entity instanceof PostUpdateInterface) {
            $entity->onPostUpdate($args);
        }
    }

    /**
     * using postLoad, as Doctrine does not call entity __constructors when hydrating objects from DB
     * http://www.culttt.com/2014/08/04/understanding-doctrine-2-lifecycle-events/
     * https://www.doctrine-project.org/2010/03/21/doctrine-2-give-me-my-constructor-back.html
     * https://stackoverflow.com/questions/6555237/how-does-doctrine-2-retrieve-entities-without-calling-the-entitys-constructor
     *
     * @param LifecycleEventArgs $args
     */
    public function postLoad(LifecycleEventArgs $args): void
    {
        $entity = $args->getEntity();

        if ($entity instanceof PostLoadInterface) {
            $entity->onPostLoad($args);
        }
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getEntity();

        //if ($entity instanceof ValidateableInterface) {
        //    $entity->validate();
        //}

        if ($entity instanceof PrePersistInterface) {
            $entity->onPrePersist($args);
        }
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getEntity();

        if ($entity instanceof PostPersistInterface) {
            $entity->onPostPersist($args);
        }
    }

}
