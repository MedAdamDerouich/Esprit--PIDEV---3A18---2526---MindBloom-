<?php

namespace App\Entity;

use App\Repository\EventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: 'evenement')]
#[ORM\HasLifecycleCallbacks]
class Event
{
    const STATUT_ACTIF = 'actif';
    const STATUT_ANNULE = 'annulé';
    const STATUT_TERMINE = 'terminé';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_evenement', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $titre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'date_debut', type: 'datetime')]
    #[Assert\NotBlank(message: 'La date de début est obligatoire')]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(name: 'date_fin', type: 'datetime', nullable: true)]
    #[Assert\GreaterThan(
        propertyPath: 'dateDebut',
        message: 'La date de fin doit être postérieure à la date de début'
    )]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $lieu = null;

    #[ORM\Column(name: 'capacite_max', type: 'integer', nullable: true)]
    #[Assert\Positive(message: 'La capacité maximale doit être positive')]
    private ?int $capaciteMax = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $organisateur = null;

    #[ORM\Column(name: 'date_creation', type: 'datetime')]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\Choice(
        choices: [self::STATUT_ACTIF, self::STATUT_ANNULE, self::STATUT_TERMINE],
        message: 'Le statut doit être: actif, annulé ou terminé'
    )]
    private string $statut = self::STATUT_ACTIF;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Assert\PositiveOrZero(message: 'Le prix doit être positif ou zéro')]
    private ?float $prix = null;

    #[ORM\OneToMany(mappedBy: 'evenement', targetEntity: Participation::class, cascade: ['remove'])]
    private Collection $participations;

    public function __construct()
    {
        $this->participations = new ArrayCollection();
        $this->dateCreation = new \DateTime();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if ($this->dateCreation === null) {
            $this->dateCreation = new \DateTime();
        }
    }

    // ── Getters & Setters ──────────────────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin;
        return $this;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function setLieu(?string $lieu): static
    {
        $this->lieu = $lieu;
        return $this;
    }

    public function getCapaciteMax(): ?int
    {
        return $this->capaciteMax;
    }

    public function setCapaciteMax(?int $capaciteMax): static
    {
        $this->capaciteMax = $capaciteMax;
        return $this;
    }

    public function getOrganisateur(): ?string
    {
        return $this->organisateur;
    }

    public function setOrganisateur(?string $organisateur): static
    {
        $this->organisateur = $organisateur;
        return $this;
    }

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;
        return $this;
    }

    public function getPrix(): ?float
    {
        return $this->prix;
    }

    public function setPrix(?float $prix): static
    {
        $this->prix = $prix;
        return $this;
    }

    /**
     * @return Collection<int, Participation>
     */
    public function getParticipations(): Collection
    {
        return $this->participations;
    }

    public function addParticipation(Participation $participation): static
    {
        if (!$this->participations->contains($participation)) {
            $this->participations->add($participation);
            $participation->setEvenement($this);
        }
        return $this;
    }

    public function removeParticipation(Participation $participation): static
    {
        if ($this->participations->removeElement($participation)) {
            if ($participation->getEvenement() === $this) {
                $participation->setEvenement(null);
            }
        }
        return $this;
    }

    // ── Helper Methods ──────────────────────────────────────────────────

    public function isGratuit(): bool
    {
        return $this->prix === null || $this->prix == 0.0;
    }

    public function countParticipants(): int
    {
        return $this->participations->count();
    }

    public function isPlacesDisponibles(): bool
    {
        if ($this->capaciteMax === null) {
            return true; // Pas de limite
        }
        return $this->countParticipants() < $this->capaciteMax;
    }

    public function getPlacesRestantes(): ?int
    {
        if ($this->capaciteMax === null) {
            return null; // Illimité
        }
        return max(0, $this->capaciteMax - $this->countParticipants());
    }

    public function isActif(): bool
    {
        return $this->statut === self::STATUT_ACTIF;
    }

    public function __toString(): string
    {
        return $this->titre ?? 'Événement #' . $this->id;
    }
}
