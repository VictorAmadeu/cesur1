<?php

namespace App\Repository;

use App\Entity\UserWorkSchedule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\User;

/**
 * @method UserWorkSchedule|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserWorkSchedule|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserWorkSchedule[]    findAll()
 * @method UserWorkSchedule[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserWorkScheduleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserWorkSchedule::class);
    }

    public function findActiveByUserAtDate(User $user, string $date): ?UserWorkSchedule
    {
        $qb = $this->createQueryBuilder('uws');

        return $qb
            ->andWhere('uws.user = :user')
            ->andWhere('uws.startDate <= :date')
            ->andWhere('uws.endDate IS NULL OR uws.endDate >= :date')
            ->setParameter('user', $user)
            ->setParameter('date', $date) // ← CORREGIR SEGÚN TIPO EN BD
            ->getQuery()
            ->getOneOrNullResult();
    }



    // /**
    //  * @return UserWorkSchedule[] Returns an array of UserWorkSchedule objects
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
    public function findOneBySomeField($value): ?UserWorkSchedule
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
