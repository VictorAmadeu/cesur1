<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\LicenseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LicenseRepository::class)]
class License
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'licenses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $comments = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?int $typeId = null;

    #[ORM\Column(length: 4, nullable: true)]
    private ?int $days = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateStart = null;
    
    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateEnd = null;
    
    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $timeStart = null;
    
    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $timeEnd = null;    

    #[ORM\Column(type: Types::INTEGER, options: ["default" => 0])]
    private ?int $status = 0; // 0 = En Proceso, 1 = Aprobada, 2 = Desaprobada

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column(type: Types::INTEGER, options: ["default" => 0])]
    private ?int $extraSegment = 0;

    #[ORM\OneToMany(mappedBy: 'license', targetEntity: Document::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $documents;  


    public function __construct()
    {
        $this->documents = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getComments(): ?string
    {
        return $this->comments;
    }

    public function setComments(?string $comments): static
    {
        $this->comments = $comments;

        return $this;
    }

    public function getTypeId(): ?int
    {
        return $this->typeId;
    }

    public function setTypeId(?int $typeId): static
    {
        $this->typeId = $typeId;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function updateDays(): void
    {
        if ($this->dateStart && $this->dateEnd) {
            $start = clone $this->dateStart;
            $end = clone $this->dateEnd;
            $days = 0;

            while ($start <= $end) {
                $dayOfWeek = (int) $start->format('N'); // 1 (Lunes) ... 7 (Domingo)
                if ($dayOfWeek < 6) { // Solo lunes a viernes
                    $days++;
                }
                $start->modify('+1 day');
            }

            $this->days = $days;
        }
    }

    public function getFechaHoraInicio(): ?string
    {
        // Verificar si la fecha de inicio y la hora de inicio están disponibles
        if ($this->dateStart && $this->timeStart) {
            return $this->dateStart->format('d/m/Y') . ' ' . $this->timeStart->format('H:i');
        } elseif ($this->dateStart) {
            // Si solo existe la fecha de inicio
            return $this->dateStart->format('d/m/Y'); // Se agrega la hora como 00:00
        }

        return null; // Si no existe fecha ni hora de inicio
    }

    public function getFechaHoraFin(): ?string
    {
        // Verificar si la fecha de finalización y la hora de finalización están disponibles
        if ($this->dateEnd && $this->timeEnd) {
            return $this->dateEnd->format('d/m/Y') . ' ' . $this->timeEnd->format('H:i');
        } elseif ($this->dateEnd) {
            // Si solo existe la fecha de finalización
            return $this->dateEnd->format('d/m/Y'); // Se agrega la hora como 00:00
        }

        return null; // Si no existe fecha ni hora de finalización
    }

    #[ORM\PrePersist] // Este método se ejecuta antes de que la entidad sea persistida
    public function prePersist(): void
    {
        $this->updateDays();
    }

    #[ORM\PreUpdate] // Este método se ejecuta antes de que la entidad sea actualizada
    public function preUpdate(): void
    {
        $this->updateDays();
    }

    public function getDateStart(): ?\DateTimeInterface
    {
        return $this->dateStart;
    }
    
    public function setDateStart(\DateTimeInterface $dateStart): static
    {
        $this->dateStart = $dateStart;
        $this->updateDays();
    
        return $this;
    }    

    public function getDateEnd(): ?\DateTimeInterface
    {
        return $this->dateEnd;
    }

    public function setDateEnd(\DateTimeInterface $dateEnd): static
    {
        $this->dateEnd = $dateEnd;
        $this->updateDays();
    
        return $this;
    }

    public function getTimeStart(): ?\DateTimeInterface
    {
        return $this->timeStart;
    }

    public function setTimeStart(?\DateTimeInterface $timeStart): static
    {
        $this->timeStart = $timeStart;

        return $this;
    }

    public function getTimeEnd(): ?\DateTimeInterface
    {
        return $this->timeEnd;
    }

    public function setTimeEnd(?\DateTimeInterface $timeEnd): static
    {
        $this->timeEnd = $timeEnd;

        return $this;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(int $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getDays(): ?int
    {
        return $this->days;
    }

    public function setDays(int $days): static
    {
        $this->days = $days;

        return $this;
    }

    public function isIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getExtraSegment(): ?int
    {
        return $this->extraSegment;
    }

    public function setExtraSegment(int $extraSegment): static
    {
        $this->extraSegment = $extraSegment;

        return $this;
    }

    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(Document $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setLicense($this);
        }

        return $this;
    }

    public function removeDocument(Document $document): static
    {
        if ($this->documents->removeElement($document)) {
            // Set license to null on document before removal
            if ($document->getLicense() === $this) {
                $document->setLicense(null);
            }
        }

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'comments' => $this->comments,
            'type' => $this->type,
            'typeId' => $this->typeId,
            'dateStart' => $this->dateStart ? $this->dateStart->format('Y-m-d') : null,
            'dateEnd' => $this->dateEnd ? $this->dateEnd->format('Y-m-d') : null,
            'timeStart' => $this->timeStart ? $this->timeStart->format('H:i') : null,
            'timeEnd' => $this->timeEnd ? $this->timeEnd->format('H:i') : null,
            'status' => $this->status,
            'isActive' => $this->isActive,
            'extraSegment' => $this->extraSegment,
        ];
    }    
}
