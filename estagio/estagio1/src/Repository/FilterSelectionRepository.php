<?php

namespace App\Repository;

use App\Entity\FilterSelection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FilterSelection>
 *
 * @method FilterSelection|null find($id, $lockMode = null, $lockVersion = null)
 * @method FilterSelection|null findOneBy(array $criteria, array $orderBy = null)
 * @method FilterSelection[]    findAll()
 * @method FilterSelection[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FilterSelectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FilterSelection::class);
    }

//    /**
//     * @return FilterSelection[] Returns an array of FilterSelection objects
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

//    public function findOneBySomeField($value): ?FilterSelection
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
