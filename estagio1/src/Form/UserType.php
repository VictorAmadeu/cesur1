<?php

namespace App\Form;

use App\Entity\Accounts;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email')
            ->add('roles')
            ->add('password')
            ->add('name')
            ->add('lastname1')
            ->add('lastname2')
            ->add('phone')
            ->add('isVerified')
            ->add('isActive')
            ->add('createdAt')
            ->add('modifiedAt')
            ->add('companies')
            ->add('accounts', EntityType::class, [
                'class' => Accounts::class,
'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
