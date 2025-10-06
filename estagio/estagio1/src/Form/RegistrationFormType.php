<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email',null,['label' => 'EmailLoginForm'])
            ->add('name',null,['label' => 'NameLoginForm'])
            ->add('NIF',null,['label' => 'NIFLoginForm','mapped'=>false])
            ->add('lastname1',null,['label' => 'FirstNameLoginForm'])
            ->add('lastname2',null,['label' => 'LastNameLoginForm'])
            ->add('phone',null,['label' => 'PhoneLoginForm'])
            /*->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'label' => 'Contraseña',
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new NotBlank(['message' => 'Por favor, introduce una contraseña.']),
                    new Length(['min' => 6,'minMessage' => 'Tu contraseña debe contener al menos {{ limit }} caracteres.',
                                'max' => 15,'maxMessage' => 'Tu contraseña no puede superar los {{ limit }} caracteres.']),
                ],
            ])
            ->add('plainPassword2', PasswordType::class, [
                'mapped' => false,
                'label' => 'Repite la contraseña',
            ])*/
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
