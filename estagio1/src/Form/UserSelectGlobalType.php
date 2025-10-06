<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\RedirectResponse;

class UserSelectGlobalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $users = $options['users']; if ($options['selectedValue'] instanceof RedirectResponse) {$sv = 0;}else{$sv = intval($options['selectedValue']);}
        $data = null;
        foreach($users as $u){ if($u->getId()===$sv) $data=$u; }
        $builder
            ->add('user', ChoiceType::class, [
                'choices' => $users,
                'label' => false,
                'data' => $data,
                'choice_label' => 'FullName',
                'choice_value' => 'id',
                'attr' => [ 
                    'class' => 'form-select', 
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'     => User::class,
            'users'      => [],
            'selectedValue'  => '1'
        ]);
    }
}
