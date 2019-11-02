<?php

namespace CodexSoft\DatabaseFirst\Orm\ModelInterfaces\Unused;

use Doctrine\ORM\Event\OnClassMetadataNotFoundEventArgs;

/**
 * @deprecated something not usable
 */
interface ClassMetadataNotFoundInterface
{

    public function onClassMetadataNotFound(OnClassMetadataNotFoundEventArgs $args): void;

}
