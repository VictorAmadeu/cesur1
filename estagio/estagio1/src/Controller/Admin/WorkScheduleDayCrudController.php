<?php

namespace App\Controller\Admin;

use App\Entity\WorkScheduleDay;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimeField;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;


class WorkScheduleDayCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return WorkScheduleDay::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPaginatorPageSize(25)
            ->setPageTitle('index', 'Horario del día')
            ->setEntityLabelInSingular('Horario del día')
            ->setEntityLabelInPlural('Horarios de los días')
            ->showEntityActionsInlined()
            ->setFormOptions(['attr' => ['class' => 'ea-form ea-form-vertical']])
            ->overrideTemplate('crud/index', 'admin/WorkScheduleDay/custom_index.html.twig');
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
                ->setFormTypeOption('mapped', false)
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
        if (!$entityInstance instanceof WorkScheduleDay) {
            return;
        }

        $request = $this->getContext()->getRequest();
        $formData = $request->request->all();

        $days = $formData['WorkScheduleDay']['daysOfWeek'] ?? [];

        foreach ($days as $day) {
            $cloned = new WorkScheduleDay();
            $cloned->setWorkSchedule($entityInstance->getWorkSchedule())
                ->setDayOfWeek((int)$day)
                ->setStart($entityInstance->getStart())
                ->setEnd($entityInstance->getEnd());

            $entityManager->persist($cloned);
        }

        $entityManager->flush();
    }
}
