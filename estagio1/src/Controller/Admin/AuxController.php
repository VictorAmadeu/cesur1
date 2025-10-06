<?php

namespace App\Controller\Admin;

use App\Entity\Accounts;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\SecurityBundle\Security;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;

use App\Entity\Companies;
use App\Entity\User;
use App\Entity\FilterSelection;
use App\Repository\CompaniesRepository;
use App\Entity\TimesRegister;

class AuxController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Accounts::class;
    }

    private $adminContextProvider, $companiesRepository, $em, $companies, $users, $currentUser, $accounts, $salesAccounts, $taxes, $tpl, $su, $adminUrlGenerator;

    public function __construct(AdminContextProvider $adminContextProvider, EntityManagerInterface $em, Security $su, CompaniesRepository $companiesRepository, AdminUrlGenerator $adminUrlGenerator,)
    {
        $this->adminContextProvider = $adminContextProvider;
        $this->em = $em;
        $this->su = $su;
        $this->companiesRepository = $companiesRepository;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPaginatorPageSize(25)
            ->setPageTitle('index', 'Cuenta')
            ->setEntityLabelInSingular('Cuenta')
            ->setEntityLabelInPlural('Cuentas')
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->update(Crud::PAGE_INDEX, Action::EDIT, function (Action $action) {
                return $action->setIcon('fa fa-pencil')->setLabel(false);
            })
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->setIcon('fa fa-trash')->setLabel(false);
            })
            ->add(Crud::PAGE_EDIT, Action::INDEX)
            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE);
    }
    
    

    public function configureFields(string $pageName): iterable
    {
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
        
        return [
            $nameField,
            BooleanField::new('allowDevice', 'Fichar por dispositivos')->setColumns(3),
            BooleanField::new('allowManualEntry', 'Fichar manualmente')->setColumns(3),
            BooleanField::new('allowProjects', 'Fichar por proyectos')->setColumns(3),
            BooleanField::new('allowDocument', 'Permitir documentos')->setColumns(3),
            BooleanField::new('allowWorkSchedule', 'Permitir horarios')->setColumns(3),
        ];
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Accounts) {
            $user = $this->getUser();
            // $filterSelection = $this->filterSelectionService->findFilterSelectionByUser($user);
            // $selectedCompany = $filterSelection->getCompany();
            // $entityInstance->setCompany($selectedCompany);
            $allowProjects = $entityInstance->getAllowProjects();
            $allowManualEntry = $entityInstance->getAllowManualEntry();
            $allowDevice = $entityInstance->getAllowDevice();
            $allowDocument = $entityInstance->getAllowDocument();
            $allowWorkSchedule = $entityInstance->getAllowWorkSchedule();
            
            $account = $user->getAccounts();
            $companies = $this->companiesRepository->findBy(['accounts' => $account]);

            if(!$allowProjects){
                foreach ($companies as $company) {
                    $company->setSetManual(false);
                    $this->em->persist($company);
                }
            }

            if(!$allowProjects){
                foreach ($companies as $company) {
                    $company->setAllowProjects(false);
                    $this->em->persist($company);
                }
            }

            if(!$allowDevice){
                foreach ($companies as $company) {
                    $company->setAllowDeviceRegistration(false);
                    $this->em->persist($company);
                }
            }

            if(!$allowDocument){
                foreach ($companies as $company) {
                    $company->setAllowDocument(false);
                    $this->em->persist($company);
                }
            }
            
            if(!$allowWorkSchedule){
                foreach ($companies as $company) {
                    $company->setAllowWorkSchedule(false);
                    $company->setApplyAssignedSchedule(false);
                    $this->em->persist($company);
                }
            }
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    /* getAllCompanies*/
    public function getCompanies()
    {
        if (!$this->companies) {
            $this->companies = $this->em->getRepository(Companies::class)->findAll();
        }
        return $this->companies;
    }

    /* getCurrentCompany*/
    public function getCompaniesCurrentAccount()
    {
        return $this->em->getRepository(Companies::class)->findBy(['accounts' => $this->getAccountUser()]);
    }

    /* getCurrentCompany*/
    public function getCompany()
    {
        return $this->em->getRepository(Companies::class)->find($this->getCompanySelectedValue());
    }

    /* getAllUsers*/
    public function getUsers()
    {
        if (!$this->users) {
            $this->users = $this->em->getRepository(User::class)->findAll();
        }
        return $this->users;
    }

    /* getUserByCompanySelected*/
    public function getUserByCompanySelected($company)
    {
        if (!$this->users) {
            $this->users = $this->em->getRepository(User::class)->getUserByCompanySelected($company);
        }
        return $this->users;
    }

    /* getAllAccounts*/
    public function getAccounts()
    {
        $r = [];
        if (!$this->accounts) {
            $this->accounts = $this->em->getRepository(Accounts::class)->findAll();
        }
        foreach ($this->accounts as $a) {
            $r[$a->getName()] = $a;
        }
        return $r;
    }

    /* get account user*/
    public function getAccountUser()
    {
        $r = $this->em->getRepository(Accounts::class)->find($this->getUser()->getAccounts()->getId());
        return $r;
    }


    public function getTimeSlotByDate(\DateTime $date)
    {
        $todayStart = $date->format('Y-m-d 00:00:00');
        $tomorrowStart = (clone $date)->modify('+1 day')->format('Y-m-d 00:00:00');

        $r = $this->em->getRepository(TimesRegister::class)->createQueryBuilder('tr')
            ->where('tr.user = :user')
            ->andWhere('tr.date >= :todayStart')
            ->andWhere('tr.date < :tomorrowStart')
            ->setParameter('user', $this->getUser())
            ->setParameter('todayStart', $todayStart)
            ->setParameter('tomorrowStart', $tomorrowStart)
            ->orderBy('tr.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $r;
    }

    
    public function getTimeSlot(\DateTimeInterface $date): ?TimesRegister
    {
        $formattedDate = $date->format('Y-m-d');

        return $this->em->getRepository(TimesRegister::class)->createQueryBuilder('tr')
            ->where('tr.user = :user')
            ->andWhere('tr.date = :date')
            ->setParameter('user', $this->getUser())
            ->setParameter('date', $formattedDate)
            ->orderBy('tr.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }



    /* get time slot */
    public function getTimesSlot(\DateTimeInterface $date)
    {
        $formattedDate = $date->format('Y-m-d');

        $r = $this->em->getRepository(TimesRegister::class)->createQueryBuilder('tr')
            ->where('tr.user = :user')
            ->andWhere('tr.date = :date')
            ->setParameter('user', $this->getUser())
            ->setParameter('date', $formattedDate)
            ->orderBy('tr.id', 'DESC')
            ->getQuery()
            ->getResult();
        return $r;
    }


    /* getSelectCompanyValue or redirect to create one */
    public function getCompanySelectedValue()
    {
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class); /* Prepare to generate url */
        if (count($this->getCompanies()) == 0) { /* Check if company exist */
            /* If not, then go to create */
            $this->addFlash('warning', 'Es necesario crear una empresa para empezar a trabajar. Haga click <a href="https://admin.intranek.comdashboard?crudAction=new&crudControllerFqcn=App%5CController%5CAdmin%5CCompaniesCrudController">aquí.</a>');
            return $this->redirect("https://admin.intranek.comdashboard?crudAction=new&crudControllerFqcn=App%5CController%5CAdmin%5CCompaniesCrudController");
        } else { /* If exist, then render de form in dashboard */
            $selectCU = $this->em->getRepository(FilterSelection::class)->findOneBy(['user' => $this->getUser()], ['id' => 'DESC']);

            /* Check first use for user (push default company (first) in DDBB) */
            if (!$selectCU) {
                $selectCU = new FilterSelection();
                $companies = $this->getCompaniesCurrentAccount();
                if (!empty($companies)) {
                    $selectCU->setCompany($companies[0]); // Aquí estamos pasando el objeto
                }
                $selectCU->setUser($this->su->getUser());
                $this->em->persist($selectCU);
                $this->em->flush();
                $selectedValue = $companies[0]->getId(); // Si necesitas el ID, lo puedes obtener aquí
            } else {
                $selectedValue = $selectCU->getCompany()->getId(); // Asegúrate de obtener el ID del objeto
            }
            return $selectedValue;
        }
    }

    public function getProvincies()
    {
        $provincias = array(
            'Álava' => 'Álava',
            'Albacete' => 'Albacete',
            'Alicante' => 'Alicante',
            'Almería' => 'Almería',
            'Asturias' => 'Asturias',
            'Ávila' => 'Ávila',
            'Badajoz' => 'Badajoz',
            'Barcelona' => 'Barcelona',
            'Burgos' => 'Burgos',
            'Cáceres' => 'Cáceres',
            'Cádiz' => 'Cádiz',
            'Cantabria' => 'Cantabria',
            'Castellón' => 'Castellón',
            'Ciudad Real' => 'Ciudad Real',
            'Córdoba' => 'Córdoba',
            'Cuenca' => 'Cuenca',
            'Gerona' => 'Gerona',
            'Granada' => 'Granada',
            'Guadalajara' => 'Guadalajara',
            'Guipúzcoa' => 'Guipúzcoa',
            'Huelva' => 'Huelva',
            'Huesca' => 'Huesca',
            'Islas Baleares' => 'Islas Baleares',
            'Jaén' => 'Jaén',
            'La Coruña' => 'La Coruña',
            'La Rioja' => 'La Rioja',
            'Las Palmas' => 'Las Palmas',
            'León' => 'León',
            'Lérida' => 'Lérida',
            'Lugo' => 'Lugo',
            'Madrid' => 'Madrid',
            'Málaga' => 'Málaga',
            'Murcia' => 'Murcia',
            'Navarra' => 'Navarra',
            'Orense' => 'Orense',
            'Palencia' => 'Palencia',
            'Pontevedra' => 'Pontevedra',
            'Salamanca' => 'Salamanca',
            'Santa Cruz de Tenerife' => 'Santa Cruz de Tenerife',
            'Segovia' => 'Segovia',
            'Sevilla' => 'Sevilla',
            'Soria' => 'Soria',
            'Tarragona' => 'Tarragona',
            'Teruel' => 'Teruel',
            'Toledo' => 'Toledo',
            'Valencia' => 'Valencia',
            'Valladolid' => 'Valladolid',
            'Vizcaya' => 'Vizcaya',
            'Zamora' => 'Zamora',
            'Zaragoza' => 'Zaragoza'
        );

        return $provincias;
    }

    public static function getDays()
    {
        return [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ];
    }

    public static function getSegments()
    {
        return [
            1 => 'Almuerzo',
            2 => 'Descanso',
        ];
    }

    public static function getExtraSegments()
    {
        return [
            1 => 'Hora extra',
            2 => 'Evento',
        ];
    }
}
