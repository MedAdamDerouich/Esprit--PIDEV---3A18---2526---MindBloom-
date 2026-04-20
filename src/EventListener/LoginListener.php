<?php

namespace App\EventListener;

use App\Service\WhatsAppService;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class LoginListener
{
    public function __construct(
        private WhatsAppService $whatsAppService,
        private ReservationRepository $reservationRepository,
        private EntityManagerInterface $em
    ) {}

    public function onSecurityInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();

        if (!$user || !method_exists($user, 'getPhone')) return;

        $now = new \DateTime();
        $in24h = (new \DateTime())->modify('+24 hours');

        $reservations = $this->reservationRepository->findPendingRemindersForPatient($user, $now, $in24h);

        foreach ($reservations as $reservation) {
            $creneau = $reservation->getCreneau();
            $cabinet = $creneau?->getCabinet();

            if (!$user->getPhone()) continue;

            $sent = $this->whatsAppService->sendReminderMessage(
                '+216' . $user->getPhone(),
                $user->getFullName() ?? 'Patient',
                $cabinet?->getPsychologue()?->getFullName() ?? 'Dr. Inconnu',
                $creneau->getDateCreneau()->format('d/m/Y'),
                $creneau->getHeureDebut()->format('H:i'),
                $cabinet?->getAdresse() ?? 'Adresse inconnue'
            );

            if ($sent) {
                $reservation->setReminderSent(true);
                $this->em->flush();
            }
        }
    }
}