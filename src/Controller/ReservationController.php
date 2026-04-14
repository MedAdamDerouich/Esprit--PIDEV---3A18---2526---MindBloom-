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
    public function reserverCreneau(int $id, \App\Repository\CreneauRepository $creneauRepository, \Doctrine\ORM\EntityManagerInterface $em): Response
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

        // Marquer le créneau comme indisponible
        $creneau->setDisponible(false);

        $em->persist($reservation);
        $em->flush();

        $this->addFlash('success', 'Votre réservation a été confirmée avec succès !');

        return $this->redirectToRoute('app_patient_reservations');
    }
}

