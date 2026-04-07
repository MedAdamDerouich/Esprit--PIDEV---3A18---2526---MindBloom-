<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\UserRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AdminExtension extends AbstractExtension
{
    public function __construct(private UserRepository $userRepository) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('pending_psychologues_count', [$this, 'getPendingPsychologuesCount']),
        ];
    }

    /**
     * Returns the number of psychologue accounts awaiting admin approval.
     */
    public function getPendingPsychologuesCount(): int
    {
        return $this->userRepository->count([
            'role'   => User::ROLE_PSYCHOLOGUE,
            'status' => User::STATUS_PENDING,
        ]);
    }
}
