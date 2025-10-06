<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\Accounts;
use App\Entity\Companies;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\Admin\AuxController;

use App\Form\CompanySelectGlobalType;
use App\Form\UserSelectGlobalType;

use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;

class ProfileUserController extends AbstractCrudController
{
    private $adminUrlGenerator, $em, $aux, $entityManager;

    public function __construct(AdminUrlGenerator $adminUrlGenerator, EntityManagerInterface $em, AuxController $aux, EntityManagerInterface $entityManager)
    {
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->em = $em;
        $this->aux = $aux;
        $this->entityManager = $entityManager;
    }
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
        ->setPaginatorPageSize(25)
            ->setPageTitle('index', 'Mi perfil')
            ->setEntityLabelInSingular('Mi perfil')
            ->setEntityLabelInPlural('Mi perfil')
            ->showEntityActionsInlined()
            ->setPageTitle('edit', 'Mi perfil');
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        // Obtener el usuario actual
        $currentUser = $this->getUser();
    
        // Crear el formulario solo con el usuario actual
        $userForm = $this->createForm(UserSelectGlobalType::class, null, [
            'users' => [$currentUser], // Pasar el usuario actual como único elemento en un array
            // Omitir el parámetro 'selectedValue' si no es necesario
        ]);
    
        // Asignar el formulario al parámetro de respuesta
        $responseParameters->set('formUser', $userForm->createView());
    
        return $responseParameters;
    }
    

    public function configureActions(Actions $actions): Actions
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            $actions->add(Crud::PAGE_EDIT, Action::new('Empresas', 'Mis empresas', 'fa fa-custom')
                ->linkToUrl($this->adminUrlGenerator->setController(CompaniesCrudController::class)->generateUrl())
                ->addCssClass('btn btn-secondary')
                ->setIcon('fa fa-building')
            );
        }

        return $actions
            ->remove(Crud::PAGE_DETAIL, Action::INDEX)
            ->remove(Crud::PAGE_DETAIL, Action::DELETE)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            EmailField::new('email','E-mail')->setColumns(3),
            TelephoneField::new('phone','Teléfono')->setColumns(3),
            FormField::addRow(),
            TextField::new('name','Nombre')->setColumns(2),
            TextField::new('lastname1','1 Apellido')->setColumns(3),
            TextField::new('lastname2','2 Apellido')->setColumns(3),
            FormField::addPanel('Datos de la cuenta')->setPermission('ROLE_ADMIN'),
            AssociationField::new('accounts', 'Cuenta')->renderAsEmbeddedForm()->setPermission('ROLE_ADMIN')->setRequired(false)->setCrudController(AccountsCrudController::class),
        ];
    }
}
