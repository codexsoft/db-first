<?php

namespace CodexSoft\DatabaseFirst\Orm\ModelInterfaces;

/**
 * Сущности, имплементирующие этот интерфейс, будут обработаны в event LifecycleEventSubscriber-е.
 * Interface PostPersistInterface
 */
interface PostPersistInterface
{

    public function postPersist(): void;

}