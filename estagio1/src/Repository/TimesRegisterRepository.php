<?php

namespace App\Repository;

use App\Entity\TimesRegister;
use App\Entity\User;
use App\Entity\Companies;
use App\Entity\Office;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Enum\JustificationStatus;

/**
 * @extends ServiceEntityRepository<TimesRegister>
 *
 * @method TimesRegister|null find($id, $lockMode = null, $lockVersion = null)
 * @method TimesRegister|null findOneBy(array $criteria, array $orderBy = null)
 * @method TimesRegister[]    findAll()
 * @method TimesRegister[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TimesRegisterRepository extends ServiceEntityRepository
    {
        public function __construct(ManagerRegistry $registry)
        {
            parent::__construct($registry, TimesRegister::class);
        }

    /**
     * @return TimesRegister[] Returns an array of TimesRegister objects
     */
    public function getTimesByUserDate($user, \DateTime $date): array
    {
        // Extraemos solo la parte de la fecha y comparamos con la fecha almacenada
        $dateFormatted = $date->format('Y-m-d');

        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->andWhere("t.date = :date") // Formato solo de fecha
            ->setParameter('user', $user)
            ->setParameter('date', $dateFormatted) // Usamos el valor formateado
            ->orderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getTimesByUserDatesRange($user, $startDate, $endDate): array
    {
        $queryBuilder = $this->createQueryBuilder('t')
            ->select('t.date AS date, t.totalTime')
            ->andWhere('t.user = :user')
            ->andWhere('t.date BETWEEN :startDate AND :endDate')
            ->andWhere('t.id IN (
                SELECT MAX(t2.id) 
                FROM App\Entity\TimesRegister t2
                WHERE t2.user = t.user 
                AND t2.date = t.date
                GROUP BY t2.date
            )')
            ->setParameter('user', $user)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('t.date', 'ASC');

        $results = $queryBuilder->getQuery()->getResult();

        $allDays = [];
        $currentDay = $startDate;
        $end = $endDate;

        while ($currentDay <= $end) {
            $formattedDate = $currentDay->format('Y-m-d');
            $totalTime = "00:00:00";

            foreach ($results as $result) {
                if ($result['date']->format('Y-m-d') === $formattedDate) {
                    if ($result['totalTime'] instanceof \DateTime) {
                        $totalTime = $result['totalTime']->format('H:i:s');
                    } else {
                        $totalTime = $result['totalTime'];
                    }
                    break;
                }
            }
            
            $allDays[] = [
                'date' => $currentDay->format('Y-m-d'),
                'totalTime' => $totalTime
            ];

            $currentDay->modify('+1 day');
        }

        return $allDays;
    }

    public function findAllByCurrentDateAndEqualHours(): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.date = :today') // Filtro por fecha actual
            ->setParameter('today', (new \DateTime())->format('Y-m-d'))
            ->andWhere('t.hourStart = t.hourEnd')  // Filtro donde hourStart es igual a hourEnd
        ;
    
        return $qb->getQuery()->getResult();
    }

    public function findByFilters(?User $user, ?Companies $company, ?string $start, ?string $end)
    {
        if (!$start || !$end) {
            throw new \InvalidArgumentException("Las fechas de inicio y fin deben ser proporcionadas.");
        }

        $qb = $this->createQueryBuilder('tr')
            ->join('tr.user', 'u')
            ->leftJoin('u.company', 'c')
            ->where('tr.date BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $start)
            ->setParameter('endDate', $end);

        if ($user) {
            $qb->andWhere('u = :user')
                ->setParameter('user', $user);
        }

        if ($company) {
            $qb->andWhere('c = :company')
                ->setParameter('company', $company);
        }

        return $qb->getQuery()->getResult();
    }

    public function findByUserIdsAndDateRange(array $userIds, string $start, string $end)
    {
        if (!$start || !$end) {
            throw new \InvalidArgumentException("Las fechas de inicio y fin deben ser proporcionadas.");
        }

        $qb = $this->createQueryBuilder('tr')
            ->where('tr.user IN (:userIds)')
            ->setParameter('userIds', $userIds)
            ->andWhere('tr.date BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $start)
            ->setParameter('endDate', $end)
            ->orderBy('tr.date', 'ASC');

        return $qb->getQuery()->getResult();
    }   


    public function findPendingRegistersByUserAndDateRange(User $user, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $qb = $this->createQueryBuilder('tr')
            ->where('tr.user = :user')
            ->setParameter('user', $user)
            ->andWhere('tr.date BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->andWhere('tr.justificationStatus = :justificationStatus')
            ->setParameter('justificationStatus', JustificationStatus::PENDING)
            ->andWhere('tr.status = :status')
            ->setParameter('status', 1);

        return $qb->getQuery()->getResult();
    }

}
