<?php
namespace App\Controller\Admin;

use App\Entity\UserExtraSegment;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
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
use Doctrine\ORM\EntityManagerInterface;    
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use App\Repository\OfficeRepository;
use App\Repository\UserRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use App\Repository\AssignedUserRepository;

use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Enum\SegmentConstants;

class UserExtraSegmentCrudController extends AbstractCrudController
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
    )
    {
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
        $request = $this->requestStack->getCurrentRequest();
        $us = $request->query->get('us');
        $user = null;

        if ($us !== 'all') {
            $user = $this->userRepository->find($us);
        }

        return [
            AssociationField::new('user', 'Usuario')
                ->setFormTypeOption('data', $user)
                ->setColumns(3),

            ChoiceField::new('type', 'Tipo')
                ->setChoices(SegmentConstants::SEGMENTS)
                ->onlyOnForms()
                ->setColumns(3),

            DateField::new('date', 'Fecha')
                ->setRequired(false)
                ->setColumns(2),

            TimeField::new('timeStart', 'Hora de inicio')
                ->setFormat('H:i')
                ->formatValue(fn($value) => $value instanceof \DateTimeInterface ? $value->format('H:i') : null)
                ->setRequired(false)
                ->setColumns(2),

            TimeField::new('timeEnd', 'Hora de fin')
                ->setFormat('H:i')
                ->formatValue(fn($value) => $value instanceof \DateTimeInterface ? $value->format('H:i') : null)
                ->setRequired(false)
                ->setColumns(2),

            TextField::new('type', 'Tipo')
                ->formatValue(fn($value) => SegmentConstants::getLabel($value))
                ->onlyOnIndex()
                ->setColumns(2),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $entityManager->persist($entityInstance);
        $entityManager->flush();
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $user = $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();

        /** @var \App\Entity\User $user */
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
        $us = $request->query->get('us');

        if($us !== 'all'){
            $qb->andWhere('entity.user = :selectedUser')
               ->setParameter('selectedUser', $this->userRepository->find($us));
        }

        if($off !== 'all'){
            $qb->andWhere('entity.office = :selectedOffice')
               ->setParameter('selectedOffice', $this->officeRepository->find($off));
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
