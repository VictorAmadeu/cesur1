<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\TimesRegister;
use App\Enum\TimeRegisterStatus;
use App\Enum\JustificationStatus;
use App\Enum\ScheduleType;
use App\Helper\TimeSlotHelper;

#[AsCommand(
    name: 'app:daily-task',
    description: 'Cerrar fichajes de ayer y crear nuevos para hoy',
)]
class DailyTaskCommand extends Command
{
    private $em;
    private $timeSlotHelper;

    public function __construct(EntityManagerInterface $em, TimeSlotHelper $timeSlotHelper)
    {
        parent::__construct();
        $this->em = $em;
        $this->timeSlotHelper = $timeSlotHelper;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $yesterday = (new \DateTime('yesterday'))->format('Y-m-d');
        $today = (new \DateTime('today'))->format('Y-m-d');
    
        $output->writeln("⏳ Buscando registros abiertos del día $yesterday...");
    
        $openTimes = $this->em->getRepository(TimesRegister::class)->findBy([
            'date' => new \DateTime($yesterday),
            'status' => TimeRegisterStatus::OPEN,
        ]);
    
        if (empty($openTimes)) {
            $output->writeln("✅ No se encontraron registros abiertos.");
            return Command::SUCCESS;
        }
    
        foreach ($openTimes as $time) {
            $userId = $time->getUser()->getId();
            $output->writeln("🔄 Cerrando registro ID " . $time->getId() . " (Usuario $userId)");
    
            // Cerrar el registro del día anterior
            $time->setHourEnd(new \DateTime("$yesterday 23:59:59"));
            $time->setStatus(TimeRegisterStatus::CLOSED); // Completed
            $this->em->flush();

            $this->timeSlotHelper->setTotalTimeById($time->getId());
    
            // Crear uno nuevo desde 00:00:00 del día actual
            $newTime = new TimesRegister();
            $newTime->setUser($time->getUser());
            $newTime->setDate(new \DateTime($today));
            $newTime->setHourStart(new \DateTime("$today 00:00:00"));
            $newTime->setHourEnd(new \DateTime("$today 00:00:00")); // Se queda abierto
            $newTime->setStatus(TimeRegisterStatus::OPEN); // Abierto
            $newTime->setSlot(1); // slot siguiente
            $newTime->setComments($time->getComments());
            $newTime->setProject($time->getProject());
            $newTime->setTotalTime('00:00:00');
            $newTime->setTotalSlotTime('00:00:00');            
            $newTime->setJustificationStatus(JustificationStatus::COMPLETED);
            $newTime->setScheduleType(ScheduleType::NORMAL);
    
            $this->em->persist($newTime);
        }
    
        $this->em->flush();
    
        $output->writeln("✅ Se cerraron y replicaron " . count($openTimes) . " registros.");
    
        return Command::SUCCESS;
    }
    
}
