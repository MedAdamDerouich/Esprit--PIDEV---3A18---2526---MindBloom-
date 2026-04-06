<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'],    message: 'Cet email est déjà utilisé.')]
#[UniqueEntity(fields: ['username'], message: 'Ce nom d\'utilisateur est déjà pris.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    const ROLE_PATIENT     = 'PATIENT';
    const ROLE_PSYCHOLOGUE = 'PSYCHOLOGUE';
    const ROLE_ADMIN       = 'ADMIN';

    const STATUS_ACTIVE    = 'ACTIVE';
    const STATUS_SUSPENDED = 'SUSPENDED';
    const STATUS_INACTIVE  = 'INACTIVE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'full_name', type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2)]
    private ?string $fullName = null;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3)]
    private ?string $username = null;

    // Stored as single string enum in DB: PATIENT | PSYCHOLOGUE | ADMIN
    #[ORM\Column(type: 'string', length: 20, columnDefinition: "ENUM('PATIENT','PSYCHOLOGUE','ADMIN') DEFAULT 'PATIENT'")]
    private string $role = self::ROLE_PATIENT;

    #[ORM\Column(type: 'string', length: 255)]
    private string $password = '';

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(name: 'date_of_birth', type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateOfBirth = null;

    #[ORM\Column(name: 'profile_image', type: 'string', length: 255, nullable: true)]
    private ?string $profileImage = null;

    #[ORM\Column(type: 'string', length: 20, columnDefinition: "ENUM('ACTIVE','INACTIVE','SUSPENDED') DEFAULT 'ACTIVE'")]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(name: 'is_email_valid', type: 'boolean', nullable: true)]
    private bool $isEmailValid = false;

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

    // ── UserInterface ──────────────────────────────────────────────────────

    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * Returns Symfony-style roles from the single `role` DB field.
     * e.g. "ADMIN" → ["ROLE_ADMIN"]
     */
    public function getRoles(): array
    {
        return ['ROLE_' . $this->role, 'ROLE_USER'];
    }

    /**
     * Accepts Symfony-style roles array and stores the first matching role.
     * e.g. ["ROLE_PSYCHOLOGUE"] → sets $role = "PSYCHOLOGUE"
     */
    public function setRoles(array $roles): static
    {
        foreach ($roles as $r) {
            $plain = str_replace('ROLE_', '', $r);
            if (in_array($plain, [self::ROLE_PATIENT, self::ROLE_PSYCHOLOGUE, self::ROLE_ADMIN])) {
                $this->role = $plain;
                return $this;
            }
        }
        $this->role = self::ROLE_PATIENT;
        return $this;
    }

    public function eraseCredentials(): void {}

    // ── Getters & Setters ──────────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getFullName(): ?string { return $this->fullName; }
    public function setFullName(string $v): static { $this->fullName = $v; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $v): static { $this->email = $v; return $this; }

    public function getUsername(): ?string { return $this->username; }
    public function setUsername(string $v): static { $this->username = $v; return $this; }

    public function getRole(): string { return $this->role; }
    public function setRole(string $v): static { $this->role = $v; return $this; }

    public function getPassword(): string { return $this->password; }
    public function setPassword(string $v): static { $this->password = $v; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $v): static { $this->phone = $v; return $this; }

    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $v): static { $this->address = $v; return $this; }

    public function getDateOfBirth(): ?\DateTimeInterface { return $this->dateOfBirth; }
    public function setDateOfBirth(?\DateTimeInterface $v): static { $this->dateOfBirth = $v; return $this; }

    public function getProfileImage(): ?string { return $this->profileImage; }
    public function setProfileImage(?string $v): static { $this->profileImage = $v; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }

    public function isActive(): bool { return $this->status === self::STATUS_ACTIVE; }

    public function isEmailValid(): bool { return $this->isEmailValid; }
    public function setIsEmailValid(bool $v): static { $this->isEmailValid = $v; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
}
