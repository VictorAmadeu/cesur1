<?php

namespace App\Entity;

use App\Repository\AssignedUserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Entidad intermedia que representa la relación Supervisor ↔ Trabajador.
 *
 * Cada fila indica que un usuario (user) tiene asignado un supervisor concreto.
 * La combinación (supervisor, user) debe ser única para evitar duplicados.
 */
#[ORM\Entity(repositoryClass: AssignedUserRepository::class)]
#[ORM\Table(name: 'assigned_user', uniqueConstraints: [
    // Restricción de unicidad a nivel de base de datos (SQL).
    new ORM\UniqueConstraint(name: 'unique_assignment', columns: ['supervisor_id', 'user_id'])
])]
#[UniqueEntity(
    // Restricción de unicidad a nivel de validación Symfony (antes de llegar a la BD).
    fields: ['supervisor', 'user'],
    message: 'Este supervisor ya está asignado a este trabajador.'
)]
#[ORM\HasLifecycleCallbacks]
class AssignedUser
{
    /** Identificador autoincremental de la relación. */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Supervisor asignado al trabajador.
     * ManyToOne → un supervisor puede tener muchas filas de AssignedUser.
     */
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'assignedUsers')]
    #[ORM\JoinColumn(name: 'supervisor_id', referencedColumnName: 'id', nullable: false)]
    private ?User $supervisor = null;

    /**
     * Usuario supervisado.
     * ManyToOne → un usuario puede tener distintos supervisores a lo largo del tiempo,
     * pero la combinación supervisor-usuario es única en esta tabla.
     */
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'supervisors')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private ?User $user = null;

    /**
     * Fecha/hora en la que se creó la asignación.
     */
    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $assignedAt = null;

    /**
     * Constructor.
     *
     * Permite inyectar supervisor, usuario y fecha de asignación.
     * Si no se pasa fecha, se usa la fecha/hora actual.
     */
    public function __construct(?User $supervisor = null, ?User $user = null, ?\DateTimeInterface $assignedAt = null)
    {
        $this->supervisor = $supervisor;
        $this->user = $user;
        $this->assignedAt = $assignedAt ?? new \DateTimeImmutable();
    }

    /** Devuelve el identificador de la fila. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Devuelve el supervisor asignado. */
    public function getSupervisor(): ?User
    {
        return $this->supervisor;
    }

    /**
     * Establece el supervisor de la relación.
     * Devuelve $this para permitir encadenar llamadas.
     */
    public function setSupervisor(?User $supervisor): self
    {
        $this->supervisor = $supervisor;

        return $this;
    }

    /** Devuelve el usuario supervisado. */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * Establece el usuario supervisado.
     * Devuelve $this para permitir encadenar llamadas.
     */
    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /** Devuelve la fecha/hora en la que se creó la asignación. */
    public function getAssignedAt(): ?\DateTimeInterface
    {
        return $this->assignedAt;
    }

    /**
     * Establece la fecha/hora de asignación.
     * Útil si se quiere fijar una fecha concreta (importación, migraciones, etc.).
     */
    public function setAssignedAt(\DateTimeInterface $assignedAt): self
    {
        $this->assignedAt = $assignedAt;

        return $this;
    }

    /**
     * Representación en texto de la entidad.
     *
     * Se usa, por ejemplo, en selectores de EasyAdmin.
     * Si por algún motivo el usuario aún es null, se devuelve cadena vacía
     * para evitar errores del tipo "Call to a member function on null".
     */
    public function __toString(): string
    {
        return $this->user ? $this->user->getFullName() : '';
    }
}
