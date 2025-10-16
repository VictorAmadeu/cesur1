<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use App\Controller\Admin\AuxController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
    use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
    use Symfony\Component\HttpFoundation\RequestStack;

use App\Entity\License;
use App\Repository\UserRepository;
use App\Repository\OfficeRepository;
use App\Repository\AssignedUserRepository;
use Symfony\Bundle\SecurityBundle\Security;
use App\Repository\CompaniesRepository;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\MailerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use App\Enum\AbsenceConstants;
use App\Entity\UserExtraSegment;

/**
 * CRUD de Licencias/Ausencias.
 *
 * Cambios clave (🧩 Etapa 3):
 *  - normalizeLicenseDates(): si fecha inicio = fecha fin y hora fin < hora inicio,
 *    se interpreta “cruce de medianoche” y se ajusta dateEnd +1 día.
 *  - Tipado PHPDoc para que Intelephense reconozca métodos de User (getRole,
 *    getAccounts, getAssignedUsers, getCompany, etc.).
 *  - onAfterEntityPersisted(): elimina variables no definidas usando helper
 *    getRequestParamsWithDates() para el redirect.
 *
 * Nota: no se altera la lógica de negocio existente (segmentos extra, emails,
 * filtros, queries). Solo se corrigen incoherencias y se añade la normalización.
 */
class LicenseCrudController extends AbstractCrudController
{
    private $assignedUserRepository;
    private $adminUrlGenerator;
    private $mailer;
    private $em;
    private $aux;
    private $userPasswordHasher;
    private $userRepository;
    private $officeRepository;
    private $security;
    private $companiesRepository;
    private $requestStack;

    public function __construct(
        AssignedUserRepository $assignedUserRepository,
        MailerInterface $mailer,
        Security $security,
        AdminUrlGenerator $adminUrlGenerator,
        EntityManagerInterface $em,
        AuxController $aux,
        UserPasswordHasherInterface $userPasswordHasher,
        UserRepository $userRepository,
        OfficeRepository $officeRepository,
        CompaniesRepository $companiesRepository,
        RequestStack $requestStack
    ) {
        $this->adminUrlGenerator   = $adminUrlGenerator;
        $this->em                  = $em;
        $this->aux                 = $aux;
        $this->userPasswordHasher  = $userPasswordHasher;
        $this->userRepository      = $userRepository;
        $this->officeRepository    = $officeRepository;
        $this->security            = $security;
        $this->companiesRepository = $companiesRepository;
        $this->mailer              = $mailer;
        $this->requestStack        = $requestStack;
        $this->assignedUserRepository = $assignedUserRepository;
    }

    public static function getEntityFqcn(): string
    {
        return License::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPaginatorPageSize(25)
            ->setPageTitle('index', 'Ausencias/Vacaciones')
            ->setEntityLabelInSingular('Ausencia/Vacación')
            ->setEntityLabelInPlural('Ausencias/Vacaciones')
            ->showEntityActionsInlined()
            ->overrideTemplate('crud/index', 'admin/license/custom_index.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            // Botón "Nuevo" con preservación de filtros
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                $params = $this->getRequestParamsWithDates();
                $url = $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Action::NEW);

                foreach ($params as $key => $value) {
                    $url->set($key, $value);
                }

                return $action->linkToUrl($url->generateUrl());
            })
            // Lápiz custom para editar preservando filtros
            ->add(Crud::PAGE_INDEX, Action::new('customEdit', '', 'fa fa-pencil')
                ->linkToUrl(function ($entity) {
                    /** @var License $entity */
                    $params = $this->getRequestParamsWithDates();
                    $url = $this->adminUrlGenerator
                        ->setController(self::class)
                        ->setAction(Action::EDIT)
                        ->setEntityId($entity->getId());

                    foreach ($params as $key => $value) {
                        $url->set($key, $value);
                    }

                    return $url->generateUrl();
                }))
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            // Tacho de eliminar sin etiqueta
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn(Action $a) => $a->setIcon('fa fa-trash')->setLabel(false));
    }

    public function createEntity(string $entityFqcn)
    {
        $license = new License();

        $request = $this->getContext()->getRequest();
        $us = $request->query->get('us');

        if ($us && $us !== 'all') {
            $user = $this->userRepository->find($us);
            if ($user) {
                $license->setUser($user);
            }
        }

        return $license;
    }

    public function configureFields(string $pageName): iterable
    {
        $adminUrlGenerator = $this->adminUrlGenerator;

        $nameField = TextField::new('user.name', 'Nombre')->setColumns(2)->onlyOnIndex()
            ->formatValue(function ($value, $entity) use ($adminUrlGenerator) {
                /** @var License $entity */
                $value = $entity->getUser() ? $entity->getUser()->getName() : 'Sin nombre';
                $url = $adminUrlGenerator
                    ->setController(self::class)
                    ->setAction('edit')
                    ->setEntityId($entity->getId())
                    ->generateUrl();

                return sprintf('<a href="%s">%s</a>', $url, $value);
            });

        $request = $this->getContext()->getRequest();
        $us = $request->query->get('us');

        if ($us && $us !== 'all') {
            $user = AssociationField::new('user', 'Usuario')
                ->setColumns(3)
                ->setFormTypeOption('query_builder', function (UserRepository $er) use ($us) {
                    return $er->createQueryBuilder('u')
                        ->andWhere('u.id = :userId')
                        ->setParameter('userId', $us);
                })
                ->setFormTypeOption('disabled', true);
        } else {
            /** @var User|null $currentUser */
            $currentUser = $this->getUser();
            $account = $currentUser ? $currentUser->getAccounts() : null;

            $user = AssociationField::new('user', 'Usuario')
                ->setColumns(3)
                ->setFormTypeOption('query_builder', function (UserRepository $er) use ($account) {
                    return $er->createQueryBuilder('u')
                        ->where('u.accounts = :account')
                        ->setParameter('account', $account)
                        ->orderBy('u.email', 'ASC');
                });
        }

        return [
            $nameField,
            TextField::new('user.lastname1', 'Apellido')->onlyOnIndex(),
            FormField::addPanel('Detalles de ausencia'),
            $user,
            TextField::new('comments', 'Comentario')->setColumns(5),

            // Tipo y estado con constantes de dominio
            \EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField::new('typeId', 'Tipo de ausencia')
                ->setChoices(AbsenceConstants::TYPES)
                ->renderExpanded(false)
                ->setColumns(4),

            \EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField::new('status', 'Estado')
                ->setChoices(AbsenceConstants::STATUS)
                ->renderExpanded(false)
                ->setColumns(3)
                ->formatValue(function ($value) {
                    return sprintf(
                        '<span class="badge %s">%s</span>',
                        AbsenceConstants::STATUS_COLORS[$value] ?? 'badge-secondary',
                        AbsenceConstants::STATUS_LABELS[$value] ?? 'Desconocido'
                    );
                })
                ->setCustomOption('html', true),

            // Vistas “solo índice” legibles para las fechas/horas
            TextField::new('getFechaHoraInicio', 'Fecha de inicio')->setColumns(3)->onlyOnIndex(),
            TextField::new('getFechaHoraFin', 'Fecha de fin')->setColumns(3)->onlyOnIndex(),

            // Campos de formulario reales
            DateField::new('dateStart', 'Fecha de inicio')->setColumns(2)->onlyOnForms(),
            DateField::new('dateEnd', 'Fecha de finalización')->setColumns(2)->onlyOnForms(),
            TimeField::new('timeStart', 'Hora de inicio')->setColumns(2)->setRequired(false)->onlyOnForms(),
            TimeField::new('timeEnd', 'Hora de finalización')->setColumns(2)->setRequired(false)->onlyOnForms(),

            NumberField::new('days', 'Días totales')->onlyOnIndex(),

            // Listado de documentos como links (solo índice)
            ArrayField::new('documents')
                ->setLabel('Documents')
                ->formatValue(function ($value) {
                    if ($value) {
                        $links = '';
                        foreach ($value as $document) {
                            $links .= '<a href="' . $document->getUrl() . '" target="_blank">' . $document->getName() . '</a><br>';
                        }
                        return $links;
                    }
                    return '';
                })
                ->setTextAlign('center')
                ->setCustomOption('html', true)
                ->onlyOnIndex(),
        ];
    }

    /**
     * 🧩 Etapa 3 — Normalización en el CRUD.
     * Si mismo día y hora fin < hora inicio, interpretamos “cruce de medianoche”
     * y ajustamos dateEnd +1 día. Evita errores aguas abajo y mantiene la
     * intención del usuario.
     */
    private function normalizeLicenseDates(License $entityInstance): void
    {
        $dateStart = $entityInstance->getDateStart();
        $dateEnd   = $entityInstance->getDateEnd();
        $timeStart = $entityInstance->getTimeStart();
        $timeEnd   = $entityInstance->getTimeEnd();

        if (
            $dateStart instanceof \DateTimeInterface &&
            $dateEnd instanceof \DateTimeInterface &&
            $timeStart instanceof \DateTimeInterface &&
            $timeEnd instanceof \DateTimeInterface
        ) {
            $sameDay  = $dateStart->format('Y-m-d') === $dateEnd->format('Y-m-d');
            $startVal = (int) $timeStart->format('His');
            $endVal   = (int) $timeEnd->format('His');

            if ($sameDay && $endVal < $startVal) {
                // Representamos el cruce de medianoche: fin = fin + 1 día
                $entityInstance->setDateEnd(\DateTimeImmutable::createFromInterface($dateEnd)->modify('+1 day'));
            }
        }
    }

    /**
     * Persistimos normalizando previamente para garantizar coherencia.
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof License) {
            $this->normalizeLicenseDates($entityInstance);
        }
        parent::persistEntity($entityManager, $entityInstance);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AfterEntityPersistedEvent::class => ['onAfterEntityPersisted'],
        ];
    }

    /**
     * Redirect post-persist preservando filtros de consulta.
     * Además, elimina variables no definidas ($startDate/$endDate) usando
     * getRequestParamsWithDates() como fuente de valores por defecto.
     */
    public function onAfterEntityPersisted(AfterEntityPersistedEvent $event): void
    {
        $entity = $event->getEntityInstance();
        if (!$entity instanceof User) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        $paramsDefault = $this->getRequestParamsWithDates();

        $com = $request->query->get('com') ?? (new \DateTime())->format('m');
        $off = $request->query->get('off') ?? 'all';
        $us  = $entity->getId();
        $start = $request->query->get('start', $paramsDefault['start']);
        $end   = $request->query->get('end',   $paramsDefault['end']);

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

    /**
     * Actualizamos con normalización + lógica de segmentos y notificación.
     */
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $shouldCreateSegment = false;
        $segmentPayload = null;
        $shouldRemoveSegment = false;
        $segmentToRemoveId = null;
        $email = null;

        if ($entityInstance instanceof License) {
            // 1) Normalización previa (cruce medianoche)
            $this->normalizeLicenseDates($entityInstance);

            $unitOfWork   = $entityManager->getUnitOfWork();
            $originalData = $unitOfWork->getOriginalEntityData($entityInstance);

            // Protegemos en caso de que la licencia no tenga usuario asociado
            $user = $entityInstance->getUser();
            $email = $user ? $user->getEmail() : null;

            // Marcamos acciones a ejecutar después de que EasyAdmin/proceso
            // principal haya actualizado la entidad (parent::updateEntity)
            if (
                $entityInstance->getStatus() === 1 &&
                $entityInstance->getExtraSegment() === 0
            ) {
                if ($user) {
                    $shouldCreateSegment = true;
                    $segmentPayload = [
                        'user' => $user,
                        'date' => $entityInstance->getDateStart(),
                        'timeStart' => $entityInstance->getTimeStart(),
                        'timeEnd' => $entityInstance->getTimeEnd(),
                        'typeId' => $entityInstance->getTypeId(),
                        'comments' => $entityInstance->getComments(),
                    ];
                }
            }

            // Si se rechaza y tenía segmento, eliminarlo (deferred)
            if (
                $entityInstance->getStatus() === 2 &&
                $entityInstance->getExtraSegment() !== 0
            ) {
                $shouldRemoveSegment = true;
                $segmentToRemoveId = $entityInstance->getExtraSegment();
                $entityInstance->setExtraSegment(0);
            }
        }

        // Primero dejamos que el flujo normal de EasyAdmin actualice la entidad
        parent::updateEntity($entityManager, $entityInstance);

        // Ahora ejecutamos las acciones diferidas para evitar que el formulario
        // de EasyAdmin intente mapear campos a la entidad equivocada.
        if ($shouldCreateSegment && $segmentPayload) {
            $segment = new UserExtraSegment();
            $segment->setUser($segmentPayload['user']);
            $segment->setDate($segmentPayload['date']);
            $segment->setTimeStart($segmentPayload['timeStart']);
            $segment->setTimeEnd($segmentPayload['timeEnd']);

            // Mapear a tipo de segmento según typeId
            if ($segmentPayload['typeId'] === 1) {
                $segment->setType(5);
            } elseif ($segmentPayload['typeId'] === 2) {
                $segment->setType(6);
            } else {
                $segment->setType(7);
            }

            $entityManager->persist($segment);
            $entityManager->flush();

            // Asociamos el id del segmento a la licencia y guardamos
            $entityInstance->setExtraSegment($segment->getId());
            $entityManager->persist($entityInstance);
            $entityManager->flush();
        }

        if ($shouldRemoveSegment && $segmentToRemoveId) {
            $segment = $entityManager->getRepository(UserExtraSegment::class)->find($segmentToRemoveId);
            if ($segment) {
                $entityManager->remove($segment);
                $entityManager->flush();
            }
        }

        // Envío de email si cambió el estado (se hace después del flush principal)
        if (isset($originalData['status']) && $originalData['status'] !== $entityInstance->getStatus()) {
            $newStatusLabel = AbsenceConstants::STATUS_LABELS[$entityInstance->getStatus()] ?? 'Desconocido';

            $htmlContent = $this->renderView('email/change_status_email.html.twig', [
                'newStatusLabel' => $newStatusLabel
            ]);

            // Solo enviamos email si tenemos una dirección válida
            if ($email) {
                $emailMessage = (new Email())
                    ->from('no-reply@intranek.com')
                    ->to($email)
                    ->subject('Solicitud de ausencia')
                    ->html($htmlContent);

                $this->mailer->send($emailMessage);
            }
        }
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $role = $user->getRole();
        $request = $this->requestStack->getCurrentRequest();

        $com = $request->query->get('com', $user->getCompany()->getId());
        $off = $request->query->get('off', 'all');
        $us  = $request->query->get('us', $user->getId());
        $account = $user->getAccounts();
        $company = $this->companiesRepository->findOneBy(['id' => $com]);

        $totalDays = null;
        $totalDaysLicense = null;
        $totalDaysUser = null;

        if ($user->getRole() === 'ROLE_SUPERVISOR') {
            // Supervisores: solo usuarios asignados
            $assigned = $user->getAssignedUsers();
            $users = array_map(fn($au) => $au->getUser(), $assigned->toArray());

            $userSelected = ($us && $us !== 'all')
                ? $this->userRepository->findOneBy(['id' => $us])
                : 'all';

            $responseParameters->set('users', $users);
            $responseParameters->set('selectedUser', $userSelected);

            if ($userSelected !== 'all') {
                $totalDays = $userSelected->getVacationDays();

                // Vacaciones aprobadas (typeId=3, status=1)
                $licenseByUser = $this->em->getRepository(License::class)->createQueryBuilder('l')
                    ->where('l.user = :user')
                    ->andWhere('l.typeId = :typeId')
                    ->andWhere('l.status = :status')
                    ->setParameter('user', $userSelected->getId())
                    ->setParameter('typeId', 3)
                    ->setParameter('status', 1)
                    ->getQuery()
                    ->getResult();

                $totalDaysLicense = 0;
                foreach ($licenseByUser as $license) {
                    $totalDaysLicense += $license->getDays();
                }

                $totalDaysUser = $totalDays - $totalDaysLicense;
            }

            $responseParameters->set('totalDays', $totalDays);
            $responseParameters->set('totalDaysLicense', $totalDaysLicense);
            $responseParameters->set('totalDaysUser', $totalDaysUser);

            return $responseParameters;
        }

        // Resto de roles (admin, etc.)
        $office = $off === 'all'
            ? $off
            : $this->officeRepository->findOneBy(['id' => $off]);

        $userSelected = $us === 'all'
            ? $us
            : $this->userRepository->findOneBy(['id' => $us]);

        $offices = $this->officeRepository->findBy(['company' => $com], ['name' => 'ASC']);
        $companies = $this->companiesRepository->findBy(['accounts' => $account], ['name' => 'ASC']);
        $responseParameters->set('companies', $companies);

        $users = ($off !== 'all')
            ? $this->userRepository->findBy(['office' => $office], ['name' => 'ASC'])
            : $this->userRepository->findBy(['company' => $com], ['name' => 'ASC']);

        $responseParameters->set('users', $users);
        $responseParameters->set('selectedUser', $userSelected);
        $responseParameters->set('selectedOffice', $office);
        $responseParameters->set('offices', $offices);
        $responseParameters->set('selectedCompany', $company);

        if ($userSelected !== 'all') {
            $totalDays = $userSelected->getVacationDays();

            $licenseByUser = $this->em->getRepository(License::class)->createQueryBuilder('l')
                ->where('l.user = :user')
                ->andWhere('l.typeId = :typeId')
                ->andWhere('l.status = :status')
                ->setParameter('user', $userSelected->getId())
                ->setParameter('typeId', 3)
                ->setParameter('status', 1)
                ->getQuery()
                ->getResult();

            $totalDaysLicense = 0;
            foreach ($licenseByUser as $license) {
                $totalDaysLicense += $license->getDays();
            }

            $totalDaysUser = $totalDays - $totalDaysLicense;
        }

        $responseParameters->set('totalDays', $totalDays);
        $responseParameters->set('totalDaysLicense', $totalDaysLicense);
        $responseParameters->set('totalDaysUser', $totalDaysUser);

        return $responseParameters;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        /** @var User $user */
        $user = $this->getUser();
        $role = $user->getRole();

        $request = $this->requestStack->getCurrentRequest();

        $com = $request->query->get('com');
        $off = $request->query->get('off');
        $us  = $request->query->get('us');

        if ($role === 'ROLE_SUPERVISOR') {
            if ($us !== 'all') {
                $selectedUser = $this->userRepository->findOneBy(['id' => $us]);

                if ($selectedUser) {
                    $qb->andWhere('entity.user = :selectedUser')
                        ->setParameter('selectedUser', $selectedUser);
                }
            } else {
                // Usuarios asignados al supervisor
                $assigned = $user->getAssignedUsers(); // colección de AssignedUser
                $users = array_map(fn($au) => $au->getUser(), $assigned->toArray());

                $qb->andWhere('entity.user IN (:assignedUsers)')
                    ->setParameter('assignedUsers', $users);
            }
        } else {
            if ($us === 'all') {
                if ($off !== 'all') {
                    $qb->innerJoin('entity.user', 'u')
                        ->andWhere('u.office = :selectedOffice')
                        ->setParameter('selectedOffice', $off);
                } else {
                    $qb->innerJoin('entity.user', 'u')
                        ->andWhere('u.company = :selectedCompany')
                        ->setParameter('selectedCompany', $com);
                }
            } else {
                $selectedUser = $this->userRepository->findOneBy(['id' => $us]);
                $qb->andWhere('entity.user = :selectedUser')
                    ->setParameter('selectedUser', $selectedUser);
            }
        }

        return $qb;
    }

    /**
     * Helper: devuelve filtros actuales, con defaults al 1er/último día del mes.
     */
    private function getRequestParamsWithDates(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $com = $request->query->get('com');
        $off = $request->query->get('off');
        $us  = $request->query->get('us');

        $startDate = new \DateTime('first day of this month');
        $endDate   = new \DateTime('last day of this month');

        $start = $request->query->get('start', $startDate->format('Y-m-d'));
        $end   = $request->query->get('end',   $endDate->format('Y-m-d'));

        return [
            'com'   => $com,
            'off'   => $off,
            'us'    => $us,
            'start' => $start,
            'end'   => $end,
        ];
    }
}
