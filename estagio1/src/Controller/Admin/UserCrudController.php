<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use Symfony\Bundle\SecurityBundle\Security;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;

use App\Entity\AssignedUser;
use App\Controller\Admin\AuxController;
use App\Entity\Role;
use Symfony\Component\HttpFoundation\Request;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;

use App\Repository\CompaniesRepository;
use App\Repository\OfficeRepository;
use App\Repository\AssignedUserRepository;
use App\Repository\UserRepository;
use App\Service\RestartPassword;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\Session;

class UserCrudController extends AbstractCrudController implements EventSubscriberInterface
{
    private $assignedUserRepository, $adminUrlGenerator, $em, $security, $userPasswordHasher, $aux, $resetPasswordHelper, $mailer, $companiesRepository, $officeRepository, $userRepository, $requestStack;

    public function __construct(
        Security $security,
        AdminUrlGenerator $adminUrlGenerator,
        EntityManagerInterface $em,
        AuxController $aux,
        UserPasswordHasherInterface $userPasswordHasher,
        ResetPasswordHelperInterface $resetPasswordHelper,
        MailerInterface $mailer,
        CompaniesRepository $companiesRepository,
        OfficeRepository $officeRepository,
        UserRepository $userRepository,
        RequestStack $requestStack,
        AssignedUserRepository $assignedUserRepository
    ) {
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->em = $em;
        $this->aux = $aux;
        $this->security = $security;
        $this->userPasswordHasher = $userPasswordHasher;
        $this->resetPasswordHelper = $resetPasswordHelper;
        $this->mailer = $mailer;
        $this->companiesRepository = $companiesRepository;
        $this->officeRepository = $officeRepository;
        $this->userRepository = $userRepository;
        $this->requestStack = $requestStack;
        $this->assignedUserRepository = $assignedUserRepository;
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPaginatorPageSize(25)
            ->setPageTitle('index', 'Empleados')
            ->setEntityLabelInSingular('Empleado')
            ->setEntityLabelInPlural('Empleados')
            ->showEntityActionsInlined()
            ->overrideTemplate('crud/index', 'admin/user/custom_index.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        /** @var User App/Entity/User $user */
        $user = $this->getUser();
        $role = $user->getRole();
        $request = $this->requestStack->getCurrentRequest();
        $com = $request->query->get('com') ?? $user->getCompany()->getId();

        if ($com) {
            $company = $this->companiesRepository->find($com);
        }

        $allowCreate = true;
        $allowEdit = true;

        if ($role === 'ROLE_SUPERVISOR') {
            $allowCreate = $company->getAllowSupervisorCreate();
            $allowEdit = $company->getAllowSupervisorEdit();
        }

        // Acción para reset de contraseña
        $sendPasswordResetEmailAction = Action::new('sendPasswordResetEmail', '', 'fa fa-envelope')
            ->linkToCrudAction('sendPasswordResetEmail');

        // Personalizar botón EDIT con URL contextual
        $actions = $actions->update(
            Crud::PAGE_INDEX,
            Action::EDIT,
            fn(Action $action) =>
            $action
                ->setIcon('fa fa-pencil')
                ->setLabel(false)
                ->linkToUrl(fn($entity) => $this->generateUrlWithContext(Action::EDIT, $entity->getId()))
        );

        // Personalizar botón DELETE
        $actions = $actions->update(
            Crud::PAGE_INDEX,
            Action::DELETE,
            fn(Action $action) =>
            $action->setIcon('fa fa-trash')->setLabel(false)
        );

        // Botón NUEVO con parámetros personalizados
        $actions = $actions->update(
            Crud::PAGE_INDEX,
            Action::NEW,
            fn(Action $action) =>
            $action->linkToUrl(fn() => $this->generateUrlWithContext(Action::NEW))
        );

        // Remover "Guardar y crear otro"
        $actions = $actions->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER);

        // Permiso para eliminar solo admins
        $actions = $actions->setPermission(Action::DELETE, 'ROLE_ADMIN');

        // Agregar acción de reset para supervisores
        $actions = $actions->add(Crud::PAGE_INDEX, $sendPasswordResetEmailAction)
            ->setPermission('sendPasswordResetEmail', 'ROLE_SUPERVISOR');

        $actions = $actions->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE);

        // Control de permisos para supervisores
        if ($role === 'ROLE_SUPERVISOR') {
            if (!$allowCreate) {
                $actions = $actions->remove(Crud::PAGE_INDEX, Action::NEW);
            }
            if (!$allowEdit) {
                $actions = $actions->remove(Crud::PAGE_INDEX, Action::EDIT);
            }
        }

        return $actions;
    }

    // Función reutilizable para armar URLs con contexto (crear/editar)
    private function generateUrlWithContext(string $action, ?int $entityId = null): string
    {
        $request = $this->requestStack->getCurrentRequest();

        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction($action)
            ->set('com', $request->query->get('com'))
            ->set('off', $request->query->get('off'))
            ->set('us', $request->query->get('us'))
            ->set('mo', $request->query->get('mo') ?? (new \DateTime())->format('m'))
            ->set('ye', $request->query->get('ye') ?? (new \DateTime())->format('Y'));

        if ($entityId) {
            $url->setEntityId($entityId);
        }

        return $url->generateUrl();
    }

    public function createEntity(string $entityFqcn)
    {
        $user = new User();

        $request = $this->getContext()->getRequest();
        $off = $request->query->get('off');

        if ($off && $off !== 'all') {
            $office = $this->officeRepository->find($off);
            if ($office) {
                $user->setOffice($office);
            }
        }

        return $user;
    }


    public function configureFields(string $pageName): iterable
    {
        /** @var User App/Entity/User $user */
        $user = $this->getUser();
        $currentUserRole = $user->getRole(); // Usamos el stringRole ahora

        $rolesHierarchy = [
            'ROLE_SUPER_ADMIN' => ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_SUPERVISOR', 'ROLE_USER'],
            'ROLE_ADMIN'       => ['ROLE_ADMIN', 'ROLE_SUPERVISOR', 'ROLE_USER'],
            'ROLE_SUPERVISOR'  => ['ROLE_SUPERVISOR', 'ROLE_USER'],
            'ROLE_USER'        => ['ROLE_USER'],
        ];

        $roleLabels = [
            'ROLE_SUPER_ADMIN' => 'Superadministrador',
            'ROLE_ADMIN'       => 'Administrador',
            'ROLE_SUPERVISOR'  => 'Supervisor',
            'ROLE_USER'        => 'Usuario',
        ];

        $allowedRoles = [];

        // Filtrar roles permitidos según el rol actual del usuario
        foreach ($rolesHierarchy as $role => $allowed) {
            if ($currentUserRole === $role) {
                foreach ($allowed as $r) {
                    $allowedRoles[$roleLabels[$r] ?? $r] = $r;
                }
                break;
            }
        }


        // Configurar el campo para los roles con las opciones permitidas
        $roleField = ChoiceField::new('role', 'Rol')
            ->setChoices($allowedRoles)
            ->renderExpanded(false);


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
        // Obtener la compañía seleccionada por el usuario
        $user = $this->security->getUser();
        $request = $this->getContext()->getRequest();
        $us = $request->query->get('us');
        $off = $request->query->get('off');

        if ($off && $off !== 'all') {
            $office = AssociationField::new('office', 'Oficina')
                ->setColumns(3)
                ->setFormTypeOption('choice_value', 'id')
                ->setFormTypeOption('disabled', true)
                ->setFormTypeOption('query_builder', function (OfficeRepository $er) use ($off) {
                    return $er->createQueryBuilder('u')
                        ->where('u.id = :off')
                        ->setParameter('off', $off)
                        ->orderBy('u.name', 'ASC');
                })
                ->setDisabled(true);
        } else {
            $office = AssociationField::new('office', 'Oficina')
                ->setColumns(3)
                ->setFormTypeOption('query_builder', function (EntityRepository $er) {
                    /** @var User App/Entity/User $user */
                    $user = $this->getUser();
                    $account = $user->getAccounts();
                    $companies = $account->getCompany();

                    return $er->createQueryBuilder('o')
                        ->where('o.company IN (:companies)')
                        ->setParameter('companies', $companies)
                        ->orderBy('o.name', 'ASC');
                })
                ->setFormTypeOptions([
                    'choice_label' => function ($office) {
                        return $office->getName();
                    },
                    'group_by' => function ($office) {
                        return $office->getCompany()->getComercialName();
                    },
                ]);
        }


        $fields = [
            $office,
            $nameField,
            TextField::new('lastname1', '1 Apellido'),
            TextField::new('lastname2', '2 Apellido'),
            EmailField::new('email', 'E-mail')->setRequired(true),
            $roleField,
            FormField::AddRow(),
            TextField::new('dni', 'DNI')->setRequired(true),
            TelephoneField::new('phone', 'Teléfono'),
            NumberField::new('vacationDays', 'Días de vacaciones'),
            FormField::AddRow(),
            BooleanField::new('isActive', 'Activo'),
        ];

        return $fields;
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            $user = $this->security->getUser();
            $request = $this->requestStack->getCurrentRequest();

            /** @var \App\Entity\User $user */
            $com = $request->query->get('com', $user->getCompany()->getId());

            $company = $this->companiesRepository->find($com);

            $account = $company->getAccounts();

            $entityInstance->setCompany($company);
            $entityInstance->setAccounts($account);
            $entityInstance->setPassword(
                $this->userPasswordHasher->hashPassword($entityInstance, $entityInstance->getDni())
            );
            $entityInstance->setFirstTime(true);

            if (!$entityInstance->getRole()) {
                $roleRepository = $this->em->getRepository(Role::class);
                $roleUser = $roleRepository->findOneBy(['name' => 'ROLE_USER']);
                $entityInstance->setRole($roleUser);
            }

            if ($entityInstance->getRole() === 'ROLE_SUPERVISOR') {
                $existingAssignment = $entityManager->getRepository(AssignedUser::class)
                    ->findOneBy([
                        'supervisor' => $entityInstance,
                        'user' => $entityInstance
                    ]);

                if (!$existingAssignment) {
                    $assignedUser = new AssignedUser();
                    $assignedUser->setSupervisor($entityInstance);
                    $assignedUser->setUser($entityInstance);
                    $entityManager->persist($assignedUser);
                }
            }
        }

        parent::persistEntity($entityManager, $entityInstance);
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

        $off = 'all';
        $us = $entity->getId();
        $com = $entity->getCompany()->getId() ?? $request->query->get('com');

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

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            $role = $entityInstance->getRole();
            if ($role === 'ROLE_SUPERVISOR') {
                $existingAssignment = $entityManager->getRepository(AssignedUser::class)
                    ->findOneBy([
                        'supervisor' => $entityInstance,
                        'user' => $entityInstance
                    ]);

                if (!$existingAssignment) {
                    $assignedUser = new AssignedUser();
                    $assignedUser->setSupervisor($entityInstance);
                    $assignedUser->setUser($entityInstance);

                    $entityManager->persist($assignedUser);

                    $entityManager->flush();
                }
            }
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        /** @var User App/Entity/User $user */
        $user = $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();

        // Parámetros de entrada con defaults
        $com = $request->query->get('com', $user->getCompany()->getId());
        $off = $request->query->get('off', 'all');
        // Obtener el primer y último día del mes actual
        $startDate = new \DateTime('first day of this month');  // Primer día del mes
        $endDate = new \DateTime('last day of this month');    // Último día del mes

        $startDateFormatted = $startDate->format('Y-m-d');
        $endDateFormatted = $endDate->format('Y-m-d');

        $start = $request->query->get('start', $startDateFormatted);
        $end = $request->query->get('end', $endDateFormatted);
        $us = $request->query->get('us', $user->getId());

        $account = $user->getAccounts();
        $company = $this->companiesRepository->find($com);

        // === SI ES SUPERVISOR ===
        if ($user->getRole() === 'ROLE_SUPERVISOR') {
            // Obtenemos los usuarios asignados directamente
            $assignedUsers = $this->assignedUserRepository->findBy(['supervisor' => $user]);

            // Filtramos los usuarios reales
            $users = array_map(fn($assigned) => $assigned->getUser(), $assignedUsers);

            $companies = [];
            $offices = [];

            foreach ($users as $assignedUser) {
                $assignedCompany = $assignedUser->getCompany();
                $assignedOffice = $assignedUser->getOffice();

                if ($assignedCompany && !isset($companies[$assignedCompany->getId()])) {
                    $companies[$assignedCompany->getId()] = $assignedCompany;
                }

                // Filtramos oficinas solo para las empresas del supervisor
                if ($assignedCompany && $assignedOffice && !isset($offices[$assignedOffice->getId()]) && $assignedOffice->getCompany()->getId() === $assignedCompany->getId()) {
                    $offices[$assignedOffice->getId()] = $assignedOffice;
                }
            }

            // Aplanamos los arrays
            $uniqueCompanies = array_values($companies);
            $uniqueOffices = array_values($offices);

            $responseParameters->set('moreThanOne', count($uniqueCompanies) > 1);
            $responseParameters->set('companies', $uniqueCompanies);
            $responseParameters->set('offices', $uniqueOffices);

            // Si se tiene una empresa seleccionada, filtrar las oficinas de esa empresa
            if ($company) {
                $filteredOffices = array_filter($uniqueOffices, fn($office) => $office->getCompany()->getId() === $company->getId());
                $responseParameters->set('offices', $filteredOffices);
            }

            $responseParameters->set('selectedCompany', $company);
            $responseParameters->set('selectedOffice', $uniqueOffices ? $off : null);

            return $responseParameters;
        }


        // === SI NO ES SUPERVISOR ===
        $companies = $this->companiesRepository->findBy(['accounts' => $account], ['name' => 'ASC']);
        $offices = $this->officeRepository->findBy(['company' => $company], ['name' => 'ASC']);

        $responseParameters->set('companies', $companies);
        $responseParameters->set('offices', $offices);
        $responseParameters->set('selectedCompany', $company);
        $responseParameters->set('selectedOffice', $off);
        $responseParameters->set('moreThanOne', true);

        return $responseParameters;
    }


    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        /** @var User App/Entity/User $user */
        $user = $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();
        $companyId = $request->query->get('com', $user->getCompany()->getId());
        $company = $this->companiesRepository->findOneBy(['id' => $companyId]);
        $office = $request->query->get('off', 'all');

        if ($user->getRole() === 'ROLE_SUPERVISOR') {
            $assignedUsers = $user->getAssignedUsers();
            $assignedUserIds = array_map(fn($assignedUser) => $assignedUser->getUser()->getId(), $assignedUsers->toArray());

            if (empty($assignedUserIds)) {
                $qb->andWhere('1 = 0'); // fuerza resultado vacío
                return $qb;
            }

            $qbUser = $this->userRepository->createQueryBuilder('u')
                ->select('u.id')
                ->where('u.id IN (:assignedUserIds)')
                ->setParameter('assignedUserIds', $assignedUserIds);

            if ($office !== 'all') {
                $qbUser->andWhere('u.office = :office')->setParameter('office', $office);
            } else {
                $qbUser->andWhere('u.company = :company')->setParameter('company', $company);
            }

            $userIds = array_column($qbUser->getQuery()->getArrayResult(), 'id');

            $qb->andWhere('entity.id IN (:userIds)')
                ->setParameter('userIds', $userIds);
        } else {
            if ($office && $office !== 'all') {
                $qb->andWhere('entity.office = :office')
                    ->setParameter('office', $office);
            } else {
                $qb->andWhere('entity.company = :company')
                    ->setParameter('company', $company);
            }
        }

        return $qb;
    }


public function sendPasswordResetEmail(Request $request, Session $session): RedirectResponse
{
    $userId = $request->query->get('entityId');

    // Buscar usuario
    $user = $this->em->getRepository(User::class)->findOneBy(['id' => $userId]);

    if (!$user) {
        $session->getFlashBag()->add('error', 'Usuario no encontrado.');
        return new RedirectResponse($request->headers->get('referer'));
    }

    // Obtener DNI
    $userDni = $user->getDni();

    if (!$userDni) {
        $session->getFlashBag()->add('error', 'El usuario no tiene DNI asignado.');
        return new RedirectResponse($request->headers->get('referer'));
    }

    // Hashear la nueva contraseña con el DNI
    $hashedPassword = $this->userPasswordHasher->hashPassword($user, $userDni);
    $user->setPassword($hashedPassword);
    $user->setFirstTime(true);

    // Guardar cambios
    $this->em->persist($user);
    $this->em->flush();

    // Mensaje de éxito
    $session->getFlashBag()->add('success', 'La contraseña ha sido restablecida correctamente.');

    // Redirigir a la misma página donde estaba el usuario
    return new RedirectResponse($request->headers->get('referer'));
}
}