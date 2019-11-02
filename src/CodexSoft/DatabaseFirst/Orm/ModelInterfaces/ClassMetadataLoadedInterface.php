<?php

namespace CodexSoft\DatabaseFirst\Orm\ModelInterfaces;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;

/**
 * Сущности, имплементирующие этот интерфейс, будут обработаны в event LifecycleEventSubscriber-е.
 */
interface ClassMetadataLoadedInterface
{

    public function onClassMetadataLoaded(LoadClassMetadataEventArgs $args): void;

}
