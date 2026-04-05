<?php

namespace App\Entity;

use App\Repository\ParticipationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: ParticipationRepository::class)]
#[ORM\Table(name: 'participation')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(
    fields: ['evenement', 'user'],
    message: 'Vous êtes déjà inscrit à cet événement'
)]
class Participation
{
    const STATUT_INSCRIT = 'inscrit';
    const STATUT_CONFIRME = 'confirmé';
    const STATUT_EN_ATTENTE = 'en_attente';
    const STATUT_ANNULE = 'annulé';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_participation', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'participations')]
    #[ORM\JoinColumn(name: 'id_evenement', referencedColumnName: 'id_evenement', nullable: false, onDelete: 'CASCADE')]
    private ?Event $evenement = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_utilisateur', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(name: 'date_participation', type: 'datetime')]
    private ?\DateTimeInterface $dateInscription = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $statut = self::STATUT_INSCRIT;

    // Champ qr_code désactivé car non présent dans la base
    // #[ORM\Column(name: 'qr_code', type: 'string', length: 255, nullable: true)]
    private ?string $qrCode = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    public function __construct()
    {
        $this->dateInscription = new \DateTime();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if ($this->dateInscription === null) {
            $this->dateInscription = new \DateTime();
        }
    }

    // ── Getters & Setters ──────────────────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvenement(): ?Event
    {
        return $this->evenement;
    }

    public function setEvenement(?Event $evenement): static
    {
        $this->evenement = $evenement;
        return $this;
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

    public function getDateInscription(): ?\DateTimeInterface
    {
        return $this->dateInscription;
    }

    public function setDateInscription(\DateTimeInterface $dateInscription): static
    {
        $this->dateInscription = $dateInscription;
        return $this;
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

    public function getQrCode(): ?string
    {
        return $this->qrCode;
    }

    public function setQrCode(?string $qrCode): static
    {
        $this->qrCode = $qrCode;
        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;
        return $this;
    }

    // ── Helper Methods ──────────────────────────────────────────────────

    public function isConfirme(): bool
    {
        return $this->statut === self::STATUT_CONFIRME;
    }

    public function isAnnule(): bool
    {
        return $this->statut === self::STATUT_ANNULE;
    }
}
