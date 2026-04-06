<?php

namespace App\Entity;

use App\Repository\ResultatTestRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ResultatTestRepository::class)]
class ResultatTest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $score = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $etat = null;

    #[ORM\ManyToOne(inversedBy: 'resultats')]
    #[ORM\JoinColumn(referencedColumnName: 'id_test', nullable: false)]
    private ?Test $test = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $patient = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = $score;

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

    public function getEtat(): ?string
    {
        return $this->etat;
    }

    public function setEtat(?string $etat): static
    {
        $this->etat = $etat;

        return $this;
    }

    public function getTest(): ?Test
    {
        return $this->test;
    }

    public function setTest(?Test $test): static
    {
        $this->test = $test;

        return $this;
    }

    public function getPatient(): ?User
    {
        return $this->patient;
    }

    public function setPatient(?User $patient): static
    {
        $this->patient = $patient;

        return $this;
    }
}
