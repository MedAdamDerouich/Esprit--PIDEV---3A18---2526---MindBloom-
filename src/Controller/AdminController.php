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
    public function dashboard(UserRepository $userRepository, WalletRepository $walletRepository): Response
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
}
