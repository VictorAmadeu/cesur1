<?php

namespace App\Entity;

use App\Repository\UserExtraSegmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserExtraSegmentRepository::class)]
class UserExtraSegment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $timeStart = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $timeEnd = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $type = '3'; // valor por defecto seguro

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    // ----------------
    // Getters/Setters
    // ----------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(?\DateTimeInterface $date): self
    {
        $this->date = $date;
        return $this;
    }

    public function getTimeStart(): ?\DateTimeInterface
    {
        return $this->timeStart;
    }

    public function setTimeStart(?\DateTimeInterface $timeStart): self
    {
        $this->timeStart = $timeStart;
        return $this;
    }

    public function getTimeEnd(): ?\DateTimeInterface
    {
        return $this->timeEnd;
    }

    public function setTimeEnd(?\DateTimeInterface $timeEnd): self
    {
        $this->timeEnd = $timeEnd;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getTypeLabel(): string
    {
        // Si $type viene como '3'/'4' en string, lo forzamos a int para mapear.
        $types = [
            3 => 'Hora extra',
            4 => 'Evento',
        ];

        return $types[(int) $this->type] ?? 'Desconocido';
    }

    // --------------------------------------------------------------------
    // Alias de compatibilidad: algunas capas pueden invocar setDateStart()
    // o setDateEnd() esperando un DateTime combinado (fecha + hora).
    // Estos métodos reparten la info entre $date, $timeStart y $timeEnd.
    // --------------------------------------------------------------------

    public function setDateStart(?\DateTimeInterface $dateTime): self
    {
        if ($dateTime === null) {
            $this->timeStart = null;
            // No tocamos $this->date si no viene nada.
            return $this;
        }

        // Guardamos la parte de la fecha (00:00) en $date
        $this->date = \DateTimeImmutable::createFromInterface($dateTime)->setTime(0, 0);

        // Guardamos la hora en timeStart (Doctrine TIME ignorará la fecha)
        $this->timeStart = $dateTime;
        return $this;
    }

    public function getDateStart(): ?\DateTimeInterface
    {
        // Si tuvieras que reconstruir un solo DateTime combinando $date + $timeStart:
        if ($this->date === null || $this->timeStart === null) {
            return null;
        }

        $d = \DateTimeImmutable::createFromInterface($this->date);
        return $d->setTime(
            (int)$this->timeStart->format('H'),
            (int)$this->timeStart->format('i'),
            (int)$this->timeStart->format('s')
        );
    }

    public function setDateEnd(?\DateTimeInterface $dateTime): self
    {
        if ($dateTime === null) {
            $this->timeEnd = null;
            return $this;
        }

        // La fecha normalmente ya estará en $date por setDateStart(); si no, la fijamos.
        if ($this->date === null) {
            $this->date = \DateTimeImmutable::createFromInterface($dateTime)->setTime(0, 0);
        }

        $this->timeEnd = $dateTime;
        return $this;
    }

    public function getDateEnd(): ?\DateTimeInterface
    {
        if ($this->date === null || $this->timeEnd === null) {
            return null;
        }

        $d = \DateTimeImmutable::createFromInterface($this->date);
        return $d->setTime(
            (int)$this->timeEnd->format('H'),
            (int)$this->timeEnd->format('i'),
            (int)$this->timeEnd->format('s')
        );
    }
}