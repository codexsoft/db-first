<?php

namespace CodexSoft\DatabaseFirst\Orm\ModelInterfaces\Unused;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;

/**
 * @deprecated something not usable
 */
interface ClassMetadataLoadedInterface
{

    public function onClassMetadataLoaded(LoadClassMetadataEventArgs $args): void;

}
