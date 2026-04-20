<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\WalletRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('', name: 'app_admin_dashboard')]
    public function dashboard(
        UserRepository $userRepository,
        WalletRepository $walletRepository,
        \App\Repository\CabinetRepository $cabinetRepository,
        \App\Repository\ReservationRepository $reservationRepository,
        \App\Repository\ProduitRepository $produitRepository,
        \App\Repository\EventRepository $eventRepository,
        \App\Repository\CommandeRepository $commandeRepository,
        \App\Repository\TestRepository $testRepository
    ): Response {
        $monthly = $userRepository->getMonthlyRegistrations();

        return $this->render('admin/dashboard.html.twig', [
            'monthlyLabels' => json_encode(array_column($monthly, 'month')),
            'monthlyData'   => json_encode(array_column($monthly, 'total')),
            'stats' => [
                'users'        => $userRepository->count([]),
                'patients'     => $userRepository->countByRole(User::ROLE_PATIENT),
                'psychologues' => $userRepository->countByRole(User::ROLE_PSYCHOLOGUE),
                'admins'       => $userRepository->countByRole(User::ROLE_ADMIN),
                'active'       => $userRepository->countByStatus(User::STATUS_ACTIVE),
                'suspended'    => $userRepository->countByStatus(User::STATUS_SUSPENDED),
                'revenue'      => (float) $walletRepository->getTotalBalance(),
                'reservations' => $reservationRepository->count([]),
                'cabinets'     => $cabinetRepository->count([]),
                'produits'     => $produitRepository->count([]),
                'evenements'   => $eventRepository->count([]),
                'commandes'    => $commandeRepository->count([]),
                'tests'        => $testRepository->count([]),
            ],
        ]);
    }

    #[Route('/psychologues/pending', name: 'app_admin_pending_psychologues')]
    public function pendingPsychologues(UserRepository $userRepository): Response
    {
        $pending = $userRepository->findBy([
            'role'   => User::ROLE_PSYCHOLOGUE,
            'status' => User::STATUS_PENDING,
        ], ['createdAt' => 'ASC']);

        return $this->render('admin/psychologues/pending.html.twig', [
            'pendingUsers' => $pending,
        ]);
    }

    #[Route('/psychologues/{id}/approve', name: 'app_admin_approve_psychologue', methods: ['POST'])]
    public function approvePsychologue(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('approve-psycho-' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_pending_psychologues');
        }

        if ($user->getRole() !== User::ROLE_PSYCHOLOGUE) {
            throw $this->createNotFoundException();
        }

        $user->setStatus(User::STATUS_ACTIVE);
        $em->flush();

        $this->addFlash('success', sprintf('Le compte de %s a été approuvé.', $user->getFullName()));
        return $this->redirectToRoute('app_admin_pending_psychologues');
    }

    #[Route('/psychologues/{id}/reject', name: 'app_admin_reject_psychologue', methods: ['POST'])]
    public function rejectPsychologue(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('reject-psycho-' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_pending_psychologues');
        }

        if ($user->getRole() !== User::ROLE_PSYCHOLOGUE) {
            throw $this->createNotFoundException();
        }

        $user->setStatus(User::STATUS_INACTIVE);
        $em->flush();

        $this->addFlash('success', sprintf('Le compte de %s a été rejeté.', $user->getFullName()));
        return $this->redirectToRoute('app_admin_pending_psychologues');
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
