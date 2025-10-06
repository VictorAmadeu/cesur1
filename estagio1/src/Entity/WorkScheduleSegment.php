<?php

namespace App\Entity;

use App\Repository\WorkScheduleSegmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkScheduleSegmentRepository::class)]
class WorkScheduleSegment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: WorkScheduleDay::class, inversedBy: 'segments')]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?WorkScheduleDay $workScheduleDay = null;

    #[ORM\ManyToOne(targetEntity: WorkSchedule::class, inversedBy: 'segments')]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?WorkSchedule $workSchedule = null;

    #[ORM\Column(type: "time")]
    private \DateTimeInterface $start;

    #[ORM\Column(type: "time")]
    private \DateTimeInterface $end;

    #[ORM\Column(type: "string", length: 50)]
    private string $type; // ej: 'almuerzo', 'descanso'

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWorkScheduleDay(): ?WorkScheduleDay
    {
        return $this->workScheduleDay;
    }

    public function setWorkScheduleDay(?WorkScheduleDay $workScheduleDay): self
    {
        $this->workScheduleDay = $workScheduleDay;
        return $this;
    }

    public function getWorkSchedule(): ?WorkSchedule
    {
        return $this->workSchedule;
    }

    public function setWorkSchedule(?WorkSchedule $workSchedule): self
    {
        $this->workSchedule = $workSchedule;
        return $this;
    }

    public function getStart(): \DateTimeInterface
    {
        return $this->start;
    }

    public function setStart(\DateTimeInterface $start): self
    {
        $this->start = $start;
        return $this;
    }

    public function getEnd(): \DateTimeInterface
    {
        return $this->end;
    }

    public function setEnd(\DateTimeInterface $end): self
    {
        $this->end = $end;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function __toString(): string
    {
        $types = [
            1 => 'Almuerzo',
            2 => 'Descanso',
            3 => 'Hora extra',
        ];

        $days = [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miercoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sabado',
            7 => 'Domingo',
        ];

        $typeLabel = $types[$this->type] ?? 'Desconocido';
        $dayLabel = $days[$this->workScheduleDay?->getDayOfWeek()] ?? 'Desconocido';

        return sprintf(
            '%s - %s (%s) -  %s',
            $this->start?->format('H:i') ?? '',
            $this->end?->format('H:i') ?? '',
            $typeLabel,
            $dayLabel
        );
    }

    public function getTypeLabel(): string
    {
        $types = [
            1 => 'Almuerzo',
            2 => 'Descanso',
            3 => 'Hora extra',
        ];

        return $types[$this->type] ?? 'Desconocido';
    }

}
