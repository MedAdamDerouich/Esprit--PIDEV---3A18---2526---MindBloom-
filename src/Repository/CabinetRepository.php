<?php

namespace App\Repository;

use App\Entity\Cabinet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Cabinet>
 */
class CabinetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cabinet::class);
    }

    public function save(Cabinet $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Cabinet $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Equivalent de addEntity() et addEntity2()
     */
    public function addCabinet(Cabinet $cabinet): void
    {
        $this->getEntityManager()->persist($cabinet);
        $this->getEntityManager()->flush();
    }

    /**
     * Equivalent de updateEntity()
     */
    public function updateCabinet(Cabinet $cabinet): void
    {
        // Doctrine tracks changes, so just flushing is enough
        $this->getEntityManager()->flush();
    }

    /**
     * Equivalent de deleteEntity()
     */
    public function deleteCabinet(Cabinet $cabinet): void
    {
        $this->getEntityManager()->remove($cabinet);
        $this->getEntityManager()->flush();
    }

    /**
     * Equivalent de getData() avec jointure
     */
    public function getAllCabinetsWithPsychologue(): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.psychologue', 'u')
            ->addSelect('u')
            ->getQuery()
            ->getResult();
    }

    /**
     * Equivalent de getCabinetByPsychologueId()
     */
    public function getCabinetByPsychologue(int $idPsychologue): ?Cabinet
    {
        return $this->createQueryBuilder('c')
            ->join('c.psychologue', 'u')
            ->addSelect('u')
            ->where('u.id = :idPsychologue')
            ->setParameter('idPsychologue', $idPsychologue)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
