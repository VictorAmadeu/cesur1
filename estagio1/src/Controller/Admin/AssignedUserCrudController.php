<?php

namespace App\Controller\Admin;

use App\Controller\Admin\AuxController; // 👈 Type-hint explícito para el autowire de $aux
use App\Entity\User;
use App\Entity\AssignedUser;
use App\Repository\CompaniesRepository;
use App\Repository\OfficeRepository;
use App\Repository\UserRepository;
use App\Service\FilterSelectionService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controlador de asignaciones Supervisor ↔ Usuario en EasyAdmin.
 * Cambios clave:
 *  - Se quita la multiselección del campo 'user' para evitar 500 al persistir.
 *  - Se tipa $aux (AuxController) para que el autowire funcione.
 *  - Helper isSupervisor() compatible con getRole() / getRoles().
 */
class AssignedUserCrudController extends AbstractCrudController implements EventSubscriberInterface
{
    /** @var Security */
    private $security;
    /** @var AdminUrlGenerator */
    private $adminUrlGenerator;
    /** @var EntityManagerInterface */
    private $em;
    /** @var AuxController */
    private $aux;
    /** @var EntityManagerInterface */
    private $entityManager;
    /** @var FilterSelectionService */
    private $filterSelectionService;
    /** @var UserRepository */
    private $userRepository;
    /** @var RequestStack */
    private $requestStack;
    /** @var CompaniesRepository */
    private $companiesRepository;
    /** @var OfficeRepository */
    private $officeRepository;

    public function __construct(
        Security $security,
        AdminUrlGenerator $adminUrlGenerator,
        EntityManagerInterface $em,
        AuxController $aux, // 👈 aquí estaba el problema: faltaba el type-hint
        EntityManagerInterface $entityManager,
        FilterSelectionService $filterSelectionService,
        UserRepository $userRepository,
        RequestStack $requestStack,
        CompaniesRepository $companiesRepository,
        OfficeRepository $officeRepository
    ) {
        $this->security = $security;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->em = $em;
        $this->aux = $aux;
        $this->entityManager = $entityManager;
        $this->filterSelectionService = $filterSelectionService;
        $this->userRepository = $userRepository;
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
        /** @var User|null $currentUser */
        $currentUser = $this->security->getUser();
        $isSupervisor = $this->isSupervisor($currentUser);

        $actions = $actions
            ->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->setIcon('fa fa-trash')->setLabel(false);
            });

        if (!$isSupervisor) {
            // Botón "Nuevo" que respeta los filtros actuales (empresa/oficina/usuario y rango de fechas)
            $actions = $actions->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                $request = $this->requestStack->getCurrentRequest();
                $com = $request->query->get('com');
                $off = $request->query->get('off');
                $us  = $request->query->get('us');

                $startDefault = (new \DateTime('first day of this month'))->format('Y-m-d');
                $endDefault   = (new \DateTime('last day of this month'))->format('Y-m-d');

                $start = $request->query->get('start', $startDefault);
                $end   = $request->query->get('end',   $endDefault);

                $url = $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Action::NEW)
                    ->set('com', $com)
                    ->set('off', $off)
                    ->set('us',  $us)
                    ->set('start', $start)
                    ->set('end',   $end)
                    ->generateUrl();

                return $action->setLabel('Asignar usuario')->linkToUrl($url);
            });
        } else {
            // Un supervisor no crea nuevas asignaciones desde index
            $actions = $actions->remove(Crud::PAGE_INDEX, Action::NEW);
        }

        return $actions;
    }

    public function configureFields(string $pageName): iterable
    {
        /** @var User|null $currentUser */
        $currentUser = $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();

        // Empresa desde query o la del usuario logueado
        $com = $request->query->get('com', $currentUser ? $currentUser->getCompany()->getId() : null);
        $company = $com ? $this->companiesRepository->findOneBy(['id' => $com]) : null;
        $account = $company ? $company->getAccounts() : null;

        $us = $request->query->get('us'); // id de usuario en el filtro “Usuario” de la cabecera (o 'all')

        // Campo SUPERVISOR (con preselección si llega ?us=)
        if ($us && $us !== 'all') {
            $selectedUser = $this->userRepository->find($us);

            $supervisorField = AssociationField::new('supervisor', 'Supervisor')
                ->setColumns(3)
                ->setFormTypeOption('query_builder', function (UserRepository $er) use ($com) {
                    return $er->createQueryBuilder('u')
                        ->where('u.company = :company')
                        ->andWhere('u.role = :role')
                        ->setParameter('company', $com)
                        ->setParameter('role', 'ROLE_SUPERVISOR')
                        ->orderBy('u.name', 'ASC');
                })
                ->setFormTypeOption('data', $selectedUser);

            // ❗ SIN multiselección para evitar ArrayCollection en propiedad ManyToOne
            $userField = AssociationField::new('user', 'Usuario')
                ->setColumns(3)
                ->setFormTypeOption('query_builder', function (UserRepository $er) use ($account, $selectedUser) {
                    // Excluir usuarios ya asignados a ese supervisor
                    return $er->createQueryBuilder('u')
                        ->where('u.accounts = :account')
                        ->andWhere('u NOT IN (
                            SELECT IDENTITY(au.user) FROM App\Entity\AssignedUser au WHERE au.supervisor = :supervisor
                        )')
                        ->setParameter('account', $account)
                        ->setParameter('supervisor', $selectedUser)
                        ->orderBy('u.name', 'ASC');
                });
        } else {
            $supervisorField = AssociationField::new('supervisor', 'Supervisor')
                ->setColumns(3)
                ->setFormTypeOption('query_builder', function (UserRepository $er) use ($com) {
                    return $er->createQueryBuilder('u')
                        ->where('u.company = :company')
                        ->andWhere('u.role = :role')
                        ->setParameter('company', $com)
                        ->setParameter('role', 'ROLE_SUPERVISOR')
                        ->orderBy('u.name', 'ASC');
                });

            // ❗ SIN multiselección
            $userField = AssociationField::new('user', 'Usuario')
                ->setColumns(3)
                ->setFormTypeOption('query_builder', function (UserRepository $er) use ($account) {
                    return $er->createQueryBuilder('u')
                        ->where('u.accounts = :accounts')
                        ->setParameter('accounts', $account)
                        ->orderBy('u.name', 'ASC');
                });
        }

        return [
            IdField::new('id')->onlyOnIndex(),
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
        /** @var User|null $currentUser */
        $currentUser = $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();

        $com = $request->query->get('com', $currentUser ? $currentUser->getCompany()->getId() : null);
        $us  = $request->query->get('us', 'all');
        $off = $request->query->get('off', 'all');

        $company   = $com ? $this->companiesRepository->findOneBy(['id' => $com]) : null;
        $companies = $currentUser ? $this->companiesRepository->findBy(['accounts' => $currentUser->getAccounts()]) : [];
        $role = 'ROLE_SUPERVISOR';

        $offices = $company ? $this->officeRepository->findBy(['company' => $company]) : [];
        $responseParameters->set('offices', $offices);
        $responseParameters->set('selectedOffice', $off);

        // Si el logueado es supervisor, limitamos a su contexto
        if ($this->isSupervisor($currentUser)) {
            $responseParameters->set('companies', $companies);
            $responseParameters->set('selectedCompany', $company);
            return $responseParameters;
        }

        // Vista de administración
        $selectedUser = $us;
        if ($us !== 'all') {
            $selectedUserEntity = $this->userRepository->findOneBy(['id' => $us]);
            if ($selectedUserEntity instanceof User && $this->isSupervisor($selectedUserEntity)) {
                $selectedUser = $selectedUserEntity;
            } else {
                $selectedUser = 'all';
            }
        }

        $qb = $this->userRepository->createQueryBuilder('u')
            ->andWhere('u.role = :role')
            ->setParameter('role', $role);

        if ($company) {
            $qb->andWhere('u.company = :company')->setParameter('company', $company);
        }

        $supervisors = $qb->orderBy('u.name', 'ASC')->getQuery()->getResult();

        if (!$supervisors) {
            $responseParameters->set('errorMessage', 'Aún no se han asignado supervisores en esta compañía.');
        }

        $responseParameters->set('selectedCompany', $company);
        $responseParameters->set('companies', $companies);
        $responseParameters->set('selectedUser', $selectedUser);
        $responseParameters->set('users', $supervisors);

        return $responseParameters;
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
        if (!$entity instanceof AssignedUser) {
            return; // Sólo actuamos cuando se persiste una asignación
        }

        $request = $this->requestStack->getCurrentRequest();

        // Recalcular la empresa desde el supervisor recién asignado
        $com = $entity->getSupervisor() && $entity->getSupervisor()->getCompany()
            ? $entity->getSupervisor()->getCompany()->getId()
            : null;

        $off = $request->query->get('off') ?? 'all';
        $us  = $request->query->get('us')  ?? 'all';

        $startDefault = (new \DateTime('first day of this month'))->format('Y-m-d');
        $endDefault   = (new \DateTime('last day of this month'))->format('Y-m-d');

        $start = $request->query->get('start', $startDefault);
        $end   = $request->query->get('end',   $endDefault);

        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction('index')
            ->set('com', $com)
            ->set('us',  $us)
            ->set('off', $off)
            ->set('start', $start)
            ->set('end',   $end)
            ->generateUrl();

        $response = new RedirectResponse($url);
        $this->getContext()->getRequest()->getSession()->save();
        $response->send();
    }

    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters
    ): QueryBuilder {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        /** @var User|null $currentUser */
        $currentUser = $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();

        $com = $request->query->get('com');
        $us  = $request->query->get('us');
        $off = $request->query->get('off', 'all');

        $company = $com ? $this->companiesRepository->find($com) : null;
        $role = 'ROLE_SUPERVISOR';

        // Si quien mira es un supervisor: sólo sus asignaciones
        if ($this->isSupervisor($currentUser)) {
            $qb->andWhere('entity.supervisor = :selectedUser')
               ->andWhere('entity.user != :currentUser')
               ->setParameter('selectedUser', $currentUser)
               ->setParameter('currentUser', $currentUser);

            if ($company) {
                $qb->innerJoin('entity.user', 'u_company')
                   ->andWhere('u_company.company = :selectedCompany')
                   ->setParameter('selectedCompany', $company);
            }
            if ($off && $off !== 'all') {
                $qb->innerJoin('entity.user', 'u_office')
                   ->andWhere('u_office.office = :selectedOffice')
                   ->setParameter('selectedOffice', $off);
            }
            return $qb;
        }

        // Vista de administración con filtros
        if ($us !== 'all') {
            $selectedUserEntity = $this->userRepository->findOneBy(['id' => $us]);
            if ($selectedUserEntity instanceof User && $this->isSupervisor($selectedUserEntity)) {
                $qb->innerJoin('entity.user', 'u')
                   ->andWhere('entity.supervisor = :selectedUser')
                   ->andWhere('entity.user != :currentUser')
                   ->setParameter('selectedUser', $selectedUserEntity)
                   ->setParameter('currentUser',  $selectedUserEntity);

                if ($off && $off !== 'all') {
                    $qb->innerJoin('entity.supervisor', 's_office')
                       ->andWhere('s_office.office = :selectedOffice')
                       ->setParameter('selectedOffice', $off);
                }
            } else {
                // 'us' no es supervisor → listamos asignaciones de todos los supervisores de la empresa
                $supervisorsQB = $this->userRepository->createQueryBuilder('u')
                    ->andWhere('u.role = :role')
                    ->setParameter('role', $role);

                if ($company) {
                    $supervisorsQB->andWhere('u.company = :company')->setParameter('company', $company);
                }

                $list = $supervisorsQB->getQuery()->getResult();
                if (empty($list)) {
                    return $qb->andWhere('1 = 0');
                }

                $qb->innerJoin('entity.user', 'u')
                   ->andWhere('entity.supervisor IN (:supervisors)')
                   ->andWhere('entity.supervisor != entity.user')
                   ->setParameter('supervisors', $list);

                if ($off && $off !== 'all') {
                    $qb->innerJoin('entity.supervisor', 's_office')
                       ->andWhere('s_office.office = :selectedOffice')
                       ->setParameter('selectedOffice', $off);
                }
            }
        } else {
            // 'us' == all → asignaciones de todos los supervisores (opcionalmente filtradas)
            $supervisorsQB = $this->userRepository->createQueryBuilder('u')
                ->andWhere('u.role = :role')
                ->setParameter('role', $role);

            if ($company) {
                $supervisorsQB->andWhere('u.company = :company')->setParameter('company', $company);
            }

            $list = $supervisorsQB->getQuery()->getResult();
            if (empty($list)) {
                return $qb->andWhere('1 = 0');
            }

            $qb->innerJoin('entity.user', 'u')
               ->andWhere('entity.supervisor IN (:supervisors)')
               ->andWhere('entity.supervisor != entity.user')
               ->setParameter('supervisors', $list);

            if ($off && $off !== 'all') {
                $qb->innerJoin('entity.supervisor', 's_office')
                   ->andWhere('s_office.office = :selectedOffice')
                   ->setParameter('selectedOffice', $off);
            }
        }

        return $qb;
    }

    /**
     * Comprueba si un usuario es supervisor, tolerando getRole() (string) o getRoles() (array).
     */
    private function isSupervisor(?User $user): bool
    {
        if (!$user) {
            return false;
        }
        if (method_exists($user, 'getRole')) {
            return $user->getRole() === 'ROLE_SUPERVISOR';
        }
        if (method_exists($user, 'getRoles')) {
            return in_array('ROLE_SUPERVISOR', (array) $user->getRoles(), true);
        }
        return false;
    }
}
