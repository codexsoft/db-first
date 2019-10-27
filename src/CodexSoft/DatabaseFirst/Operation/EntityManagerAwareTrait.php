<?php

namespace CodexSoft\DatabaseFirst\Operation;

use Doctrine\ORM\EntityManager;

/**
 * @deprecated
 */
trait EntityManagerAwareTrait
{

    /** @var EntityManager */
    private $em;

    /**
     * @param EntityManager $em
     *
     * @return static
     */
    public function setEm(EntityManager $em): self
    {
        $this->em = $em;
        return $this;
    }

}
