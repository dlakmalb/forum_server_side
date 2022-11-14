<?php

namespace App\Utils;

use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;

trait RepositoryTrait
{
    private $em;

    public function __construct(ManagerRegistry $registry)
    {
        $this->em = $registry;
    }

    /**
     * Get entity manager
     *
     * @return EntityManager
     */
    private function getEntityManager(): EntityManager
    {
        return $this->em->getManager();
    }

    /**
     * Get entity repository
     *
     * @param string $entityName
     * @return ObjectRepository
     */
    private function getEntityRepository(string $entityName): ObjectRepository
    {
        return $this->em->getRepository($entityName);
    }
}
