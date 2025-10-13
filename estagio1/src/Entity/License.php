<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\LicenseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

// 🎯 NUEVO: validación de Symfony para el callback de fechas/horas
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Entidad de Licencias/Ausencias.
 * - Se añade validación de dominio para evitar intervalos incoherentes (mismo día con hora fin < hora inicio).
 * - Se reescribe updateDays() con DateTimeImmutable + DatePeriod para evitar el warning "Undefined method modify".
 */

// 👉 NUEVO: habilita los lifecycle callbacks (prePersist, preUpdate) si no estaban funcionando.
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: LicenseRepository::class)]
// 👉 NUEVO: callback de validación a nivel de clase (Symfony)
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

    // -----------------------------
    // Getters / Setters autogenerados
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
     * 🔁 Recalcula $days contando solo días laborables (L-V).
     * Reescrito usando DateTimeImmutable + DatePeriod para evitar el warning de Intelephense.
     */
    public function updateDays(): void
    {
        if ($this->dateStart && $this->dateEnd) {
            // Normalizamos a DateTimeImmutable para poder usar modify() sin warnings
            $start = \DateTimeImmutable::createFromInterface($this->dateStart);
            $end   = \DateTimeImmutable::createFromInterface($this->dateEnd);

            // Periodo inclusivo: hasta $end (por eso sumamos +1 día en el límite)
            $endInclusive = $end->modify('+1 day');
            $period = new \DatePeriod($start, new \DateInterval('P1D'), $endInclusive);

            $days = 0;
            foreach ($period as $d) {
                $dayOfWeek = (int) $d->format('N'); // 1 (Lunes) ... 7 (Domingo)
                if ($dayOfWeek < 6) { // Solo lunes a viernes
                    $days++;
                }
            }

            $this->days = $days;
        }
    }

    public function getFechaHoraInicio(): ?string
    {
        if ($this->dateStart && $this->timeStart) {
            return $this->dateStart->format('d/m/Y') . ' ' . $this->timeStart->format('H:i');
        } elseif ($this->dateStart) {
            return $this->dateStart->format('d/m/Y');
        }
        return null;
    }

    public function getFechaHoraFin(): ?string
    {
        if ($this->dateEnd && $this->timeEnd) {
            return $this->dateEnd->format('d/m/Y') . ' ' . $this->timeEnd->format('H:i');
        } elseif ($this->dateEnd) {
            return $this->dateEnd->format('d/m/Y');
        }
        return null;
    }

    // ⚙️ Mantengo tus lifecycle hooks (y activo la clase con #[ORM\HasLifecycleCallbacks] arriba)
    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->updateDays();
    }

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

    // -------------------------------------------
    // ✅ NUEVO: Validación de dominio (Symfony)
    // -------------------------------------------
    /**
     * Si la ausencia empieza y termina el mismo día y la hora de fin es
     * anterior a la hora de inicio, generamos un error de validación.
     * Evita el 500 al aprobar al frenar datos incoherentes.
     */
    public function validateDates(ExecutionContextInterface $context): void
    {
        if ($this->dateStart instanceof \DateTimeInterface &&
            $this->dateEnd instanceof \DateTimeInterface &&
            $this->timeStart instanceof \DateTimeInterface &&
            $this->timeEnd instanceof \DateTimeInterface
        ) {
            $sameDay  = $this->dateStart->format('Y-m-d') === $this->dateEnd->format('Y-m-d');
            $startVal = (int) $this->timeStart->format('His');
            $endVal   = (int) $this->timeEnd->format('His');

            if ($sameDay && $endVal < $startVal) {
                $context->buildViolation(
                    'La hora de fin no puede ser anterior a la hora de inicio en el mismo día. ' .
                    'Si la ausencia cruza la medianoche, establezca la fecha de finalización al día siguiente o ajuste la hora de fin.'
                )
                ->atPath('timeEnd')
                ->addViolation();
            }
        }
    }
}
