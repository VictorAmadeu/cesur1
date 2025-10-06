<?php

namespace App\Entity;

use App\Entity\FilterOffice;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\OfficeRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity(repositoryClass: OfficeRepository::class)]
class Office
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;
    
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $province = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?int $code = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $longitude = null;

    #[ORM\OneToMany(mappedBy: 'office', targetEntity: User::class, orphanRemoval: true)]
    private Collection $users;      
    
    #[ORM\OneToMany(mappedBy: 'office', targetEntity: FilterOffice::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $filterOffices;

    #[ORM\ManyToOne(inversedBy: 'office', targetEntity: Companies::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $company;

    public function __construct()
    {
        $this->filterOffices = new ArrayCollection();
    }

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

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getProvince(): ?string
    {
        return $this->province;
    }

    public function setProvince(string $province): static
    {
        $this->province = $province;

        return $this;
    }

    public function getCode(): ?int
    {
        return $this->code;
    }

    public function setCode(int $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(string $country): static
    {
        $this->country = $country;

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

    public function __toString(): string
    {
        return $this->getName();
    }

    public function toArray(): array
    {
        $companyName = $this->company ? $this->company->getComercialName() : null;

        return [
            'id' => $this->id,
            'company' => $companyName,
            'name' => $this->name,
            'country' => $this->country,
            'province' => $this->province,
            'city' => $this->city,
            'code' => $this->code,
            'address' => $this->address,
        ];
    }
}
