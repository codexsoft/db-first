<?php

namespace CodexSoft\DatabaseFirst\Orm\ModelInterfaces;

use Doctrine\ORM\Event\LifecycleEventArgs;

/**
 * Сущности, имплементирующие этот интерфейс, будут обработаны в event LifecycleEventSubscriber-е.
 */
interface PostUpdateInterface
{

    public function onPostUpdate(LifecycleEventArgs $args): void;

}
