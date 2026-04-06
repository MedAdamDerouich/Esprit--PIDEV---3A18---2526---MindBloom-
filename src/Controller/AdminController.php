<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\WalletRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('', name: 'app_admin_dashboard')]
    public function dashboard(UserRepository $userRepository, WalletRepository $walletRepository, \App\Repository\CabinetRepository $cabinetRepository, \App\Repository\ReservationRepository $reservationRepository): Response
    {
        $monthly = $userRepository->getMonthlyRegistrations();

        return $this->render('admin/dashboard.html.twig', [
            'recentUsers' => $userRepository->findRecentUsers(8),
            'stats' => [
                'users'       => $userRepository->count([]),
                'patients'    => $userRepository->countByRole(User::ROLE_PATIENT),
                'psychologues'=> $userRepository->countByRole(User::ROLE_PSYCHOLOGUE),
                'admins'      => $userRepository->countByRole(User::ROLE_ADMIN),
                'active'      => $userRepository->countByStatus(User::STATUS_ACTIVE),
                'suspended'   => $userRepository->countByStatus(User::STATUS_SUSPENDED),
                'revenue'     => number_format($walletRepository->getTotalBalance(), 2),
                'reservations'=> $reservationRepository->count([]),
                'cabinets'    => $cabinetRepository->count([]),
            ],
            'chartLabels' => array_column($monthly, 'month'),
            'chartData'   => array_map('intval', array_column($monthly, 'total')),
            'rolesData'   => [
                $userRepository->countByRole(User::ROLE_PATIENT),
                $userRepository->countByRole(User::ROLE_PSYCHOLOGUE),
                $userRepository->countByRole(User::ROLE_ADMIN),
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
