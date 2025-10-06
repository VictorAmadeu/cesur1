<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\ResetPasswordRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;

#[Route('/api/reset-password', methods: ['POST'])]
class ApiResetPasswordController extends AbstractController
{
    private $resetPasswordHelper;
    private $entityManager;

    public function __construct(ResetPasswordHelperInterface $resetPasswordHelper, EntityManagerInterface $entityManager)
    {
        $this->resetPasswordHelper = $resetPasswordHelper;
        $this->entityManager = $entityManager;
    }

     #[Route('/request', name: 'requestPasswordFront')]
     public function requestResetPassword(Request $request, MailerInterface $mailer, TranslatorInterface $translator): JsonResponse
     {
         $data = json_decode($request->getContent(), true);
         $email = $data['email'] ?? null;

         if (!$email) {
             return new JsonResponse(['message' => 'El correo es necesario', 'code' => '400']);
         }

         $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

         if (!$user) {
             return new JsonResponse(['message' => 'Si el correo esta registrado, vas a recibir el enlace.', 'code' => '200']);
         }

         try {
             $resetToken = $this->resetPasswordHelper->generateResetToken($user);
         } catch (ResetPasswordExceptionInterface $e) {
             return new JsonResponse([
                 'message' => sprintf(
                     '%s - %s',
                     $translator->trans(ResetPasswordExceptionInterface::MESSAGE_PROBLEM_HANDLE, [], 'ResetPasswordBundle'),
                     $translator->trans($e->getReason(), [], 'ResetPasswordBundle')
                 ), 'code' => '404'
            ]);
         }
         $resetUrl = sprintf('https://www.intranek.com/forgot-password/%s', $resetToken->getToken());

         $email = (new TemplatedEmail())
             ->from(new Address($this->getParameter('app.web.email_from'), $this->getParameter('app.web.name')))
             ->to($user->getEmail())
             ->subject('Restablecer contraseña')
             ->htmlTemplate('reset_password/email.html.twig')
             ->context([
                 'resetUrl' => $resetUrl,
                 'resetToken' => $resetToken
             ]);

         $mailer->send($email);

         return new JsonResponse(['message' => 'Se a enviado un enlace a su correo.', 'code' => '200']);
    } 

    #[Route('/change', name: 'changePassword', methods: ['POST'])]
    public function resetPassword(
        Request $request, 
        UserPasswordHasherInterface $passwordHasher, 
        TranslatorInterface $translator
    ): JsonResponse {
        // Obtener los datos enviados en el cuerpo de la solicitud
        $data = json_decode($request->getContent(), true);
        $newPassword = $data['newPassword'] ?? null;
        $token = $data['token'] ?? null;

        // Validar que se haya enviado la nueva contraseña
        if (!$newPassword) {
            return new JsonResponse(['message' => 'Es necesaria una contraseña.', 'code' => '400']);
        }

        try {
            // Validar el token y obtener el usuario asociado
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            // En caso de error, devolver un mensaje traducido
            return new JsonResponse([
                'message' => sprintf(
                    '%s - %s',
                    $translator->trans(ResetPasswordExceptionInterface::MESSAGE_PROBLEM_VALIDATE, [], 'ResetPasswordBundle'),
                    $translator->trans($e->getReason(), [], 'ResetPasswordBundle')
                ), 'code' => '404'
            ]);
        }

        // Hashear la nueva contraseña y asignarla al usuario
        $encodedPassword = $passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($encodedPassword);

        // Guardar los cambios en la base de datos
        $this->entityManager->flush();

        // Eliminar el token de restablecimiento usado
        $this->resetPasswordHelper->removeResetRequest($token);

        // Responder con éxito
        return new JsonResponse(['message' => 'Contraseña cambiada con exito', 'code' => '200']);
    }

    #[Route('/change-first', name: 'changePasswordFirstTime', methods: ['POST'])]
    public function resetPasswordFirstTime(
        Request $request, 
        UserPasswordHasherInterface $passwordHasher, 
        TranslatorInterface $translator
    ): JsonResponse {
        $user = $this->getUser();
        // Obtener los datos enviados en el cuerpo de la solicitud
        $data = json_decode($request->getContent(), true);
        $newPassword = $data['newPassword'] ?? null;

        // Validar que se haya enviado la nueva contraseña
        if (!$newPassword) {
            return new JsonResponse(['message' => 'Es necesaria una contraseña.', 'code' => '400']);
        };

        // Hashear la nueva contraseña y asignarla al usuario
        $encodedPassword = $passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($encodedPassword);
        $user->setFirstTime(false);

        // Guardar los cambios en la base de datos
        $this->entityManager->flush();

        // Responder con éxito
        return new JsonResponse(['message' => 'Contraseña cambiada con exito', 'code' => '200']);
    }

}
