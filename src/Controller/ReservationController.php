<?php

namespace App\Controller;

use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReservationController extends AbstractController
{
    #[Route('/psychologue/reservations', name: 'app_reservation_index')]
    #[IsGranted('ROLE_PSYCHOLOGUE')]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $psychologue = $this->getUser();
        $reservations = $reservationRepository->getReservationsByPsychologue($psychologue);

        return $this->render('psychologue/liste_reservations.html.twig', [
            'reservations' => $reservations,
        ]);
    }

    #[Route('/patient/reserver/creneau/{id}', name: 'app_patient_reserver_creneau', methods: ['POST'])]
    #[IsGranted('ROLE_PATIENT')]
    public function reserverCreneau(int $id, \App\Repository\CreneauRepository $creneauRepository, \Doctrine\ORM\EntityManagerInterface $em): Response
    {
        $patient = $this->getUser();
        $creneau = $creneauRepository->find($id);
        $reservation->setStatus(\App\Entity\Status::Confirmé);

        // Marquer le créneau comme indisponible
        $creneau->setDisponible(false);

        $em->persist($reservation);
        // Doctrine détecte le changement sur le créneau
        $em->flush();

        $this->addFlash('success', 'Votre réservation a été confirmée avec succès !');

        return $this->redirectToRoute('app_patient_index'); // ou dashboard patient
    }
}
