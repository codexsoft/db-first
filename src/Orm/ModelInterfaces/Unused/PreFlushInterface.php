<?php

namespace CodexSoft\DatabaseFirst\Orm\ModelInterfaces\Unused;

use Doctrine\ORM\Event\PreFlushEventArgs;

/**
 * @deprecated something not usable
 */
interface PreFlushInterface
{

    function onPreFlush(PreFlushEventArgs $args): void;

}
