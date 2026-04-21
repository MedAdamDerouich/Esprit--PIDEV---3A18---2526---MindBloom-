<?php

namespace App\Repository;

use App\Entity\Produit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Produit>
 */
class ProduitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Produit::class);
    }

    public function search(string $query)
    {
        return $this->createQueryBuilder('p')
            ->where('p.nom LIKE :query')
            ->orWhere('p.description LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->getQuery()
            ->getResult();
    }

    public function getProductSalesStats(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = '
            SELECT
                p.id_produit AS id,
                p.nom,
                p.prix,
                p.quantite AS stock,
                p.stock_seuil AS stockSeuil,
                COALESCE(SUM(
                    CASE WHEN f.statut_livraison != :cancelled OR f.id_facture IS NULL
                    THEN c.quantite ELSE 0 END
                ), 0) AS totalVendu,
                COALESCE(SUM(
                    CASE WHEN f.statut_livraison != :cancelled OR f.id_facture IS NULL
                    THEN c.quantite * p.prix ELSE 0 END
                ), 0) AS chiffreAffaires
            FROM produit p
            LEFT JOIN commande c ON c.id_produit = p.id_produit
            LEFT JOIN facture f ON f.id_facture = c.id_facture
            GROUP BY p.id_produit, p.nom, p.prix, p.quantite, p.stock_seuil
            ORDER BY totalVendu ASC
        ';

        return $conn->executeQuery($sql, ['cancelled' => 'ANNULE'])->fetchAllAssociative();
    }
}
