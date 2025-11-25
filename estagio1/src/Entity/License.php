<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\LicenseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Entidad de Licencias/Ausencias.
 *
 * Incluye:
 * - Cálculo automático de días laborables entre fecha de inicio y fin.
 * - Validación de coherencia entre fechas y horas.
 * - Relación con documentos adjuntos.
 */
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: LicenseRepository::class)]
#[Assert\Callback('validateDates')]
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

    /**
     * 0 = En proceso, 1 = Aprobada, 2 = Desaprobada
     */
    #[ORM\Column(type: Types::INTEGER, options: ["default" => 0])]
    private ?int $status = 0;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column(type: Types::INTEGER, options: ["default" => 0])]
    private ?int $extraSegment = 0;

    /**
     * Documentos asociados a la licencia (justificantes, etc.).
     */
    #[ORM\OneToMany(mappedBy: 'license', targetEntity: Document::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $documents;

    public function __construct()
    {
        $this->documents = new ArrayCollection();
    }

    // -----------------------------
    // Getters / Setters principales
    // -----------------------------

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

    /**
     * Recalcula la propiedad $days contando solo días laborables (lunes a viernes)
     * entre dateStart y dateEnd (intervalo inclusivo).
     */
    public function updateDays(): void
    {
        if ($this->dateStart && $this->dateEnd) {
            // Usamos DateTimeImmutable para trabajar de forma segura con modify()
            $start = \DateTimeImmutable::createFromInterface($this->dateStart);
            $end   = \DateTimeImmutable::createFromInterface($this->dateEnd);

            // Periodo inclusivo: sumamos +1 día al límite
            $endInclusive = $end->modify('+1 day');
            $period = new \DatePeriod($start, new \DateInterval('P1D'), $endInclusive);

            $days = 0;
            foreach ($period as $d) {
                $dayOfWeek = (int) $d->format('N'); // 1 (lunes) ... 7 (domingo)
                if ($dayOfWeek < 6) {
                    $days++;
                }
            }

            $this->days = $days;
        }
    }

    /**
     * Devuelve la fecha/hora de inicio en formato amigable para mostrar en vistas.
     */
    public function getFechaHoraInicio(): ?string
    {
        if ($this->dateStart && $this->timeStart) {
            return $this->dateStart->format('d/m/Y') . ' ' . $this->timeStart->format('H:i');
        } elseif ($this->dateStart) {
            return $this->dateStart->format('d/m/Y');
        }
        return null;
    }

    /**
     * Devuelve la fecha/hora de fin en formato amigable para mostrar en vistas.
     */
    public function getFechaHoraFin(): ?string
    {
        if ($this->dateEnd && $this->timeEnd) {
            return $this->dateEnd->format('d/m/Y') . ' ' . $this->timeEnd->format('H:i');
        } elseif ($this->dateEnd) {
            return $this->dateEnd->format('d/m/Y');
        }
        return null;
    }

    /**
     * Lifecycle hook de Doctrine: antes de insertar, recalculamos los días.
     */
    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->updateDays();
    }

    /**
     * Lifecycle hook de Doctrine: antes de actualizar, recalculamos los días.
     */
    #[ORM\PreUpdate]
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

    /**
     * Colección de documentos asociados a la licencia.
     */
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
            if ($document->getLicense() === $this) {
                $document->setLicense(null);
            }
        }
        return $this;
    }

    /**
     * Convierte la licencia en array.
     *
     * @param bool $withDocuments Si es true, añade también un array de documentos asociados.
     */
    public function toArray(bool $withDocuments = false): array
    {
        $base = [
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

        if ($withDocuments) {
            $base['documents'] = array_map(function (Document $d) {
                return [
                    'id' => $d->getId(),
                    'name' => $d->getName(),
                    'url' => $d->getUrl(),
                    'createdAt' => $d->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'uploadedBy' => $d->getUser()->getEmail(),
                ];
            }, $this->documents->toArray());
        }

        return $base;
    }

    /**
     * Validación de dominio:
     * si la ausencia empieza y termina el mismo día y la hora de fin
     * es anterior a la hora de inicio, se añade una violación de validación.
     */
    public function validateDates(ExecutionContextInterface $context): void
    {
        if (
            $this->dateStart instanceof \DateTimeInterface &&
            $this->dateEnd instanceof \DateTimeInterface &&
            $this->timeStart instanceof \DateTimeInterface &&
            $this->timeEnd instanceof \DateTimeInterface
        ) {
            $sameDay  = $this->dateStart->format('Y-m-d') === $this->dateEnd->format('Y-m-d');
            $startVal = (int) $this->timeStart->format('His');
            $endVal   = (int) $this->timeEnd->format('His');

            if ($sameDay && $endVal < $startVal) {
                $context->buildViolation('La hora fin no puede ser anterior a la hora inicio.')
                    ->atPath('timeEnd')
                    ->addViolation();
            }
        }
    }
}
