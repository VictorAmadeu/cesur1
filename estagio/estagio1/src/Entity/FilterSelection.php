<?php

namespace App\Entity;

use App\Repository\FilterSelectionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FilterSelectionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class FilterSelection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'filterSelections')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Accounts::class, inversedBy: 'filterSelections')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Accounts $account = null;    

    #[ORM\ManyToOne(targetEntity: Companies::class, inversedBy: 'filterSelections')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Companies $company = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'filterSelectionsSelected')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $userSelected = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateStart = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateEnd = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getAccount(): ?Accounts
    {
        return $this->account;
    }

    public function setAccount(?Accounts $account): self
    {
        $this->account = $account;
        return $this;
    }

    public function getCompany(): ?Companies
    {
        return $this->company;
    }

    public function setCompany(?Companies $company): self
    {
        $this->company = $company;
        return $this;
    }

    public function getUserSelected(): ?User
    {
        return $this->userSelected;
    }

    public function setUserSelected(?User $userSelected): self
    {
        $this->userSelected = $userSelected;
        return $this;
    }

    public function getDateStart(): ?\DateTimeInterface
    {
        return $this->dateStart;
    }

    public function setDateStart(?\DateTimeInterface $dateStart): self
    {
        $this->dateStart = $dateStart;
        return $this;
    }

    public function getDateEnd(): ?\DateTimeInterface
    {
        return $this->dateEnd;
    }

    public function setDateEnd(?\DateTimeInterface $dateEnd): self
    {
        $this->dateEnd = $dateEnd;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
