<?php

namespace App\Form;

use EasyCorp\Bundle\EasyAdminBundle\Form\Type\CrudFormType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class CrudFormTypeExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [CrudFormType::class];
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        parent::finishView($view, $form, $options);

        $view->vars['ea_crud_form'] = array_merge($view->vars['ea_crud_form'], [
            'form_tab' => $form->getConfig()->getAttribute('ea_form_tab'),
            'form_panel' => $form->getConfig()->getAttribute('ea_form_panel'),
        ]);
    }
}