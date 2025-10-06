<?php

namespace App\Entity;

use App\Repository\DocumentRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Event\LifecycleEventArgs;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Document
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $url = null;

    #[ORM\ManyToOne(targetEntity: DocumentType::class, inversedBy: "documents")]
    #[ORM\JoinColumn(nullable: false)]
    private ?DocumentType $type = null;    

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'document')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Companies::class, inversedBy: "document")]
    #[ORM\JoinColumn(nullable: false)]
    private ?Companies $company = null;    

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $viewedAt = null;

    #[ORM\ManyToOne(targetEntity: License::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?License $license = null;

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

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getType(): ?DocumentType
    {
        return $this->type;
    }

    public function setType(DocumentType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getCompany(): ?Companies
    {
        return $this->company;
    }

    public function setCompany(Companies $company): static
    {
        $this->company = $company;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist] // Evento de pre-persistencia
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime(); // Establecer la fecha y hora actual
    }

    public function getViewedAt(): ?\DateTimeInterface
    {
        return $this->viewedAt;
    }

    public function setViewedAt(?\DateTimeInterface $viewedAt): static
    {
        $this->viewedAt = $viewedAt;

        return $this;
    }

    public function getLicense(): ?License
    {
        return $this->license;
    }

    public function setLicense(?License $license): static
    {
        $this->license = $license;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?: 'Documento'; // Esto devuelve el nombre del archivo o una cadena por defecto
    }
}
