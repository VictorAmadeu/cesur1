<?php
namespace App\Controller\Admin;

use App\Entity\WorkScheduleSegment;
use App\Entity\WorkScheduleDay;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimeField;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Repository\WorkScheduleRepository;
use App\Repository\CompaniesRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use Doctrine\ORM\EntityManagerInterface;

class WorkScheduleSegmentCrudController extends AbstractCrudController
{
    private $companiesRepository, $security, $requestStack, $workScheduleRepository;

    public function __construct(
        Security $security, 
        RequestStack $requestStack, 
        WorkScheduleRepository $workScheduleRepository,
        CompaniesRepository $companiesRepository
        )
    {
        $this->security = $security;
        $this->requestStack = $requestStack;
        $this->workScheduleRepository = $workScheduleRepository;
        $this->companiesRepository = $companiesRepository;
    }

    public static function getEntityFqcn(): string
    {
        return WorkScheduleSegment::class;
    }

        public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPaginatorPageSize(25)
            ->setPageTitle('index', 'Segmentos')
            ->setEntityLabelInSingular('Segmento')
            ->setEntityLabelInPlural('Segmentos')
            ->showEntityActionsInlined()
            ->overrideTemplate('crud/index', 'admin/workScheduleSegment/custom_index.html.twig');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            ChoiceField::new('daysOfWeek', 'Días de la semana')
                ->setChoices([
                    'Lunes' => 1,
                    'Martes' => 2,
                    'Miércoles' => 3,
                    'Jueves' => 4,
                    'Viernes' => 5,
                    'Sábado' => 6,
                    'Domingo' => 7,
                ])
                ->allowMultipleChoices()
                ->onlyOnForms()
                ->setFormTypeOption('mapped', false)
                ->setColumns(4),
            ChoiceField::new('type', 'Tipo')
                ->setChoices([
                    'Almuerzo' => 1,
                    'Descanso' => 2,
                ])
                ->setColumns(4),
            TimeField::new('start', 'Inicio')
                ->setFormat('H:i')
                ->formatValue(function ($value, $entity) {
                    return $value instanceof \DateTimeInterface ? $value->format('H:i') : null;
                })
                ->setRequired(false)
                ->setColumns(2),
            TimeField::new('end', 'Fin')
                ->setFormat('H:i')
                ->formatValue(function ($value, $entity) {
                    return $value instanceof \DateTimeInterface ? $value->format('H:i') : null;
                })
                ->setRequired(false)
                ->setColumns(2),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof WorkScheduleSegment) {
            return;
        }

        $request = $this->getContext()->getRequest();
        $formData = $request->request->all('WorkScheduleSegment'); // ya te da el subarray

        $dayIds = $formData['workScheduleDay'] ?? [];

        foreach ($dayIds as $dayId) {
            $cloned = new WorkScheduleSegment();
            
            // Buscar la entidad WorkScheduleDay por ID
            $dayEntity = $entityManager->getRepository(WorkScheduleDay::class)->find($dayId);
            if (!$dayEntity) {
                continue;
            }

            $cloned->setWorkScheduleDay($dayEntity)
                ->setStart(new \DateTime($formData['start']))
                ->setEnd(new \DateTime($formData['end']))
                ->setType($formData['type']);

            $entityManager->persist($cloned);
        }

        $entityManager->flush();
    }


    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $user = $this->security->getUser();
        $role = $user->getRole();
        $request = $this->requestStack->getCurrentRequest();

        $companyUser = $user->getCompany();
        $com = $request->query->get('com', $user->getCompany()->getId());
        $wd = $request->query->get('wd', $user->getCompany()->getId());
        $account = $user->getAccounts();
        $workSchedule = $this->workScheduleRepository->findBy(['company' => $com]);
        $workScheduleSelectd = $this->workScheduleRepository->findOneBy(['company' => $com]);
        
        $selectedCompany = $this->companiesRepository->find($com);
        $companies = $this->companiesRepository->findBy(['accounts' => $account]);
        
        $responseParameters->set('selectedCompany', $selectedCompany);
        $responseParameters->set('companies', $companies);
        $responseParameters->set('workSchedule', $workSchedule);
        $responseParameters->set('selectedWorkSchedule', $workScheduleSelectd);
        return $responseParameters;
    }
}
