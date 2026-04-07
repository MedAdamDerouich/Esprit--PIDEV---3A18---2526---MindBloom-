<?php

namespace App\Entity;

use App\Repository\WalletRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WalletRepository::class)]
#[ORM\Table(name: 'wallets')]
#[ORM\HasLifecycleCallbacks]
class Wallet
{
    const STATUS_ACTIVE   = 'ACTIVE';
    const STATUS_INACTIVE = 'INACTIVE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $balance = '0.0';

    #[ORM\Column(name: 'last_recharge_date', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastRechargeDate = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(name: 'created_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getBalance(): float { return (float) $this->balance; }
    public function setBalance(float $balance): static { $this->balance = $balance; return $this; }

    public function getLastRechargeDate(): ?\DateTimeInterface { return $this->lastRechargeDate; }
    public function setLastRechargeDate(?\DateTimeInterface $date): static { $this->lastRechargeDate = $date; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function isActive(): bool { return $this->status === self::STATUS_ACTIVE; }

    public function toggleStatus(): void
    {
        $this->status = $this->isActive() ? self::STATUS_INACTIVE : self::STATUS_ACTIVE;
    }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
}
