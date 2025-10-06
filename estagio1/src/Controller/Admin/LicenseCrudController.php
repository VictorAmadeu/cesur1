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
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use App\Form\CompanySelectGlobalType;
use App\Form\UserSelectGlobalType;
use App\Controller\Admin\AuxController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use App\Utils\DateUtils;
use Symfony\Component\HttpFoundation\RequestStack;

use App\Entity\Companies;
use App\Entity\License;
use App\Entity\Document;
use App\Repository\UserRepository;
use App\Repository\OfficeRepository;
use App\Repository\AssignedUserRepository;
use Symfony\Bundle\SecurityBundle\Security;
use App\Repository\CompaniesRepository;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\MailerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use App\Enum\AbsenceConstants;
use App\Entity\UserExtraSegment;

class LicenseCrudController extends AbstractCrudController
{
    private $assignedUserRepository, $adminUrlGenerator, $mailer, $em, $aux, $userPasswordHasher, $userRepository, $officeRepository, $security, $companiesRepository, $requestStack;


    public function __construct(AssignedUserRepository $assignedUserRepository, MailerInterface $mailer, Security $security, AdminUrlGenerator $adminUrlGenerator, EntityManagerInterface $em, AuxController $aux, UserPasswordHasherInterface $userPasswordHasher, UserRepository $userRepository, OfficeRepository $officeRepository, CompaniesRepository $companiesRepository, RequestStack $requestStack)
    {
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->em = $em;
        $this->aux = $aux;
        $this->userPasswordHasher = $userPasswordHasher;
        $this->userRepository = $userRepository;
        $this->officeRepository = $officeRepository;
        $this->security = $security;
        $this->companiesRepository = $companiesRepository;
        $this->mailer = $mailer;
        $this->requestStack = $requestStack;
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
            // Personalizamos botón "Nuevo"
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

            // Ícono lápiz para editar (custom)
            ->add(Crud::PAGE_INDEX, Action::new('customEdit', '', 'fa fa-pencil')
                ->linkToUrl(function ($entity) {
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

            // Ícono tacho para eliminar
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action
                    ->setIcon('fa fa-trash')
                    ->setLabel(false);
            });
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
            $user = AssociationField::new('user', 'Usuario')
                ->setColumns(3)
                ->setFormTypeOption('query_builder', function (UserRepository $er) {
                    $account = $this->getUser()->getAccounts();
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
            ChoiceField::new('typeId', 'Tipo de ausencia')
                ->setChoices(AbsenceConstants::TYPES)
                ->renderExpanded(false)
                ->setColumns(4),

            TextField::new('comments', 'Comentario')->setColumns(5),

            ChoiceField::new('status', 'Estado')
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

            TextField::new('getFechaHoraInicio', 'Fecha de inicio')->setColumns(3)->onlyOnIndex(),
            TextField::new('getFechaHoraFin', 'Fecha de fin')->setColumns(3)->onlyOnIndex(),
            DateField::new('dateStart', 'Fecha de inicio')->setColumns(2)->onlyOnForms(),
            DateField::new('dateEnd', 'Fecha de finalización')->setColumns(2)->onlyOnForms(),
            TimeField::new('timeStart', 'Hora de inicio')->setColumns(2)->setRequired(false)->onlyOnForms(),
            TimeField::new('timeEnd', 'Hora de finalización')->setColumns(2)->setRequired(false)->onlyOnForms(),
            NumberField::new('days', 'Días totales')->onlyOnIndex(),

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
                ->OnlyOnIndex(),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
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

        $com = $request->query->get('com') ?? (new \DateTime())->format('m');
        $off = $request->query->get('off') ?? 'all';
        $us = $entity->getId();
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
        if ($entityInstance instanceof License) {
            $unitOfWork = $entityManager->getUnitOfWork();
            $originalData = $unitOfWork->getOriginalEntityData($entityInstance);
            $email = $entityInstance->getUser()->getEmail();

            if (
                $entityInstance->getStatus() === 1 &&
                $entityInstance->getExtraSegment() === 0
            ) {
                $user = $entityInstance->getUser();
                $segment = new UserExtraSegment();

                $segment->setUser($user);
                $segment->setDateStart($entityInstance->getDateStart());
                $segment->setDateEnd($entityInstance->getDateEnd());
                $segment->setTimeStart($entityInstance->getTimeStart());
                $segment->setTimeEnd($entityInstance->getTimeEnd());
                if ($entityInstance->getTypeId() === 1) {
                    $segment->setType(5);
                } else if ($entityInstance->getTypeId() === 2) {
                    $segment->setType(6);
                } else {
                    $segment->setType(7);
                };

                $entityManager->persist($segment);
                $entityManager->flush();
                $entityInstance->setExtraSegment($segment->getId());
            }

            if (
                $entityInstance->getStatus() === 2 &&
                $entityInstance->getExtraSegment() !== 0
            ) {
                $segmentId = $entityInstance->getExtraSegment();
                $segment = $entityManager->getRepository(UserExtraSegment::class)->find($segmentId);

                if ($segment) {
                    $entityManager->remove($segment);
                }

                $entityInstance->setExtraSegment(0);
            }


            if ($originalData['status'] !== $entityInstance->getStatus()) {
                $newStatusLabel = AbsenceConstants::STATUS_LABELS[$entityInstance->getStatus()] ?? 'Desconocido';

                $htmlContent = $this->renderView('email/change_status_email.html.twig', [
                    'newStatusLabel' => $newStatusLabel
                ]);

                $emailMessage = (new Email())
                    ->from('no-reply@intranek.com')
                    ->to($email)
                    ->subject('Solicitud de ausencia')
                    ->html($htmlContent);

                $this->mailer->send($emailMessage);
            }
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $user = $this->security->getUser();
        $role = $user->getRole();
        $request = $this->requestStack->getCurrentRequest();

        $com = $request->query->get('com', $user->getCompany()->getId());
        $off = $request->query->get('off', 'all');
        $us = $request->query->get('us', $user->getId());
        $account = $user->getAccounts();
        $company = $this->companiesRepository->findOneBy(['id' => $com]);

        $totalDays = null;
        $totalDaysLicense = null;
        $totalDaysUser = null;

        if ($user->getRole() === 'ROLE_SUPERVISOR') {
            $assigned = $user->getAssignedUsers(); // colección de AssignedUser
            $users = array_map(fn($au) => $au->getUser(), $assigned->toArray());

            if ($us && $us !== 'all') {
                $userSelected = $this->userRepository->findOneBy(['id' => $us]);
            } else {
                $userSelected = 'all';
            }
            $responseParameters->set('users', $users);
            $responseParameters->set('selectedUser', $userSelected);

            if ($userSelected !== 'all') {
                $totalDays = $userSelected->getVacationDays();

                // Buscar licencias por usuario y dentro del año
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
        } else {
            if ($off === 'all') {
                $office = $off;
            } else {
                $office = $this->officeRepository->findOneBy(['id' => $off]);
            }
            if ($us === 'all') {
                $userSelected = $us;
            } else {
                $userSelected = $this->userRepository->findOneBy(['id' => $us]);
            }

            $offices = $this->officeRepository->findBy(['company' => $com], ['name' => 'ASC']);


            $companies = $this->companiesRepository->findBy(['accounts' => $account], ['name' => 'ASC']);
            $responseParameters->set('companies', $companies);

            if ($off !== 'all') {
                $users = $this->userRepository->findBy(['office' => $office], ['name' => 'ASC']);
            } else {
                $users = $this->userRepository->findBy(['company' => $com], ['name' => 'ASC']);
            }
            $responseParameters->set('users', $users);
            $responseParameters->set('selectedUser', $userSelected);
            $responseParameters->set('selectedOffice', $office);
            $responseParameters->set('offices', $offices);
            $responseParameters->set('selectedCompany', $company);

            if ($userSelected !== 'all') {
                $totalDays = $userSelected->getVacationDays();

                // Buscar licencias por usuario y dentro del año
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
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $user = $this->getUser();
        $role = $user->getRole();

        $request = $this->requestStack->getCurrentRequest();

        $com = $request->query->get('com');
        $off = $request->query->get('off');
        $us = $request->query->get('us');

        if ($role === 'ROLE_SUPERVISOR') {
            if ($us !== 'all') {
                $selectedUser = $this->userRepository->findOneBy(['id' => $us]);

                if ($selectedUser) {
                    $qb->andWhere('entity.user = :selectedUser')
                        ->setParameter('selectedUser', $selectedUser);
                }
            } else {
                // Obtener los IDs de los usuarios asignados al supervisor
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

    private function getRequestParamsWithDates(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $com = $request->query->get('com');
        $off = $request->query->get('off');
        $us = $request->query->get('us');

        $startDate = new \DateTime('first day of this month');
        $endDate = new \DateTime('last day of this month');

        $start = $request->query->get('start', $startDate->format('Y-m-d'));
        $end = $request->query->get('end', $endDate->format('Y-m-d'));

        return [
            'com' => $com,
            'off' => $off,
            'us' => $us,
            'start' => $start,
            'end' => $end,
        ];
    }
}
