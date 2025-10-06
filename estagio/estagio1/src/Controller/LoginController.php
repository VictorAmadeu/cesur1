<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use App\Service\FilterSelectionService;
use App\Entity\Accounts;
use App\Entity\Companies;
use App\Entity\Office;
use App\Entity\FilterOffice;
use Symfony\Bundle\SecurityBundle\Security;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;


class LoginController extends AbstractController
{
    private $em, $security;

    public function __construct(EntityManagerInterface $em, Security $security)
    {
        $this->em = $em;
        $this->security = $security;

    }

    #[Route('/', name: 'app_login')]
    public function index(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('login/index.html.twig', [
            'controller_name' => 'LoginController',
            'last_username'   => $lastUsername,
            'error'           => $error,
        ]);
    }

    #[Route('/logout', name: 'logout')]
    public function logout(): never
    {
        throw new \Exception('Don\'t forget to activate logout in security.yaml');
    }

    #[Route('/switch', name: 'switch')]
    public function switch(): Response
    {
        $user = $this->getUser();
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_SUPER_ADMIN') || $this->isGranted('ROLE_SUPERVISOR')) {
            // Obtener la compañía y la cuenta del usuario
            $company = $user->getCompany();
            $account = $user->getAccounts();

            // Obtener el primer y último día del mes actual
            $startDate = new \DateTime('first day of this month');  // Primer día del mes
            $endDate = new \DateTime('last day of this month');    // Último día del mes
    
            // Formatear las fechas a 'Y-m-d' para usarlas en el formulario
            $startDateFormatted = $startDate->format('Y-m-d');
            $endDateFormatted = $endDate->format('Y-m-d');

            $url = $this->generateUrl('admin', [
                'com' => $company->getId(),
                'off' => 'all', 
                'start' => $startDateFormatted,
                'end' => $endDateFormatted,
                'us' => $user->getId(),
            ]);
    
            return $this->redirect($url);
        }
    
        throw $this->createAccessDeniedException('No tienes permisos suficientes para acceder a esta página.');
    }
    
}
