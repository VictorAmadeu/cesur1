<?php
namespace App\Controller\Admin;

use App\Entity\UserExtraSegment;
use App\Entity\User;
use App\Enum\SegmentConstants;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimeField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

use Symfony\Bundle\SecurityBundle\Security; // usar el servicio moderno
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

use App\Controller\Admin\AuxController;
use App\Repository\CompaniesRepository;
use App\Repository\OfficeRepository;
use App\Repository\UserRepository;
use App\Repository\AssignedUserRepository;

/**
 * CRUD para UserExtraSegment.
 *
 * Puntos clave:
 * - ⚠️ La entidad UserExtraSegment NO tiene dateStart/dateEnd. Solo 'date'.
 *   Cualquier formulario o query debe usar 'date' para evitar el 500
 *   "Attempted to call setDateEnd(). Did you mean setDate()?".
 * - El filtrado por oficina se hace a través del usuario relacionado:
 *   JOIN entity.user AS u  y  u.office = :selectedOffice
 */
class UserExtraSegmentCrudController extends AbstractCrudController
{
    private RequestStack $requestStack;
    private EntityManagerInterface $em;
    private Security $security;
    private AuxController $aux;
    private CompaniesRepository $companiesRepository;
    private AdminUrlGenerator $adminUrlGenerator;
    private OfficeRepository $officeRepository;
    private UserRepository $userRepository;
    private AssignedUserRepository $assignedUserRepository;

    public function __construct(
        Security $security,
        EntityManagerInterface $em,
        AuxController $aux,
        RequestStack $requestStack,
        CompaniesRepository $companiesRepository,
        AdminUrlGenerator $adminUrlGenerator,
        OfficeRepository $officeRepository,
        UserRepository $userRepository,
        AssignedUserRepository $assignedUserRepository
    ) {
        $this->requestStack = $requestStack;
        $this->em = $em;
        $this->aux = $aux;
        $this->security = $security;
        $this->companiesRepository = $companiesRepository;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->officeRepository = $officeRepository;
        $this->userRepository = $userRepository;
        $this->assignedUserRepository = $assignedUserRepository;
    }

    public static function getEntityFqcn(): string
    {
        return UserExtraSegment::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPaginatorPageSize(25)
            ->setPageTitle('index', 'Segmentos Extraordinarios')
            ->setEntityLabelInSingular('Segmento Extraordinario')
            ->setEntityLabelInPlural('Segmentos Extraordinarios')
            ->showEntityActionsInlined()
            ->overrideTemplate('crud/index', 'admin/UserExtraSegment/custom_index.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            // Botón "Nuevo" preservando filtros (com/off/us/start/end)
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                $request = $this->requestStack->getCurrentRequest();
                $com = $request->query->get('com');
                $off = $request->query->get('off');
                $us  = $request->query->get('us');

                // Defaults seguros: primer/último día de mes
                $start = $request->query->get('start', (new \DateTime('first day of this month'))->format('Y-m-d'));
                $end   = $request->query->get('end',   (new \DateTime('last day of this month'))->format('Y-m-d'));

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
            // Lápiz custom que conserva filtros
            ->add(Crud::PAGE_INDEX, Action::new('customEdit', '', 'fa fa-pencil')
                ->linkToUrl(function ($entity) {
                    /** @var UserExtraSegment $entity */
                    $request = $this->requestStack->getCurrentRequest();
                    $com = $request->query->get('com');
                    $off = $request->query->get('off');
                    $us  = $request->query->get('us');

                    $start = $request->query->get('start', (new \DateTime('first day of this month'))->format('Y-m-d'));
                    $end   = $request->query->get('end',   (new \DateTime('last day of this month'))->format('Y-m-d'));

                    return $this->adminUrlGenerator
                        ->setController(self::class)
                        ->setAction(Action::EDIT)
                        ->setEntityId($entity->getId())
                        ->set('com', $com)
                        ->set('off', $off)
                        ->set('us', $us)
                        ->set('start', $start)
                        ->set('end', $end)
                        ->generateUrl();
                }))
            ->remove(Crud::PAGE_INDEX, Action::EDIT) // usamos el lápiz custom
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn(Action $a) => $a->setIcon('fa fa-trash')->setLabel(false))
            // Acciones de navegación habituales
            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE);
    }

    public function configureFields(string $pageName): iterable
    {
        $request = $this->requestStack->getCurrentRequest();
        $us = $request->query->get('us');

        // Si viene un usuario filtrado distinto de "all", se pre-selecciona
        $prefillUser = ($us && $us !== 'all') ? $this->userRepository->find($us) : null;

        return [
            AssociationField::new('user', 'Usuario')
                ->setFormTypeOption('data', $prefillUser)
                ->setColumns(3),

            // En formulario el tipo se elige por Choice (constantes de dominio)
            ChoiceField::new('type', 'Tipo')
                ->setChoices(SegmentConstants::SEGMENTS)
                ->onlyOnForms()
                ->setColumns(3),

            // ⚠️ IMPORTANTE: esta entidad SOLO tiene 'date' (no usar dateStart/dateEnd)
            DateField::new('date', 'Fecha')
                ->setRequired(false)
                ->setColumns(2),

            TimeField::new('timeStart', 'Hora de inicio')
                ->setFormat('H:i')
                ->formatValue(fn($v) => $v instanceof \DateTimeInterface ? $v->format('H:i') : null)
                ->setRequired(false)
                ->setColumns(2),

            TimeField::new('timeEnd', 'Hora de fin')
                ->setFormat('H:i')
                ->formatValue(fn($v) => $v instanceof \DateTimeInterface ? $v->format('H:i') : null)
                ->setRequired(false)
                ->setColumns(2),

            // En índice mostramos la etiqueta del tipo
            TextField::new('type', 'Tipo')
                ->formatValue(fn($value) => SegmentConstants::getLabel($value))
                ->onlyOnIndex()
                ->setColumns(2),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        // Mantener el flujo estándar de EasyAdmin (eventos, subscribers, etc.)
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();

        $com = $request->query->get('com', $user->getCompany()->getId());
        $off = $request->query->get('off', 'all');
        $us  = $request->query->get('us', $user->getId());

        $account = $user->getAccounts();
        $company = $this->companiesRepository->find($com);

        // === SUPERVISOR: limitar a sus asignados ===
        if ($user->getRole() === 'ROLE_SUPERVISOR') {
            $assignedUsers = $this->assignedUserRepository->findBy(['supervisor' => $user]);
            $users = array_map(fn($a) => $a->getUser(), $assignedUsers);

            // Construir listas únicas de compañías y oficinas derivadas de sus asignados
            $companies = [];
            $offices = [];
            foreach ($users as $u) {
                if ($u->getCompany()) {
                    $companies[$u->getCompany()->getId()] = $u->getCompany();
                }
                if ($u->getOffice()) {
                    $offices[$u->getOffice()->getId()] = $u->getOffice();
                }
            }
            $uniqueCompanies = array_values($companies);

            // Filtrar oficinas por la empresa seleccionada
            $uniqueOffices = array_values(array_filter(
                $offices,
                fn($o) => $o->getCompany()?->getId() == $com
            ));

            // Filtrar usuarios por empresa/oficina seleccionadas
            $filteredUsers = array_filter($users, function (User $u) use ($com, $off) {
                $matchCompany = $u->getCompany()?->getId() == $com;
                $matchOffice  = ($off === 'all') || !$u->getOffice() ? true : $u->getOffice()?->getId() == $off;
                return $matchCompany && $matchOffice;
            });

            $selectedOffice = ($off && $off !== 'all') ? $this->officeRepository->find($off) : $off;
            $selectedUser   = ($us  && $us  !== 'all') ? $this->userRepository->find($us)   : $us;

            $responseParameters->set('companies', $uniqueCompanies);
            $responseParameters->set('offices', $uniqueOffices);
            $responseParameters->set('users', $filteredUsers);
            $responseParameters->set('selectedUser', $selectedUser);
            $responseParameters->set('selectedOffice', $selectedOffice);
        } else {
            // === Admin/otros: compañías de la cuenta y usuarios por compañía/oficina ===
            $companies = $this->companiesRepository->findBy(
                ['accounts' => $account],
                ['comercialName' => 'ASC']
            );
            $responseParameters->set('companies', $companies);

            $selectedUser   = ($us  && $us  !== 'all') ? $this->userRepository->find($us)   : $us;
            $selectedOffice = ($off && $off !== 'all') ? $this->officeRepository->find($off) : $off;

            $responseParameters->set('selectedUser', $selectedUser);

            $offices = $this->officeRepository->findBy(['company' => $com], ['name' => 'ASC']);
            $responseParameters->set('offices', $offices);
            $responseParameters->set('selectedOffice', $selectedOffice);

            if ($selectedOffice !== 'all') {
                $users = $this->userRepository->findBy(['office' => $selectedOffice], ['name' => 'ASC']);
            } else {
                $users = $this->userRepository->findBy(['company' => $company], ['name' => 'ASC']);
            }
            $responseParameters->set('users', $users);
        }

        $responseParameters->set('selectedCompany', $company);
        return $responseParameters;
    }

    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters
    ): QueryBuilder {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        $request = $this->requestStack->getCurrentRequest();
        $off = $request->query->get('off');
        $us  = $request->query->get('us');

        // Filtrar por usuario si se selecciona uno concreto
        if ($us !== 'all') {
            $selectedUser = $this->userRepository->find($us);
            if ($selectedUser) {
                $qb->andWhere('entity.user = :selectedUser')
                   ->setParameter('selectedUser', $selectedUser);
            }
        }

        // ⚠️ UserExtraSegment no tiene 'office'; filtramos por la oficina del usuario
        if ($off !== 'all') {
            $qb->innerJoin('entity.user', 'u')
               ->andWhere('u.office = :selectedOffice')
               ->setParameter('selectedOffice', $this->officeRepository->find($off));
        }

        return $qb;
    }

    // Suscriptor al evento post-persist (se mantiene el patrón del proyecto)
    public static function getSubscribedEvents(): array
    {
        return [
            AfterEntityPersistedEvent::class => ['onAfterEntityPersisted'],
        ];
    }

    public function onAfterEntityPersisted(AfterEntityPersistedEvent $event): void
    {
        $entity = $event->getEntityInstance();
        // Este redirect solo aplica si se ha persistido un User (patrón heredado del proyecto)
        if (!$entity instanceof User) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        $com = $entity->getCompany()->getId();
        $off = $request->query->get('off') ?? 'all';
        $us  = $request->query->get('us')  ?? 'all';

        $start = $request->query->get('start', (new \DateTime('first day of this month'))->format('Y-m-d'));
        $end   = $request->query->get('end',   (new \DateTime('last day of this month'))->format('Y-m-d'));

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
