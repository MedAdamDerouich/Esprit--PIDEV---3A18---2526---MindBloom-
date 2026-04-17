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
    public function dashboard(UserRepository $userRepository): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'recentUsers' => $userRepository->findRecentUsers(8),
            'stats' => [
                'users'        => $userRepository->count([]),//louay
                'reservations' => 0,
                'orders'       => 0,
                'revenue'      => '0.00',
            ],
        ]);
    }
}
