<?php

namespace App\Entity;

use App\Repository\UserExtraSegmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserExtraSegmentRepository::class)]
class UserExtraSegment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type:"integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete:"CASCADE")]
    private User $user;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $date = null; 

    #[ORM\Column(type:"time", nullable: true)]
    private ?\DateTimeInterface $timeStart = null;

    #[ORM\Column(type:"time", nullable: true)]
    private ?\DateTimeInterface $timeEnd = null;

    #[ORM\Column(type:"string", length:50)]
    private string $type;

    #[ORM\Column(type:"text", nullable:true)]
    private ?string $description = null;

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
        $types = [
            3 => 'Hora extra',
            4 => 'Evento',
        ];

        return $types[$this->type] ?? 'Desconocido';
    }
}
