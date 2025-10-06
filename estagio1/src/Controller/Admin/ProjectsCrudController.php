<?php

namespace App\Controller\Admin;

use App\Entity\Projects;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
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

use App\Service\FilterSelectionService;
use App\Repository\CompaniesRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;

class ProjectsCrudController extends AbstractCrudController
{
    private $adminUrlGenerator;
    private $em;
    private $security;
    private $aux;
    private $filterSelectionService; 
    private $companiesRepository;

    public function __construct(
        Security $security, 
        AdminUrlGenerator $adminUrlGenerator, 
        EntityManagerInterface $em, 
        AuxController $aux,
        FilterSelectionService $filterSelectionService, 
        CompaniesRepository $companiesRepository
        )
    {
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->em = $em;
        $this->aux = $aux;
        $this->security = $security;
        $this->filterSelectionService = $filterSelectionService;
        $this->companiesRepository = $companiesRepository;        
    }

    public static function getEntityFqcn(): string
    {
        return Projects::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPaginatorPageSize(25)
            ->setPageTitle('index', 'Proyectos')
            ->setEntityLabelInSingular('Proyecto')
            ->setEntityLabelInPlural('Proyectos')
            ->showEntityActionsInlined()
            ->overrideTemplate('crud/index', 'admin/projects/custom_index.html.twig');
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $user = $this->security->getUser();
        $isSuperAdmin = $this->isGranted('ROLE_SUPER_ADMIN');
        $filterSelection = $this->filterSelectionService->findFilterSelectionByUser($user);
        $params = [];

        $account = $user->getAccounts();

        if($isSuperAdmin){
            $responseParameters->set('companies', $this->companiesRepository->findAll());
        }else{
            $responseParameters->set('companies', $this->companiesRepository->findBy(['accounts' => $account]));
        }

        $responseParameters->set('selectedCompany', $filterSelection->getCompany());
        return $responseParameters;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $user = $this->getUser();
        $selectedCompany = $user->getCompany();

        $qb->andWhere('entity.company = :selectedCompany')
            ->setParameter('selectedCompany', $selectedCompany);

        return $qb;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_EDIT, Action::INDEX)
            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->setIcon('fa fa-trash')->setLabel(false);
            })
            ->update(Crud::PAGE_INDEX, Action::EDIT,function (Action $action) {
                return $action->setIcon('fa fa-pencil')->setLabel(false);
            });
    }

    public function configureFields(string $pageName): iterable
    {
        $adminUrlGenerator = $this->adminUrlGenerator;

        $nameField = TextField::new('name', 'Nombre')->setColumns(2)
        ->formatValue(function ($value, $entity) use ($adminUrlGenerator) {
            $url = $adminUrlGenerator
                ->setController(self::class)
                ->setAction('edit')
                ->setEntityId($entity->getId())
                ->generateUrl();

            return sprintf('<a href="%s">%s</a>', $url, $value);
        });

        $companyField = AssociationField::new('company', 'Compañía')->setColumns(2)->setFormTypeOption('data', true)->OnlyOnIndex();

        return [
            $companyField,
            $nameField,
            BooleanField::new('active', 'Activo')->setColumns(3)->setFormTypeOption('data', true)->OnlyOnIndex()
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Projects) {
            if ($entityInstance->isActive() === null) {
                $entityInstance->setActive(true);
            }
    
            $user = $this->getUser();
            $selectedCompany = $user->getCompany();
            $entityInstance->setCompany($selectedCompany);
        }
    
        parent::persistEntity($entityManager, $entityInstance);
    }
    

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Projects) {
            $user = $this->getUser();
            $selectedCompany = $user->getCompany();
            $entityInstance->setCompany($selectedCompany);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }
}
