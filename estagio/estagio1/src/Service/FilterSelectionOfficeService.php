<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Office;
use App\Entity\Companies;
use App\Entity\FilterOffice;
use Doctrine\ORM\EntityManagerInterface;

class FilterSelectionOfficeService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function findOfficeSelectionByUser($user): ?FilterOffice
    {
        $filterOffice = $this->entityManager->getRepository(FilterOffice::class)->findOneBy([
            'user' => $user,
        ]);

        return $filterOffice;
    }

    public function findOfficeById($officeId): ?Office
    {
        return $this->entityManager->getRepository(Office::class)->findOneBy([
            'id' => $officeId,
        ]);
    }

    public function saveFilterSelection($user, $officeId): void
    {
        $filterSelection = $this->findOfficeSelectionByUser($user);
        $officeSelection = $this->findOfficeById($officeId);

        if ($filterSelection) {
            if ($officeSelection) {
                $filterSelection->setOffice($officeSelection);
            }
        } else {
            $filterSelection = new FilterSelection();
            $filterSelection->setUser($user);
            $filterSelection->setOffice($officeSelection);
            $this->entityManager->persist($filterSelection);
        }

        $this->entityManager->flush();
    }
}
