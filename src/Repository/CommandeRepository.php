<?php

namespace App\Repository;

use App\Entity\Commande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commande>
 */
class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    /**
     * @return Commande[]
     */
    public function findCartByUser($user): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.produit', 'p')
            ->andWhere('c.user = :user')
            ->andWhere('c.facture IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }
}
