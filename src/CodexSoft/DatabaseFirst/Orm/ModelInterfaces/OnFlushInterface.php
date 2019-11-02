<?php

namespace CodexSoft\DatabaseFirst\Orm\ModelInterfaces;

use Doctrine\ORM\Event\OnFlushEventArgs;

/**
 * Сущности, имплементирующие этот интерфейс, будут обработаны в event LifecycleEventSubscriber-е.
 */
interface OnFlushInterface
{

    function onFlush(OnFlushEventArgs $args): void;

}
