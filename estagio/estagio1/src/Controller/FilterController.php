<?php

namespace App\Controller;

use App\Entity\FilterSelection;
use App\Entity\Companies;
use App\Service\FilterSelectionService;
use App\Service\FilterSelectionOfficeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

class FilterController extends AbstractController
{
    private $filterSelectionService;
    private $filterSelectionOfficeService;
    private $entityManager;

    public function __construct(FilterSelectionService $filterSelectionService, FilterSelectionOfficeService $filterSelectionOfficeService, EntityManagerInterface $entityManager)
    {
        $this->filterSelectionService = $filterSelectionService;
        $this->filterSelectionOfficeService = $filterSelectionOfficeService;
        $this->entityManager = $entityManager;
    }

}

