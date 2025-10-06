<?php

namespace App\Entity;

use App\Repository\WorkScheduleDayRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkScheduleDayRepository::class)]
class WorkScheduleDay
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: WorkSchedule::class, inversedBy: 'workScheduleDays')]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?WorkSchedule $workSchedule = null;

    #[ORM\Column(type: "smallint")]
    private int $dayOfWeek; // 1 = Lunes, ..., 7 = Domingo

    #[ORM\Column(type: "time", nullable: true)]
    private ?\DateTimeInterface $start = null;

    #[ORM\Column(type: "time", nullable: true)]
    private ?\DateTimeInterface $end = null;

    #[ORM\OneToMany(mappedBy: 'workScheduleDay', targetEntity: WorkScheduleSegment::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $segments;

    public function __construct()
    {
        $this->segments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDayOfWeek(): int
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(int $dayOfWeek): self
    {
        if ($dayOfWeek < 1 || $dayOfWeek > 7) {
            throw new \InvalidArgumentException('El día de la semana debe estar entre 1 (Lunes) y 7 (Domingo).');
        }
        $this->dayOfWeek = $dayOfWeek;
        return $this;
    }

    public function getStart(): ?\DateTimeInterface
    {
        return $this->start;
    }

    public function setStart(?\DateTimeInterface $start): self
    {
        $this->start = $start;
        return $this;
    }

    public function getEnd(): ?\DateTimeInterface
    {
        return $this->end;
    }

    public function setEnd(?\DateTimeInterface $end): self
    {
        $this->end = $end;
        return $this;
    }

    /**
    * @return Collection<int, WorkScheduleSegment>
    */
    public function getSegments(): Collection
    {
        return $this->segments;
    }

    public function addSegment(WorkScheduleSegment $segment): self
    {
        if (!$this->segments->contains($segment)) {
            $this->segments[] = $segment;
            $segment->setWorkScheduleDay($this);
        }

        return $this;
    }

    public function removeSegment(WorkScheduleSegment $segment): self
    {
        if ($this->segments->removeElement($segment)) {
            if ($segment->getWorkScheduleDay() === $this) {
                $segment->setWorkScheduleDay(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        $days = [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ];

        $dayName = $days[$this->dayOfWeek] ?? 'Día desconocido';

        return $dayName;
    }


}
