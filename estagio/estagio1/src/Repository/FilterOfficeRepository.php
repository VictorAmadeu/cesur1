<?php

namespace App\Repository;

use App\Entity\FilterOffice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FilterOffice>
 *
 * @method FilterOffice|null find($id, $lockMode = null, $lockVersion = null)
 * @method FilterOffice|null findOneBy(array $criteria, array $orderBy = null)
 * @method FilterOffice[]    findAll()
 * @method FilterOffice[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FilterOfficeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FilterOffice::class);
    }

//    /**
//     * @return FilterOffice[] Returns an array of FilterOffice objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('r.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?FilterOffice
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
