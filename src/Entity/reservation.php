<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Status; // <- enum status dans Entity

#[ORM\Entity]
#[ORM\Table(name: "reservation")]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $idReservation = null;

    #[ORM\Column(type: "string", enumType: Status::class)]
    private ?status $status = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(name: "id_creneau", referencedColumnName: "id", unique: true)]
    private ?Creneau $creneau = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: "id_patient", referencedColumnName: "id")]
    private ?User $patient = null;

    #[ORM\Column(type: "boolean")]
    private bool $reminderSent = false;

    public function getIdReservation(): ?int
    {
        return $this->idReservation;
    }

    public function setIdReservation(int $idReservation): self
    {
        $this->idReservation = $idReservation;
        return $this;
    }

    public function getStatus(): ?status
    {
        return $this->status;
    }

    public function setStatus(status $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getCreneau(): ?Creneau
    {
        return $this->creneau;
    }

    public function setCreneau(?Creneau $creneau): self
    {
        $this->creneau = $creneau;
        return $this;
    }

    public function getPatient(): ?User
    {
        return $this->patient;
    }

    public function setPatient(?User $patient): self
    {
        $this->patient = $patient;
        return $this;
    }

    public function isReminderSent(): bool
    {
        return $this->reminderSent;
    }

    public function setReminderSent(bool $reminderSent): self
    {
        $this->reminderSent = $reminderSent;
        return $this;
    }

    public function __toString(): string
    {
        return "Reservation{idReservation={$this->idReservation}, status={$this->status?->value}}";
    }
}