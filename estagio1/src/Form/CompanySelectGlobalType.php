<?php

namespace App\Form;

use App\Entity\Companies;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\RedirectResponse;

class CompanySelectGlobalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        
        $companies = $options['companies']; if ($options['selectedValue'] instanceof RedirectResponse) {$sv = 0;}else{$sv = intval($options['selectedValue']);}
        $data = null;
        foreach($companies as $c){ if($c->getId()===$sv) $data=$c; }
        $builder
            ->add('id', ChoiceType::class, [
                'choices' => $companies,
                'label' => false,
                'data' => $data,
                'choice_label' => 'comercialName',
                'choice_value' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'     => Companies::class,
            'companies'      => [],
            'selectedValue'  => '1'
        ]);
    }
}
