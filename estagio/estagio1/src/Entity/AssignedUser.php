<?php

namespace App\Entity;

use App\Repository\AssignedUserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AssignedUserRepository::class)]
#[ORM\Table(name: 'assigned_user', uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'unique_assignment', columns: ['supervisor_id', 'user_id'])
])]
#[ORM\HasLifecycleCallbacks]
class AssignedUser
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'assignedUsers')]
    #[ORM\JoinColumn(name: 'supervisor_id', referencedColumnName: 'id', nullable: false)]
    private ?User $supervisor = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'supervisors')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $assignedAt = null;

    public function __construct(?User $supervisor = null, ?User $user = null, ?\DateTimeInterface $assignedAt = null)
    {
        $this->supervisor = $supervisor;
        $this->user = $user;
        $this->assignedAt = $assignedAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSupervisor(): ?User
    {
        return $this->supervisor;
    }

    public function setSupervisor(?User $supervisor): self
    {
        $this->supervisor = $supervisor;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getAssignedAt(): ?\DateTimeInterface
    {
        return $this->assignedAt;
    }

    public function setAssignedAt(\DateTimeInterface $assignedAt): self
    {
        $this->assignedAt = $assignedAt;

        return $this;
    }

    public function __toString()
    {
        return $this->user->getFullName();
    }
}
