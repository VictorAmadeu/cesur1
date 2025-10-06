<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Accounts;
use App\Entity\Companies;
use App\Entity\FilterSelection;
use Doctrine\ORM\EntityManagerInterface;

class FilterSelectionService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function findFilterSelectionByUser($user): ?FilterSelection
    {
        $filterSelection = $this->entityManager->getRepository(FilterSelection::class)->findOneBy([
            'user' => $user,
        ]);

        return $filterSelection;
    }

    public function findAccountById($accountId): ?Accounts
    {
        return $this->entityManager->getRepository(Accounts::class)->findOneBy([
            'id' => $accountId,
        ]);
    }

    public function findCompanyById($companyId): ?Companies
    {
        return $this->entityManager->getRepository(Companies::class)->findOneBy([
            'id' => $companyId,
        ]);
    }

    public function findUserById($userId): ?User
    {
        return $this->entityManager->getRepository(User::class)->findOneBy([
            'id' => $userId,
        ]);
    }

    public function saveFilterSelection($user, $accountId, $companyId, $userSelectedId, ?string $dateStart, ?string $dateEnd): void
    {
        $filterSelection = $this->findFilterSelectionByUser($user);
        $accountSelection = $this->findAccountById($accountId);
        $companySelection = $this->findCompanyById($companyId);
        $userSelectedSelection = $this->findUserById($userSelectedId);

        $dateStartObj = $dateStart ? new \DateTime($dateStart) : null;
        $dateEndObj = $dateEnd ? new \DateTime($dateEnd) : null;

        if ($filterSelection) {
            if ($accountSelection) {
                $filterSelection->setAccount($accountSelection);
            }
            if ($companySelection) {
                $filterSelection->setCompany($companySelection);
            }
            if ($userSelectedSelection) {
                $filterSelection->setUserSelected($userSelectedSelection);
            }
            $filterSelection->setDateStart($dateStartObj);
            $filterSelection->setDateEnd($dateEndObj);
        } else {
            $filterSelection = new FilterSelection();
            $filterSelection->setUser($user);
            $filterSelection->setAccount($accountSelection);
            $filterSelection->setCompany($companySelection);
            $filterSelection->setUserSelected($userSelectedSelection);
            $filterSelection->setDateStart($dateStartObj);
            $filterSelection->setDateEnd($dateEndObj);
            $this->entityManager->persist($filterSelection);
        }

        $this->entityManager->flush();
    }

    public function updateFilterNoDates($user, $accountId, $companyId, $userSelectedId): void
    {
        $filterSelection = $this->findFilterSelectionByUser($user);
        $accountSelection = $this->findAccountById($accountId);
        $companySelection = $this->findCompanyById($companyId);
        $userSelectedSelection = $this->findUserById($userSelectedId);

        if (!$filterSelection) {
            $startDate = new \DateTime('first day of this month');
            $endDate = new \DateTime('last day of this month');

            $filterSelection = new FilterSelection();
            $filterSelection->setUser($user);
            $filterSelection->setAccount($accountSelection);
            $filterSelection->setCompany($companySelection);
            $filterSelection->setUserSelected($userSelectedSelection);
            $filterSelection->setDateStart($startDate);
            $filterSelection->setDateEnd($endDate);
            $this->entityManager->persist($filterSelection);
        }else{
            $startDate = $filterSelection->getDateStart() ?? new \DateTime('first day of this month');
            $endDate = $filterSelection->getDateEnd() ?? new \DateTime('last day of this month');

            $filterSelection->setAccount($accountSelection);
            $filterSelection->setCompany($companySelection);
            $filterSelection->setUserSelected($userSelectedSelection);
            $filterSelection->setDateStart($startDate);
            $filterSelection->setDateEnd($endDate);
            $this->entityManager->persist($filterSelection);
        }

        $this->entityManager->flush();
    }

    public function updateFilterDates($user, ?string $dateStart, ?string $dateEnd): void
    {
        $filterSelection = $this->findFilterSelectionByUser($user);

        if (!$filterSelection) {
            $filterSelection = new FilterSelection();
            $filterSelection->setUser($user);
            $this->entityManager->persist($filterSelection);
        }

        if ($dateStart !== null) {
            $filterSelection->setDateStart(new \DateTime($dateStart));
        }

        if ($dateEnd !== null) {
            $filterSelection->setDateEnd(new \DateTime($dateEnd));
        }

        $this->entityManager->flush();
    }


}
