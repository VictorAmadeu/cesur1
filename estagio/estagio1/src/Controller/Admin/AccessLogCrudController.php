<?php

namespace App\Controller\Admin;

use App\Entity\AccessLog;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory;
use EasyCorp\Bundle\EasyAdminBundle\Orm\EntityRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use App\Form\CompanySelectGlobalType;
use App\Controller\Admin\AuxController;

use App\Entity\Companies;

class AccessLogCrudController extends AbstractCrudController
{
    private $adminContextProvider, $em, $aux, $adminUrlGenerator;

    public function __construct(AdminContextProvider $adminContextProvider, EntityManagerInterface $em, AuxController $aux, AdminUrlGenerator $adminUrlGenerator)
    {
        $this->adminContextProvider = $adminContextProvider;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->em = $em;
        $this->aux = $aux;
    }

    public static function getEntityFqcn(): string
    {
        return AccessLog::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPaginatorPageSize(25)
            ->setPageTitle('index', 'Registro de acceso')
            ->setEntityLabelInSingular('Registro de acceso')
            ->setEntityLabelInPlural('Registro de')
            ->showEntityActionsInlined();
    }

        /*Add to pass form select company */
        public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
        {
            $companies = $this->em->getRepository(Companies::class)->findAll();
            $form = $this->createForm(CompanySelectGlobalType::class,null,
            ['companies'=>$companies,'selectedValue' => $this->aux->getCompanySelectedValue()]);
            $responseParameters->set('form', $form->createView());
            return $responseParameters;
        } 

    public function configureActions(Actions $actions): Actions
    {
        return $actions
        ->add(Crud::PAGE_INDEX, Action::new('Volver', 'Volver', 'fa fa-arrow-left')
                                            ->linkToUrl($this->adminUrlGenerator->setController(ProfileUserController::class)->setAction(Action::DETAIL)->setEntityId($this->getUser()->getId())->generateUrl())
                                            ->createAsGlobalAction()
                                            )
        ->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
        ->remove(Crud::PAGE_INDEX, Action::EDIT)
        ->remove(Crud::PAGE_INDEX, Action::DELETE)
        ->remove(Crud::PAGE_INDEX, Action::NEW)
        ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE);
    }    

    public function configureFields(string $pageName): iterable
    {
        return [
            //IdField::new('id')->onlyOnIndex(),
            TextField::new('user','Usuario')->setColumns(2),
            DateTimeField::new('date','Fecha')->setColumns(2),
            TextField::new('type','Tipo de acceso')->setColumns(2),
            AssociationField::new('company', 'Empresa')->setColumns(2),
        ];
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
        ->andWhere('entity.company = :companies')
        ->setParameter('companies', $this->em->getRepository(Companies::class)->find($this->aux->getCompanySelectedValue()))
        ->orderBy('entity.date','desc');
    }
}
