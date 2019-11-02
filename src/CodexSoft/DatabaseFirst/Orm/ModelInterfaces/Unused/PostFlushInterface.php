<?php

namespace CodexSoft\DatabaseFirst\Orm\ModelInterfaces\Unused;

use Doctrine\ORM\Event\PostFlushEventArgs;

/**
 * @deprecated something not usable
 */
interface PostFlushInterface
{

    function onPostFlush(PostFlushEventArgs $args): void;

}
