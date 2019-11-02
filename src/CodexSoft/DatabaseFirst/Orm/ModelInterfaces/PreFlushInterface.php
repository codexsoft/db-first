<?php

namespace CodexSoft\DatabaseFirst\Orm\ModelInterfaces;

use Doctrine\ORM\Event\PreFlushEventArgs;

/**
 * Сущности, имплементирующие этот интерфейс, будут обработаны в event LifecycleEventSubscriber-е.
 */
interface PreFlushInterface
{

    function onPreFlush(PreFlushEventArgs $args): void;

}
