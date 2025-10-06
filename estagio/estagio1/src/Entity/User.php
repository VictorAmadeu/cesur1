<?php

namespace App\Entity;

use App\Entity\FilterOffice;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use App\Enum\UserRole;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'user')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'El email introducido ya está registrado')]

class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true, nullable: true)]
    private ?string $email = null;

    /**
     * @var string The hashed password
     */
    #[ORM\Column(nullable: true)]
    private ?string $password = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $lastname1 = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $lastname2 = null;

    #[ORM\Column(length: 15, nullable: true)]
    private ?string $dni = null;

    #[ORM\Column(length: 4, nullable: true)]
    private ?int $vacationDays = 0;

    #[ORM\Column(length: 15, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private $isVerified = false;

    #[ORM\Column]
    private ?bool $isActive = null;

    #[ORM\Column(options: ['default' => false])]
    private ?bool $firstTime = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $modifiedAt = null;

    #[ORM\ManyToOne(targetEntity: Accounts::class, inversedBy: "Users")]
    #[ORM\JoinColumn(nullable: true)]
    private ?Accounts $accounts = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: TimesRegister::class, orphanRemoval: true)]
    private Collection $timesRegisters;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: License::class, orphanRemoval: true)]
    private Collection $licenses;

    #[ORM\ManyToOne(targetEntity: Companies::class, inversedBy: 'users')]
    private ?Companies $company = null;

    #[ORM\ManyToOne(targetEntity: Office::class, inversedBy: "users")]
    #[ORM\JoinColumn(nullable: true)]
    private ?Office $office = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $role = UserRole::USER->value;

    #[ORM\OneToMany(mappedBy: 'supervisor', targetEntity: AssignedUser::class, cascade: ['persist', 'remove'])]
    private Collection $assignedUsers;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: AssignedUser::class, cascade: ['persist', 'remove'])]
    private Collection $supervisors;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Document::class)]
    private Collection $document;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: FilterSelection::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $filterSelections;

    #[ORM\OneToMany(mappedBy: 'userSelected', targetEntity: FilterSelection::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $filterSelectionsSelected;

    #[ORM\OneToMany(mappedBy: 'office', targetEntity: FilterOffice::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $filterOffices;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ResetPasswordRequest::class, cascade: ['remove'])]
    private Collection $resetPasswordRequests;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserWorkSchedule::class, cascade: ['persist', 'remove'])]
    private Collection $userWorkSchedules;

    #[ORM\OneToMany(targetEntity: UserExtraSegment::class, mappedBy: 'user', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $extraSegments;

    public function __construct()
    {
        $this->timesRegisters = new ArrayCollection();
        $this->licenses = new ArrayCollection();
        $this->isVerified = true;
        $this->document = new ArrayCollection();
        $this->filterSelections = new ArrayCollection();
        $this->filterSelectionsSelected = new ArrayCollection();
        $this->assignedUsers = new ArrayCollection();
        $this->supervisors = new ArrayCollection();
        $this->resetPasswordRequests = new ArrayCollection();
        $this->filterOffices = new ArrayCollection();
        $this->userWorkSchedules = new ArrayCollection();
        $this->extraSegments = new ArrayCollection();
    }

    public function eraseCredentials(): void
    {
        // Si almacenas datos sensibles temporales en el usuario, límpialos aquí
        // $this->plainPassword = null;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(?string $role)
    {
        $this->role = $role;

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

    public function getOffice(): ?Office
    {
        return $this->office;
    }

    public function setOffice(?Office $office): static
    {
        $this->office = $office;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getFullName(): ?string
    {
        return $this->name . ' ' . $this->lastname1 . ' ' . $this->lastname2;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getLastname1(): ?string
    {
        return $this->lastname1;
    }

    public function setLastname1(?string $lastname1): self
    {
        $this->lastname1 = $lastname1;

        return $this;
    }

    public function getLastname2(): ?string
    {
        return $this->lastname2;
    }

    public function setLastname2(?string $lastname2): self
    {
        $this->lastname2 = $lastname2;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getDni(): ?string
    {
        return $this->dni;
    }

    public function setDni(?string $dni): self
    {
        $this->dni = $dni;

        return $this;
    }

    public function getVacationDays(): ?int
    {
        return $this->vacationDays;
    }

    public function setVacationDays(?int $vacationDays): self
    {
        $this->vacationDays = $vacationDays;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): self
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getFirstTime(): ?bool
    {
        return $this->firstTime;
    }

    public function setFirstTime(bool $firstTime): self
    {
        $this->firstTime = $firstTime;

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

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->modifiedAt = new \DateTimeImmutable();
        $this->isVerified = true; // se establece isVerified a true al crear
    }

    public function getModifiedAt(): ?\DateTimeImmutable
    {
        return $this->modifiedAt;
    }

    public function setModifiedAt(\DateTimeImmutable $modifiedAt): self
    {
        $this->modifiedAt = $modifiedAt;

        return $this;
    }

    #[ORM\PreUpdate]
    public function setModifiedAtValue(): void
    {
        $this->modifiedAt = new \DateTimeImmutable();
    }

    public function getAccounts(): ?Accounts
    {
        return $this->accounts;
    }

    public function setAccounts(?Accounts $accounts): static
    {
        $this->accounts = $accounts;

        return $this;
    }

    public function getTimesRegisters(): Collection
    {
        return $this->timesRegisters;
    }

    public function addTimesRegister(TimesRegister $timesRegister): static
    {
        if (!$this->timesRegisters->contains($timesRegister)) {
            $this->timesRegisters->add($timesRegister);
            $timesRegister->setUser($this);
        }

        return $this;
    }

    public function removeTimesRegister(TimesRegister $timesRegister): static
    {
        if ($this->timesRegisters->removeElement($timesRegister)) {
            if ($timesRegister->getUser() === $this) {
                $timesRegister->setUser(null);
            }
        }

        return $this;
    }

    public function getLicenses(): Collection
    {
        return $this->licenses;
    }

    public function addLicense(License $license): static
    {
        if (!$this->licenses->contains($license)) {
            $this->licenses->add($license);
            $license->setUser($this);
        }

        return $this;
    }

    public function removeLicense(License $license): static
    {
        if ($this->licenses->removeElement($license)) {
            if ($license->getUser() === $this) {
                $license->setUser(null);
            }
        }

        return $this;
    }

    public function getAssignedUsers(): Collection
    {
        return $this->assignedUsers;
    }

    public function addAssignedUser(AssignedUser $assignedUser): self
    {
        if (!$this->assignedUsers->contains($assignedUser)) {
            $this->assignedUsers[] = $assignedUser;
            $assignedUser->setSupervisor($this);
        }

        return $this;
    }

    public function removeAssignedUser(AssignedUser $assignedUser): self
    {
        if ($this->assignedUsers->contains($assignedUser)) {
            $this->assignedUsers->removeElement($assignedUser);
            // No es necesario establecer a null el supervisor, ya que se gestionará desde la entidad `AssignedUser`
        }

        return $this;
    }

    public function getSupervisors(): Collection
    {
        return $this->supervisors;
    }

    public function addSupervisor(AssignedUser $supervisor): self
    {
        if (!$this->supervisors->contains($supervisor)) {
            $this->supervisors[] = $supervisor;
            $supervisor->setUser($this);
        }

        return $this;
    }

    public function removeSupervisor(AssignedUser $supervisor): self
    {
        if ($this->supervisors->contains($supervisor)) {
            $this->supervisors->removeElement($supervisor);
            // No es necesario establecer a null el usuario, ya que se gestionará desde la entidad `AssignedUser`
        }

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role,
            'name' => $this->name,
            'dni' => $this->dni,
            'lastname1' => $this->lastname1,
            'lastname2' => $this->lastname2,
            'phone' => $this->phone,
            'isActive' => $this->isActive,
            'createdAt' => $this->createdAt ? $this->createdAt->format('Y-m-d H:i:s') : null,
            'modifiedAt' => $this->modifiedAt ? $this->modifiedAt->format('Y-m-d H:i:s') : null,
            'accounts' => $this->accounts ? $this->accounts->toArray() : null,
            'timesRegisters' => $this->timesRegisters->toArray(),
            'licenses' => $this->licenses->toArray(),
            'firstTime' => $this->firstTime
        ];
    }

    public function __toString()
    {
        return $this->getFullName() ?: 'Sin nombre';
    }

    public function getRoles(): array
    {
        $role = $this->getRole();
        return [$role ? $role : 'ROLE_USER'];
    }

    public function getDocument(): Collection
    {
        return $this->document;
    }

    // Métodos para agregar y eliminar documentos
    public function addDocument(Document $document): static
    {
        if (!$this->document->contains($document)) {
            $this->document->add($document);
            $document->setUsuario($this);
        }

        return $this;
    }

    public function removeDocument(Document $document): static
    {
        if ($this->document->removeElement($document)) {
            // Si el documento se elimina, también se debe eliminar la relación en el documento
            if ($document->getUsuario() === $this) {
                $document->setUsuario(null);
            }
        }

        return $this;
    }

    public function getFilterSelections(): Collection
    {
        return $this->filterSelections;
    }

    public function addFilterSelection(FilterSelection $filterSelection): self
    {
        if (!$this->filterSelections->contains($filterSelection)) {
            $this->filterSelections[] = $filterSelection;
            $filterSelection->setUser($this);
        }

        return $this;
    }

    public function removeFilterSelection(FilterSelection $filterSelection): self
    {
        if ($this->filterSelections->removeElement($filterSelection)) {
            // Establecer la propiedad de usuario a null
            if ($filterSelection->getUser() === $this) {
                $filterSelection->setUser(null);
            }
        }

        return $this;
    }

    public function getFilterSelectionsSelected(): Collection
    {
        return $this->filterSelectionsSelected;
    }

    public function addFilterSelectionsSelected(FilterSelection $filterSelection): self
    {
        if (!$this->filterSelectionsSelected->contains($filterSelection)) {
            $this->filterSelectionsSelected[] = $filterSelection;
            $filterSelection->setUserSelected($this);
        }

        return $this;
    }

    public function removeFilterSelectionsSelected(FilterSelection $filterSelection): self
    {
        if ($this->filterSelectionsSelected->removeElement($filterSelection)) {
            if ($filterSelection->getUserSelected() === $this) {
                $filterSelection->setUserSelected(null);
            }
        }

        return $this;
    }

    public function getResetPasswordRequests(): Collection
    {
        return $this->resetPasswordRequests;
    }

    public function getFilterOffice(): Collection
    {
        return $this->filterOffices;
    }

    public function addFilterOffice(FilterOffice $filterOffice): self
    {
        if (!$this->filterOffices->contains($filterOffice)) {
            $this->filterOffices[] = $filterOffice;
            $filterOffice->setCustomer($this);
        }

        return $this;
    }

    public function removefilterOffice(FilterOffice $filterOffice): self
    {
        if ($this->filterOffices->removeElement($filterOffice)) {
            if ($filterOffice->getCustomer() === $this) {
                $filterOffice->setCustomer(null);
            }
        }

        return $this;
    }

    public function getUserWorkSchedules(): Collection
    {
        return $this->userWorkSchedules;
    }

    public function addUserWorkSchedule(UserWorkSchedule $userWorkSchedule): self
    {
        if (!$this->userWorkSchedules->contains($userWorkSchedule)) {
            $this->userWorkSchedules[] = $userWorkSchedule;
            $userWorkSchedule->setUser($this);
        }

        return $this;
    }

    public function removeUserWorkSchedule(UserWorkSchedule $userWorkSchedule): self
    {
        if ($this->userWorkSchedules->removeElement($userWorkSchedule)) {
            // Set the owning side to null (unless already changed)
            if ($userWorkSchedule->getUser() === $this) {
                $userWorkSchedule->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|UserExtraSegment[]
     */
    public function getExtraSegments(): Collection
    {
        return $this->extraSegments;
    }

    public function addExtraSegment(UserExtraSegment $extraSegment): self
    {
        if (!$this->extraSegments->contains($extraSegment)) {
            $this->extraSegments[] = $extraSegment;
            $extraSegment->setUser($this);
        }

        return $this;
    }

    public function removeExtraSegment(UserExtraSegment $extraSegment): self
    {
        if ($this->extraSegments->removeElement($extraSegment)) {
            if ($extraSegment->getUser() === $this) {
                $extraSegment->setUser(null);
            }
        }

        return $this;
    }
}
