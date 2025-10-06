<?php

namespace App\Entity;

use App\Repository\WorkScheduleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkScheduleRepository::class)]
class WorkSchedule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 255)]
    private string $name;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\OneToMany(mappedBy: 'workSchedule', targetEntity: UserWorkSchedule::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $userWorkSchedules;

    #[ORM\OneToMany(mappedBy: 'workSchedule', targetEntity: WorkScheduleDay::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $workScheduleDays;

    #[ORM\OneToMany(mappedBy: 'workSchedule', targetEntity: WorkScheduleSegment::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $workScheduleSegments;

    #[ORM\ManyToOne(inversedBy: 'workSchedule')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Companies $company = null;

    public function __construct()
    {
        $this->workScheduleDays = new ArrayCollection();
        $this->userWorkSchedules = new ArrayCollection();
        $this->workScheduleSegments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return Collection<int, WorkScheduleDay>
     */
    public function getWorkScheduleDays(): Collection
    {
        return $this->workScheduleDays;
    }

    public function addWorkScheduleDay(WorkScheduleDay $workScheduleDay): self
    {
        if (!$this->workScheduleDays->contains($workScheduleDay)) {
            $this->workScheduleDays[] = $workScheduleDay;
            $workScheduleDay->setWorkSchedule($this);
        }

        return $this;
    }

    public function removeWorkScheduleDay(WorkScheduleDay $workScheduleDay): self
    {
        if ($this->workScheduleDays->removeElement($workScheduleDay)) {
            // Set the owning side to null (unless already changed)
            if ($workScheduleDay->getWorkSchedule() === $this) {
                $workScheduleDay->setWorkSchedule(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserWorkSchedule>
     */
    public function getUserWorkSchedules(): Collection
    {
        return $this->userWorkSchedules;
    }

    public function addUserWorkSchedule(UserWorkSchedule $userWorkSchedule): self
    {
        if (!$this->userWorkSchedules->contains($userWorkSchedule)) {
            $this->userWorkSchedules[] = $userWorkSchedule;
            $userWorkSchedule->setWorkSchedule($this);
        }

        return $this;
    }

    public function removeUserWorkSchedule(UserWorkSchedule $userWorkSchedule): self
    {
        if ($this->userWorkSchedules->removeElement($userWorkSchedule)) {
            if ($userWorkSchedule->getWorkSchedule() === $this) {
                $userWorkSchedule->setWorkSchedule(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, WorkScheduleDay>
     */
    public function getWorkScheduleSegments(): Collection
    {
        return $this->workScheduleSegments;
    }

    public function addWorkScheduleSegment(WorkScheduleSegment $workScheduleSegment): self
    {
        if (!$this->workScheduleSegments->contains($workScheduleSegment)) {
            $this->workScheduleSegments[] = $workScheduleSegment;
            $workScheduleSegment->setWorkSchedule($this);
        }

        return $this;
    }

    public function removeWorkScheduleSegment(WorkScheduleSegment $workScheduleSegment): self
    {
        if ($this->workScheduleSegments->removeElement($workScheduleSegment)) {
            // Set the owning side to null (unless already changed)
            if ($workScheduleSegment->getWorkSchedule() === $this) {
                $workScheduleSegment->setWorkSchedule(null);
            }
        }

        return $this;
    }

    public function getCompany(): ?Companies
    {
        return $this->company;
    }

    public function setCompany(?Companies $company): static
    {
        $this->company = $company;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
