<?php

namespace App\Repository;

use App\Entity\Creneau;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Creneau>
 */
class CreneauRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Creneau::class);
    }
    
    /**
     * Equivalent de getCreneauxByPsychologueId dans creneauservice.java
     */
    public function getCreneauxByPsychologue(int $idPsychologue): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.cabinet', 'cab')
            ->join('cab.psychologue', 'u')
            ->where('u.id = :idPsychologue')
            ->setParameter('idPsychologue', $idPsychologue)
            ->orderBy('c.dateCreneau', 'ASC')
            ->addOrderBy('c.heureDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Equivalent de deleteEntity dans creneauservice.java
     */
    public function deleteCreneau(Creneau $creneau): void
    {
        $this->getEntityManager()->remove($creneau);
        $this->getEntityManager()->flush();
    }

    /**
     * Equivalent de addEntity() et addEntity2()
     */
    public function addCreneau(Creneau $creneau): void
    {
        $this->getEntityManager()->persist($creneau);
        $this->getEntityManager()->flush();
    }

    /**
     * Equivalent de updateEntity()
     */
    public function updateCreneau(Creneau $creneau): void
    {
        $this->getEntityManager()->flush();
    }
}
