<?php

namespace App\Controller\Admin;

use App\Entity\DocumentType;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;

use App\Form\CompanySelectGlobalType;
use Symfony\Bundle\SecurityBundle\Security;
use App\Controller\Admin\AuxController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use Doctrine\ORM\QueryBuilder;
use App\Entity\Companies;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;

use App\Service\FilterSelectionService;
use App\Repository\CompaniesRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

class DocumentTypeCrudController extends AbstractCrudController
{
    private $em;
    private $security;
    private $aux;
    private $filterSelectionService;
    private $companiesRepository;
    private $adminUrlGenerator;
    private $requestStack;

    public function __construct(
        Security $security, 
        EntityManagerInterface $em, 
        AuxController $aux,  
        FilterSelectionService $filterSelectionService,
        CompaniesRepository $companiesRepository,
        AdminUrlGenerator $adminUrlGenerator,
        RequestStack $requestStack,
    )
    {
        $this->em = $em;
        $this->aux = $aux;
        $this->security = $security;
        $this->filterSelectionService = $filterSelectionService;
        $this->companiesRepository = $companiesRepository;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->requestStack = $requestStack;
    }

    public static function getEntityFqcn(): string
    {
        return DocumentType::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPaginatorPageSize(25)
            ->setPageTitle('index', 'Tipos de documento')
            ->setEntityLabelInSingular('Tipo de documento')
            ->setEntityLabelInPlural('Tipos de documento')
            ->showEntityActionsInlined()
            ->overrideTemplate('crud/index', 'admin/document_type/custom_index.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
         return $actions
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                $request = $this->requestStack->getCurrentRequest();
                $com = $request->query->get('com');
                $off = $request->query->get('off');
                $us = $request->query->get('us');
                // Obtener el primer y último día del mes actual
                $startDate = new \DateTime('first day of this month');  // Primer día del mes
                $endDate = new \DateTime('last day of this month');    // Último día del mes
            
                $startDateFormatted = $startDate->format('Y-m-d');
                $endDateFormatted = $endDate->format('Y-m-d');

                $start = $request->query->get('start', $startDateFormatted);
                $end = $request->query->get('end', $endDateFormatted);
            
                $url = $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Action::NEW)
                    ->set('com', $com)
                    ->set('off', $off)
                    ->set('us', $us)
                    ->set('start', $start)
                    ->set('end', $end)
                    ->generateUrl();
            
                return $action
                    ->setLabel('Cargar documentos') // <- Cambia aquí el texto
                    ->linkToUrl($url);
            })
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::EDIT, function (Action $action) {
                return $action->linkToCrudAction(Action::EDIT);
            })
            ->add(Crud::PAGE_INDEX, Action::new('customEdit', '', 'fa fa-pencil')
            ->linkToUrl(function ($entity) {
                $id = $entity->getId();
                $request = $this->requestStack->getCurrentRequest();
                $com = $request->query->get('com');
                $off = $request->query->get('off');
                $us = $request->query->get('us');
                // Obtener el primer y último día del mes actual
                $startDate = new \DateTime('first day of this month');  // Primer día del mes
                $endDate = new \DateTime('last day of this month');    // Último día del mes
            
                $startDateFormatted = $startDate->format('Y-m-d');
                $endDateFormatted = $endDate->format('Y-m-d');

                $start = $request->query->get('start', $startDateFormatted);
                $end = $request->query->get('end', $endDateFormatted);
        
                return $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Action::EDIT)
                    ->setEntityId($id)
                    ->set('com', $com)
                    ->set('off', $off)
                    ->set('us', $us)
                    ->set('start', $start)
                    ->set('end', $end)
                    ->generateUrl();
            }))
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->setIcon('fa fa-trash')->setLabel(false);
            })
            
            ->add(Crud::PAGE_EDIT, Action::INDEX)
            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE)
            ->remove(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function createEntity(string $entityFqcn)
    {
        $documentType = new DocumentType();

        $request = $this->getContext()->getRequest();
        $com = $request->query->get('com');

        if ($com && $com !== 'all') {
            $company = $this->companiesRepository->find($com);
            if ($company) {
                $documentType->setCompany($company);
            }
        }

        return $documentType;
    }

    public function configureFields(string $pageName): iterable
    {
        $adminUrlGenerator = $this->adminUrlGenerator;
        $request = $this->requestStack->getCurrentRequest();
        $com = $request->query->get('com');
        $company = $this->companiesRepository->findOneBy(['id' => $com]);

        $nameField = TextField::new('name', 'Nombre')->setColumns(2)
        ->setHelp('Máximo 100 caracteres.')
        ->formatValue(function ($value, $entity) use ($adminUrlGenerator) {
            $url = $adminUrlGenerator
                ->setController(self::class)
                ->setAction('edit')
                ->setEntityId($entity->getId())
                ->generateUrl();

            return sprintf('<a href="%s">%s</a>', $url, $value);
        });

        if($com && $com !== 'all'){
            $companyField = AssociationField::new('company', 'Empresa')
            ->setColumns(3)
            ->setFormTypeOption('query_builder', function (CompaniesRepository $er) use ($com) {
                return $er->createQueryBuilder('u')
                    ->where('u.id = :company')
                    ->setParameter('company', $com) // El id del usuario seleccionado
                    ->orderBy('u.comercialName', 'ASC'); // o 'u.name', según el campo que uses
            })
            ->setFormTypeOption('disabled', true);
        }else{
            $companyField = AssociationField::new('company', 'Empresa')
                ->setColumns(3)
                ->setFormTypeOption('query_builder', function (CompaniesRepository $er) {
                    $account = $this->getUser()->getAccounts();
                    return $er->createQueryBuilder('u')
                        ->where('u.accounts = :account')
                        ->setParameter('account', $account)
                        ->orderBy('u.comercialName', 'ASC'); // o 'u.name', según el campo que uses
                });
        }

        return [
            $companyField,
            $nameField
        ];
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $user = $this->security->getUser();
        $isSuperAdmin = $this->isGranted('ROLE_SUPER_ADMIN');
        $request = $this->requestStack->getCurrentRequest();
        $com = $request->query->get('com', $user->getCompany()->getId());
        $off = $request->query->get('off', 'all');

        $params = [];

        $account = $user->getAccounts();
        $company = $this->companiesRepository->findOneBy(['id' => $com]);

        // if($isSuperAdmin){
        //     $responseParameters->set('companies', $this->companiesRepository->findAll());
        // }else{
        // }
        $responseParameters->set('companies', $this->companiesRepository->findBy(['accounts' => $account, 'allowDocument' => true]));

        $responseParameters->set('selectedCompany', $company);
        return $responseParameters;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $user = $this->getUser();
        $request = $this->requestStack->getCurrentRequest();

        $isSuperAdmin = $this->isGranted('ROLE_SUPERADMIN');
        $com = $request->query->get('com', $user->getCompany()->getId());

        $company = $this->companiesRepository->findOneBy(['id' => $com]);

        $qb->andWhere('entity.company = :selectedCompany')
            ->setParameter('selectedCompany', $company);
        return $qb;
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof DocumentType) {
            return;
        }
        $user = $this->security->getUser();
        $selectedCompany = $entityInstance->getCompany();
        $companyName = $selectedCompany->getComercialName();
        if ($selectedCompany) {
            $entityInstance->setCompany($selectedCompany);
        }

        $formattedName = $this->formatName($entityInstance->getName());
        $formattedCompanyName = $this->formatFolderName($companyName);
        $entityInstance->setName($formattedName);
        $folderName = $this->formatFolderName($entityInstance->getName());
        $entityInstance->setFolderName($folderName);
        $this->createFolder($formattedCompanyName, $folderName);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof DocumentType) {
            return;
        }
        $companyName = $entityInstance->getCompany()->getComercialName();
        $oldFolderName = $entityInstance->getFolderName();
        $newFolderName = $this->formatFolderName($entityInstance->getName());
        if ($oldFolderName !== $newFolderName) {
            $this->renameFolder($companyName, $oldFolderName, $newFolderName);
            $entityInstance->setFolderName($newFolderName);
        }
        $formattedName = $this->formatName($entityInstance->getName());
        $entityInstance->setName($formattedName);
        parent::updateEntity($entityManager, $entityInstance);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof DocumentType) {
            return;
        }
    
        // Aquí puedes agregar cualquier lógica que necesites antes de eliminar
        $folderName = $entityInstance->getFolderName();
        $companyName = $entityInstance->getCompany()->getComercialName();
    
        // Lógica para eliminar la carpeta asociada si es necesario
        $this->removeFolder($companyName, $folderName);
    
        // Eliminar la entidad
        parent::deleteEntity($entityManager, $entityInstance);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AfterEntityPersistedEvent::class => ['onAfterEntityPersisted'],
        ];
    }

    public function onAfterEntityPersisted(AfterEntityPersistedEvent $event): void
    {
        $entity = $event->getEntityInstance();
        if (!$entity instanceof User) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        $com = $entity->getCompany()->getId();
        $off = $request->query->get('off') ?? 'all';
        $us =  $request->query->get('us') ?? 'all';
       // Obtener el primer y último día del mes actual
        $startDate = new \DateTime('first day of this month');  // Primer día del mes
        $endDate = new \DateTime('last day of this month');    // Último día del mes
            
        $startDateFormatted = $startDate->format('Y-m-d');
        $endDateFormatted = $endDate->format('Y-m-d');

        $start = $request->query->get('start', $startDateFormatted);
        $end = $request->query->get('end', $endDateFormatted);

        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction('index')
            ->set('com', $com)
            ->set('us', $us)
            ->set('off', $off)
            ->set('start', $start)
            ->set('end', $end)
            ->generateUrl();

        $response = new RedirectResponse($url);
        $this->getContext()->getRequest()->getSession()->save(); 
        $response->send();
    }
    
    private function removeFolder(string $companyName, string $folderName): void
    {
        $filesystem = new Filesystem();
        $folderPath = sprintf('uploads/documentos/%s/%s', $companyName, $folderName);
        
        if ($filesystem->exists($folderPath)) {
            $filesystem->remove($folderPath);
        }
    }
    
    private function renameFolder(string $companyName, string $oldFolderName, string $newFolderName): void
    {
        $filesystem = new Filesystem();
        $oldFolderPath = sprintf('uploads/documentos/%d/%s', $companyName, $oldFolderName);
        $newFolderPath = sprintf('uploads/documentos/%d/%s', $companyName, $newFolderName);
    
        if ($filesystem->exists($oldFolderPath)) {
            $filesystem->rename($oldFolderPath, $newFolderPath);
        }
    }
    
    private function formatName(string $name): string
    {
        $name = trim($name);
        $accents = ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'];
        $noAccents = ['a', 'e', 'i', 'o', 'u', 'n', 'u', 'a', 'e', 'i', 'o', 'u', 'n'];
        $name = str_replace($accents, $noAccents, $name);
        $name = strtoupper($name);
        $name = preg_replace('/[^A-Z0-9 ]/', '', $name);

        return $name;
    }

    private function formatFolderName(string $name): string
    {
        $name = trim($name);
        $accents = ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'];
        $noAccents = ['a', 'e', 'i', 'o', 'u', 'n', 'u', 'a', 'e', 'i', 'o', 'u', 'n'];
        $name = str_replace($accents, $noAccents, $name);
        $name = strtolower($name);
        $name = str_replace(' ', '_', $name);
        $name = preg_replace('/[^a-z0-9_]/', '', $name);
        $name = preg_replace('/_+/', '_', $name);
        $name = trim($name, '_');
        return $name;
    }

    private function createFolder(string $companyName, string $folderName): void
    {
        $filesystem = new Filesystem();
        $folderPath = sprintf('uploads/documentos/%s/%s', $companyName, $folderName);
        if (!$filesystem->exists($folderPath)) {
            $filesystem->mkdir($folderPath);
        }
    }


    
}
