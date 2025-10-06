<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\AssignedUser;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;

use Symfony\Component\HttpFoundation\Response;
use App\Service\FilterSelectionService;
use App\Repository\UserRepository;
use Symfony\Bundle\SecurityBundle\Security;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;

use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Repository\CompaniesRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Repository\OfficeRepository;

class AssignedUserCrudController extends AbstractCrudController
{
    private $security, $adminUrlGenerator, $em, $aux, $entityManager, $filterSelectionService, $userRepository, $requestStack, $companiesRepository, $officeRepository;

    public function __construct(Security $security, AdminUrlGenerator $adminUrlGenerator, EntityManagerInterface $em, AuxController $aux, EntityManagerInterface $entityManager, FilterSelectionService $filterSelectionService, UserRepository $userRepository, RequestStack $requestStack, CompaniesRepository $companiesRepository, OfficeRepository $officeRepository)
    {
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->em = $em;
        $this->aux = $aux;
        $this->entityManager = $entityManager;
        $this->filterSelectionService = $filterSelectionService;
        $this->userRepository = $userRepository;
        $this->security = $security;
        $this->requestStack = $requestStack;
        $this->companiesRepository = $companiesRepository;
        $this->officeRepository = $officeRepository;
    }

    public static function getEntityFqcn(): string
    {
        return AssignedUser::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPaginatorPageSize(25)
            ->setPageTitle('index', 'Supervisores')
            ->setPageTitle('new', 'Asignar Supervisor')
            ->setEntityLabelInSingular('Supervisor')
            ->setEntityLabelInPlural('Supervisores')
            ->showEntityActionsInlined()
            ->overrideTemplate('crud/index', 'admin/assignedUser/custom_index.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        $user = $this->getUser();
        $isSupervisor = $user && method_exists($user, 'getRole') && $user->getRole() === 'ROLE_SUPERVISOR';

        $actions = $actions
            ->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->setIcon('fa fa-trash')->setLabel(false);
            });

        if (!$isSupervisor) {
            $actions = $actions->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
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

                return $action
                    ->setLabel('Asignar usuario')
                    ->linkToUrl($url);
            });
        } else {
            // Ocultar el botón NEW para supervisores
            $actions = $actions->remove(Crud::PAGE_INDEX, Action::NEW);
        }

        return $actions;
    }


    public function configureFields(string $pageName): iterable
    {
        $user = $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();
        $com = $request->query->get('com', $user->getCompany()->getId());
        $company = $this->companiesRepository->findOneBy(['id' => $com]);
        $account = $company->getAccounts();
        $us = $request->query->get('us');
    
        if ($us && $us !== 'all') {
            $supervisor = $this->userRepository->find($us); // Buscás el objeto User

            $supervisorField = AssociationField::new('supervisor', 'Supervisor')
                ->setColumns(3)
                ->setFormTypeOption('query_builder', function (UserRepository $er) use ($com) {
                    return $er->createQueryBuilder('u')
                        ->where('u.company = :company')
                        ->andWhere('u.role = :role')
                        ->setParameter('role', 'ROLE_SUPERVISOR')
                        ->setParameter('company', $com)
                        ->orderBy('u.name', 'ASC');
                })
                ->setFormTypeOption('data', $supervisor);
            $userField = AssociationField::new('user', 'Usuario')
                ->setColumns(3)
                ->setFormTypeOptions(['multiple' => true])
                ->setFormTypeOption('query_builder', function (UserRepository $er) use ($account, $supervisor) {
                    // Subquery para excluir los users ya asignados al supervisor
                    return $er->createQueryBuilder('u')
                        ->where('u.accounts = :account')
                        ->andWhere('u NOT IN (
                            SELECT IDENTITY(au.user) FROM App\Entity\AssignedUser au WHERE au.supervisor = :supervisor
                        )')
                        ->setParameter('account', $account)
                        ->setParameter('supervisor', $supervisor)
                        ->orderBy('u.name', 'ASC');
                });
        }else{
            $supervisorField = AssociationField::new('supervisor', 'Supervisor')
                ->setColumns(3)
                ->setFormTypeOption('query_builder', function (UserRepository $er) use ($com) {
                    return $er->createQueryBuilder('u')
                        ->where('u.company = :company')
                        ->andWhere('u.role = :role')
                        ->setParameter('role', 'ROLE_SUPERVISOR')
                        ->setParameter('company', $com) // El id del usuario seleccionado
                        ->orderBy('u.name', 'ASC'); // o 'u.name', según el campo que uses
                });

            $userField = AssociationField::new('user', 'Usuario')
                ->setColumns(3)
                ->setFormTypeOptions(['multiple' => true])
                ->setFormTypeOption('query_builder', function (UserRepository $er) use ($account) {
                    return $er->createQueryBuilder('u')
                        ->where('u.accounts = :accounts')
                        ->setParameter('accounts', $account) // El id del usuario seleccionado
                        ->orderBy('u.name', 'ASC'); // o 'u.name', según el campo que uses
                });
        }
    
        return [
            IdField::new('id')->OnlyOnIndex(),
            TextField::new('supervisor.company.comercialName', 'Empresa del supervisor')->onlyOnIndex(),
            TextField::new('supervisor.office.name', 'Centro del supervisor')->onlyOnIndex(),
            $supervisorField,
            TextField::new('user.company.comercialName', 'Empresa del supervisado')->onlyOnIndex(),
            TextField::new('user.office.name', 'Centro del supervisado')->onlyOnIndex(),
            $userField,
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $user = $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();

        $com = $request->query->get('com', $user->getCompany()->getId());
        $us = $request->query->get('us', 'all');
        $off = $request->query->get('off', 'all');

        $account = $user->getAccounts();
        $company = $this->companiesRepository->findOneBy(['id' => $com]);
        $companies = $this->companiesRepository->findBy(['accounts' => $account]);
        $role = 'ROLE_SUPERVISOR';

        $offices = $this->officeRepository->findBy(['company' => $company]);
        $responseParameters->set('offices', $offices);
        $responseParameters->set('selectedOffice', $off);

         // === SI ES SUPERVISOR ===
        if ($user->getRole() === 'ROLE_SUPERVISOR') {
            $responseParameters->set('companies', $companies);
            $responseParameters->set('selectedCompany', $company);
            return $responseParameters;
        }else{

        if($us === 'all'){          
            $supervisor = $us;
        }else{
            $user = $this->userRepository->findOneBy(['id' => $us]);
            $userRole = $user->getRole();
            if($userRole === $role){
                $supervisor = $user;
            }else{
                $supervisor = 'all';            
            }
        }

        
        $supervisors = $this->userRepository->createQueryBuilder('u')
            ->andWhere('u.role = :role') 
            ->andWhere('u.company = :company')
            ->setParameter('company', $company)
            ->setParameter('role', $role)
            ->orderBy('u.name', 'ASC') 
            ->getQuery()
            ->getResult();    
        
        if (!$supervisors) {
            $responseParameters->set('errorMessage', 'Aún no se han asignado supervisores en esta compañía.');
        }

        $responseParameters->set('selectedCompany', $company);
        $responseParameters->set('companies', $companies);
        $responseParameters->set('selectedUser', $supervisor);
        $responseParameters->set('users', $supervisors);

        return $responseParameters;
        }
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

        $com = $entity->getCompany()->getId();
        $off = $request->query->get('off') ?? 'all';
        $us =  $request->query->get('us') ?? 'all';
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
            ->set('com', $com)
            ->set('us', $us)
            ->set('off', $off)
            ->set('start', $start)
            ->set('end', $end)
            ->generateUrl();

        $response = new RedirectResponse($url);
        $this->getContext()->getRequest()->getSession()->save(); 
        $response->send();
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        $user = $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();

        $com = $request->query->get('com');
        $us = $request->query->get('us');
        $off = $request->query->get('off', 'all');

        $company = $this->companiesRepository->find($com);
        $role = 'ROLE_SUPERVISOR';
        $account = $company ? $company->getAccounts() : null;

        if ($user->getRole() === 'ROLE_SUPERVISOR') {
            $qb->andWhere('entity.supervisor = :selectedUser')
                ->andWhere('entity.user != :currentUser')
                ->setParameter('currentUser', $user)
                ->setParameter('selectedUser', $user);

            // Filtro por empresa del usuario supervisado
            if ($company) {
                $qb->innerJoin('entity.user', 'u_company')
                   ->andWhere('u_company.company = :selectedCompany')
                   ->setParameter('selectedCompany', $company);
            }
            // Filtro por oficina del usuario supervisado
            if ($off && $off !== 'all') {
                $qb->innerJoin('entity.user', 'u_office')
                   ->andWhere('u_office.office = :selectedOffice')
                   ->setParameter('selectedOffice', $off);
            }
            return $qb;
        }else{

            if ($us !== 'all') {
                $user = $this->userRepository->findOneBy(['id' => $us]);
                $userRole = $user->getRole();
                if($userRole === $role){
                    $qb->innerJoin('entity.user', 'u')
                        ->andWhere('entity.supervisor = :selectedUser')
                        ->andWhere('entity.user != :currentUser')
                        ->setParameter('currentUser', $user)
                        ->setParameter('selectedUser', $user);
                    if ($off && $off !== 'all') {
                        $qb->innerJoin('entity.supervisor', 's_office')
                           ->andWhere('s_office.office = :selectedOffice')
                           ->setParameter('selectedOffice', $off);
                    }
                }else{
                    $supervisors = $this->userRepository->createQueryBuilder('u')
                        ->innerJoin('u.role', 'r')
                        ->andWhere('u.company = :company')
                        ->andWhere('r = :role')
                        ->setParameter('company', $company)
                        ->setParameter('role', $role)
                        ->getQuery()
                        ->getResult();
    
                if (empty($supervisors)) {
                    return $qb->andWhere('1 = 0');
                }
    
                $qb->innerJoin('entity.user', 'u')
                    ->andWhere('entity.supervisor IN (:supervisors)')
                    ->andWhere('entity.supervisor != entity.user')
                    ->setParameter('supervisors', $supervisors);
                if ($off && $off !== 'all') {
                    $qb->innerJoin('entity.supervisor', 's_office')
                       ->andWhere('s_office.office = :selectedOffice')
                       ->setParameter('selectedOffice', $off);
                }
                }
            } else {
                $supervisors = $this->userRepository->createQueryBuilder('u')
                    ->andWhere('u.company = :company')
                    ->andWhere('u.role = :role')
                    ->setParameter('company', $company)
                    ->setParameter('role', $role)
                    ->getQuery()
                    ->getResult();
    
                if (empty($supervisors)) {
                    return $qb->andWhere('1 = 0');
                }
    
                $qb->innerJoin('entity.user', 'u')
                    ->andWhere('entity.supervisor IN (:supervisors)')
                    ->andWhere('entity.supervisor != entity.user')
                    ->setParameter('supervisors', $supervisors);
                if ($off && $off !== 'all') {
                    $qb->innerJoin('entity.supervisor', 's_office')
                       ->andWhere('s_office.office = :selectedOffice')
                       ->setParameter('selectedOffice', $off);
                }
            }
    
            return $qb;
        }
    }

}
