<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('', name: 'app_admin_dashboard')]
    public function dashboard(UserRepository $userRepository, \App\Repository\CabinetRepository $cabinetRepository, \App\Repository\ReservationRepository $reservationRepository): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'recentUsers' => $userRepository->findRecentUsers(8),
            'stats' => [
                'users'        => $userRepository->count([]),
                'reservations' => $reservationRepository->count([]),
                'cabinets'     => $cabinetRepository->count([]),
            ],
        ]);
    }

    #[Route('/cabinets', name: 'app_admin_cabinets')]
    public function cabinets(\App\Repository\CabinetRepository $cabinetRepository, \App\Repository\ReservationRepository $reservationRepository): Response
    {
        $cabinets = $cabinetRepository->findAllWithPsychologue();
        
        $resCounts = $reservationRepository->createQueryBuilder('r')
            ->select('cab.idCabinet as idCabinet', 'COUNT(r.idReservation) as resCount')
            ->join('r.creneau', 'cr')
            ->join('cr.cabinet', 'cab')
            ->groupBy('cab.idCabinet')
            ->getQuery()
            ->getArrayResult();
        
        $resMap = [];
        foreach ($resCounts as $rc) {
            $resMap[$rc['idCabinet']] = (int) $rc['resCount'];
        }

        $cabinetsData = [];
        foreach ($cabinets as $cabinet) {
            $id = $cabinet->getIdCabinet();
            $cabinetsData[] = [
                'cabinet' => $cabinet,
                'slotCount' => $cabinet->getCreneaux()->count(),
                'resCount' => $resMap[$id] ?? 0
            ];
        }

        return $this->render('admin/cabinets/index.html.twig', [
            'cabinetsData' => $cabinetsData
        ]);
    }
}
