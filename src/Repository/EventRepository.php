<?php

namespace App\Repository;

use App\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * Recherche des événements par titre, lieu ou organisateur
     */
    public function search(string $query): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.titre LIKE :query')
            ->orWhere('e.lieu LIKE :query')
            ->orWhere('e.organisateur LIKE :query')
            ->orWhere('e.description LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('e.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère tous les événements actifs triés par date
     */
    public function findActifs(): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.statut = :statut')
            ->setParameter('statut', Event::STATUT_ACTIF)
            ->orderBy('e.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les événements actifs avec places disponibles
     */
    public function findActifsAvecPlaces(): array
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.participations', 'p')
            ->where('e.statut = :statut')
            ->setParameter('statut', Event::STATUT_ACTIF)
            ->groupBy('e.id')
            ->orderBy('e.dateDebut', 'ASC');

        // Filtrer ceux avec capacité illimitée OU places disponibles
        $qb->having('e.capaciteMax IS NULL OR COUNT(p.id) < e.capaciteMax');

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère les événements à venir (date début future)
     */
    public function findUpcoming(int $limit = 10): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.dateDebut > :now')
            ->andWhere('e.statut = :statut')
            ->setParameter('now', new \DateTime())
            ->setParameter('statut', Event::STATUT_ACTIF)
            ->orderBy('e.dateDebut', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de participants pour un événement
     */
    public function countParticipants(Event $event): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(p.id)')
            ->leftJoin('e.participations', 'p')
            ->where('e.id = :eventId')
            ->setParameter('eventId', $event->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récupère les événements récents
     */
    public function findRecent(int $limit = 5): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
