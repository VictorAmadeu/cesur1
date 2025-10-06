<?php

namespace App\Entity;

use App\Repository\AccountsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AccountsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Accounts
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $name = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private ?bool $allowProjects = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private ?bool $allowManualEntry = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private ?bool $allowDevice = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private ?bool $allowDocument = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private ?bool $allowWorkSchedule = false;

    #[ORM\Column]
    #[ORM\JoinColumn(nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[ORM\JoinColumn(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'accounts', targetEntity: Companies::class, orphanRemoval: true)]
    private Collection $company;

    #[ORM\OneToMany(mappedBy: 'accounts', targetEntity: User::class, orphanRemoval: true)]
    private Collection $Users;

    #[ORM\OneToMany(mappedBy: 'account', targetEntity: FilterSelection::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $filterSelections;    

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->company = new ArrayCollection();
        $this->Users = new ArrayCollection();
        $this->filterSelections = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function __toString(): string
    {
        return $this->name;
    }


    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getAllowProjects(): ?bool
    {
        return $this->allowProjects;
    }

    public function setAllowProjects(bool $allowProjects): static
    {
        $this->allowProjects = $allowProjects;

        return $this;
    }

    public function getAllowManualEntry(): ?bool
    {
        return $this->allowManualEntry;
    }

    public function setAllowManualEntry(bool $allowManualEntry): static
    {
        $this->allowManualEntry = $allowManualEntry;

        return $this;
    }

    public function getAllowDevice(): ?bool
    {
        return $this->allowDevice;
    }

    public function setAllowDevice(bool $allowDevice): static
    {
        $this->allowDevice = $allowDevice;

        return $this;
    }

    public function getAllowDocument(): ?bool
    {
        return $this->allowDocument;
    }

    public function setAllowDocument(bool $allowDocument): static
    {
        $this->allowDocument = $allowDocument;

        return $this;
    }

    public function getAllowWorkSchedule(): ?bool
    {
        return $this->allowWorkSchedule;
    }

    public function setAllowWorkSchedule(bool $allowWorkSchedule): static
    {
        $this->allowWorkSchedule = $allowWorkSchedule;

        return $this;
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, Companies>
     */
    public function getCompany(): Collection
    {
        return $this->company;
    }

    public function addCompany(Companies $company): static
    {
        if (!$this->company->contains($company)) {
            $this->company->add($company);
            $company->setAccounts($this);
        }

        return $this;
    }

    public function removeCompany(Companies $company): static
    {
        if ($this->company->removeElement($company)) {
            // set the owning side to null (unless already changed)
            if ($company->getAccounts() === $this) {
                $company->setAccounts(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->Users;
    }

    public function addUser(User $user): static
    {
        if (!$this->Users->contains($user)) {
            $this->Users->add($user);
            $user->setAccounts($this);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->Users->removeElement($user)) {
            // set the owning side to null (unless already changed)
            if ($user->getAccounts() === $this) {
                $user->setAccounts(null);
            }
        }

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setModifiedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getFilterSelections(): Collection
    {
        return $this->filterSelections;
    }

    public function addFilterSelection(FilterSelection $filterSelection): self
    {
        if (!$this->filterSelections->contains($filterSelection)) {
            $this->filterSelections[] = $filterSelection;
            $filterSelection->setAccount($this);
        }

        return $this;
    }

    public function removeFilterSelection(FilterSelection $filterSelection): self
    {
        if ($this->filterSelections->removeElement($filterSelection)) {
            // Establecer la propiedad de account a null
            if ($filterSelection->getAccount() === $this) {
                $filterSelection->setAccount(null);
            }
        }

        return $this;
    }
}
