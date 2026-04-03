<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: "cabinet")]
class Cabinet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $idCabinet = null;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $nomCabinet = null;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $adresse = null;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $specialite = null;

    #[ORM\Column(type: "string", length: 50)]
    private ?string $telephone = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $description = null;

    // Psychologue du cabinet
    #[ORM\OneToOne]
    #[ORM\JoinColumn(name: "id_psychologue", referencedColumnName: "id", unique: true)]
    private ?User $psychologue = null;

    // Liste des créneaux
    #[ORM\OneToMany(mappedBy: "cabinet", targetEntity: Creneau::class)]
    private Collection $creneaux;

    public function __construct()
    {
        $this->creneaux = new ArrayCollection();
    }

    // Getters & Setters

    public function getIdCabinet(): ?int
    {
        return $this->idCabinet;
    }

    public function setIdCabinet(int $idCabinet): self
    {
        $this->idCabinet = $idCabinet;
        return $this;
    }

    public function getNomCabinet(): ?string
    {
        return $this->nomCabinet;
    }

    public function setNomCabinet(string $nomCabinet): self
    {
        $this->nomCabinet = $nomCabinet;
        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(string $adresse): self
    {
        $this->adresse = $adresse;
        return $this;
    }

    public function getSpecialite(): ?string
    {
        return $this->specialite;
    }

    public function setSpecialite(string $specialite): self
    {
        $this->specialite = $specialite;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(string $telephone): self
    {
        $this->telephone = $telephone;
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

    public function getPsychologue(): ?User
    {
        return $this->psychologue;
    }

    public function setPsychologue(?User $psychologue): self
    {
        $this->psychologue = $psychologue;
        return $this;
    }

    public function getCreneaux(): Collection
    {
        return $this->creneaux;
    }

    public function addCreneau(Creneau $creneau): self
    {
        if (!$this->creneaux->contains($creneau)) {
            $this->creneaux[] = $creneau;
            $creneau->setCabinet($this);
        }
        return $this;
    }

    public function removeCreneau(Creneau $creneau): self
    {
        if ($this->creneaux->removeElement($creneau)) {
            if ($creneau->getCabinet() === $this) {
                $creneau->setCabinet(null);
            }
        }
        return $this;
    }
}