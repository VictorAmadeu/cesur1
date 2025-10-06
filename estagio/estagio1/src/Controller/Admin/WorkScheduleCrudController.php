<?php

namespace App\Controller\Admin;

use App\Entity\WorkSchedule;
use App\Entity\WorkScheduleDay;
use App\Entity\WorkScheduleSegment;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\HiddenField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use App\Controller\Admin\WorkScheduleDayCrudController;
use App\Controller\Admin\WorkScheduleSegmentCrudController;
use Doctrine\ORM\EntityManagerInterface;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use Symfony\Bundle\SecurityBundle\Security;
use App\Controller\Admin\AuxController;
use App\Entity\User;
use App\Entity\UserWorkSchedule;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;

use App\Repository\CompaniesRepository;
use App\Repository\AssignedUserRepository;
use App\Service\DocumentErrorLogService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;

class WorkScheduleCrudController extends AbstractCrudController
{
    private $requestStack;
    private $em;
    private $security;
    private $aux;
    private $companiesRepository;
    private $documentErrorLogService;
    private $params;
    private $adminUrlGenerator;
    private $assignedUserRepository;

    public function __construct(
        Security $security,
        EntityManagerInterface $em,
        AuxController $aux,
        RequestStack $requestStack,
        CompaniesRepository $companiesRepository,
        DocumentErrorLogService $documentErrorLogService,
        ParameterBagInterface $params,
        AdminUrlGenerator $adminUrlGenerator,
        AssignedUserRepository $assignedUserRepository
    ) {
        $this->requestStack = $requestStack;
        $this->em = $em;
        $this->aux = $aux;
        $this->security = $security;
        $this->companiesRepository = $companiesRepository;
        $this->documentErrorLogService = $documentErrorLogService;
        $this->params = $params;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->assignedUserRepository = $assignedUserRepository;
    }

    public static function getEntityFqcn(): string
    {
        return WorkSchedule::class;
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets->addCssFile('css/customEditNew.css');
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPaginatorPageSize(25)
            ->setPageTitle('index', 'Horarios')
            ->setEntityLabelInSingular('Horario')
            ->setEntityLabelInPlural('Horarios')
            ->showEntityActionsInlined()
            ->overrideTemplate('crud/index', 'admin/WorkSchedule/custom_index.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions

            // Personalizamos botón "Nuevo"
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

            // Ícono lápiz para editar
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



            // Ícono tacho para eliminar
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action
                    ->setIcon('fa fa-trash')
                    ->setLabel(false);
            })

            ->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE);
    }

    public function configureFields(string $pageName): iterable
    {
        $fields = [
            FormField::addPanel('Horario laboral'),
            TextField::new('name')->setColumns(2),
            TextField::new('description')->setColumns(4),
            DateField::new('startDate', 'Fecha inicio')->setColumns(2),
            DateField::new('endDate', 'Fecha fin')->setColumns(2),

        ];

        $daysOfWeek = [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ];

        if ($pageName === Crud::PAGE_NEW) {
            $fields[] = CollectionField::new('workScheduleDays', 'Días')
                ->useEntryCrudForm(WorkScheduleDayCrudController::class) // o tu CrudController de WorkScheduleDay si lo tenés
                ->setEntryIsComplex(true)
                ->setFormTypeOption('by_reference', false)
                ->setColumns(12);

            $fields[] = CollectionField::new('workScheduleSegments', 'Segmentos')
                ->useEntryCrudForm(WorkScheduleSegmentCrudController::class)
                ->setEntryIsComplex(true)
                ->setFormTypeOption('by_reference', false)
                ->setColumns(12);
        }

        if ($pageName === Crud::PAGE_EDIT) {
            $entity = $this->getContext()->getEntity()->getInstance();

            // Obtener los días ya configurados (como array [dayOfWeek => WorkScheduleDay])
            $daysByNumber = [];
            if ($entity) {
                foreach ($entity->getWorkScheduleDays() as $wsDay) {
                    $daysByNumber[$wsDay->getDayOfWeek()] = $wsDay;
                }
            }

            $daysOfWeek = [
                1 => 'Lunes',
                2 => 'Martes',
                3 => 'Miércoles',
                4 => 'Jueves',
                5 => 'Viernes',
                6 => 'Sábado',
                7 => 'Domingo',
            ];

            $fields[] = FormField::addPanel('Días y horarios');

            $segmentsByDay = [];
            foreach ($entity->getWorkScheduleSegments() as $wsSegment) {
                $dayNum = $wsSegment->getWorkScheduleDay()?->getDayOfWeek();
                if ($dayNum !== null) {
                    $segmentsByDay[$dayNum][] = $wsSegment;
                }
            }

            foreach ($daysOfWeek as $dayNum => $dayName) {
                $wsDay = $daysByNumber[$dayNum] ?? null;

                $fields[] = Field::new("dayActive_{$dayNum}", $dayName)
                    ->setFormType(CheckboxType::class)
                    ->setFormTypeOption('mapped', false)
                    ->setFormTypeOption('data', $wsDay !== null)
                    ->setColumns(4);


                $fields[] = TimeField::new("start_{$dayNum}", 'Inicio')
                    ->setFormat('H:i')
                    ->setFormTypeOption('mapped', false)
                    ->setFormTypeOption('data', $wsDay ? $wsDay->getStart() : null)
                    ->setRequired(false)
                    ->setColumns(4);

                $fields[] = TimeField::new("end_{$dayNum}", 'Fin')
                    ->setFormat('H:i')
                    ->setFormTypeOption('mapped', false)
                    ->setFormTypeOption('data', $wsDay ? $wsDay->getEnd() : null)
                    ->setRequired(false)
                    ->setColumns(4);
            }

            $fields[] = FormField::addPanel('Segmentos');

            $segmentChoices = [
                'Almuerzo' => 1,
                'Descanso' => 2,
            ];

            $dayChoices = [
                'Lunes' => 1,
                'Martes' => 2,
                'Miércoles' => 3,
                'Jueves' => 4,
                'Viernes' => 5,
                'Sábado' => 6,
                'Domingo' => 7,
            ];

            $segmentsByDay = [];
            foreach ($entity->getWorkScheduleSegments() as $wsSegment) {
                $dayNum = $wsSegment->getWorkScheduleDay()?->getDayOfWeek();
                if ($dayNum !== null) {
                    if (!isset($segmentsByDay[$dayNum])) {
                        $segmentsByDay[$dayNum] = [];
                    }
                    $segmentsByDay[$dayNum][] = $wsSegment;
                }
            }

            foreach ($segmentsByDay as $dayNum => $segments) {
                foreach ($segments as $index => $segment) {
                    $dayName = $daysOfWeek[$dayNum] ?? 'Desconocido';

                    if ($segment->getId()) {
                        $fields[] = HiddenField::new("segment_{$dayNum}_{$index}_id")
                            ->setFormTypeOption('mapped', false)
                            ->setFormTypeOption('data', $segment->getId());
                    }

                    $fields[] = Field::new("segment_{$dayNum}_{$index}_active", 'Activo')
                        ->setFormType(CheckboxType::class)
                        ->setFormTypeOption('mapped', false)
                        ->setFormTypeOption('data', true) // por defecto activo
                        ->setColumns(2);

                    $fields[] = ChoiceField::new("segment_{$dayNum}_{$index}_day", 'Día')
                        ->setChoices($dayChoices)
                        ->setFormTypeOption('mapped', false)
                        ->setFormTypeOption('data', $dayNum)
                        ->setColumns(3);


                    $fields[] = ChoiceField::new("segment_{$dayNum}_{$index}_type", 'Tipo')
                        ->setChoices($segmentChoices)
                        ->setFormTypeOption('mapped', false)
                        ->setFormTypeOption('data', (int)$segment->getType())
                        ->setColumns(3);


                    $fields[] = TimeField::new("segment_{$dayNum}_{$index}_start", 'Inicio')
                        ->setFormat('H:i')
                        ->setFormTypeOption('mapped', false)
                        ->setFormTypeOption('data', $segment->getStart())
                        ->setColumns(2);

                    $fields[] = TimeField::new("segment_{$dayNum}_{$index}_end", 'Fin')
                        ->setFormat('H:i')
                        ->setFormTypeOption('mapped', false)
                        ->setFormTypeOption('data', $segment->getEnd())
                        ->setColumns(2);
                }
            }

            $fields[] = CollectionField::new('workScheduleSegments', 'Segmentos')
                ->useEntryCrudForm(WorkScheduleSegmentCrudController::class)
                ->setEntryIsComplex(true)
                ->setFormTypeOption('by_reference', false)
                ->setColumns(12);
        }

        return $fields;
    }


    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof WorkSchedule) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        $comId = $request->query->get('com');

        if ($comId) {
            $company = $this->companiesRepository->find($comId);
            if ($company) {
                $entityInstance->setCompany($company);
            }
        }

        $requestData = $this->getContext()->getRequest()->request->all();

        // Limpiar los días/segmentos actuales si estás reescribiendo todo
        foreach ($entityInstance->getWorkScheduleDays() as $existingDay) {
            $entityManager->remove($existingDay);
        }

        // Vaciar la colección en memoria para evitar residuos
        $entityInstance->getWorkScheduleDays()->clear();


        foreach ($entityInstance->getWorkScheduleSegments() as $existingSegment) {
            $entityManager->remove($existingSegment);
        }

        $entityInstance->getWorkScheduleSegments()->clear();

        // Guardar nuevos días
        if (!empty($requestData["WorkSchedule"]['workScheduleDays'])) {
            foreach ($requestData["WorkSchedule"]['workScheduleDays'] as $dayData) {
                foreach ($dayData['daysOfWeek'] as $dayOfWeek) {
                    // Saltar si dayOfWeek es null, vacío o 0
                    if (empty($dayOfWeek) && $dayOfWeek !== '0') {
                        continue;
                    }

                    $dayOfWeekInt = (int) $dayOfWeek;
                    if ($dayOfWeekInt <= 0) {
                        // Ignorar valores cero o negativos
                        continue;
                    }

                    $day = new WorkScheduleDay();
                    $day->setWorkSchedule($entityInstance);
                    $day->setDayOfWeek($dayOfWeekInt);

                    $start = \DateTime::createFromFormat('H:i', $dayData['start']);
                    $end = \DateTime::createFromFormat('H:i', $dayData['end']);

                    $day->setStart($start);
                    $day->setEnd($end);

                    $entityInstance->addWorkScheduleDay($day);
                    $entityManager->persist($day);
                }
            }
        }

        if (!empty($requestData["WorkSchedule"]['workScheduleSegments'])) {
            foreach ($requestData["WorkSchedule"]['workScheduleSegments'] as $segData) {
                foreach ($segData['daysOfWeek'] as $dayOfWeek) {
                    $segment = new \App\Entity\WorkScheduleSegment();

                    try {
                        $start = new \DateTime($segData['start']);
                        $end = new \DateTime($segData['end']);
                    } catch (\Exception $e) {
                        continue;
                    }

                    $segment->setStart($start);
                    $segment->setEnd($end);
                    $segment->setType($segData['type'] ?? '');
                    $segment->setWorkSchedule($entityInstance);

                    // Asociar el segmento al WorkScheduleDay correspondiente
                    foreach ($entityInstance->getWorkScheduleDays() as $existingDay) {
                        if ((string) $existingDay->getDayOfWeek() === (string) $dayOfWeek) {
                            $segment->setWorkScheduleDay($existingDay);
                            break;
                        }
                    }

                    $entityInstance->addWorkScheduleSegment($segment);
                    $entityManager->persist($segment);
                }
            }
        }


        $entityManager->persist($entityInstance);
        $entityManager->flush();
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $requestData = $this->getContext()->getRequest()->request->all();
        $formData = $requestData['WorkSchedule'] ?? [];
        $existingDays = $entityInstance->getWorkScheduleDays();
        $existingSegments = $entityInstance->getWorkScheduleSegments();

        $formDataStart = $formData["startDate"];
        $formDataEnd = $formData["endDate"];

        /** @var WorkSchedule $workSchedule */
        $existingAssignments = $this->em->getRepository(UserWorkSchedule::class)->findBy([
            'workSchedule' => $entityInstance
        ]);

        if ($existingAssignments) {
            foreach ($existingAssignments as $assignment) {
                $assignment->setStartDate(new \DateTime($formDataStart));
                $assignment->setEndDate($formDataEnd ? new \DateTime($formDataEnd) : null);
                $entityManager->persist($assignment);
            }
        }

        $daysToKeep = [];

        // Procesar los días activos y actualizar o crear WorkScheduleDay
        foreach (range(1, 7) as $dayNum) {
            $activeKey = "dayActive_$dayNum";
            $startKey = "start_$dayNum";
            $endKey = "end_$dayNum";

            $isActive = isset($formData[$activeKey]) && $formData[$activeKey] == "1";

            /** @var WorkScheduleDay|null $existingDay */
            $existingDay = $existingDays->filter(fn($d) => $d->getDayOfWeek() === $dayNum)->first();

            if ($isActive) {
                if (!$existingDay) {
                    $existingDay = new WorkScheduleDay();
                    $existingDay->setWorkSchedule($entityInstance);
                    $existingDay->setDayOfWeek($dayNum);
                }

                $existingDay->setStart(new \DateTime($formData[$startKey]));
                $existingDay->setEnd(new \DateTime($formData[$endKey]));

                $daysToKeep[] = $existingDay;
            }
        }

        // Limpiar y reemplazar los días activos
        $entityInstance->getWorkScheduleDays()->clear();
        foreach ($daysToKeep as $day) {
            $entityInstance->addWorkScheduleDay($day);
        }

        // Parsear segmentos del formulario
        $segments = [];
        foreach ($formData as $key => $value) {
            if (preg_match('/^segment_(\d+)_(\d+)_(\w+)$/', $key, $matches)) {
                $dayNum = (int) $matches[1];
                $index = (int) $matches[2];
                $field = $matches[3];

                $segments[$dayNum][$index][$field] = $value;
            }
        }

        $segmentsToKeep = [];

        foreach ($segments as $dayNum => $daySegments) {
            foreach ($daySegments as $index => $segmentData) {
                $isActive = isset($segmentData['active']) && $segmentData['active'] == "1";

                if (!$isActive) {
                    // Eliminar si corresponde
                    foreach ($existingSegments as $existingSegment) {
                        $segDay = $existingSegment->getWorkScheduleDay()?->getDayOfWeek();
                        if ($segDay === $dayNum) {
                            $entityInstance->removeWorkScheduleSegment($existingSegment);
                            $entityManager->remove($existingSegment);
                        }
                    }
                    continue;
                }

                $segmentId = $segmentData['id'] ?? null;
                $existingSegment = null;

                if ($segmentId) {
                    $existingSegment = $existingSegments->filter(
                        fn($s) => $s->getId() === (int)$segmentId
                    )->first();
                }

                if ($existingSegment) {
                    $existingSegment->setType((int)$segmentData['type']);
                    $existingSegment->setStart(new \DateTime($segmentData['start']));
                    $existingSegment->setEnd(new \DateTime($segmentData['end']));

                    $segmentsToKeep[] = $existingSegment;
                }
            }
        }

        if (!empty($formData['workScheduleSegments'])) {
            foreach ($formData['workScheduleSegments'] as $segmentData) {
                if (!isset($segmentData['daysOfWeek']) || empty($segmentData['daysOfWeek'])) {
                    continue; // O lanzar error
                }

                $dayNum = (int)$segmentData['daysOfWeek'][0]; // asumimos un solo día

                $workScheduleDay = $entityInstance->getWorkScheduleDays()
                    ->filter(fn($d) => $d->getDayOfWeek() === $dayNum)
                    ->first();

                if (!$workScheduleDay) {
                    $workScheduleDay = new WorkScheduleDay();
                    $workScheduleDay->setDayOfWeek($dayNum);
                    $workScheduleDay->setWorkSchedule($entityInstance);
                    $entityManager->persist($workScheduleDay);
                    $entityInstance->addWorkScheduleDay($workScheduleDay);
                }

                $segment = new WorkScheduleSegment();
                $segment->setWorkSchedule($entityInstance);
                $segment->setWorkScheduleDay($workScheduleDay);
                $segment->setType((int)$segmentData['type']);
                $segment->setStart(new \DateTime($segmentData['start']));
                $segment->setEnd(new \DateTime($segmentData['end']));

                $entityManager->persist($segment);
                $segmentsToKeep[] = $segment;
            }
        }

        // Limpiar y agregar sólo los segmentos activos que queremos mantener
        $entityInstance->getWorkScheduleSegments()->clear();
        foreach ($segmentsToKeep as $segment) {
            $entityInstance->addWorkScheduleSegment($segment);
        }

        $entityManager->persist($entityInstance);
        $entityManager->flush();
    }


    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $user = $this->security->getUser();
        $isSuperAdmin = $this->isGranted('ROLE_SUPER_ADMIN');
        $request = $this->requestStack->getCurrentRequest();

        /** @var \App\Entity\User $user */
        $com = $request->query->get('com', $user->getCompany()->getId());

        /** @var \App\Entity\User $user */
        $account = $user->getAccounts();
        $company = $this->companiesRepository->findOneBy(['id' => $com]);
        $companies = $this->companiesRepository->findBy(['accounts' => $account]);

        /** @var \App\Entity\User $user */
        if ($user->getRole() === 'ROLE_SUPERVISOR') {
            $assignedUsers = $this->assignedUserRepository->findBy(['supervisor' => $user]);

            $users = array_map(fn($assigned) => $assigned->getUser(), $assignedUsers);

            $companies = [];
            $offices = [];

            foreach ($users as $assignedUser) {
                $assignedCompany = $assignedUser->getCompany();
                $assignedOffice = $assignedUser->getOffice();

                if ($assignedCompany && !isset($companies[$assignedCompany->getId()])) {
                    $companies[$assignedCompany->getId()] = $assignedCompany;
                }

                if ($assignedCompany && $assignedOffice && !isset($offices[$assignedOffice->getId()]) && $assignedOffice->getCompany()->getId() === $assignedCompany->getId()) {
                    $offices[$assignedOffice->getId()] = $assignedOffice;
                }
            }

            $uniqueCompanies = array_values($companies);
            $uniqueOffices = array_values($offices);

            $responseParameters->set('moreThanOne', count($uniqueCompanies) > 1);
            $responseParameters->set('companies', $uniqueCompanies);
            $responseParameters->set('offices', $uniqueOffices);

            $responseParameters->set('selectedCompany', $company);

            return $responseParameters;
        } else {

            $responseParameters->set('companies', $companies);
            $responseParameters->set('selectedCompany', $company);
            return $responseParameters;
        }
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $user = $this->getUser();
        $request = $this->requestStack->getCurrentRequest();

        /** @var \App\Entity\User $user */
        $com = $request->query->get('com', $user->getCompany()->getId());

        $company = $this->companiesRepository->findOneBy(['id' => $com]);

        $qb->andWhere('entity.company = :selectedCompany')
            ->setParameter('selectedCompany', $company);
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
