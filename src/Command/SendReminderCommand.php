<?php

namespace App\Command;

use App\Entity\Status;
use App\Repository\ReservationRepository;
use App\Service\WhatsAppService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:send-reminders', description: 'Envoie les rappels WhatsApp aux patients')]
class SendReminderCommand extends Command
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private WhatsAppService $whatsAppService,
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('⏳ Recherche des réservations à rappeler...');

        $now = new \DateTime();
        $in24h = (new \DateTime())->modify('+24 hours');

        $reservations = $this->reservationRepository->findPendingReminders($now, $in24h);

        if (empty($reservations)) {
            $output->writeln('✅ Aucun rappel à envoyer.');
            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($reservations as $reservation) {
            $patient = $reservation->getPatient();
            $creneau = $reservation->getCreneau();
            $cabinet = $creneau?->getCabinet();

            if (!$patient->getPhone()) {
                $output->writeln("⚠️ Pas de téléphone pour {$patient->getFullName()}");
                continue;
            }

            $sent = $this->whatsAppService->sendReminderMessage(
                '+216' . $patient->getPhone(),
                $patient->getFullName() ?? 'Patient',
                $cabinet?->getPsychologue()?->getFullName() ?? 'Dr. Inconnu',
                $creneau->getDateCreneau()->format('d/m/Y'),
                $creneau->getHeureDebut()->format('H:i'),
                $cabinet?->getAdresse() ?? 'Adresse inconnue'
            );

            if ($sent) {
                $reservation->setReminderSent(true);
                $this->em->flush();
                $output->writeln("✅ Rappel envoyé à {$patient->getFullName()}");
                $count++;
            } else {
                $output->writeln("❌ Échec envoi à {$patient->getFullName()}");
            }
        }

        $output->writeln("🎉 {$count} rappel(s) envoyé(s).");
        return Command::SUCCESS;
    }
}