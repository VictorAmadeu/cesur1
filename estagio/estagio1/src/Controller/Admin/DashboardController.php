<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use Symfony\Component\Security\Core\User\UserInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Symfony\Component\HttpFoundation\RequestStack;
use Doctrine\ORM\EntityManagerInterface;

use App\Repository\CompaniesRepository;
use App\Entity\User;
use App\Entity\TimesRegister;
use App\Entity\License;
use App\Entity\Companies;
use App\Entity\Accounts;
use App\Entity\AccessLog;
use App\Entity\Device;
use App\Entity\Office;
use App\Entity\DocumentType;
use App\Entity\Document;
use App\Entity\AssignedUser;
use App\Entity\Projects;

use App\Entity\WorkSchedule;
use App\Entity\WorkScheduleDay;
use App\Entity\UserWorkSchedule;
use App\Entity\WorkScheduleSegment;
use App\Entity\UserExtraSegment;

use App\Repository\UserRepository;

use App\Service\FilterSelectionService;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;

class DashboardController extends AbstractDashboardController
{
    private $aux;
    private $filterSelectionService;
    private $companiesRepository;
    private $userRepository;
    private $requestStack;

    public function __construct(
        AuxController $aux,
        FilterSelectionService $filterSelectionService,
        CompaniesRepository $companiesRepository,
        UserRepository $userRepository,
        RequestStack $requestStack,
    ) {
        $this->aux = $aux;
        $this->filterSelectionService = $filterSelectionService;
        $this->companiesRepository = $companiesRepository;
        $this->userRepository = $userRepository;
        $this->requestStack = $requestStack;
    }

    public function configureAssets(): Assets
    {
        return parent::configureAssets()
            ->addCssFile('css/customAdmin.css')
            ->addJsFile('https://code.jquery.com/jquery-3.6.0.min.js')
            ->addJsFile('https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js')
            ->addJsFile('https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js')
            ->addJsFile('js/customAdmin.js')
            ->addJsFile('js/accounting.min.js')
            ->addJsFile('js/filters-handler.js');
    }

    #[Route('/dashboard', name: 'admin')]
    public function index(): Response
    {
        $request = $this->requestStack->getCurrentRequest();
        /** @var User App/Entity/User $user */
        $user = $this->getUser();
        $com = $request->query->get('com', $user->getCompany()->getId());
        $isSuperAdmin = $this->isGranted('ROLE_SUPER_ADMIN');
        $params = [];

        $account = $user->getAccounts();
        $company = $this->companiesRepository->findOneBy(['id' => $com]);
        $params['companies'] = $this->companiesRepository->findBy(['accounts' => $account], ['name' => 'ASC']);

        $companies = $this->companiesRepository->findBy(['accounts' => $account], ['name' => 'ASC']); // Orden alfabético
        $params['selectedCompany'] = $company;
        return $this->render('bundles/EasyAdminBundle/dashboard.html.twig', $params);
    }

    public function configureDashboard(): Dashboard
    {
        $company = $this->aux->getCompany();
        return Dashboard::new()
            ->setTitle('<div><img style="height: 80px;" src="uploads/companiesLogo/' . $company->getLogo() . '"></div> <div>ADMIN INTRANEK</div>')
            ->setFaviconPath('images/favicon.png');
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        $request = $this->requestStack->getCurrentRequest();
        /** @var User App/Entity/User $user */
        $user = $this->getUser();
        $com = $request->query->get('com', $user->getCompany()->getId());
        $off = $request->query->get('off', 'all');

        // Obtener el primer y último día del mes actual
        $startDate = new \DateTime('first day of this month');  // Primer día del mes
        $endDate = new \DateTime('last day of this month');    // Último día del mes

        $startDateFormatted = $startDate->format('Y-m-d');
        $endDateFormatted = $endDate->format('Y-m-d');

        $start = $request->query->get('start', $startDateFormatted);
        $end = $request->query->get('end', $endDateFormatted);
        $us = $request->query->get('us', 'all');

        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            return parent::configureUserMenu($user)
                ->addMenuItems([
                    MenuItem::linkToCrud('Mi perfil', 'fa fa-user', User::class)
                        ->setController(ProfileUserController::class)
                        ->setAction('edit')
                        ->setEntityId($user->getId())
                        ->setQueryParameter('com', $com)
                        ->setQueryParameter('off', $off)
                        ->setQueryParameter('start', $start)
                        ->setQueryParameter('end', $end)
                        ->setQueryParameter('us', $us),
                    MenuItem::linkToCrud('Empresas', 'fa fa-building', Companies::class)
                        ->setQueryParameter('com', $com)
                        ->setQueryParameter('off', $off)
                        ->setQueryParameter('start', $start)
                        ->setQueryParameter('end', $end)
                        ->setQueryParameter('us', $us)
                ]);
        } else {
            $company = $this->aux->getCompany();
            return parent::configureUserMenu($user)
                ->addMenuItems([
                    MenuItem::linkToCrud('Mi perfil', 'fa fa-user', User::class)
                        ->setController(ProfileUserController::class)
                        ->setAction('edit')
                        ->setEntityId($user->getId())
                        ->setQueryParameter('com', $com)
                        ->setQueryParameter('off', $off)
                        ->setQueryParameter('start', $start)
                        ->setQueryParameter('end', $end)
                        ->setQueryParameter('us', $us),
                    MenuItem::linkToCrud('Mi empresa', 'fa fa-building', Companies::class)
                        ->setPermission('ROLE_ADMIN')
                        ->setController(CompaniesCrudController::class)
                        ->setAction('index')
                        ->setEntityId($company->getId())
                        ->setQueryParameter('com', $com)
                        ->setQueryParameter('off', $off)
                        ->setQueryParameter('start', $start)
                        ->setQueryParameter('end', $end)
                        ->setQueryParameter('us', $us)
                ]);
        }
    }

    public function configureMenuItems(): iterable
    {
        /** @var User App/Entity/User $user */
        $user = $this->getUser();
        $account = $user->getAccounts();
        $allowProjects = $account->getAllowProjects();
        $allowDevice = $account->getAllowDevice();
        $allowDocument = $account->getAllowDocument();
        $allowWorkSchedule = $account->getAllowWorkSchedule();


        //en el caso de necesitar permisos de empresas por si introducimos nuevos roles
        // $allowCompanyProjects = $account->getAllowCompanyProjects();
        // $allowCompanyDocument = $account->getAllowCompanyDocument();
        // $allowCompanyOffice = $account->getAllowCompanyOffice();
        // $allowCompanyWorkSchedule = $account->getAllowCompanyWorkSchedule();

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
        $us = $request->query->get('us', 'all');

        $queryParams = [
            'com' => $com,
            'off' => $off,
            'start' => $start,
            'end' => $end,
            'us' => $us
        ];
        yield MenuItem::linkToRoute('Inicio', 'fas fa-home', 'admin', $queryParams)
            ->setQueryParameter('com', $com)
            ->setQueryParameter('off', 'all')
            ->setQueryParameter('start', $start)
            ->setQueryParameter('end', $end)
            ->setQueryParameter('us', 'all');

        //Empleados y Roles
        yield MenuItem::section('Empleados');
        yield MenuItem::linkToCrud('Empleados', 'fa fa-user', User::class)
            ->setQueryParameter('com', $com)
            ->setQueryParameter('off', 'all')
            ->setQueryParameter('start', $start)
            ->setQueryParameter('end', $end)
            ->setQueryParameter('us', 'all');
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_SUPERVISOR')) {
            yield MenuItem::linkToCrud('Tiempos del empleado', 'fa fa-calendar', TimesRegister::class)
                ->setQueryParameter('com', $com)
                ->setQueryParameter('off', 'all')
                ->setQueryParameter('start', $start)
                ->setQueryParameter('end', $end)
                ->setQueryParameter('us', 'all');
            yield MenuItem::linkToCrud('Ausencias/Vacaciones', 'fa fa-id-card', License::class)
                ->setQueryParameter('com', $com)
                ->setQueryParameter('off', 'all')
                ->setQueryParameter('start', $start)
                ->setQueryParameter('end', $end)
                ->setQueryParameter('us', 'all');
        }

        // Estructura organizativa
        yield MenuItem::section('Estructura organizativa');
        if ($this->isGranted('ROLE_ADMIN')) {
            yield MenuItem::linkToCrud('Oficinas', 'fa fa-building', Office::class)
                ->setQueryParameter('com', $com)
                ->setQueryParameter('off', 'all')
                ->setQueryParameter('start', $start)
                ->setQueryParameter('end', $end)
                ->setQueryParameter('us', 'all');
        }
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_SUPERVISOR')) {
            yield MenuItem::linkToCrud('Supervisor', 'fa fa-lock', AssignedUser::class)
                ->setQueryParameter('com', $com)
                ->setQueryParameter('off', 'all')
                ->setQueryParameter('start', $start)
                ->setQueryParameter('end', $end)
                ->setQueryParameter('us', 'all');
        }
        if ($this->isGranted('ROLE_SUPER_ADMIN') || ($this->isGranted('ROLE_ADMIN') && $allowDevice)) {
            yield MenuItem::linkToCrud('Dispositivos', 'fas fa-mobile-alt', Device::class)->setPermission('ROLE_ADMIN')
                ->setQueryParameter('com', $com)
                ->setQueryParameter('off', 'all')
                ->setQueryParameter('start', $start)
                ->setQueryParameter('end', $end)
                ->setQueryParameter('us', 'all');
        }

        if ($allowWorkSchedule && $this->isGranted('ROLE_SUPERVISOR')) {
            yield MenuItem::section('Horarios');
            if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_SUPERVISOR')) {
                yield MenuItem::linkToCrud('Horario', 'fas fa-mobile-alt', WorkSchedule::class)
                    ->setQueryParameter('com', $com)
                    ->setQueryParameter('off', 'all')
                    ->setQueryParameter('start', $start)
                    ->setQueryParameter('end', $end)
                    ->setQueryParameter('us', 'all');
                yield MenuItem::linkToCrud('Asignación de horarios', 'fa fa-lock', UserWorkSchedule::class)
                    ->setQueryParameter('com', $com)
                    ->setQueryParameter('off', 'all')
                    ->setQueryParameter('start', $start)
                    ->setQueryParameter('end', $end)
                    ->setQueryParameter('us', 'all')
                    ->setQueryParameter('ws', 'all');
                yield MenuItem::linkToCrud('Segmentos Extraordinarios', 'fa fa-bolt', UserExtraSegment::class)
                    ->setQueryParameter('com', $com)
                    ->setQueryParameter('off', 'all')
                    ->setQueryParameter('start', $start)
                    ->setQueryParameter('end', $end)
                    ->setQueryParameter('us', 'all');
            }
        }

        if ($allowDocument) {
            if ($this->isGranted('ROLE_SUPER_ADMIN') || ($this->isGranted('ROLE_ADMIN') && $allowDocument)) {
                yield MenuItem::section('Documentos');
                yield MenuItem::linkToCrud('Documentos', 'fas fa-file-alt', Document::class)->setPermission('ROLE_ADMIN')
                    ->setQueryParameter('com', $com)
                    ->setQueryParameter('off', 'all')
                    ->setQueryParameter('start', $start)
                    ->setQueryParameter('end', $end)
                    ->setQueryParameter('us', 'all');
                yield MenuItem::linkToCrud('Tipos de documentos', 'fas fa-folder', DocumentType::class)->setPermission('ROLE_ADMIN')
                    ->setQueryParameter('com', $com)
                    ->setQueryParameter('off', 'all')
                    ->setQueryParameter('start', $start)
                    ->setQueryParameter('end', $end)
                    ->setQueryParameter('us', 'all');
            }
        }

        if ($allowProjects) {
            if ($this->isGranted('ROLE_SUPER_ADMIN') || ($this->isGranted('ROLE_ADMIN') && $allowProjects)) {
                yield MenuItem::section('Proyectos');
                yield MenuItem::linkToCrud('Proyectos', 'fa fa-project-diagram', Projects::class)->setPermission('ROLE_ADMIN')
                    ->setQueryParameter('com', $com)
                    ->setQueryParameter('off', 'all')
                    ->setQueryParameter('start', $start)
                    ->setQueryParameter('end', $end)
                    ->setQueryParameter('us', 'all');
            }
        }

        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            yield MenuItem::section('Configuración del sistema');
            yield MenuItem::linkToCrud('Cuentas globales', 'fa fa-lock', Accounts::class)->setPermission('ROLE_SUPER_ADMIN')
                ->setQueryParameter('com', $com)
                ->setQueryParameter('off', 'all')
                ->setQueryParameter('start', $start)
                ->setQueryParameter('end', $end)
                ->setQueryParameter('us', 'all');
        }
    }
}
