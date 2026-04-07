<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->getStatus() === User::STATUS_INACTIVE) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte est inactif. Veuillez contacter l\'administrateur.'
            );
        }

        if ($user->getStatus() === User::STATUS_SUSPENDED) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte a été suspendu. Veuillez contacter l\'administrateur.'
            );
        }

        if ($user->getStatus() === User::STATUS_PENDING) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte est en attente de validation par l\'administrateur. Vous serez notifié dès son approbation.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void {}
}
