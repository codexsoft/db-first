<?php

namespace CodexSoft\DatabaseFirst\Orm\ModelInterfaces;

use Doctrine\ORM\Event\OnClassMetadataNotFoundEventArgs;

/**
 * Сущности, имплементирующие этот интерфейс, будут обработаны в event LifecycleEventSubscriber-е.
 */
interface ClassMetadataNotFoundInterface
{

    public function onClassMetadataNotFound(OnClassMetadataNotFoundEventArgs $args): void;

}
