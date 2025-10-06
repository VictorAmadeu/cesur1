<?php

namespace App\Form;

use App\Entity\User;
use App\Entity\Company;
use App\Entity\Office;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class UserFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            // Campo de la empresa (company)
            ->add('company', EntityType::class, [
                'class' => Company::class,
                'choice_label' => 'name', // Aquí 'name' es el nombre de la empresa
                'placeholder' => 'Seleccionar Compañía',
                'required' => true,
                'mapped' => false, // No está directamente mapeado a la entidad User
                'query_builder' => function ($er) use ($options) {
                    return $er->createQueryBuilder('c')
                        ->where('c.account = :account') // Filtra por cuenta asociada al usuario
                        ->setParameter('account', $options['account']);
                }
            ])
            // Campo de la oficina (office)
            ->add('office', EntityType::class, [
                'class' => Office::class,
                'choice_label' => 'name', // Aquí 'name' es el nombre de la oficina
                'placeholder' => 'Seleccionar Oficina',
                'required' => true,
                'mapped' => false, // No está directamente mapeado a la entidad User
                'query_builder' => function ($er) {
                    // Se dejará vacío hasta que se seleccione una empresa
                    return $er->createQueryBuilder('o')
                        ->where('o.company IS NULL'); // Inicialmente, no se muestra ninguna oficina
                }
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => User::class, // Esto mapea el formulario a la entidad User
            'account' => null, // Asegúrate de pasar la cuenta del usuario desde el controlador
        ]);
    }
}
