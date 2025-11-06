<?php

/**
 * DocumentCrudController.php — Versión final corregida y comentada (ES-ES)
 *
 * Cambio clave:
 *  - Se elimina el desplazamiento injustificado de fechas (+1 mes) en createIndexQueryBuilder(),
 *    para que el listado respete EXACTAMENTE el rango [start, end] recibido por la URL.
 *
 * Notas de seguridad (producción):
 *  - Mantiene validaciones existentes (DocumentValidator::validateDocumentAndFindUser).
 *  - Mantiene creación del fichero de log de errores y su enlace de descarga.
 *  - No se alteran rutas, nombres de campos, ni contratos usados por EasyAdmin.
 *  - El resto de métodos se dejan funcionalmente idénticos, con comentarios aclaratorios.
 */

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

    /**
     * Configuración general del CRUD (paginación, títulos, plantilla índice).
     */
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

    /**
     * Configuración de acciones (personalización botón NUEVO -> "Cargar documentos", etc.).
     */
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            // Personalizamos botón "Nuevo" para que preserve filtros y texto.
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                $request = $this->requestStack->getCurrentRequest();
                $com = $request->query->get('com');
                $off = $request->query->get('off');
                $us  = $request->query->get('us');

                // Por defecto, rango del mes actual si no viene en la URL.
                $startDate = new \DateTime('first day of this month');
                $endDate   = new \DateTime('last day of this month');

                $start = $request->query->get('start', $startDate->format('Y-m-d'));
                $end   = $request->query->get('end',   $endDate->format('Y-m-d'));

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
                    ->setLabel('Cargar documentos')
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

    /**
     * Preselecciona la empresa en función del parámetro ?com= si procede.
     */
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

    /**
     * Definición de campos para formularios y listado.
     */
    public function configureFields(string $pageName): iterable
    {
        $user    = $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();
        $com     = $request->query->get('com');
        $company = $this->companiesRepository->findOneBy(['id' => $com]);

        // Campo Empresa: si se ha filtrado por ?com=, bloquear la selección a esa empresa.
        if ($com && $com !== 'all') {
            $companyField = AssociationField::new('company', 'Empresa')
                ->setColumns(3)
                ->setFormTypeOption('query_builder', function (CompaniesRepository $er) use ($com) {
                    return $er->createQueryBuilder('u')
                        ->where('u.id = :company')
                        ->setParameter('company', $com)
                        ->orderBy('u.comercialName', 'ASC');
                })
                ->setFormTypeOption('disabled', true);
        } else {
            // Si no hay ?com=, filtrar empresas por la cuenta del usuario autenticado.
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

            // Campo de subida múltiple de PDFs (no mapeado a la entidad).
            TextField::new('fieldUpload', 'Cargar Archivo')
                ->setFormType(FileType::class)
                ->setRequired(true)
                ->setFormTypeOptions([
                    'required' => true,
                    'multiple' => true,
                    'mapped'   => false,
                    'attr'     => ['accept' => '.pdf'],
                ])
                ->onlyOnForms(),

            // En el índice, el nombre del documento se muestra como enlace directo al fichero público.
            TextField::new('name', 'Nombre del Documento')
                ->setFormTypeOption('attr', ['class' => 'document-link'])
                ->formatValue(function ($value, $entity) {
                    $companyName  = $entity->getCompany()->getComercialName();
                    $documentType = $entity->getType()->getFolderName();
                    $formatName   = $this->formatFolderName($companyName);
                    $baseUrl      = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost();

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

            // El selector de tipo de documento se filtra por la empresa actual.
            AssociationField::new('type', 'Tipo de Documento')
                ->setRequired(true)
                ->setQueryBuilder(function (QueryBuilder $qb) use ($company) {
                    $qb->where('entity.company = :company')
                       ->setParameter('company', $company);
                }),

            DateTimeField::new('createdAt', 'Fecha de Creación')->onlyOnIndex(),
            DateTimeField::new('viewedAt',  'Fecha de Visualización')->onlyOnIndex(),
        ];
    }

    /**
     * Parámetros extra que se envían a la plantilla de índice (filtros, combos, etc.).
     */
    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $user = $this->security->getUser();
        $isSuperAdmin = $this->isGranted('ROLE_SUPER_ADMIN');

        $request = $this->requestStack->getCurrentRequest();

        /** @var \App\Entity\User $user */
        $com = $request->query->get('com', $user->getCompany()->getId());
        $off = $request->query->get('off', 'all');

        // Por defecto, mes actual.
        $startDate = new \DateTime('first day of this month');
        $endDate   = new \DateTime('last day of this month');

        $start = $request->query->get('start', $startDate->format('Y-m-d'));
        $end   = $request->query->get('end',   $endDate->format('Y-m-d'));

        $account = $user->getAccounts();
        $company = $this->companiesRepository->findOneBy(['id' => $com]);

        // Poblamos datos auxiliares para filtros de UI.
        $responseParameters->set('months',    DateUtils::getMonths());
        $responseParameters->set('years',     DateUtils::getYears());
        $responseParameters->set('startDate', $start);
        $responseParameters->set('endDate',   $end);

        if ($isSuperAdmin) {
            $responseParameters->set('companies', $this->companiesRepository->findAll());
        } else {
            $responseParameters->set('companies', $this->companiesRepository->findBy([
                'accounts'      => $account,
                'allowDocument' => true
            ]));
        }

        $responseParameters->set('selectedCompany', $company);
        return $responseParameters;
    }

    /**
     * Construye el QueryBuilder del índice respetando el rango de fechas recibido por GET.
     * (FIX aplicado: se elimina el desplazamiento +1 mes)
     */
    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters
    ): QueryBuilder {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        $user    = $this->getUser();
        $request = $this->requestStack->getCurrentRequest();

        // Nota: esta variable no se usa después, pero mantenemos la línea para no alterar lógicas futuras.
        $isSuperAdmin = $this->isGranted('ROLE_SUPERADMIN');

        /** @var \App\Entity\User $user */
        $com = $request->query->get('com', $user->getCompany()->getId());

        // Por defecto, mes actual si no viene especificado.
        $startDate = new \DateTime('first day of this month');
        $endDate   = new \DateTime('last day of this month');

        $start = $request->query->get('start', $startDate->format('Y-m-d'));
        $end   = $request->query->get('end',   $endDate->format('Y-m-d'));

        // Convertir a DateTime S I N modificar el mes (FIX).
        $startDateObj = new \DateTime($start);
        $endDateObj   = new \DateTime($end);

        // Devolvemos a string para el BETWEEN.
        $start = $startDateObj->format('Y-m-d');
        $end   = $endDateObj->format('Y-m-d');

        $company = $this->companiesRepository->findOneBy(['id' => $com]);

        $qb->andWhere('entity.company = :selectedCompany')
           ->setParameter('selectedCompany', $company)
           ->andWhere('entity.createdAt BETWEEN :startDate AND :endDate')
           ->setParameter('startDate', $start)
           ->setParameter('endDate',   $end);

        return $qb;
    }

    /**
     * Persistencia de documentos subidos:
     *  - Valida y asigna usuario mediante DocumentValidator.
     *  - Mueve cada PDF a su carpeta (empresa/tipo).
     *  - Registra errores (si los hay) en un fichero descargable.
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $request = $this->requestStack->getCurrentRequest();

        // Importante: el campo de subida no está mapeado, viene en $request->files.
        // La clave 'Document' corresponde al nombre del Form basado en la entidad.
        $files = $request->files->get('Document')['fieldUpload'] ?? null;

        $user = $this->security->getUser();
        $selectedCompany = $entityInstance->getCompany();
        $selectedCompanyComercialName = $selectedCompany->getComercialName();
        $formatName = $this->formatFolderName($selectedCompanyComercialName);

        $errorLog = []; // Acumula errores por archivo.

        if ($files) {
            $documentType = $entityInstance->getType();
            if ($documentType) {
                $folderName = strtolower($documentType->getFolderName());
                $uploadDirectory = $this->getParameter('uploads_directory') . '/documentos' . '/' . $formatName . '/' . $folderName;

                /** @var UploadedFile $file */
                foreach ($files as $file) {
                    $newDocument = new Document();
                    $newDocument->setType($documentType);

                    // Valida el documento y detecta el usuario destino por el propio PDF (nomenclatura/contenido).
                    [$isValid, $errorMessage, $userFound] = $this->documentValidator->validateDocumentAndFindUser($file, $uploadDirectory);

                    if (!$isValid) {
                        // Guardamos en log y pasamos al siguiente fichero.
                        $errorLog[] = [
                            'filename' => $file->getClientOriginalName(),
                            'error'    => $errorMessage
                        ];
                        continue;
                    }

                    // Documento válido: completamos datos mínimos.
                    $newDocument->setUser($userFound);
                    if ($selectedCompany) {
                        $newDocument->setCompany($selectedCompany);
                    }

                    // Guardado físico con el mismo nombre (con la extensión detectada).
                    $extension        = $file->guessExtension();
                    $originalFileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    $newFileName      = $originalFileName . '.' . $extension;

                    $file->move($uploadDirectory, $newFileName);

                    $fileUrl = '/uploads/documentos/' . $formatName . '/' . $folderName . '/' . $newFileName;
                    $newDocument->setUrl($fileUrl);
                    $newDocument->setName($newFileName);

                    // Persistimos en DB (flush al final del bucle).
                    $entityManager->persist($newDocument);
                }

                // Si hubo errores, se genera el fichero y se muestra el enlace de descarga.
                if (!empty($errorLog)) {
                    $filePath = $this->documentErrorLogService->generateErrorFile($errorLog);
                    $fileName = basename($filePath);

                    $fileDownloadUrl = $this->generateUrl('download_error_file', ['filename' => $fileName]);

                    $this->addFlash(
                        'success',
                        'El archivo de error se ha guardado correctamente. ' .
                        'Puedes descargarlo <a href="' . $fileDownloadUrl . '" target="_blank">aquí</a>.'
                    );
                }

                // Consolidamos todo lo persistido.
                $entityManager->flush();
            }
        }
    }

    /**
     * Eliminación de un documento:
     *  - Borra el fichero físico en /public/uploads/... si existe.
     *  - Elimina la fila en DB.
     *  - Informa mediante flashes de estado/errores.
     */
    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Document) {
            return;
        }

        $fileUrl = $entityInstance->getUrl();

        $projectDir = $this->params->get('kernel.project_dir');
        $filePath   = $projectDir . '/public' . $fileUrl;

        if (file_exists($filePath)) {
            if (@unlink($filePath)) {
                $this->addFlash('success', 'El archivo ha sido eliminado correctamente.');
            } else {
                $this->addFlash('error', 'No se pudo eliminar el archivo.');
            }
        } else {
            $this->addFlash('warning', 'El archivo no existe y no se pudo eliminar.');
        }

        $entityManager->remove($entityInstance);
        $entityManager->flush();
    }

    /**
     * Suscripción a eventos de EasyAdmin (post persist).
     * Mantengo la firma original para no cambiar el wiring en producción.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            AfterEntityPersistedEvent::class => ['onAfterEntityPersisted'],
        ];
    }

    /**
     * Tras persistir una entidad, redirige al índice preservando filtros.
     * Nota: se mantiene la condición original (User) para no alterar el comportamiento global del proyecto.
     * Si en tu instancia quieres que se aplique al Document, bastaría con cambiar a "instanceof Document".
     */
    public function onAfterEntityPersisted(AfterEntityPersistedEvent $event): void
    {
        $entity = $event->getEntityInstance();
        if (!$entity instanceof User) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        $com = $entity->getCompany()->getId();
        $off = $request->query->get('off') ?? 'all';
        $us  = $request->query->get('us')  ?? 'all';

        // Por defecto, mes actual.
        $startDate = new \DateTime('first day of this month');
        $endDate   = new \DateTime('last day of this month');

        $start = $request->query->get('start', $startDate->format('Y-m-d'));
        $end   = $request->query->get('end',   $endDate->format('Y-m-d'));

        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction('index')
            ->set('com', $com)
            ->set('us',  $us)
            ->set('off', $off)
            ->set('start', $start)
            ->set('end',   $end)
            ->generateUrl();

        $response = new RedirectResponse($url);
        $this->getContext()->getRequest()->getSession()->save();
        $response->send();
    }

    /**
     * Normaliza el nombre de carpeta de empresa (minúsculas, sin acentos, sin espacios).
     */
    private function formatFolderName(string $name): string
    {
        $name = trim($name);
        $accents   = ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'];
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
