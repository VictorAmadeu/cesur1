<?php

namespace App\Controller\Admin;

use App\Entity\Companies;
use App\Entity\Accounts;
use App\Entity\User;
use App\Entity\Office;
use App\Entity\FilterOffice;
use App\Entity\FilterSelection;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use App\Controller\Admin\AuxController;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use App\Service\FilterSelectionService;

use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

class CompaniesCrudController extends AbstractCrudController
{
    private $entityManager, $security, $adminUrlGenerator, $aux, $filterSelectionService, $requestStack;

    public function __construct(EntityManagerInterface $entityManager, Security $security, FilterSelectionService $filterSelectionService, AdminUrlGenerator $adminUrlGenerator, AuxController $aux, RequestStack $requestStack)
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->aux = $aux;
        $this->filterSelectionService = $filterSelectionService;
        $this->requestStack = $requestStack;
    }

    public static function getEntityFqcn(): string
    {
        return Companies::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPaginatorPageSize(25)
            ->setPageTitle('index', 'Empresas')
            ->setEntityLabelInSingular('Empresa')
            ->setEntityLabelInPlural('Empresas')
            ->showEntityActionsInlined()
            ->overrideTemplate('crud/index', 'admin/company/custom_index.html.twig');
    }

    public function configureFields(string $pageName): iterable
    {
        /** @var User App/Entity/User $user */
        $user = $this->security->getUser();
        $account = $user->getAccounts();
        $allowProjects = $account->getAllowProjects();
        $allowManual = $account->getAllowManualEntry();
        $allowDevice = $account->getAllowDevice();
        $allowDocument = $account->getAllowDocument();
        $allowWorkSchedule = $account->getAllowWorkSchedule();

        if ($pageName === Crud::PAGE_INDEX) {
            yield TextField::new('comercialName', 'Nombre Comercial')->setColumns(3);
            yield TextField::new('name', 'Razón Social')->setColumns(3);
            yield TextField::new('NIF', 'CIF o NIF')->setColumns(1);
            yield BooleanField::new('active', 'Activa')->setColumns(3)->setFormTypeOption('data', true);
            return;
        }

        // Tab: Información Básica
        yield FormField::addTab('Información');
        yield TextField::new('comercialName', 'Nombre Comercial')->setColumns(3);
        yield TextField::new('name', 'Razón Social')->setColumns(3);
        yield TextField::new('NIF', 'CIF o NIF')->setColumns(1);
        yield ImageField::new('logo', 'Logo')
            ->setUploadDir('public/uploads/companiesLogo/')
            ->setBasePath('uploads/companiesLogo/')
            ->setUploadedFileNamePattern('[slug]-' . $this->aux->getCompany() . '.[extension]')
            ->setColumns(3)
            ->setFormTypeOption('allow_delete', false)
            ->setRequired(false);

        yield ImageField::new('logoAPP', 'Logo App')
            ->setUploadDir('public/uploads/companiesLogo/')
            ->setBasePath('uploads/companiesLogo/')
            ->setUploadedFileNamePattern('[slug]-' . $this->aux->getCompany() . '.[extension]')
            ->setColumns(3)
            ->setFormTypeOption('allow_delete', false)
            ->setRequired(false);

        // Tab: Información de Ubicación
        yield FormField::addPanel('Información de Ubicación');
        yield TextField::new('address', 'Dirección')->setColumns(4);
        yield ChoiceField::new('province', 'Provincia')
            ->setChoices($this->aux->getProvincies())
            ->setColumns(2);
        yield TextField::new('town', 'Municipio')->setColumns(3);
        yield TextField::new('CP', 'CP')->setColumns(1);

        // Tab: Contacto
        yield FormField::addPanel('Contacto');
        yield EmailField::new('email', 'E-mail')->setColumns(3);
        yield TelephoneField::new('phone', 'Teléfono')->setColumns(3);

        // Tab: Configuración Adicional
        yield FormField::addTab('Configuración');
        yield BooleanField::new('active', 'Activa')->setColumns(3)->setFormTypeOption('data', true);

        if ($allowDocument) {
            yield BooleanField::new('allowDocument', 'Permitir documentos')->setColumns(3);
        }

        if ($allowDevice) {
            yield BooleanField::new('allowDeviceRegistration', 'Fichar por dispositivo')->setColumns(3);
        }

        if ($allowManual) {
            yield BooleanField::new('setManual', 'Fichar manualmente')->setColumns(3);
        }

        if ($allowProjects) {
            yield BooleanField::new('allowProjects', 'Fichar por proyectos')->setColumns(3);
        }


        // Tab: Supervisor
        yield FormField::addPanel('Supervisor');
        yield BooleanField::new('allowSupervisorCreate', 'El supervisor puede crear usuarios?')
            ->setColumns(3);
        yield BooleanField::new('allowSupervisorEdit', 'El supervisor puede editar usuarios?')
            ->setColumns(3);

        if ($allowWorkSchedule) {
            yield FormField::addPanel('Horarios');
            yield BooleanField::new('allowWorkSchedule', 'Permitir horarios')->setColumns(3);
            yield BooleanField::new('applyAssignedSchedule', 'Permitir fichar en base al horario')
                ->setHelp('Si habilita esta opción se va a considerar el horario asignado al usuario para fichar, teniendo una consideración de 15 minutos previos y 15 minutos posteriores. Fuera del horario se considerará como horas extras.')
                ->setColumns(3)
                ->setDisabled(true);
        }
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

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        // Default values to create new company
        $entityInstance->setRemove(false);
        $entityInstance->setActive(true);

        /** @var User App/Entity/User $user */
        $user = $this->security->getUser();
        $entityInstance->setAccounts($user->getAccounts());

        // Check if a new logo was uploaded
        $uploadedLogo = $entityInstance->getLogo();
        if ($uploadedLogo === null) {
            // No new logo was uploaded, use the default logo
            $entityInstance->setLogo('defaultLogo.png');
        }

        parent::persistEntity($entityManager, $entityInstance);
    }


    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        /** @var User App/Entity/User $user */
        $user = $this->getUser();
        $isSuperAdmin = $this->security->isGranted('ROLE_SUPER_ADMIN');
        $account = $user->getAccounts();

        if (!$isSuperAdmin) {
            $qb->andWhere('entity.accounts = :account')
                ->setParameter('account', $account);
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

        $com = $entity->getOffice()->getCompany()->getId();
        $off = $entity->getOffice()->getId();
        $us = $entity->getId();
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
