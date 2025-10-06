<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class RegistrationController extends AbstractController
{
    private $emailVerifier;
    private $security;
    private $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher, EmailVerifier $emailVerifier, Security $security)
    {
        $this->emailVerifier = $emailVerifier;
        $this->security = $security;
        $this->passwordHasher = $passwordHasher;
    }

    #[Route('/registro', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        $user = new User(); 
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $nif = $form->get('NIF')->getData();
            // encode the plain password
            $user->setPassword($userPasswordHasher->hashPassword($user,'p@assw0rd_')); //register is locked, only submit to access or set access in admin panel
            $user->setisActive(false);
            $entityManager->persist($user);
            $entityManager->flush();

            // Asignar el rol ROLE_USER al usuario
            $user->setRole("ROLE_USER");

            // generate a signed url and email it to the user
            $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user,
                (new TemplatedEmail())
                    ->from(new Address($this->getParameter('app.web.email_from'), $this->getParameter('app.web.name')))
                    ->to($user->getEmail())
                    ->subject('Validación de usuario')
                    ->htmlTemplate('registration/confirmation_email.html.twig')
            );

            // send email to administrator to check the user and will activated
            $email = (new TemplatedEmail())
            ->from(new Address($this->getParameter('app.web.email_from'), $this->getParameter('app.web.name')))
            ->to($this->getParameter('app.web.email_admin'))
            ->subject('Nuevo acceso solicitado: ' . $user->getName() . ' ' . $user->getLastName1(). ' ' . $user->getLastName2())
            ->htmlTemplate('registration/check_user_registration.html.twig')
            ->context(['user' => $user]);

            $mailer->send($email);
            
            // do anything else you need here, like send an email
            $this->addFlash('register_submit', 'Tu solicitud de acceso se ha enviado. Por favor, revisa tu bandeja de entrada y confirma tu email. Activaremos tu cuenta antes posible.', 'Solicitud de acceso');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/verificar/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, TranslatorInterface $translator, UserRepository $userRepository): Response
    {
        $id = $request->get('id');

        if (null === $id) {
            return $this->redirectToRoute('app_register');
        }

        $user = $userRepository->find($id);

        if (null === $user) {
            return $this->redirectToRoute('app_register');
        }

        // validate email confirmation link, sets User::isVerified=true and persists
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));

            return $this->redirectToRoute('app_register');
        }

        // @TODO Change the redirect on success and handle or remove the flash message in your templates
        $this->addFlash('register_submit', 'Tu email ha sido verificado. Activaremos tu cuenta lo antes posible.');

        return $this->redirectToRoute('app_login');
    }

    #[Route('/admin/first_time', name: 'admin_first_time')]
    public function changePasswordFirstTime(
        Request $request, 
        Security $security, 
        EntityManagerInterface $em, 
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        // Obtener el usuario autenticado
        $user = $security->getUser();
    
        // Crear el formulario con dos campos de contraseña
        $form = $this->createFormBuilder()
            ->add('password', PasswordType::class, ['label' => 'Nueva Contraseña'])
            ->add('confirm_password', PasswordType::class, ['label' => 'Confirmar Contraseña'])
            ->add('save', SubmitType::class, ['label' => 'Actualizar información'])
            ->getForm();
    
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $newPassword = $data['password'];
            $confirmPassword = $data['confirm_password'];
    
            // Verificar que ambas contraseñas coincidan
            if ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'Las contraseñas no coinciden.');
            } else {
                // Hashear la nueva contraseña antes de guardarla
                $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
                $user->setPassword($hashedPassword);
                $user->setFirstTime(false);
    
                // Guardar los cambios en la base de datos
                $em->persist($user);
                $em->flush();
    
                $this->addFlash('success', 'Contraseña actualizada correctamente.');
    
                // Redirigir al área de administración
                return $this->redirectToRoute('admin');
            }
        }
    
        return $this->render('admin/register.html.twig', [
            'form' => $form->createView(),
        ]);
    }

}
