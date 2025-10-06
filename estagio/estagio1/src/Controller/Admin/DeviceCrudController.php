<?php

namespace App\Controller\Admin;

use App\Entity\Device;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use Symfony\Bundle\SecurityBundle\Security;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\Admin\AuxController;
use App\Entity\Companies;
use App\Form\CompanySelectGlobalType;
use Symfony\Component\HttpFoundation\RequestStack;

use App\Service\FilterSelectionService;
use App\Repository\CompaniesRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;

class DeviceCrudController extends AbstractCrudController
{
    private $adminUrlGenerator;
    private $em;
    private $security;
    private $aux;
    private $filterSelectionService; 
    private $companiesRepository;
    private $requestStack;

    public function __construct(
        Security $security, 
        AdminUrlGenerator $adminUrlGenerator, 
        EntityManagerInterface $em, 
        AuxController $aux,
        FilterSelectionService $filterSelectionService, 
        CompaniesRepository $companiesRepository,
        RequestStack $requestStack
        )
    {
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->em = $em;
        $this->aux = $aux;
        $this->security = $security;
        $this->filterSelectionService = $filterSelectionService;
        $this->companiesRepository = $companiesRepository;  
        $this->requestStack = $requestStack;      
    }

    public static function getEntityFqcn(): string
    {
        return Device::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPaginatorPageSize(25)
            ->setPageTitle('index', 'Dispositivos')
            ->setEntityLabelInSingular('Dispositivo')
            ->setEntityLabelInPlural('Dispositivos')
            ->showEntityActionsInlined()
            ->overrideTemplate('crud/index', 'admin/device/custom_index.html.twig');
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $user = $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();
        $com = $request->query->get('com', $user->getCompany()->getId());
        $isSuperAdmin = $this->isGranted('ROLE_SUPER_ADMIN');
        $filterSelection = $this->filterSelectionService->findFilterSelectionByUser($user);
        $company = $this->companiesRepository->findOneBy(['id' => $com]);
        $params = [];

        $account = $user->getAccounts();
        $responseParameters->set('companies', $this->companiesRepository->findBy(['accounts' => $account]));

        // if($isSuperAdmin){
        //     $responseParameters->set('companies', $this->companiesRepository->findAll());
        // }else{
        // }

        $responseParameters->set('selectedCompany', $company);
        return $responseParameters;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $user = $this->getUser();
        $request = $this->requestStack->getCurrentRequest();
        $isSuperAdmin = $this->isGranted('ROLE_SUPERADMIN');
        $companyId = $request->query->get('com', $user->getCompany()->getId());
        $company = $this->companiesRepository->findOneBy(['id' => $companyId]);

        $qb->andWhere('entity.company = :company')
            ->setParameter('company', $company);

        return $qb;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->add(Crud::PAGE_EDIT, Action::INDEX)
            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE)
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->setIcon('fa fa-trash')->setLabel(false);
            });
    }

    public function configureFields(string $pageName): iterable
    {
        $adminUrlGenerator = $this->adminUrlGenerator;

        $nameField = AssociationField::new('registeredBy', 'Usuario')->setColumns(2)
        ->formatValue(function ($value, $entity) use ($adminUrlGenerator) {
            $url = $adminUrlGenerator
                ->setController(self::class)
                ->setAction('edit')
                ->setEntityId($entity->getId())
                ->generateUrl();

            return sprintf('<a href="%s">%s</a>', $url, $value);
        });

        return [
            $nameField,
            TextField::new('deviceName', 'Nombre'),
            TextField::new('deviceId', 'Identificador'),
            TextField::new('deviceType', 'Tipo de dispositivo')
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Device) {
            $user = $this->getUser();
            $filterSelection = $this->filterSelectionService->findFilterSelectionByUser($user);
            $selectedCompany = $filterSelection->getCompany();
            $entityInstance->setCompany($selectedCompany);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Device) {
            $user = $this->getUser();
            $filterSelection = $this->filterSelectionService->findFilterSelectionByUser($user);
            $selectedCompany = $filterSelection->getCompany();
            $entityInstance->setCompany($selectedCompany);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }
}
