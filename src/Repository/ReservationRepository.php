<?php

namespace App\Repository;

use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    /**
     * Equivalent de getReservationsByPsychologue dans reservationservice.java
     */
    public function getReservationsByPsychologue(int $idPsychologue): array
    {
        return $this->createQueryBuilder('r')
            ->select('r', 'c', 'cab', 'pat')
            ->join('r.creneau', 'c')
            ->join('c.cabinet', 'cab')
            ->join('cab.psychologue', 'psy')
            ->join('r.patient', 'pat')
            ->where('psy.id = :idPsychologue')
            ->setParameter('idPsychologue', $idPsychologue)
            ->orderBy('c.dateCreneau', 'ASC')
            ->addOrderBy('c.heureDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
