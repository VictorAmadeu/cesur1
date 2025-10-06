<?php

namespace App\Controller;

use App\Entity\User;

use App\Form\User1Type;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Routing\Attribute\Route;
use App\Controller\Admin\AuxController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[Route('/api/user', methods: ['POST'])]
class UserController extends AbstractController
{
    private $em, $aux;
    public function __construct(EntityManagerInterface $em, AuxController $aux)
    {
        $this->em = $em;
        $this->aux = $aux;
    }

    #[Route('/', name: 'app_user_index')]
    public function findAll(): JsonResponse
    {
        $user = $this->getUser();
        
        if (null === $user) return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.', 'code' => Response::HTTP_UNAUTHORIZED]);

        $data = $this->em->getRepository(User::class)->findAll();
        $dataArray = [];
        foreach ($data as $entity) $dataArray[] = $entity->toArray();

        return new JsonResponse(['data' => $dataArray, 'message' => 'La petición de solicitud fue correcta.', 'code' => Response::HTTP_OK]);
    }

    #[Route('/profile', name: 'app_user_profile')]
    public function getUserProfile(): JsonResponse
    {
        $user = $this->getUser();
    
        if (!$user) return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.', 'code' => Response::HTTP_UNAUTHORIZED]);
    
        $data = [
            'id' => $user->getId(),
            'name' => $user->getName(),
            'dni' => $user->getDni(),
            'lastname1' => $user->getLastname1(),
            'lastname2' => $user->getLastname2(),
            'phone' => $user->getPhone(),
            'email' => $user->getEmail(),
            'isActive' => $user->isActive(),
            'firstTime' => $user->getFirstTime(),
        ];

        // Obtener el rol del usuario
        $role = $user->getRole();
        $accounts = $user->getAccounts();
        $companies = $user->getCompany();

        // Si el usuario tiene un rol, incluirlo en los datos de respuesta
        if ($role) { $data['role'] = $role; };
        if ($accounts) { $data['accounts'] = $accounts->getName(); };
        if ($companies) { $data['companies'] = $companies->getName(); };
    
        return new JsonResponse(['data' => [$data], 'message' => 'La petición de solicitud fue correcta.', 'code' => Response::HTTP_OK]);
    }

    #[Route('/edit', name: 'app_user_edit')]
    public function setUser(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.', 'code' => Response::HTTP_UNAUTHORIZED]);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['dni']) && !preg_match('/^\d{7}[A-Z]$/', $data['dni'])) {
            return $this->json(['message' => 'El DNI debe seguir el formato correcto (ej. 4151109A).', 'code' => Response::HTTP_BAD_REQUEST]);
        }

        if (isset($data['name'])) {
            $user->setName($data['name']);
        }
        if (isset($data['dni'])) {
            $user->setDni($data['dni']);
        }
        if (isset($data['lastname1'])) {
            $user->setLastname1($data['lastname1']);
        }
        if (isset($data['lastname2'])) {
            $user->setLastname2($data['lastname2']);
        }
        if (isset($data['phone'])) {
            $user->setPhone($data['phone']);
        }
        if (isset($data['email'])) {
            $user->setEmail($data['email']);
        }

        $this->em->flush();

        return $this->json(['message' => 'Usuario actualizado correctamente', 'code' => Response::HTTP_OK]);
    }

    #[Route('/disable', name: 'app_user_disable')]
    public function disableUser(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.', 'code' => Response::HTTP_UNAUTHORIZED]);

        $user->setIsActive(false);

        $this->em->flush();

        return $this->json(['message' => 'Usuario desactivado correctamente'], Response::HTTP_OK);
    }

    #[Route('/active', name: 'app_user_active', methods: ['PUT'])]
    public function activeUser(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.', 'code' => Response::HTTP_UNAUTHORIZED]);

        $user->setIsActive(true);

        $this->em->flush();

        return $this->json(['message' => 'Usuario activado correctamente'], Response::HTTP_OK);
    }

    #[Route('/request-registration', name: 'app_user_request_registration', methods: ['POST'])]
    public function requestRegistration(Request $request): JsonResponse
    {
        // Obtener los datos de la solicitud
        $data = json_decode($request->getContent(), true);

        // Verificar que los datos requeridos estén presentes
        if (!isset($data['name']) || !isset($data['lastname1']) || !isset($data['lastname2']) || !isset($data['email'])) {
            return $this->json(['message' => 'Faltan datos requeridos para el registro'], Response::HTTP_BAD_REQUEST);
        }

        // Crear una nueva instancia de User
        $user = new User();

        // Establecer los datos del usuario
        $user->setName($data['name']);
        $user->setLastname1($data['lastname1']);
        $user->setLastname2($data['lastname2']);
        $user->setEmail($data['email']);
        $user->setPassword("Passw0rd");
        $user->setAccounts(null);
        $user->setCompanies([]);
        $user->setPhone("000000000");
        $user->setIsActive(false); // Establecer isActive en false
        $user->setIsVerified(false); // Establecer isVerified en false

        // Buscar el rol ROLE_USER en la base de datos
        $roleUser = "ROLE_USER";

        // Asignar el rol ROLE_USER al usuario
        $user->setRole($roleUser);

        $this->em->persist($user);
        $this->em->flush();

        return $this->json(['message' => 'Solicitud de registro enviada correctamente', 'code' => Response::HTTP_OK]);
    }

    #[Route('/front', name: 'forgot_password')]
    public function forgotPassword(Request $request, MailerInterface $mailer): Response
    {
        $param = json_decode($request->getContent(), true);
        $email = isset($param['email']) ? $param['email'] : null;
        
        // Buscar el usuario por su correo electrónico
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        // Si el usuario existe, enviar un correo electrónico con un enlace para restablecer la contraseña
        if ($user) {
            // Lógica para generar y enviar el enlace de restablecimiento de contraseña por correo electrónico
            $email = (new Email())
                ->from('noreply@example.com')
                ->to($email)
                ->subject('Solicitud de restablecimiento de contraseña')
                ->html('<p>Haga clic en el siguiente enlace para restablecer su contraseña: <a href="https://tudominio.com/reset-password">Restablecer contraseña</a></p>');

            $mailer->send($email);

            return $this->json(['message' => 'Se ha enviado un correo electrónico con instrucciones para restablecer la contraseña.', 'code' => Response::HTTP_OK]);
        }

        // Si el usuario no existe, mostrar un mensaje genérico para evitar revelar información
        return $this->json(['message' => 'Se ha enviado un correo electrónico con instrucciones para restablecer la contraseña.', 'code' => Response::HTTP_OK]);
    }

    #[Route('/{id}', name: 'app_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }
}
