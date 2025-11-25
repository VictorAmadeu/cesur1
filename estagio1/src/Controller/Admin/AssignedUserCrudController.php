<?php

namespace App\Controller\Admin;

use App\Controller\Admin\AuxController;
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
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
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
 *
 * Responsabilidades:
 *  - Listar las relaciones supervisor/usuario con filtros por empresa, centro y supervisor.
 *  - Permitir crear NUEVAS asignaciones.
 *  - Permitir EDITAR una asignación ya existente (re-asignar supervisor/usuario).
 *  - Tras crear/editar, volver al listado respetando los filtros activos.
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

    /**
     * Inyección de dependencias necesarias para filtros, seguridad y redirecciones.
     */
    public function __construct(
        Security $security,
        AdminUrlGenerator $adminUrlGenerator,
        EntityManagerInterface $em,
        AuxController $aux,
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

    /**
     * Entidad gestionada por este CRUD.
     */
    public static function getEntityFqcn(): string
    {
        return AssignedUser::class;
    }

    /**
     * Configuración general de EasyAdmin para esta pantalla.
     */
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPaginatorPageSize(25)
            ->setPageTitle('index', 'Supervisores')
            ->setPageTitle('new', 'Asignar Supervisor')
            ->setPageTitle('edit', 'Editar relación Supervisor ↔ Usuario')
            ->setEntityLabelInSingular('Supervisor')
            ->setEntityLabelInPlural('Supervisores')
            ->showEntityActionsInlined()
            ->overrideTemplate('crud/index', 'admin/assignedUser/custom_index.html.twig');
    }

    /**
     * Configura las acciones disponibles (Nuevo, Editar, Borrar) según el rol.
     */
    public function configureActions(Actions $actions): Actions
    {
        /** @var User|null $currentUser */
        $currentUser = $this->security->getUser();
        $isSupervisor = $this->isSupervisor($currentUser);

        $actions = $actions
            // No mostrar "Guardar y añadir otro" en formularios
            ->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE)
            // Personalizar icono de borrar
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->setIcon('fa fa-trash')->setLabel(false);
            })
            // Habilitar acción Editar en el listado (antes estaba eliminada)
            ->update(Crud::PAGE_INDEX, Action::EDIT, function (Action $action) use ($isSupervisor) {
                // Los supervisores solo pueden editar SUS propias asignaciones.
                // Un administrador puede editar cualquier asignación.
                if ($isSupervisor) {
                    return $action->displayIf(function (AssignedUser $entity) {
                        return $entity->getSupervisor() === $this->security->getUser();
                    });
                }

                return $action;
            });

        if (!$isSupervisor) {
            // Botón "Nuevo" visible sólo para administración (no para supervisores),
            // respetando los filtros actuales (empresa/oficina/usuario y rango de fechas).
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

    /**
     * Define los campos que se muestran/usan en formularios y listado.
     *
     * Punto clave: cuando estamos en EDICIÓN, el campo Usuario debe seguir mostrando
     * al usuario ya asignado aunque normalmente lo excluiríamos para evitar duplicados.
     */
    public function configureFields(string $pageName): iterable
    {
        /** @var User|null $currentUser */
        $currentUser = $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();

        // Empresa seleccionada (o la del usuario logueado por defecto)
        $com = $request->query->get('com', $currentUser ? $currentUser->getCompany()->getId() : null);
        $company = $com ? $this->companiesRepository->findOneBy(['id' => $com]) : null;
        $account = $company ? $company->getAccounts() : null;

        // Filtro superior "Usuario" (id de supervisor o 'all')
        $us = $request->query->get('us');

        // Si estamos editando, obtenemos la entidad actual para poder tratarla de forma especial
        /** @var AssignedUser|null $editingEntity */
        $editingEntity = Crud::PAGE_EDIT === $pageName ? $this->getContext()?->getEntity()->getInstance() : null;

        // ----- Campo SUPERVISOR -----
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
                // Preselecciona el supervisor si viene filtrado en la cabecera
                ->setFormTypeOption('data', $selectedUser);

            // ----- Campo USUARIO cuando hay un supervisor filtrado -----
            $userField = AssociationField::new('user', 'Usuario')
                ->setColumns(3)
                ->setFormTypeOption('query_builder', function (UserRepository $er) use ($account, $selectedUser, $editingEntity) {
                    $qb = $er->createQueryBuilder('u')
                        ->where('u.accounts = :account')
                        ->setParameter('account', $account)
                        ->orderBy('u.name', 'ASC');

                    // 🔹 Alta NUEVA: excluir usuarios ya asignados a ese supervisor
                    if (!$editingEntity instanceof AssignedUser) {
                        $qb->andWhere('u NOT IN (
                            SELECT IDENTITY(au.user)
                            FROM App\Entity\AssignedUser au
                            WHERE au.supervisor = :supervisor
                        )')
                        ->setParameter('supervisor', $selectedUser);
                    }
                    // 🔹 EDICIÓN: no aplicamos el filtro de exclusión,
                    // para que el usuario actualmente asignado siga apareciendo en el combo.
                    // La UniqueEntity de AssignedUser se encargará de evitar duplicados al guardar.

                    return $qb;
                });
        } else {
            // Sin filtro específico de supervisor en cabecera

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

            // Campo USUARIO genérico: todos los usuarios de la cuenta (la UniqueEntity
            // y la lógica de negocio evitan que se creen duplicados indebidos).
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

    /**
     * Persistencia estándar de EasyAdmin.
     * (Se mantiene tal cual para no alterar el flujo por defecto).
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        parent::persistEntity($entityManager, $entityInstance);
    }

    /**
     * Envía variables extra a la plantilla de EasyAdmin (filtros, listas, etc.).
     */
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

        // Lista de centros/oficinas para el filtro
        $offices = $company ? $this->officeRepository->findBy(['company' => $company]) : [];
        $responseParameters->set('offices', $offices);
        $responseParameters->set('selectedOffice', $off);

        // Si el logueado es supervisor, limitamos su contexto y devolvemos pronto
        if ($this->isSupervisor($currentUser)) {
            $responseParameters->set('companies', $companies);
            $responseParameters->set('selectedCompany', $company);
            return $responseParameters;
        }

        // Vista de administración (selección de supervisores)
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

    /**
     * Eventos de EasyAdmin a los que se suscribe este controlador.
     * Queremos actuar tanto al CREAR como al EDITAR para redirigir al listado.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            AfterEntityPersistedEvent::class => ['onAfterEntityStored'],
            AfterEntityUpdatedEvent::class   => ['onAfterEntityStored'],
        ];
    }

    /**
     * Tras crear o editar una relación, redirige al listado respetando filtros.
     */
    public function onAfterEntityStored($event): void
    {
        $entity = $event->getEntityInstance();
        if (!$entity instanceof AssignedUser) {
            // Solo actuamos cuando la entidad es AssignedUser
            return;
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
        // Guardamos la sesión antes de enviar la redirección para evitar pérdida de datos
        $this->getContext()->getRequest()->getSession()->save();
        $response->send();
    }

    /**
     * Construye el QueryBuilder para el listado de asignaciones,
     * respetando filtros de empresa, oficina y supervisor.
     */
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

        // Si quien mira es un supervisor: solo sus asignaciones (no se ve a sí mismo como supervisado)
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

        // Vista de administración con filtro de supervisor
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
     * Comprueba si un usuario tiene rol de supervisor (compatible con getRole/getRoles).
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
