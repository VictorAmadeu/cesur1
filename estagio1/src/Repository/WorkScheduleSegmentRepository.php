<?php

namespace App\Repository;
use App\Entity\WorkScheduleSegment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method WorkScheduleSegment|null find($id, $lockMode = null, $lockVersion = null)
 * @method WorkScheduleSegment|null findOneBy(array $criteria, array $orderBy = null)
 * @method WorkScheduleSegment[]    findAll()
 * @method WorkScheduleSegment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WorkScheduleSegmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkScheduleSegment::class);
    }

    // /**
    //  * @return WorkScheduleSegment[] Returns an array of WorkScheduleSegment objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('m.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?WorkScheduleSegment
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
