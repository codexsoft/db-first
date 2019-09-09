<?php

namespace CodexSoft\DatabaseFirst\Orm\ModelInterfaces;

/**
 * Сущности, имплементирующие этот интерфейс, будут обработаны в event LifecycleEventSubscriber-е.
 * Interface PrePersistInterface
 */
interface PrePersistInterface
{

    function prePersist(): void;

}