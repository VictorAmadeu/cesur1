<?php

namespace App\Repository;

use App\Entity\License;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<License>
 *
 * @method License|null find($id, $lockMode = null, $lockVersion = null)
 * @method License|null findOneBy(array $criteria, array $orderBy = null)
 * @method License[]    findAll()
 * @method License[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LicenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, License::class);
    }

    public function getTimesByUserYear($user, $year): array
    {
        $startDate = sprintf('%d-01-01', $year); // Inicio del año
        $endDate = sprintf('%d-12-31', $year);   // Fin del año
    
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->andWhere('t.dateStart BETWEEN :startDate AND :endDate')
            ->andWhere('t.isActive = true')
            ->setParameter('user', $user)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();
    }    
    


//    /**
//     * @return License[] Returns an array of License objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('l')
//            ->andWhere('l.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('l.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?License
//    {
//        return $this->createQueryBuilder('l')
//            ->andWhere('l.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
