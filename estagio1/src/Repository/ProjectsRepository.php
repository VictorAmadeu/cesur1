<?php

namespace App\Repository;

use App\Entity\Projects;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProjectsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Projects::class);
    }

    // Función para obtener proyectos por ID de la compañía
    public function findByCompanyId(int $companyId)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.company = :companyId')
            ->setParameter('companyId', $companyId)
            ->getQuery()
            ->getResult();
    }
}
