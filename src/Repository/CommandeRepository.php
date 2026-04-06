<?php

namespace App\Repository;

use App\Entity\Commande;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commande>
 *
 * @method Commande|null find($id, $lockMode = null, $lockVersion = null)
 * @method Commande|null findOneBy(array $criteria, array $orderBy = null)
 * @method Commande[]    findAll()
 * @method Commande[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    /**
     * Find active cart items for a user (no facture linked)
     */
    public function findCartByUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.produit', 'p') // Ensure products still exist
            ->andWhere('c.user = :user')
            ->andWhere('c.facture IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }
}
