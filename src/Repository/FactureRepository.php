<?php

namespace App\Repository;

use App\Entity\Facture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Facture>
 *
 * @method Facture|null find($id, $lockMode = null, $lockVersion = null)
 * @method Facture|null findOneBy(array $criteria, array $orderBy = null)
 * @method Facture[]    findAll()
 * @method Facture[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FactureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Facture::class);
    }

    public function searchByUser(string $query, $user)
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.commandes', 'c')
            ->leftJoin('c.produit', 'p')
            ->where('f.user = :user')
            ->andWhere('(p.nom LIKE :query OR CAST(f.id AS string) LIKE :query)')
            ->setParameter('user', $user)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('f.dateFacture', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
