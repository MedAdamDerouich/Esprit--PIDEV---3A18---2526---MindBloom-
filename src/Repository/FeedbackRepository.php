<?php

namespace App\Repository;

use App\Entity\Feedback;
use App\Entity\Produit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Feedback>
 */
class FeedbackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Feedback::class);
    }

    public function findApprovedByProduct(Produit $produit, int $limit = 50): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.produit = :produit')
            ->setParameter('produit', $produit)
            ->orderBy('f.dateFeedback', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
