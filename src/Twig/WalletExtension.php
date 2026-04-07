<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\WalletRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class WalletExtension extends AbstractExtension
{
    public function __construct(private WalletRepository $walletRepository) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('wallet_balance', [$this, 'getWalletBalance']),
        ];
    }

    /**
     * Returns the wallet balance for the given user, or 0.00 if no wallet exists.
     */
    public function getWalletBalance(?User $user): string
    {
        if (!$user) {
            return '0.00';
        }

        $wallet = $this->walletRepository->findOneBy(['user' => $user]);

        return $wallet ? number_format($wallet->getBalance(), 2, '.', ' ') : '0.00';
    }
}
