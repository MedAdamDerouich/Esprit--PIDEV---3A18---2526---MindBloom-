<?php

namespace App\Controller;

use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ReservationController extends AbstractController
{
    #[Route('/psychologue/reservations', name: 'app_reservation_index')]
    #[IsGranted('ROLE_PSYCHOLOGUE')]
    public function index(ReservationRepository $reservationRepository): Response
    {
        /** @var \App\Entity\User $psychologue */
        $psychologue = $this->getUser();
        $reservations = $reservationRepository->getReservationsByPsychologue($psychologue->getId());

        return $this->render('psychologue/liste_reservations.html.twig', [
            'reservations' => $reservations,
        ]);
    }

    #[Route('/patient/reservations', name: 'app_patient_reservations')]
    #[IsGranted('ROLE_PATIENT')]
    public function mesReservations(ReservationRepository $reservationRepository): Response
    {
        $patient = $this->getUser();
        $reservations = $reservationRepository->getReservationsByPatient($patient);

        return $this->render('patient/mes_reservations.html.twig', [
            'reservations' => $reservations,
        ]);
    }

    #[Route('/patient/reservations/{id}/toggle', name: 'app_patient_reservations_toggle', methods: ['POST'])]
    #[IsGranted('ROLE_PATIENT')]
    public function toggleReservation(int $id, ReservationRepository $reservationRepository, \App\Repository\CreneauRepository $creneauRepository, \Doctrine\ORM\EntityManagerInterface $em): Response
    {
        $reservation = $reservationRepository->find($id);

        if (!$reservation) {
            $this->addFlash('error', 'Réservation introuvable.');
            return $this->redirectToRoute('app_patient_reservations');
        }

        if ($reservation->getPatient() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier cette réservation.');
        }

        $creneau = $reservation->getCreneau();

        if ($reservation->getStatus() === \App\Entity\Status::CONFIRME) {
            // Annuler la réservation
            $reservation->setStatus(\App\Entity\Status::ANNULE);
            $creneau->setDisponible(true);
            $this->addFlash('success', 'La réservation a été annulée.');
        } else {
            // Tenter de reconfirmer
            if (!$creneau->isDisponible()) {
                $this->addFlash('error', 'Désolé, ce créneau a déjà été réservé par un autre patient.');
                return $this->redirectToRoute('app_patient_reservations');
            }

            $reservation->setStatus(\App\Entity\Status::CONFIRME);
            $creneau->setDisponible(false);
            $this->addFlash('success', 'Votre réservation a été reconfirmée avec succès.');
        }

        $em->flush();

        return $this->redirectToRoute('app_patient_reservations');
    }

    #[Route('/patient/reservations/{id}/delete', name: 'app_patient_reservations_delete', methods: ['POST'])]
    #[IsGranted('ROLE_PATIENT')]
    public function deleteReservation(int $id, ReservationRepository $reservationRepository, \Doctrine\ORM\EntityManagerInterface $em): Response
    {
        $reservation = $reservationRepository->find($id);

        if (!$reservation) {
            $this->addFlash('error', 'Réservation introuvable.');
            return $this->redirectToRoute('app_patient_reservations');
        }

        if ($reservation->getPatient() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à supprimer cette réservation.');
        }

        if ($reservation->getStatus() !== \App\Entity\Status::ANNULE) {
            $this->addFlash('error', 'Seules les réservations annulées peuvent être supprimées de l\'historique.');
            return $this->redirectToRoute('app_patient_reservations');
        }

        $em->remove($reservation);
        $em->flush();

        $this->addFlash('success', 'La réservation a été supprimée de votre historique.');
        
        return $this->redirectToRoute('app_patient_reservations');
    }

    #[Route('/patient/reserver/creneau/{id}/confirmer-page', name: 'app_patient_reserver_confirm_page')]
    #[IsGranted('ROLE_PATIENT')]
    public function confirmerPage(int $id, \App\Repository\CreneauRepository $creneauRepository): Response
    {
        $creneau = $creneauRepository->find($id);

        if (!$creneau || !$creneau->isDisponible()) {
            $this->addFlash('error', 'Ce créneau n\'est plus disponible.');
            return $this->redirectToRoute('app_patient_cabinets_index');
        }

        return $this->render('patient/confirmation_reservation.html.twig', [
            'creneau' => $creneau,
        ]);
    }

    #[Route('/patient/reserver/creneau/{id}', name: 'app_patient_reserver_creneau', methods: ['POST'])]
    #[IsGranted('ROLE_PATIENT')]
    public function reserverCreneau(int $id, \App\Repository\CreneauRepository $creneauRepository, \Doctrine\ORM\EntityManagerInterface $em, \App\Service\WhatsAppService $whatsAppService): Response
    {
        $patient = $this->getUser();
        $creneau = $creneauRepository->find($id);

        if (!$creneau || !$creneau->isDisponible()) {
            $this->addFlash('error', 'Ce créneau n\'est plus disponible.');
            return $this->redirectToRoute('app_patient_cabinets_index');
        }

        $reservation = new \App\Entity\Reservation();
        $reservation->setPatient($patient);
        $reservation->setCreneau($creneau);
        $reservation->setStatus(\App\Entity\Status::CONFIRME);
        $reservation->setReminderSent(false); // Init à false

        // Marquer le créneau comme indisponible
        $creneau->setDisponible(false);

        // Envoyer automatiquement le message WhatsApp une seule fois
        $cabinet = $creneau->getCabinet();
        if ($patient->getPhone() && !$reservation->isReminderSent()) {
            $sent = $whatsAppService->sendReminderMessage(
                $patient->getPhone(),
                $patient->getFullName() ?? 'Patient',
                $cabinet?->getPsychologue()?->getFullName() ?? 'Dr. Inconnu',
                $creneau->getDateCreneau()->format('d/m/Y'),
                $creneau->getHeureDebut()->format('H:i'),
                $cabinet?->getAdresse() ?? 'Adresse inconnue'
            );
            if ($sent) {
                $reservation->setReminderSent(true);
            }
        }

        $em->persist($reservation);
        $em->flush();

        $this->addFlash('success', 'Votre réservation a été confirmée avec succès !');

        return $this->redirectToRoute('app_patient_reservations');
    }


    #[Route('/psychologue/reservations/{id}/reminder', name: 'app_psychologue_send_reminder', methods: ['POST'])]
#[IsGranted('ROLE_PSYCHOLOGUE')]
public function sendReminder(
    int $id,
    ReservationRepository $reservationRepository,
    \App\Service\WhatsAppService $whatsAppService,
    \Doctrine\ORM\EntityManagerInterface $em
): Response {
    $reservation = $reservationRepository->find($id);

    if (!$reservation) {
        $this->addFlash('error', 'Réservation introuvable.');
        return $this->redirectToRoute('app_reservation_index');
    }

    $patient = $reservation->getPatient();
    $creneau = $reservation->getCreneau();
    $cabinet = $creneau?->getCabinet();

    $sent = $whatsAppService->sendReminderMessage(
        $patient->getPhone(),
        $patient->getFullName() ?? 'Patient',
        $cabinet?->getPsychologue()?->getFullName() ?? 'Dr. Inconnu',
        $creneau->getDateCreneau()->format('d/m/Y'),
        $creneau->getHeureDebut()->format('H:i'),
        $cabinet?->getAdresse() ?? 'Adresse inconnue'
    );

    if ($sent) {
        $reservation->setReminderSent(true);
        $em->flush();
        $this->addFlash('success', '✅ Rappel WhatsApp envoyé au patient.');
    } else {
        $this->addFlash('error', '❌ Échec de l\'envoi WhatsApp.');
    }

    return $this->redirectToRoute('app_reservation_index');
}
#[Route('/patient/reservations/{id}/whatsapp', name: 'app_patient_send_whatsapp', methods: ['POST'])]
#[IsGranted('ROLE_PATIENT')]
public function sendWhatsApp(
    int $id,
    ReservationRepository $reservationRepository,
    \App\Service\WhatsAppService $whatsAppService
): Response {
    $reservation = $reservationRepository->find($id);

    if (!$reservation) {
        $this->addFlash('error', 'Réservation introuvable.');
        return $this->redirectToRoute('app_patient_reservations');
    }

    $patient = $reservation->getPatient();
    $creneau = $reservation->getCreneau();
    $cabinet = $creneau?->getCabinet();

    if (!$patient->getPhone()) {
        $this->addFlash('error', 'Aucun numéro de téléphone trouvé.');
        return $this->redirectToRoute('app_patient_reservations');
    }

    $sent = $whatsAppService->sendReminderMessage(
        $patient->getPhone(),
        $patient->getFullName() ?? 'Patient',
        $cabinet?->getPsychologue()?->getFullName() ?? 'Dr. Inconnu',
        $creneau->getDateCreneau()->format('d/m/Y'),
        $creneau->getHeureDebut()->format('H:i'),
        $cabinet?->getAdresse() ?? 'Adresse inconnue'
    );

    if ($sent) {
        $this->addFlash('success', '✅ Rappel WhatsApp envoyé sur votre téléphone !');
    } else {
        $this->addFlash('error', '❌ Échec de l\'envoi WhatsApp.');
    }

    return $this->redirectToRoute('app_patient_reservations');
}


#[Route('/psychologue/reservations/export', name: 'app_psychologue_reservations_export')]
#[IsGranted('ROLE_PSYCHOLOGUE')]
public function exportExcel(
    ReservationRepository $reservationRepository,
    \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet = null
): Response {
    $psychologue = $this->getUser();
    $reservations = $reservationRepository->getReservationsByPsychologue($psychologue->getId());

    // Filtrer seulement les confirmées
    $reservationsConfirmees = array_filter($reservations, function($r) {
        return $r->getStatus()->value === 'Confirmé';
    });

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Réservations Confirmées');

    // En-têtes
    $sheet->setCellValue('A1', 'Patient');
    $sheet->setCellValue('B1', 'Téléphone');
    $sheet->setCellValue('C1', 'Date');
    $sheet->setCellValue('D1', 'Heure');
    $sheet->setCellValue('E1', 'Durée (min)');

    // Style en-têtes
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D6F42']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
    ];
    $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

    // Largeur colonnes
    foreach (range('A', 'E') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Données
    $row = 2;
    foreach ($reservationsConfirmees as $r) {
        $patient = $r->getPatient();
        $creneau = $r->getCreneau();

        $sheet->setCellValue('A' . $row, $patient->getFullName());
        $sheet->setCellValue('B' . $row, $patient->getPhone() ?? 'N/A');
        $sheet->setCellValue('C' . $row, $creneau->getDateCreneau()->format('d/m/Y'));
        $sheet->setCellValue('D' . $row, $creneau->getHeureDebut()->format('H:i'));
        $sheet->setCellValue('E' . $row, $creneau->getDureeMinutes() . ' min');

        // Alternance couleurs lignes
        if ($row % 2 === 0) {
            $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray([
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EBF5EE']],
            ]);
        }

        $row++;
    }

    // Générer le fichier
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $fileName = 'reservations_confirmees_' . date('Y-m-d') . '.xlsx';

    $response = new \Symfony\Component\HttpFoundation\StreamedResponse(function() use ($writer) {
        $writer->save('php://output');
    });

    $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');
    $response->headers->set('Cache-Control', 'max-age=0');

    return $response;
}
}

