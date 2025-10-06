<?php

namespace App\Controller\Admin;

use App\Entity\UserWorkSchedule;
use App\Entity\WorkSchedule;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use Symfony\Component\Security\Core\Security;
use App\Controller\Admin\AuxController;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Repository\CompaniesRepository;
use App\Repository\UserWorkScheduleRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use App\Repository\OfficeRepository;
use App\Repository\UserRepository;
use App\Repository\AssignedUserRepository;
use App\Repository\WorkScheduleRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;

use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class UserWorkScheduleCrudController extends AbstractCrudController
{
    private $requestStack;
    private $em;
    private $security;
    private $aux;
    private $companiesRepository;
    private $adminUrlGenerator;
    private $officeRepository;
    private $userRepository;
    private $assignedUserRepository;
    private $userWorkScheduleRepository;
    private $workScheduleRepository;

    public function __construct(
        Security $security,
        EntityManagerInterface $em,
        AuxController $aux,
        RequestStack $requestStack,
        CompaniesRepository $companiesRepository,
        UserWorkScheduleRepository $userWorkScheduleRepository,
        AdminUrlGenerator $adminUrlGenerator,
        OfficeRepository $officeRepository,
        UserRepository $userRepository,
        AssignedUserRepository $assignedUserRepository,
        WorkScheduleRepository $workScheduleRepository,
    ) {
        $this->requestStack = $requestStack;
        $this->em = $em;
        $this->aux = $aux;
        $this->security = $security;
        $this->companiesRepository = $companiesRepository;
        $this->userWorkScheduleRepository = $userWorkScheduleRepository;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->officeRepository = $officeRepository;
        $this->userRepository = $userRepository;
        $this->assignedUserRepository = $assignedUserRepository;
        $this->workScheduleRepository = $workScheduleRepository;
    }

    public static function getEntityFqcn(): string
    {
        return UserWorkSchedule::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPaginatorPageSize(25)
            ->setPageTitle('index', 'Asignar horario a usuario')
            ->setEntityLabelInSingular('Asignación de horario a usuario')
            ->setEntityLabelInPlural('Asignaciones de horarios a usuarios')
            ->showEntityActionsInlined()
            ->overrideTemplate('crud/index', 'admin/UserWorkSchedule/custom_index.html.twig');
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

    public function configureFields(string $pageName): iterable
    {
        /** @var \App\Entity\User $user */
        $user = $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();

        $com = $request->query->get('com', $user->getCompany()?->getId());
        $off = $request->query->get('off', 'all');
        $us = $request->query->get('us', 'all');

        // Filtrar usuarios por compañía y oficina
        $qbUsers = $this->userRepository->createQueryBuilder('u')
            ->where('u.company = :company')
            ->setParameter('company', $com);

        if ($off !== 'all') {
            $qbUsers->andWhere('u.office = :office')
                ->setParameter('office', $off);
        }

        $users = $qbUsers->getQuery()->getResult();

        // Si se seleccionó un usuario específico, preseleccionarlo
        $selectedUsers = ($us !== 'all') ? [$this->userRepository->find($us)] : $users;

        $usersByOffice = [];
        foreach ($users as $user) {
            $officeName = $user->getOffice()?->getName() ?? 'Sin oficina';
            $usersByOffice[$officeName][] = $user;
        }

        $userField = ChoiceField::new('user', 'Usuarios')
            ->setFormTypeOption('choices', $usersByOffice)
            ->setFormTypeOption('choice_value', 'id')
            ->setFormTypeOption('multiple', true)
            ->setFormTypeOption('mapped', false)
            ->setFormTypeOption('choice_label', 'name')
            ->onlyWhenCreating()
            ->hideOnIndex()
            ->setFormTypeOption('data', $selectedUsers)
            ->setColumns(3);

        // Filtrar horarios de trabajo solo de los usuarios de la compañía/oficina seleccionada
        $workSchedules = $this->workScheduleRepository->createQueryBuilder('ws')
            ->where('ws.company = :company')
            ->setParameter('company', $com)
            ->getQuery()
            ->getResult();

        $workScheduleField = AssociationField::new('workSchedule', 'Horario de trabajo')
            ->setFormTypeOption('choices', $workSchedules)
            ->setColumns(3);

        return [
            $workScheduleField,
            AssociationField::new('user', 'Usuario')
                ->setFormTypeOption('choices', $users)
                ->setColumns(3)
                ->onlyOnIndex(),
            AssociationField::new('user', 'Usuario')
                ->setColumns(3)
                ->onlyWhenUpdating()
                ->setDisabled(true),
            $userField,
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof UserWorkSchedule) {
            $requestData = $this->getContext()->getRequest()->request->all();
            $formData = $requestData['UserWorkSchedule'] ?? [];

            $userIds = $formData['user'] ?? [];
            $workScheduleId = $formData['workSchedule'] ?? null;

            $workSchedule = $this->em->getRepository(WorkSchedule::class)->find($workScheduleId);

            foreach ($userIds as $userId) {
                $user = $this->userRepository->find($userId);

                $existing = $this->userWorkScheduleRepository->findOneBy([
                    'user' => $user,
                    'workSchedule' => $workSchedule,
                ]);

                if ($existing) {
                    $this->addFlash('danger', "El usuario '{$user->getName()}' ya tiene asignado este horario.");
                    continue; // o return si querés cortar por completo
                }

                $new = new UserWorkSchedule();
                $new->setUser($user);
                $new->setWorkSchedule($workSchedule);
                $new->setStartDate($workSchedule->getStartDate());
                $new->setEndDate($workSchedule->getEndDate());
                $entityManager->persist($new);
            }

            $entityManager->flush();
        }
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof UserWorkSchedule) {
            $requestData = $this->getContext()->getRequest()->request->all();
            $formData = $requestData['UserWorkSchedule'] ?? [];

            $user = $entityInstance->getUser();
            $workScheduleId = $formData['workSchedule'] ?? null;
            $workSchedule = $this->em->getRepository(WorkSchedule::class)->find($workScheduleId);

            // Verificar si ya existe otra asignación igual (evitar duplicados)
            $existing = $this->userWorkScheduleRepository->findOneBy([
                'user' => $user,
                'workSchedule' => $workSchedule,
            ]);

            if ($existing && $existing->getId() !== $entityInstance->getId()) {
                $this->addFlash('danger', "Este usuario ya tiene asignado ese horario.");
                return; // Evita que se guarde
            }

            // Aplicar los cambios
            $entityInstance->setWorkSchedule($workSchedule);
            $entityInstance->setStartDate($workSchedule->getStartDate());
            $entityInstance->setEndDate($workSchedule->getEndDate());
            $entityManager->persist($entityInstance);
            $entityManager->flush();
        }
    }


    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        /** @var \App\Entity\User $user */
        $user = $this->security->getUser();
        $role = $user->getRole();
        $request = $this->requestStack->getCurrentRequest();

        $com = $request->query->get('com', $user->getCompany()->getId());
        $off = $request->query->get('off', 'all');

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

                if ($assignedOffice && !isset($offices[$assignedOffice->getId()])) {
                    $offices[$assignedOffice->getId()] = $assignedOffice;
                }
            }

            // Aplanar arrays
            $uniqueCompanies = array_values($companies);

            // 🔸 FILTRAR OFICINAS POR LA EMPRESA SELECCIONADA
            $uniqueOffices = null;
            $uniqueOffices = array_values(array_filter(
                $offices,
                fn($o) => $o->getCompany()?->getId() == $com
            ));

            // 🔸 FILTRAR USUARIOS POR EMPRESA Y OFICINA SELECCIONADAS
            $filteredUsers = array_filter($users, function ($u) use ($com, $off) {
                $matchCompany = $u->getCompany()?->getId() == $com;
                $matchOffice = ($off === 'all') || !$u->getOffice() ? true : $u->getOffice()?->getId() == $off;
                return $matchCompany && $matchOffice;
            });

            // Obtener entidad Office si corresponde
            $selectedOffice = ($off && $off !== 'all') ? $this->officeRepository->find($off) : $off;

            // Obtener entidad User si corresponde
            $selectedUser = ($us && $us !== 'all') ? $this->userRepository->find($us) : $us;

            // Seteamos todo en el response
            $responseParameters->set('companies', $uniqueCompanies);
            $responseParameters->set('offices', $uniqueOffices);
            $responseParameters->set('users', $filteredUsers);
            $responseParameters->set('selectedUser', $selectedUser);
            $responseParameters->set('selectedOffice', $selectedOffice);
        } else {

            if ($user->getRole() !== 'ROLE_SUPERVISOR') {
                // Obtener todas las compañías asociadas a la cuenta
                $companies = $this->companiesRepository->findBy(
                    ['accounts' => $account],
                    ['comercialName' => 'ASC']
                );
                $responseParameters->set('companies', $companies);

                // Obtener entidad de usuario seleccionada si corresponde
                $selectedUser = ($us && $us !== 'all') ? $this->userRepository->find($us) : $us;
                $responseParameters->set('selectedUser', $selectedUser);

                $offices = $this->officeRepository->findBy(
                    ['company' => $com],
                    ['name' => 'ASC']
                );
                $selectedOffice = ($off && $off !== 'all') ? $this->officeRepository->find($off) : $off;


                $responseParameters->set('offices', $offices);
                $responseParameters->set('selectedOffice', $selectedOffice);

                if ($selectedOffice !== 'all') {
                    $users = $this->userRepository->findBy(
                        ['office' => $selectedOffice],
                        ['name' => 'ASC']
                    );
                } else {
                    $users = $this->userRepository->findBy(
                        ['company' => $company],
                        ['name' => 'ASC']
                    );
                }

                $responseParameters->set('users', $users);
            }
        }
        $responseParameters->set('selectedCompany', $company);
        return $responseParameters;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $request = $this->requestStack->getCurrentRequest();

        $com = $request->query->get('com');
        $off = $request->query->get('off');
        $us = $request->query->get('us');

        // Hacer join con User
        $qb->leftJoin('entity.user', 'u');

        if ($us !== 'all') {
            $qb->andWhere('u.id = :selectedUser')
                ->setParameter('selectedUser', $us);
        }

        if ($com !== 'all') {
            $qb->andWhere('u.company = :selectedCompany')
                ->setParameter('selectedCompany', $com);
        }

        if ($off !== 'all') {
            $qb->andWhere('u.office = :selectedOffice')
                ->setParameter('selectedOffice', $off);
        }


        return $qb;
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
}
