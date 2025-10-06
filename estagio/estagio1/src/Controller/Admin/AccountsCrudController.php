<?php

namespace App\Controller\Admin;

use App\Entity\Accounts;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;

use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use App\Controller\Admin\AuxController;

class AccountsCrudController extends AbstractCrudController
{
    private $entityManager, $security, $adminUrlGenerator, $aux;

    public function __construct(EntityManagerInterface $entityManager, Security $security, AdminUrlGenerator $adminUrlGenerator, AuxController $aux)
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->aux = $aux;
    }

    public static function getEntityFqcn(): string
    {
        return Accounts::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPaginatorPageSize(25)
            ->setPageTitle('index', 'Cuenta')
            ->setEntityLabelInSingular('Cuenta')
            ->setEntityLabelInPlural('Cuentas');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
        ->update(Crud::PAGE_INDEX, Action::EDIT,function (Action $action) {
            return $action->setIcon('fa fa-pencil')->setLabel(false);
        })
        ->update(Crud::PAGE_INDEX, Action::DELETE,function (Action $action) {
            return $action->setIcon('fa fa-trash')->setLabel(false);
        })
        ->add(Crud::PAGE_EDIT, Action::INDEX)
        ->add(Crud::PAGE_DETAIL, Action::INDEX)
        ->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
        ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id'),
            TextField::new('name','Nombre'),
            BooleanField::new('allowManualEntry', 'Fichar manualmente')->setColumns(3),
            BooleanField::new('allowProjects', 'Registro por proyectos')->setColumns(3),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        parent::persistEntity($entityManager, $entityInstance);
    }
}
