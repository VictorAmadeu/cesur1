<?php

namespace App\Controller\Admin;

use App\Entity\Document;
use App\Entity\User;
use App\Entity\Companies;
use App\Service\DocumentValidator;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use Symfony\Bundle\SecurityBundle\Security;
use App\Controller\Admin\AuxController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use App\Utils\DateUtils;
use App\Service\FilterSelectionService;
use App\Repository\CompaniesRepository;
use App\Service\DocumentErrorLogService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

class DocumentCrudController extends AbstractCrudController
{
    private $documentValidator;
    private $requestStack;
    private $em;
    private $security;
    private $aux;
    private $filterSelectionService;
    private $companiesRepository;
    private $documentErrorLogService;
    private $params;
    private $adminUrlGenerator;

    public function __construct(
        Security $security,
        EntityManagerInterface $em,
        AuxController $aux,
        DocumentValidator $documentValidator,
        RequestStack $requestStack,
        FilterSelectionService $filterSelectionService,
        CompaniesRepository $companiesRepository,
        DocumentErrorLogService $documentErrorLogService,
        ParameterBagInterface $params,
        AdminUrlGenerator $adminUrlGenerator,
    ) {
        $this->documentValidator = $documentValidator;
        $this->requestStack = $requestStack;
        $this->em = $em;
        $this->aux = $aux;
        $this->security = $security;
        $this->filterSelectionService = $filterSelectionService;
        $this->companiesRepository = $companiesRepository;
        $this->documentErrorLogService = $documentErrorLogService;
        $this->params = $params;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public static function getEntityFqcn(): string
    {
        return Document::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPaginatorPageSize(25)
            ->setPageTitle('index', 'Documentos')
            ->setEntityLabelInSingular('Documento')
            ->setEntityLabelInPlural('Documentos')
            ->showEntityActionsInlined()
            ->overrideTemplate('crud/index', 'admin/document/custom_index.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            // Personalizamos botón "Nuevo"
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

            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->add(Crud::PAGE_EDIT, Action::INDEX)
            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE)
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->setIcon('fa fa-trash')->setLabel(false);
            });
    }

    public function createEntity(string $entityFqcn)
    {
        $document = new Document();

        $request = $this->getContext()->getRequest();
        $com = $request->query->get('com');

        if ($com && $com !== 'all') {
            $company = $this->companiesRepository->find($com);
            if ($company) {
                $document->setCompany($company);
            }
        }

        return $document;
    }

    public function configureFields(string $pageName): iterable
    {
        $user = $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();
        $com = $request->query->get('com');
        $company = $this->companiesRepository->findOneBy(['id' => $com]);

        if ($com && $com !== 'all') {
            $companyField = AssociationField::new('company', 'Empresa')
            ->setColumns(3)
            ->setFormTypeOption('query_builder', function (CompaniesRepository $er) use ($com) {
                return $er->createQueryBuilder('u')
                    ->where('u.id = :company')
                    ->setParameter('company', $com) // El id del usuario seleccionado
                    ->orderBy('u.comercialName', 'ASC');
            })
            ->setFormTypeOption('disabled', true);
        } else {
            $companyField = AssociationField::new('company', 'Empresa')
                ->setColumns(3)
                ->setFormTypeOption('query_builder', function (CompaniesRepository $er) {
                    /** @var \App\Entity\User $user */
                    $user = $this->getUser();
                    $account = $user->getAccounts();
                    return $er->createQueryBuilder('u')
                        ->where('u.accounts = :account')
                        ->setParameter('account', $account)
                        ->orderBy('u.comercialName', 'ASC');
                });
        }

        return [
            $companyField,
            IdField::new('id')->hideOnForm(),
            TextField::new('fieldUpload', 'Cargar Archivo')
                ->setFormType(FileType::class)
                ->setRequired(true)
                ->setFormTypeOptions([
                    'required' => true,
                    'multiple' => true,
                    'mapped' => false,
                    'attr' => ['accept' => '.pdf'],
                ])
                ->onlyOnForms(),
            TextField::new('name', 'Nombre del Documento')
                ->setFormTypeOption('attr', ['class' => 'document-link'])
                ->formatValue(function ($value, $entity) {
                    $companyName = $entity->getCompany()->getComercialName();
                    $documentType = $entity->getType()->getFolderName();
                    $formatName = $this->formatFolderName($companyName);
                    $baseUrl = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost();
                    $url = sprintf(
                        '%s/uploads/documentos/%s/%s/%s',
                        $baseUrl,
                        $formatName,
                        $documentType,
                        $value
                    );
                    return sprintf('<a href="%s" target="_blank">%s</a>', $url, htmlspecialchars($value));
                })
                ->onlyOnIndex(),
            AssociationField::new('type', 'Tipo de Documento')
                ->setRequired(true)
                ->setQueryBuilder(function (QueryBuilder $qb) use ($company) {
                    $qb->where('entity.company = :company')
                        ->setParameter('company', $company);
                }),
            DateTimeField::new('createdAt', 'Fecha de Creación')->onlyOnIndex(),
            DateTimeField::new('viewedAt', 'Fecha de Visualización')->onlyOnIndex(),
        ];
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $user = $this->security->getUser();
        $isSuperAdmin = $this->isGranted('ROLE_SUPER_ADMIN');
        $request = $this->requestStack->getCurrentRequest();
        /** @var \App\Entity\User $user */
        $com = $request->query->get('com', $user->getCompany()->getId());
        $off = $request->query->get('off', 'all');
        // Obtener el primer y último día del mes actual
        $startDate = new \DateTime('first day of this month');  // Primer día del mes
        $endDate = new \DateTime('last day of this month');    // Último día del mes

        $startDateFormatted = $startDate->format('Y-m-d');
        $endDateFormatted = $endDate->format('Y-m-d');

        $start = $request->query->get('start', $startDateFormatted);
        $end = $request->query->get('end', $endDateFormatted);

        $params = [];

        $account = $user->getAccounts();
        $company = $this->companiesRepository->findOneBy(['id' => $com]);

        $responseParameters->set('months', DateUtils::getMonths());
        $responseParameters->set('years', DateUtils::getYears());
        $responseParameters->set('startDate', $start);
        $responseParameters->set('endDate', $end);

        if ($isSuperAdmin) {
            $responseParameters->set('companies', $this->companiesRepository->findAll());
        } else {
            $responseParameters->set('companies', $this->companiesRepository->findBy(['accounts' => $account, 'allowDocument' => true]));
        }

        $responseParameters->set('selectedCompany', $company);
        return $responseParameters;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $user = $this->getUser();
        $request = $this->requestStack->getCurrentRequest();

        $isSuperAdmin = $this->isGranted('ROLE_SUPERADMIN');
        /** @var \App\Entity\User $user */
        $com = $request->query->get('com', $user->getCompany()->getId());
        // Obtener el primer y último día del mes actual
        $startDate = new \DateTime('first day of this month');  // Primer día del mes
        $endDate = new \DateTime('last day of this month');    // Último día del mes

        $startDateFormatted = $startDate->format('Y-m-d');
        $endDateFormatted = $endDate->format('Y-m-d');

        $start = $request->query->get('start', $startDateFormatted);
        $end = $request->query->get('end', $endDateFormatted);

        // Convertir a DateTime y restar un mes
        $startDateObj = new \DateTime($start);
        $endDateObj = new \DateTime($end);

        $startDateObj->modify('+1 month');
        $endDateObj->modify('+1 month');

        // Convertir a string para el query builder
        $start = $startDateObj->format('Y-m-d');
        $end = $endDateObj->format('Y-m-d');

        $company = $this->companiesRepository->findOneBy(['id' => $com]);

        $qb->andWhere('entity.company = :selectedCompany')
            ->setParameter('selectedCompany', $company)
            ->andWhere('entity.createdAt BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $start)
            ->setParameter('endDate', $end);
        return $qb;
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $files = $request->files->get('Document')['fieldUpload'];

        $user = $this->security->getUser();
        $selectedCompany = $entityInstance->getCompany();
        $selectedCompanyComercialName = $selectedCompany->getComercialName();
        $formatName = $this->formatFolderName($selectedCompanyComercialName);

        $errorLog = []; // Acumulamos los errores aquí

        // Comenzamos a procesar los archivos
        if ($files) {
            $documentType = $entityInstance->getType();
            if ($documentType) {
                $folderName = strtolower($documentType->getFolderName());
                $uploadDirectory = $this->getParameter('uploads_directory') . '/documentos' . '/' . $formatName . '/' . $folderName;

                foreach ($files as $file) {
                    $newDocument = new Document();
                    $newDocument->setType($documentType);
                    [$isValid, $errorMessage, $user] = $this->documentValidator->validateDocumentAndFindUser($file, $uploadDirectory);

                    if (!$isValid) {
                        // Si el archivo tiene errores, lo acumulamos en el log
                        $errorLog[] = [
                            'filename' => $file->getClientOriginalName(),
                            'error' => $errorMessage
                        ];
                        continue; // Continuamos con el siguiente archivo
                    }

                    // Si el archivo es válido, preparamos el documento para persistirlo
                    $newDocument->setUser($user);
                    if ($selectedCompany) {
                        $newDocument->setCompany($selectedCompany);
                    }

                    $extension = $file->guessExtension();
                    $originalFileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    $newFileName = $originalFileName . '.' . $extension;
                    $file->move($uploadDirectory, $newFileName);
                    $fileUrl = '/uploads/documentos/' . $formatName . '/' . $folderName . '/' . $newFileName;
                    $newDocument->setUrl($fileUrl);
                    $newDocument->setName($newFileName);

                    // Persistimos los documentos válidos inmediatamente
                    $entityManager->persist($newDocument);
                }

                // Si existen errores, se guarda el archivo de log
                if (!empty($errorLog)) {
                    $filePath = $this->documentErrorLogService->generateErrorFile($errorLog);

                    // Obtener el nombre del archivo generado
                    $fileName = basename($filePath);

                    // Generar la URL de descarga
                    $fileDownloadUrl = $this->generateUrl('download_error_file', ['filename' => $fileName]);

                    // Mostrar el enlace para descargar el archivo
                    $this->addFlash('success', 'El archivo de error se ha guardado correctamente. ' .
                        'Puedes descargarlo <a href="' . $fileDownloadUrl . '" target="_blank">aquí</a>.');
                }

                // Finalmente, se guardan todos los cambios en la base de datos
                $entityManager->flush();
            }
        }
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Document) {
            return;
        }

        $fileUrl = $entityInstance->getUrl();

        $uploadDirectory = $this->params->get('kernel.project_dir');
        $filePath = $uploadDirectory . '/public' . $fileUrl;


        if (file_exists($filePath)) {
            if (unlink($filePath)) {
                // Archivo eliminado exitosamente
                $this->addFlash('success', 'El archivo ha sido eliminado correctamente.');
            } else {
                // Error al eliminar el archivo
                $this->addFlash('error', 'No se pudo eliminar el archivo.');
            }
        } else {
            // El archivo no existe
            $this->addFlash('warning', 'El archivo no existe y no se pudo eliminar.');
        }

        // Elimina la entidad Document de la base de datos
        $entityManager->remove($entityInstance);
        $entityManager->flush();
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

}
