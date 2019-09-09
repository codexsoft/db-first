<?php

namespace CodexSoft\DatabaseFirst\Orm\ModelInterfaces;

/**
 * Сущности, имплементирующие этот интерфейс, будут обработаны в event LifecycleEventSubscriber-е.
 * Interface PostLoadingInterface
 */
interface PostLoadingInterface
{

    public function onPostLoad(): void;

}