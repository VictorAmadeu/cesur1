<?php

namespace App\Controller\Api;

use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTManager;
use App\Controller\Admin\AuxController;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Csrf\TokenManagerInterface;

#[Route('/api/global', methods: ['POST'])]
class ApiGlobalController extends AbstractController
{
    private $em, $aux, $securityContext;
    public function __construct(EntityManagerInterface $em, AuxController $aux)
    {
        $this->em = $em;
        $this->aux = $aux;
    }

    #[Route('/keepAlive', name: 'keepAlive')]
    public function keepAlive(TokenStorageInterface $tokenStorage): JsonResponse
    {
        $token = $tokenStorage->getToken();

        if (null === $token) {
            return $this->json([
                'code' => '400',
                'key'=> 'NO_TOKEN',
                'message' => 'Es necesario iniciar sesión.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = $token->getUser();

        if (!$user instanceof User) {
            return $this->json([
                'code' => '400',
                'key'=> 'NO_USER',
                'message' => 'Es necesario iniciar sesión.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->isVerified()) {
            return $this->json([
                'code' => '400',
                'key'=> 'NO_VERIFIED',
                'message' => 'Tu cuenta no está verificada.'
            ], Response::HTTP_FORBIDDEN);
        }

        if (!$user->isActive()) {
            return $this->json([
                'code' => '400',
                'key'=> 'NO_ACTIVE',
                'message' => 'Tu cuenta está desactivada.'
            ], Response::HTTP_FORBIDDEN);
        }

        if ($user->getFirstTime()) {
            return $this->json([
                'code' => '200',
                'key'=> 'FIRST_TIME',
                'message' => 'Debes configurar tu contraseña.'
            ], Response::HTTP_OK);
        }

        return $this->json([
            'code' => '200',
            'key'=> 'SESSION_OK',
            'message' => 'Sesión activa.',
        ], Response::HTTP_OK);
    }

}
