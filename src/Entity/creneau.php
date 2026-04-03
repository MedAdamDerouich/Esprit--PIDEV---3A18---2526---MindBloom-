<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\joursemaine; // <- Import correct car enum est dans Entity



#[ORM\Entity]
#[ORM\Table(name: "creneau")]
class Creneau
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

#[ORM\Column(type: "string", enumType: JourSemaine::class)]
private ?JourSemaine $jour = null;

    #[ORM\Column(type: "date")]
    private ?\DateTimeInterface $dateCreneau = null;

    #[ORM\Column(type: "time")]
    private ?\DateTimeInterface $heureDebut = null;

    #[ORM\Column(type: "integer")]
    private int $dureeMinutes = 0;

    #[ORM\Column(type: "boolean")]
    private bool $disponible = true;

    #[ORM\ManyToOne(targetEntity: Cabinet::class, inversedBy: "creneaux")]
    #[ORM\JoinColumn(name: "id_cabinet", referencedColumnName: "idCabinet", nullable: false)]
    private ?Cabinet $cabinet = null;

    #[ORM\OneToOne(mappedBy: "creneau", targetEntity: Reservation::class)]
    private ?Reservation $reservation = null;

    public function __construct()
    {
    }

    // Getters & Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJour(): ?JourSemaine
    {
        return $this->jour;
    }

    public function setJour(JourSemaine $jour): self
    {
        $this->jour = $jour;
        return $this;
    }

    public function getDateCreneau(): ?\DateTimeInterface
    {
        return $this->dateCreneau;
    }

    public function setDateCreneau(\DateTimeInterface $dateCreneau): self
    {
        $this->dateCreneau = $dateCreneau;
        return $this;
    }

    public function getHeureDebut(): ?\DateTimeInterface
    {
        return $this->heureDebut;
    }

    public function setHeureDebut(\DateTimeInterface $heureDebut): self
    {
        $this->heureDebut = $heureDebut;
        return $this;
    }

    public function getDureeMinutes(): int
    {
        return $this->dureeMinutes;
    }

    public function setDureeMinutes(int $dureeMinutes): self
    {
        $this->dureeMinutes = $dureeMinutes;
        return $this;
    }

    public function isDisponible(): bool
    {
        return $this->disponible;
    }

    public function setDisponible(bool $disponible): self
    {
        $this->disponible = $disponible;
        return $this;
    }

    public function getCabinet(): ?Cabinet
    {
        return $this->cabinet;
    }

    public function setCabinet(?Cabinet $cabinet): self
    {
        $this->cabinet = $cabinet;
        return $this;
    }

    public function getReservation(): ?Reservation
    {
        return $this->reservation;
    }

    public function setReservation(?Reservation $reservation): self
    {
        $this->reservation = $reservation;
        return $this;
    }

    public function __toString(): string
    {
        return sprintf(
            "Creneau{id=%d, jour=%s, heureDebut=%s, disponible=%s}",
            $this->id,
            $this->jour?->value,
            $this->heureDebut?->format('H:i:s'),
            $this->disponible ? 'true' : 'false'
        );
    }
}