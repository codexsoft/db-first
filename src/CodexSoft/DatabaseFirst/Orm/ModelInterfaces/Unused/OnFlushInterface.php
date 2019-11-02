<?php

namespace CodexSoft\DatabaseFirst\Orm\ModelInterfaces\Unused;

use Doctrine\ORM\Event\OnFlushEventArgs;

/**
 * @deprecated something not usable
 */
interface OnFlushInterface
{

    function onFlush(OnFlushEventArgs $args): void;

}
