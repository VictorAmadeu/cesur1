<?php

namespace App\Entity;

use App\Repository\DocumentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Document
{
    /**
     * Identificador único del documento.
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Nombre del fichero (ej: justificante.pdf).
     */
    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * Ruta pública relativa donde se sirve el fichero.
     * Ejemplo: /uploads/documentos/empresa/ausencias/123/justificante.pdf
     */
    #[ORM\Column(length: 255)]
    private ?string $url = null;

    /**
     * Tipo de documento (categoría), por ejemplo "Ausencias".
     */
    #[ORM\ManyToOne(targetEntity: DocumentType::class, inversedBy: "documents")]
    #[ORM\JoinColumn(nullable: false)]
    private ?DocumentType $type = null;

    /**
     * Usuario que subió el documento.
     */
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'document')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    /**
     * Empresa a la que pertenece el documento.
     */
    #[ORM\ManyToOne(targetEntity: Companies::class, inversedBy: "document")]
    #[ORM\JoinColumn(nullable: false)]
    private ?Companies $company = null;

    /**
     * Fecha y hora en la que se creó el registro del documento.
     */
    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    /**
     * Fecha y hora de última visualización (si se quiere registrar).
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $viewedAt = null;

    /**
     * Licencia/ausencia a la que está asociado el documento.
     * Se elimina en cascada cuando se borra la licencia.
     */
    #[ORM\ManyToOne(targetEntity: License::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?License $license = null;

    // -----------------------------
    // Getters / Setters
    // -----------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Devuelve el nombre del archivo.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Establece el nombre del archivo.
     */
    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Devuelve la URL pública (relativa) del archivo.
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * Establece la URL pública (relativa) del archivo.
     */
    public function setUrl(string $url): static
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Devuelve el tipo de documento (DocumentType).
     */
    public function getType(): ?DocumentType
    {
        return $this->type;
    }

    /**
     * Establece el tipo de documento (DocumentType).
     */
    public function setType(DocumentType $type): static
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Devuelve el usuario que subió el documento.
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * Establece el usuario que sube el documento.
     */
    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Devuelve la empresa asociada al documento.
     */
    public function getCompany(): ?Companies
    {
        return $this->company;
    }

    /**
     * Establece la empresa asociada al documento.
     */
    public function setCompany(Companies $company): static
    {
        $this->company = $company;
        return $this;
    }

    /**
     * Devuelve la fecha de creación del registro.
     */
    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * Evento de Doctrine:
     * se ejecuta automáticamente antes de persistir el registro
     * y establece la fecha/hora actual como createdAt.
     */
    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }

    /**
     * Devuelve la fecha/hora de última visualización.
     */
    public function getViewedAt(): ?\DateTimeInterface
    {
        return $this->viewedAt;
    }

    /**
     * Establece la fecha/hora de última visualización.
     */
    public function setViewedAt(?\DateTimeInterface $viewedAt): static
    {
        $this->viewedAt = $viewedAt;
        return $this;
    }

    /**
     * Devuelve la licencia/ausencia asociada.
     */
    public function getLicense(): ?License
    {
        return $this->license;
    }

    /**
     * Asocia el documento a una licencia/ausencia concreta.
     */
    public function setLicense(?License $license): static
    {
        $this->license = $license;
        return $this;
    }

    /**
     * Representación en cadena del documento (útil en EasyAdmin, logs, etc.).
     * Si no hay nombre, devuelve "Documento" por defecto.
     */
    public function __toString(): string
    {
        return $this->name ?: 'Documento';
    }
}
