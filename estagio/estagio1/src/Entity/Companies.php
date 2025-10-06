<?php

namespace App\Entity;

use App\Repository\CompaniesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompaniesRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Companies
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $name = null;

    #[ORM\Column(length: 15)]
    private ?string $NIF = null;

    #[ORM\Column(length: 150)]
    private ?string $comercialName = null;

    #[ORM\Column(length: 255, nullable: false)]
    private ?string $address = null;

    #[ORM\Column(length: 150, nullable: false)]
    private ?string $town = null;

    #[ORM\Column(length: 5, nullable: false)]
    private ?string $CP = null;

    #[ORM\Column(length: 150, nullable: false)]
    private ?string $province = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 10)]
    private ?string $phone = null;

    #[ORM\Column(length: 255)]
    private ?string $logo = null;

    #[ORM\Column]
    private ?bool $active = null;

    #[ORM\Column]
    private ?bool $remove = null;

    #[ORM\Column]
    private ?bool $setManual = true;

    #[ORM\Column]
    private ?bool $setTimeInDistance = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private ?bool $allowDeviceRegistration = false;

    #[ORM\OneToMany(mappedBy: 'company', targetEntity: Device::class, orphanRemoval: true)]
    private Collection $devices;

    #[ORM\Column]
    #[ORM\JoinColumn(nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[ORM\JoinColumn(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'company', targetEntity: Accounts::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $accounts;

    #[ORM\OneToMany(mappedBy: 'company', targetEntity: AccessLog::class, orphanRemoval: true)]
    private Collection $accessLogs;

    #[ORM\Column(length: 255)]
    private ?string $logoAPP = null;

    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'company', cascade: ['remove'], orphanRemoval: true)]
    private Collection $users;

    #[ORM\OneToMany(mappedBy: 'company', targetEntity: Office::class, orphanRemoval: true)]
    private Collection $office;    

    #[ORM\OneToMany(mappedBy: 'company', targetEntity: Document::class)]
    private Collection $document;

    #[ORM\OneToMany(mappedBy: 'company', targetEntity: DocumentType::class)]
    private Collection $documentType;

    #[ORM\OneToMany(mappedBy: 'company', targetEntity: FilterSelection::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $filterSelections;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private ?bool $allowProjects = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private ?bool $allowDocument = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private ?bool $allowWorkSchedule = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private ?bool $applyAssignedSchedule = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private ?bool $allowSupervisorCreate = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private ?bool $allowSupervisorEdit = false;

    #[ORM\OneToMany(mappedBy: 'company', targetEntity: WorkSchedule::class, orphanRemoval: true)]
    private Collection $workSchedule;    

    public function __construct()
    {
        $this->invoicesTemplate = new ArrayCollection();
        $this->backups = new ArrayCollection();
        $this->emailSends = new ArrayCollection();
        $this->emailReceiveds = new ArrayCollection();
        $this->accessLogs = new ArrayCollection();
        $this->users = new ArrayCollection();
        $this->devices = new ArrayCollection();
        $this->filterSelections = new ArrayCollection();
        $this->workSchedule = new ArrayCollection();
    }

    // /**
    //  * @return Collection<User>
    //  */
    // public function getUsers(): Collection
    // {
    //     return $this->users;
    // }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function __toString(){
        return $this->getComercialName();
    }

    public function getNIF(): ?string
    {
        return $this->NIF;
    }

    public function setNIF(string $NIF): static
    {
        $this->NIF = $NIF;

        return $this;
    }

    public function getComercialName(): ?string
    {
        return $this->comercialName;
    }

    public function setComercialName(string $comercialName): static
    {
        $this->comercialName = $comercialName;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): self
    {
        $this->address = $address;

        return $this;
    }

    public function getTown(): ?string
    {
        return $this->town;
    }

    public function setTown(?string $town): self
    {
        $this->town = $town;

        return $this;
    }

    public function getCP(): ?string
    {
        return $this->CP;
    }

    public function setCP(?string $CP): self
    {
        $this->CP = $CP;

        return $this;
    }

    public function getProvince(): ?string
    {
        return $this->province;
    }

    public function setProvince(?string $province): self
    {
        $this->province = $province;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(string $logo): static
    {
        $this->logo = $logo;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getSetManual(): ?bool
    {
        return $this->setManual;
    }

    public function setSetManual(bool $setManual): static
    {
        $this->setManual = $setManual;

        return $this;
    }

    public function getSetTimeInDistance(): ?bool
    {
        return $this->setTimeInDistance;
    }

    public function setSetTimeInDistance(bool $setTimeInDistance): static
    {
        $this->setTimeInDistance = $setTimeInDistance;

        return $this;
    }

    public function getAllowDeviceRegistration(): ?bool
    {
        return $this->allowDeviceRegistration;
    }

    public function setAllowDeviceRegistration(bool $allowDeviceRegistration): static
    {
        $this->allowDeviceRegistration = $allowDeviceRegistration;

        return $this;
    }
    
    /**
     * @return Collection<int, Device>
     */
    public function getDevices(): Collection
    {
        return $this->devices;
    }

    public function addDevice(Device $device): static
    {
        if (!$this->devices->contains($device)) {
            $this->devices->add($device);
            $device->setCompany($this);
        }

        return $this;
    }

    public function removeDevice(Device $device): static
    {
        if ($this->devices->removeElement($device)) {
            if ($device->getCompany() === $this) {
                $device->setCompany(null);
            }
        }

        return $this;
    }

    public function isRemove(): ?bool
    {
        return $this->remove;
    }

    public function setRemove(bool $remove): static
    {
        $this->remove = $remove;

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

    public function getAccounts(): ?Accounts
    {
        return $this->accounts;
    }

    public function setAccounts(?Accounts $accounts): static
    {
        $this->accounts = $accounts;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        if(empty($this->getLogoAPP())){ $this->setLogoAPP('defaultLogo.png'); }
        if(empty($this->getLogo())){ $this->setLogo('defaultLogo.png'); }
    }

    #[ORM\PreUpdate]
    public function setModifiedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @return Collection<int, AccessLog>
     */
    public function getAccessLogs(): Collection
    {
        return $this->accessLogs;
    }

    public function addAccessLog(AccessLog $accessLog): static
    {
        if (!$this->accessLogs->contains($accessLog)) {
            $this->accessLogs->add($accessLog);
            $accessLog->setCompany($this);
        }

        return $this;
    }

    public function removeAccessLog(AccessLog $accessLog): static
    {
        if ($this->accessLogs->removeElement($accessLog)) {
            // set the owning side to null (unless already changed)
            if ($accessLog->getCompany() === $this) {
                $accessLog->setCompany(null);
            }
        }

        return $this;
    }

    public function getLogoAPP(): ?string
    {
        return $this->logoAPP;
    }

    public function setLogoAPP(string $logoAPP): static
    {
        $this->logoAPP = $logoAPP;

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
            $filterSelection->setCustomer($this);
        }

        return $this;
    }

    public function removeFilterSelection(FilterSelection $filterSelection): self
    {
        if ($this->filterSelections->removeElement($filterSelection)) {
            if ($filterSelection->getCustomer() === $this) {
                $filterSelection->setCustomer(null);
            }
        }

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

    public function getApplyAssignedSchedule(): ?bool
    {
        return $this->applyAssignedSchedule;
    }

    public function setApplyAssignedSchedule(bool $applyAssignedSchedule): static
    {
        $this->applyAssignedSchedule = $applyAssignedSchedule;

        return $this;
    }

    public function getAllowSupervisorCreate(): ?bool
    {
        return $this->allowSupervisorCreate;
    }

    public function setAllowSupervisorCreate(bool $allowSupervisorCreate): static
    {
        $this->allowSupervisorCreate = $allowSupervisorCreate;

        return $this;
    }

    public function getAllowSupervisorEdit(): ?bool
    {
        return $this->allowSupervisorEdit;
    }

    public function setAllowSupervisorEdit(bool $allowSupervisorEdit): static
    {
        $this->allowSupervisorEdit = $allowSupervisorEdit;

        return $this;
    }

    /**
     * @return Collection<int, WorkSchedule>
     */
    public function getWorkSchedule(): Collection
    {
        return $this->workSchedule;
    }

    public function addWorkSchedule(WorkSchedule $workSchedule): static
    {
        if (!$this->workSchedule->contains($workSchedule)) {
            $this->workSchedule->add($workSchedule);
            $workSchedule->setAccounts($this);
        }

        return $this;
    }

    public function removeWorkSchedule(WorkSchedule $workSchedule): static
    {
        if ($this->workSchedule->removeElement($workSchedule)) {
            // set the owning side to null (unless already changed)
            if ($workSchedule->getAccounts() === $this) {
                $workSchedule->setAccounts(null);
            }
        }

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'NIF' => $this->NIF,
            'comercialName' => $this->comercialName,
            'address' => $this->address ? $this->address : null,
            'town' => $this->town ? $this->town : null,
            'CP' => $this->CP,
            'province' => $this->province,
            'email' => $this->email,
            'phone' => $this->phone,
            'logo' => $this->logo,
            'active' => $this->active,
            'remove' => $this->remove,
            'setManual' => $this->setManual,
            'setTimeInDistance' => $this->setTimeInDistance,
            'allowDeviceRegistration' => $this->allowDeviceRegistration
        ];
    }
}
