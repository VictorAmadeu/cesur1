<?php

namespace App\Helper;

use App\Entity\TimesRegister;
use App\Entity\Projects;
use App\Entity\User;
use App\Enum\TimeRegisterStatus;
use Doctrine\ORM\EntityManagerInterface;

class TimeSlotHelper
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function createNewSlotTime(User $user, ?string $comments, \DateTimeInterface $currentTime, $slot, ?Projects $project = null): TimesRegister
    {
        $newSlot = $slot instanceof TimesRegister ? $slot->getSlot() + 1 : 1;

        $totalTimeOld = $slot->getTotalTime();

        $tr = new TimesRegister();
        $tr->setUser($user);
        $tr->setComments($comments);
        $tr->setDate(new \DateTime($currentTime->format('Y-m-d')));
        $tr->setHourStart($currentTime);
        $tr->setHourEnd($currentTime);
        $tr->setSlot($newSlot);
        $tr->setStatus(TimeRegisterStatus::OPEN);
        $tr->setTotalSlotTime('00:00:00');
        $tr->setTotalTime($totalTimeOld);
        if ($project) {
            $tr->setProject($project);
        }

        return $tr;
    }

    public function createNewSlotTimeForDay(User $user, ?string $comments, \DateTimeInterface $currentTime, ?Projects $project = null): TimesRegister
    {
        $startOfDay = new \DateTime($currentTime->format('Y-m-d') . ' 00:00:00');

        $tr = new TimesRegister();
        $tr->setUser($user);
        $tr->setComments($comments);
        $tr->setDate(new \DateTime($currentTime->format('Y-m-d')));
        $tr->setHourStart($startOfDay);
        $tr->setHourEnd($currentTime);
        $tr->setStatus(TimeRegisterStatus::OPEN);
        $tr->setSlot(1);

        if ($project) {
            $tr->setProject($project);
        }

        return $tr;
    }

    public function createNewSlotForDay(User $user, ?string $comments, \DateTimeInterface $currentTime, ?Projects $project = null): TimesRegister
    {
        $tr = new TimesRegister();
        $tr->setUser($user);
        $tr->setComments($comments);
        $tr->setDate(new \DateTime($currentTime->format('Y-m-d')));
        $tr->setHourStart($currentTime);
        $tr->setHourEnd($currentTime);
        $tr->setStatus(TimeRegisterStatus::OPEN);
        $tr->setSlot(1);
        $tr->setTotalSlotTime('00:00:00');
        $tr->setTotalTime('00:00:00');

        if ($project) {
            $tr->setProject($project);
        }
        return $tr;
    }

    public function closeSlot(TimesRegister $slot, \DateTimeInterface $currentTime): TimesRegister
    {
        $slot->setHourEnd($currentTime);
        $slot->setStatus(TimeRegisterStatus::CLOSED);

        $diff = $currentTime->diff($slot->getHourStart());
        $formatted = $diff->format('%H:%I:%S');
        $slot->setTotalSlotTime($formatted);

        $currentSlotNumber = $slot->getSlot();

        if ($currentSlotNumber === 1) {
            // Primer slot del día → totalTime es igual al totalSlotTime
            $slot->setTotalTime($slot->getTotalSlotTime());
        } else {
            // Buscar el slot anterior (del mismo usuario y día)
            $totalTimeOld = $slot->getTotalTime();

            $newTotal = $this->sumTimes([
                $totalTimeOld,
                $slot->getTotalSlotTime()
            ]);
            $slot->setTotalTime($newTotal);
        }
        return $slot;
    }

    public function sumTimes(array $times): string
    {
        $totalSeconds = 0;

        foreach ($times as $timeStr) {
            [$h, $m, $s] = explode(':', $timeStr);
            $totalSeconds += ($h * 3600) + ($m * 60) + $s;
        }

        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    public function getSlotActionFromCode(int $code): ?string
    {
        return match ($code) {
            200 => 'exit',
            201, 203 => 'entry',
            default => null,
        };
    }

    /**
     * Convierte un string "HH:MM:SS" en segundos (int)
     */
    private function timeStringToSeconds(?string $timeString): int
    {
        if (!$timeString) return 0;
        list($h, $m, $s) = explode(':', $timeString);
        return ((int)$h * 3600) + ((int)$m * 60) + (int)$s;
    }

    /**
     * Convierte segundos (int) a string "HH:MM:SS"
     */
    private function secondsToTimeString(int $seconds): string
    {
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }

    // Suma todos los totalSlotTime y guarda el total acumulado en totalTime del último slot
    public function setTotalTime(string $date, User $user): void
    {
        // Obtenemos todos los slots del usuario en la fecha
        $slots = $this->getTimesSlot($date, $user);

        $totalSeconds = 0;
        foreach ($slots as $slot) {
            // Suponiendo que getTotalSlotTime() devuelve string "HH:mm:ss"
            $totalSeconds += $this->getDurationInSeconds($slot->getTotalSlotTime());
        }

        // Convertimos la suma total a formato HH:mm:ss
        $totalTimeString = $this->secondsToTimeString($totalSeconds);

        // Obtenemos el último slot para actualizar su totalTime
        $lastSlot = $this->getTimeSlot($date, $user);
        $lastSlot->setTotalTime($totalTimeString);

        $this->em->flush();
    }

    public function setTotalTimeById(int $id): void
    {
        $slot = $this->em->getRepository(TimesRegister::class)->find($id);

        if (!$slot) {
            // No existe el slot
            return;
        }

        $start = $slot->getHourStart();
        $end = $slot->getHourEnd();

        if (!$start || !$end) {
            // No hay fechas completas para calcular
            return;
        }

        // Duración actual en segundos
        $durationSeconds = $end->getTimestamp() - $start->getTimestamp();
        if ($durationSeconds < 0) {
            $durationSeconds = 0;
        }

        $previousTotalSeconds = $slot->getTotalSlotTime();

        // Total previo en segundos
        $previousTotalSeconds = $this->timeStringToSeconds($previousTotalSeconds);

        // Nuevo total sumando duración actual
        $newTotalSeconds = $previousTotalSeconds + $durationSeconds;

        $previousTotalTime = $slot->getTotalTime();
        $previousTotalTimeSeconds = $this->timeStringToSeconds($previousTotalTime);

        $newTotalTimeSeconds = $previousTotalTimeSeconds + $durationSeconds;

        // Guardar string formateado en totalSlotTime y totalTime
        $timeString = $this->secondsToTimeString($newTotalSeconds);
        $totalTimeString = $this->secondsToTimeString($newTotalTimeSeconds);
        $slot->setTotalSlotTime($timeString);
        $slot->setTotalTime($totalTimeString);

        $this->em->flush();
    }


    public function getTimesSlot(\DateTimeInterface $date): array
    {
        return $this->em->getRepository(TimesRegister::class)->findBy([
            'date' => $date,
        ]);
    }

    public function getTimeSlot(\DateTimeInterface $date, User $user): ?TimesRegister
    {
        return $this->em->getRepository(TimesRegister::class)->findOneBy(
            [
                'date' => $date,
                'user' => $user,
            ],
            [
                'createdAt' => 'DESC',
            ]
        );
    }
}
