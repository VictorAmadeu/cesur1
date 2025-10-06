<?php

namespace App\Entity;

use App\Repository\TimesRegisterRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\TimeRegisterStatus;
use App\Enum\ScheduleType;
use App\Enum\JustificationStatus;

#[ORM\Entity(repositoryClass: TimesRegisterRepository::class)]
#[ORM\HasLifecycleCallbacks]
class TimesRegister
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $hourStart = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $hourEnd = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $comments = null;

    #[ORM\ManyToOne(inversedBy: 'timesRegisters')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Projects::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: "SET NULL")]
    private ?Projects $project = null;

    #[ORM\Column]
    private ?int $slot = null;

    #[ORM\Column(type: Types::STRING)]
    private ?string $totalTime = null;

    #[ORM\Column(type: Types::STRING)]
    private ?string $totalSlotTime = null;

    #[ORM\Column(type: 'integer', enumType: TimeRegisterStatus::class)]
    private ?TimeRegisterStatus $status = TimeRegisterStatus::OPEN;

    #[ORM\Column(type: 'string', enumType: ScheduleType::class, length: 30, nullable: true)]
    private ?ScheduleType $scheduleType = ScheduleType::NORMAL;

    #[ORM\Column(type: 'string', enumType: JustificationStatus::class)]
    private JustificationStatus $justificationStatus = JustificationStatus::COMPLETED; 

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function setPrePersistValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getHourStart(): ?\DateTimeInterface
    {
        return $this->hourStart;
    }

    public function setHourStart(\DateTimeInterface $hourStart): static
    {
        $this->hourStart = $hourStart;

        return $this;
    }

    public function getHourEnd(): ?\DateTimeInterface
    {
        return $this->hourEnd;
    }

    public function setHourEnd(\DateTimeInterface $hourEnd): static
    {
        $this->hourEnd = $hourEnd;

        return $this;
    }

    public function getComments(): ?string
    {
        return $this->comments;
    }

    public function setComments(?string $comments): static
    {
        $this->comments = $comments;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getSlot(): ?int
    {
        return $this->slot;
    }

    public function setSlot(int | string $slot): static
    {
        $this->slot = $slot;

        return $this;
    }

    public function getTotalTime(): ?string
    {
        return $this->totalTime;
    }

    public function setTotalTime(string $totalTime): static
    {
        $this->totalTime = $totalTime;

        return $this;
    }

    public function getTotalSlotTime(): ?string
    {
        return $this->totalSlotTime;
    }

    public function setTotalSlotTime(string $totalSlotTime): static
    {
        $this->totalSlotTime = $totalSlotTime;

        return $this;
    }

    public function getProject(): ?Projects
    {
        return $this->project;
    }

    public function setProject(?Projects $project): static
    {
        $this->project = $project;
        return $this;
    }

    public function getStatus(): ?TimeRegisterStatus
    {
        return $this->status;
    }

    public function setStatus(TimeRegisterStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getScheduleType(): ?ScheduleType
    {
        return $this->scheduleType;
    }

    public function setScheduleType(ScheduleType $scheduleType): static
    {
        $this->scheduleType = $scheduleType;

        return $this;
    }

    public function getJustificationStatus(): JustificationStatus
    {
        return $this->justificationStatus;
    }

    public function setJustificationStatus(JustificationStatus $justificationStatus): static
    {
        $this->justificationStatus = $justificationStatus;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'createdAt' => $this->createdAt ? $this->createdAt->format('Y-m-d H:i:s') : null,
            'date' => $this->date ? $this->date->format('Y-m-d') : null,
            'hourStart' => $this->hourStart ? $this->hourStart->format('Y-m-d H:i:s') : null,
            'hourEnd' => $this->hourEnd ? $this->hourEnd->format('Y-m-d H:i:s') : null,
            'project' => $this->project ? $this->project->getName() : null,
            'comments' => $this->comments,
            'slot' => $this->slot,
            'totalTime' => $this->totalTime ? $this->totalTime : null,
            'totalSlotTime' => $this->totalSlotTime ? $this->totalSlotTime : null,
            'status' => $this->status,
            'scheduleType' => $this->scheduleType,
            'justificationStatus' => $this->justificationStatus
        ];
    }
}
