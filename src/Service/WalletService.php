<?php

namespace App\Service;

use App\Entity\Transaction;
use App\Entity\User;
use App\Entity\Wallet;
use App\Repository\TransactionRepository;
use App\Repository\WalletRepository;
use Doctrine\ORM\EntityManagerInterface;

class WalletService
{
    public function __construct(
        private EntityManagerInterface $em,
        private WalletRepository       $walletRepository,
        private TransactionRepository  $transactionRepository,
    ) {}

    public function getWallet(User $user): ?Wallet
    {
        return $this->walletRepository->findByUser($user->getId());
    }

    public function getBalance(User $user): float
    {
        $wallet = $this->getWallet($user);
        return $wallet ? $wallet->getBalance() : 0.0;
    }

    public function recharge(User $user, float $amount, string $description = 'Rechargement du wallet'): void
    {
        $wallet = $this->getWallet($user);
        if (!$wallet || !$wallet->isActive()) {
            throw new \RuntimeException('Wallet introuvable ou inactif.');
        }

        $wallet->setBalance($wallet->getBalance() + $amount);
        $wallet->setLastRechargeDate(new \DateTime());

        $tx = new Transaction();
        $tx->setUser($user);
        $tx->setType(Transaction::TYPE_RECHARGE);
        $tx->setAmount($amount);
        $tx->setDescription($description);

        $this->em->persist($tx);
        $this->em->flush();
    }

    public function deduct(User $user, float $amount, string $description = 'Paiement'): bool
    {
        $wallet = $this->getWallet($user);
        if (!$wallet || !$wallet->isActive()) {
            return false;
        }
        if ($wallet->getBalance() < $amount) {
            return false;
        }

        $wallet->setBalance($wallet->getBalance() - $amount);

        $tx = new Transaction();
        $tx->setUser($user);
        $tx->setType(Transaction::TYPE_PAYMENT);
        $tx->setAmount($amount);
        $tx->setDescription($description);

        $this->em->persist($tx);
        $this->em->flush();

        return true;
    }

    public function getTransactions(User $user): array
    {
        return $this->transactionRepository->findByUserOrdered($user->getId());
    }
}
