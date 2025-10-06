<?php

namespace App\Controller\Admin;

use App\Entity\Office;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;

use Symfony\Bundle\SecurityBundle\Security;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\Admin\AuxController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;

use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use App\Entity\Companies;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use Doctrine\ORM\QueryBuilder;

use App\Service\FilterSelectionService;
use App\Repository\CompaniesRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

class OfficeCrudController extends AbstractCrudController
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
        return Office::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPaginatorPageSize(25)
            ->setPageTitle('index', 'Sedes')
            ->setEntityLabelInSingular('Sede')
            ->setEntityLabelInPlural('Sedes')
            ->showEntityActionsInlined()
            ->overrideTemplate('crud/index', 'admin/office/custom_index.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                $request = $this->requestStack->getCurrentRequest();
                $com = $request->query->get('com');
                $off = $request->query->get('off');
                $us = $request->query->get('us');
                // Obtener el primer y último día del mes actual
                $startDate = new \DateTime('first day of this month');  // Primer día del mes
                $endDate = new \DateTime('last day of this month');    // Último día del mes
            
                $startDateFormatted = $startDate->format('Y-m-d');
                $endDateFormatted = $endDate->format('Y-m-d');

                $start = $request->query->get('start', $startDateFormatted);
                $end = $request->query->get('end', $endDateFormatted);

                $url = $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Action::NEW)
                    ->set('com', $com)
                    ->set('off', $off)
                    ->set('us', $us)
                    ->set('start', $start)
                    ->set('end', $end)
                    ->generateUrl();

                return $action->linkToUrl($url);
            })
            ->update(Crud::PAGE_INDEX, Action::EDIT, function (Action $action) {
                return $action->linkToCrudAction(Action::EDIT);
            })
            ->add(Crud::PAGE_INDEX, Action::new('customEdit', '', 'fa fa-pencil')
            ->linkToUrl(function ($entity) {
                $id = $entity->getId();
                $request = $this->requestStack->getCurrentRequest();
                $com = $request->query->get('com');
                $off = $request->query->get('off');
                $us = $request->query->get('us');
                // Obtener el primer y último día del mes actual
                $startDate = new \DateTime('first day of this month');  // Primer día del mes
                $endDate = new \DateTime('last day of this month');    // Último día del mes
            
                $startDateFormatted = $startDate->format('Y-m-d');
                $endDateFormatted = $endDate->format('Y-m-d');

                $start = $request->query->get('start', $startDateFormatted);
                $end = $request->query->get('end', $endDateFormatted);
        
                return $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Action::EDIT)
                    ->setEntityId($id)
                    ->set('com', $com)
                    ->set('off', $off)
                    ->set('us', $us)
                    ->set('start', $start)
                    ->set('end', $end)
                    ->generateUrl();
            }))
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->setIcon('fa fa-trash')->setLabel(false);
            })
            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE)
            ->remove(Crud::PAGE_NEW, Action::INDEX);
    }

    public function createEntity(string $entityFqcn)
    {
        $office = new Office();

        $request = $this->getContext()->getRequest();
        $com = $request->query->get('com');

        if ($com && $com !== 'all') {
            $companies = $this->companiesRepository->find($com);
            if ($companies) {
                $office->setCompany($companies);
            }
        }

        return $office;
    }

    public function configureFields(string $pageName): iterable
    {
        $request = $this->getContext()->getRequest();
        $com = $request->query->get('com');

        if($com && $com !== 'all'){
            $company = AssociationField::new('company', 'Empresa')
                ->setColumns(3)
                ->setFormTypeOption('choice_value', 'id')
                ->setFormTypeOption('disabled', true)
                ->setFormTypeOption('query_builder', function (CompaniesRepository $er) use ($com) {
                    return $er->createQueryBuilder('u')
                        ->where('u.id = :com')
                        ->setParameter('com', $com)
                        ->orderBy('u.name', 'ASC');
                })
                ->setDisabled(true);
        }else{
            $company = AssociationField::new('company', 'Empresa')
                ->setColumns(3)
                ->setFormTypeOption('query_builder', function ($er) {
                    $account = $this->getUser()->getAccounts();
    
                    return $er->createQueryBuilder('o')
                        ->where('o.accounts = :account')
                        ->setParameter('account', $account)
                        ->orderBy('o.name', 'ASC');
                });
        }


        return [
            $company,
            IdField::new('id')->hideOnForm(),
            TextField::new('name')->setLabel('Nombre')->setColumns(2),
            TextField::new('country')->setLabel('País')->setColumns(2),
            TextField::new('province')->setLabel('Provincia o estado')->setColumns(2),
            TextField::new('city')->setLabel('Ciudad')->setColumns(2),
            IntegerField::new('code')->setLabel('Codigo postal')->setColumns(2),
            TextField::new('address')->setLabel('Dirección')->setColumns(4)->setHelp('Ingrese la dirección como el ejemplo: Avenida de Pío XII 4'),
        ];
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AfterEntityPersistedEvent::class => ['onAfterEntityPersisted'],
        ];
    }

    public function onAfterEntityPersisted(AfterEntityPersistedEvent $event): void
    {
        $entity = $event->getEntityInstance();
        if (!$entity instanceof User) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        $co = $entity->getCompany()->getId();
        $off = $request->query->get('off', 'all');
        $us = $request->query->get('us', 'all');
        // Obtener el primer y último día del mes actual
        $startDate = new \DateTime('first day of this month');  // Primer día del mes
        $endDate = new \DateTime('last day of this month');    // Último día del mes
            
        $startDateFormatted = $startDate->format('Y-m-d');
        $endDateFormatted = $endDate->format('Y-m-d');

        $start = $request->query->get('start', $startDateFormatted);
        $end = $request->query->get('end', $endDateFormatted);

        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction('index')
            ->set('us', $us)
            ->set('off', $off)
            ->set('start', $start)
            ->set('end', $end)
            ->generateUrl();

        $response = new RedirectResponse($url);
        $this->getContext()->getRequest()->getSession()->save(); 
        $response->send();
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

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        parent::updateEntity($entityManager, $entityInstance);
    }
}