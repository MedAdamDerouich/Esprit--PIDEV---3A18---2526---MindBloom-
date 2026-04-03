<?php

namespace App\Controller;

use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReservationController extends AbstractController
{
    #[Route('/psychologue/reservations', name: 'app_reservation_index')]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $reservations = $reservationRepository->getReservationsByPsychologue($user->getId());

        return $this->render('psychologue/liste_reservations.html.twig', [
            'reservations' => $reservations,
        ]);
    }
}
