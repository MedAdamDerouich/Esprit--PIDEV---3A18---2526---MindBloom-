<?php

namespace App\Repository;

use App\Entity\Participation;
use App\Entity\Event;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Participation>
 */
class ParticipationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Participation::class);
    }

    /**
     * Vérifie si un utilisateur participe déjà à un événement
     */
    public function isUserRegistered(Event $event, User $user): bool
    {
        $count = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.evenement = :event')
            ->andWhere('p.user = :user')
            ->andWhere('p.statut != :cancelled')
            ->setParameter('event', $event)
            ->setParameter('user', $user)
            ->setParameter('cancelled', Participation::STATUT_ANNULE)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Récupère toutes les participations d'un utilisateur
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.evenement', 'e')
            ->where('p.user = :user')
            ->andWhere('p.statut != :cancelled')
            ->setParameter('user', $user)
            ->setParameter('cancelled', Participation::STATUT_ANNULE)
            ->orderBy('e.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les participations confirmées d'un événement
     */
    public function findConfirmedByEvent(Event $event): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.evenement = :event')
            ->andWhere('p.statut = :confirmed')
            ->setParameter('event', $event)
            ->setParameter('confirmed', Participation::STATUT_CONFIRME)
            ->orderBy('p.dateInscription', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de participants confirmés pour un événement
     */
    public function countConfirmedByEvent(Event $event): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.evenement = :event')
            ->andWhere('p.statut = :confirmed')
            ->setParameter('event', $event)
            ->setParameter('confirmed', Participation::STATUT_CONFIRME)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve une participation spécifique
     */
    public function findOneByEventAndUser(Event $event, User $user): ?Participation
    {
        return $this->createQueryBuilder('p')
            ->where('p.evenement = :event')
            ->andWhere('p.user = :user')
            ->setParameter('event', $event)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
