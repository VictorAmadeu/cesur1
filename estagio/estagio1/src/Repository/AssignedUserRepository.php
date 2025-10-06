<?php

namespace App\Repository;

use App\Entity\AssignedUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AssignedUserRepository>
 *
 * @method AssignedUserRepository|null find($id, $lockMode = null, $lockVersion = null)
 * @method AssignedUserRepository|null findOneBy(array $criteria, array $orderBy = null)
 * @method AssignedUserRepository[]    findAll()
 * @method AssignedUserRepository[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AssignedUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AssignedUser::class);
    }

//    /**
//     * @return AssignedUserRepository[] Returns an array of AssignedUserRepository objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('s.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?AssignedUserRepository
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
