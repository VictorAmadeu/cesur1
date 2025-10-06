<?php

namespace App\Controller\Admin;

use App\Entity\TimesRegister;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use App\Form\UserSelectGlobalType;
use App\Controller\Admin\AuxController;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use App\Utils\DateUtils;
use Symfony\Component\HttpFoundation\RequestStack;

use App\Entity\Companies;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimeField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

use Symfony\Component\HttpFoundation\Response;
use App\Repository\UserRepository;
use Symfony\Bundle\SecurityBundle\Security;
use App\Repository\CompaniesRepository;
use App\Repository\OfficeRepository;
use App\Repository\AssignedUserRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;

use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Service\TimeRegisterManager;

class TimesRegisterCrudController extends AbstractCrudController
{
    private $security, $adminUrlGenerator, $em, $aux, $entityManager, $userRepository, $companiesRepository, $officeRepository, $requestStack, $assignedUserRepository, $timeRegisterManager;

    public function __construct(Security $security, AdminUrlGenerator $adminUrlGenerator, EntityManagerInterface $em, AuxController $aux, EntityManagerInterface $entityManager, UserRepository $userRepository, CompaniesRepository $companiesRepository, OfficeRepository $officeRepository, RequestStack $requestStack, AssignedUserRepository $assignedUserRepository, TimeRegisterManager $timeRegisterManager)
    {
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->em = $em;
        $this->aux = $aux;
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;
        $this->security = $security;
        $this->companiesRepository = $companiesRepository;
        $this->officeRepository = $officeRepository;
        $this->requestStack = $requestStack;
        $this->assignedUserRepository = $assignedUserRepository;
        $this->timeRegisterManager = $timeRegisterManager;
    }


    public static function getEntityFqcn(): string
    {
        return TimesRegister::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPaginatorPageSize(25)
            ->setPageTitle('index', 'Tiempos del empleado')
            ->setEntityLabelInSingular('Tiempo del empleado')
            ->setEntityLabelInPlural('Tiempos del empleado')
            ->showEntityActionsInlined()
            ->overrideTemplate('crud/index', 'admin/timesRegister/custom_index.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
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
            ->remove(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->setIcon('fa fa-trash')->setLabel(false);
            })
            ->remove(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                return $action->setIcon('fa fa-plus')->setLabel('Añadir tiempo');
            })
            ->add(Crud::PAGE_EDIT, Action::INDEX)
            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE)
            ->remove(Crud::PAGE_INDEX, Action::EDIT, function (Action $action) {
                return $action->setIcon('fa fa-plus')->setLabel('Añadir tiempo');
            });
    }

    public function configureFields(string $pageName): iterable
    {
        $ch = [];
        foreach ($this->aux->getCompaniesCurrentAccount() as $c) {
            $ch[$c->getName()] = $c->getId();
        }
        return [
            TextField::new('user.name', 'Nombre')->onlyOnIndex(),
            TextField::new('user.lastname1', 'Apellido')->onlyOnIndex(),
            DateTimeField::new('date', 'Fecha')->onlyOnIndex()->formatValue(function ($value) {
                return $value ? date('d/m/Y', $value->getTimestamp()) : null;
            }),
            DateTimeField::new('hourStart', 'Fecha inicio')->onlyOnIndex(),
            DateTimeField::new('hourStart', 'Fecha inicio')->onlyOnForms()->addCssClass('text-large text-bold'),
            DateTimeField::new('hourEnd', 'Fecha fin')->onlyOnIndex(),
            DateTimeField::new('hourEnd', 'Fecha fin')->onlyOnForms()->addCssClass('text-large text-bold'),
            TextField::new('comments', 'Comentario')->onlyOnForms()->addCssClass('text-large text-bold'),
            NumberField::new('slot', 'Slot')->onlyOnIndex(),
            TextField::new('total_slot_time', 'Tiempo total del slot')->onlyOnIndex(),
            TextField::new('totalTime', 'Tiempo total')->onlyOnIndex(),
        ];
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $user = $this->security->getUser();
        $role = $user->getRole();
        $request = $this->requestStack->getCurrentRequest();

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
        }else{ 
        
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
        $responseParameters->set('startDate', $start); 
        $responseParameters->set('endDate', $end);
        $responseParameters->set('years', DateUtils::getYears());
        $responseParameters->set('months', DateUtils::getMonths());
        $responseParameters->set('selectedCompany', $company);
        return $responseParameters;
    }
    

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $user = $this->getUser();
        $request = $this->requestStack->getCurrentRequest();

        $com = $request->query->get('com');
        $off = $request->query->get('off');
        $start = $request->query->get('start');
        $end = $request->query->get('end');
        $us = $request->query->get('us');
    
        $company = $this->companiesRepository->findOneBy(['id' => $com]);

        if($off !== 'all'){
            $office = $this->officeRepository->findOneBy(['id' => $off]);
        }else{
            $office = $off;   
        }

            if($office !== 'all'){
                if($us !== 'all'){
                    $qb->andWhere('entity.user = :selectedUser')
                        ->setParameter('selectedUser', $us);
                }else{
                    $userIds = $this->em->getRepository(User::class)
                        ->createQueryBuilder('u')
                        ->select('u.id')
                        ->where('u.office = :office')
                        ->setParameter('office', $office)
                        ->getQuery()
                        ->getSingleColumnResult();
        
                    $qb->andWhere('entity.user IN (:users)')
                        ->setParameter('users', $userIds);
                }
            }else{
                if($us !== 'all'){
                    $qb->andWhere('entity.user = :selectedUser')
                        ->setParameter('selectedUser', $us);
                }else{
                    $userIds = $this->em->getRepository(User::class)
                        ->createQueryBuilder('u')
                        ->select('u.id')
                        ->where('u.company = :company')
                        ->setParameter('company', $company)
                        ->getQuery()
                        ->getSingleColumnResult();
        
                    $qb->andWhere('entity.user IN (:users)')
                        ->setParameter('users', $userIds);
                }
            }

        $qb->andWhere('entity.date BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $start)
            ->setParameter('endDate', $end);

    
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

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        parent::updateEntity($entityManager, $entityInstance);
    
        // Luego de persistir el cambio, recalcular tiempos
        $this->timeRegisterManager->recalculateTimesForUserAndDate(
            $entityInstance->getUser(),
            $entityInstance->getDate()
        );
    }
}