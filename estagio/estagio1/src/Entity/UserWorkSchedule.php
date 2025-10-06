<?php
namespace App\Entity;

use App\Repository\UserWorkScheduleRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity(repositoryClass: UserWorkScheduleRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_user_schedule', columns: ['user_id', 'work_schedule_id'])]
class UserWorkSchedule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $startDate;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'userWorkSchedules')]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private User $user;

    #[ORM\ManyToOne(targetEntity: WorkSchedule::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private WorkSchedule $workSchedule;

    public function getId(): ?int 
    { 
        return $this->id; 
    }

    public function getUser(): User 
    { 
        return $this->user; 
    }
    public function setUser(User $user): self 
    { 
        $this->user = $user; return $this; 
    }

    public function getStartDate(): \DateTimeInterface 
    { 
        return $this->startDate; 
    }
    public function setStartDate(\DateTimeInterface $startDate): self 
    { 
        $this->startDate = $startDate; return $this; 
    }

    public function getEndDate(): ?\DateTimeInterface 
    { 
        return $this->endDate; 
    }
    public function setEndDate(\DateTimeInterface $endDate): self 
    { 
        $this->endDate = $endDate; return $this; 
    }

    public function getWorkSchedule(): WorkSchedule 
    { 
        return $this->workSchedule; 
    }
    public function setWorkSchedule(WorkSchedule $ws): self 
    { 
        $this->workSchedule = $ws; return $this; 
    }
}
