<?php

namespace App\Repository;

use App\Entity\Wallet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class WalletRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Wallet::class);
    }

    public function getTotalBalance(): float
    {
        return (float) $this->createQueryBuilder('w')
            ->select('SUM(w.balance)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0.0;
    }

    public function findByUser(int $userId): ?Wallet
    {
        return $this->createQueryBuilder('w')
            ->join('w.user', 'u')
            ->where('u.id = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
