<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Wallet;
use App\Repository\TransactionRepository;
use App\Repository\WalletRepository;
use App\Entity\Transaction;
use App\Repository\UserRepository;
use App\Service\AI\AdminWalletAIService;
use App\Service\AI\WalletAIService;
use App\Service\StripeService;
use App\Service\WalletService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class WalletController extends AbstractController
{
    #[Route('/wallet', name: 'app_wallet')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(WalletService $walletService, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user   = $this->getUser();
        $wallet = $walletService->getWallet($user);

        if (!$wallet) {
            $wallet = new Wallet();
            $wallet->setUser($user);
            $em->persist($wallet);
            $em->flush();
        }

        return $this->render('wallet/index.html.twig', [
            'wallet'       => $wallet,
            'transactions' => $walletService->getTransactions($user),
        ]);
    }

    #[Route('/wallet/recharge', name: 'app_wallet_recharge', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function recharge(Request $request, WalletService $walletService, StripeService $stripeService): Response
    {
        if (!$this->isCsrfTokenValid('wallet-recharge', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_wallet');
        }

        // ── #2: Wallet status guard — check BEFORE any other processing ──
        /** @var User $user */
        $user   = $this->getUser();
        $wallet = $walletService->getWallet($user);

        if (!$wallet || !$wallet->isActive()) {
            $this->addFlash('error', 'Votre wallet est suspendu. Contactez l\'administrateur.');
            return $this->redirectToRoute('app_wallet');
        }

        $amount = (float) $request->request->get('amount', 0);
        if ($amount <= 0 || $amount > 10000) {
            $this->addFlash('error', 'Montant invalide. Entrez une valeur entre 1 et 10 000 DT.');
            return $this->redirectToRoute('app_wallet');
        }

        // Server-side card validation (mirrors Java AddEditWalletController)
        $cardName   = trim($request->request->get('card_name', ''));
        $cardNumber = preg_replace('/\s+/', '', $request->request->get('card_number', ''));
        $cardExpiry = trim($request->request->get('card_expiry', ''));
        $cardCvc    = trim($request->request->get('card_cvc', ''));

        if ($cardName === '') {
            $this->addFlash('error', 'Le nom du titulaire de la carte est requis.');
            return $this->redirectToRoute('app_wallet');
        }
        if (!preg_match('/^\d{16}$/', $cardNumber)) {
            $this->addFlash('error', 'Numéro de carte invalide (16 chiffres requis).');
            return $this->redirectToRoute('app_wallet');
        }
        if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $cardExpiry)) {
            $this->addFlash('error', 'Date d\'expiration invalide (format MM/AA requis).');
            return $this->redirectToRoute('app_wallet');
        }
        if (!preg_match('/^\d{3}$/', $cardCvc)) {
            $this->addFlash('error', 'CVC invalide (3 chiffres requis).');
            return $this->redirectToRoute('app_wallet');
        }

        try {
            // Stripe charge first — wallet untouched if payment fails (same as Java)
            $stripeService->charge(
                $amount,
                sprintf('Recharge %.2f DT — %s', $amount, $user->getFullName())
            );

            // Payment succeeded → update wallet balance
            $walletService->recharge($user, $amount, 'Rechargement via Stripe');
            $this->addFlash('success', sprintf('%.2f DT ajoutés à votre wallet avec succès !', $amount));
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->addFlash('error', 'Erreur de paiement Stripe : ' . $e->getMessage());
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_wallet');
    }

    // ── Admin: list all wallets ────────────────────────────────────────────

    #[Route('/admin/wallets', name: 'app_admin_wallets')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminIndex(
        WalletRepository $walletRepository,
        TransactionRepository $transactionRepository,
        UserRepository $userRepository,
        Request $request
    ): Response
    {
        $wallets = $walletRepository->findAll();
        $selectedWalletId = $request->query->getInt('wallet');
        $selectedWallet = null;
        $selectedTransactions = [];

        if ($selectedWalletId > 0) {
            $selectedWallet = $walletRepository->find($selectedWalletId);
        }

        if ($selectedWallet instanceof Wallet && $selectedWallet->getUser() !== null) {
            $selectedTransactions = $transactionRepository->findByUserOrdered($selectedWallet->getUser()->getId());
        }

        $userIdsWithWallet = array_map(
            fn (Wallet $w) => $w->getUser()?->getId(),
            $wallets
        );
        $usersWithoutWallet = array_filter(
            $userRepository->findAll(),
            fn ($u) => !in_array($u->getId(), $userIdsWithWallet, true)
        );

        $totalBalance = 0.0;
        $activeCount  = 0;
        foreach ($wallets as $w) {
            $totalBalance += $w->getBalance();
            if ($w->isActive()) $activeCount++;
        }

        return $this->render('admin/wallets/index.html.twig', [
            'wallets' => $wallets,
            'selectedWallet' => $selectedWallet,
            'selectedTransactions' => $selectedTransactions,
            'usersWithoutWallet'   => array_values($usersWithoutWallet),
            'totalBalance'         => $totalBalance,
            'activeCount'          => $activeCount,
            'inactiveCount'        => count($wallets) - $activeCount,
        ]);
    }

    // ── AI endpoints ──────────────────────────────────────────────────────────

    #[Route('/wallet/ai/fraud', name: 'app_wallet_ai_fraud', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function aiFraud(WalletAIService $aiService): JsonResponse
    {
        /** @var User $user */
        $user   = $this->getUser();
        $result = $aiService->analyzeFraudRisk($user);
        return $this->json(['result' => $result]);
    }

    #[Route('/wallet/ai/insight', name: 'app_wallet_ai_insight', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function aiInsight(WalletAIService $aiService): JsonResponse
    {
        /** @var User $user */
        $user   = $this->getUser();
        $result = $aiService->getFinancialInsight($user);
        return $this->json(['result' => $result]);
    }

    #[Route('/wallet/ai/coach', name: 'app_wallet_ai_coach', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function aiCoach(WalletAIService $aiService): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->json($aiService->categorizeAndCoach($user));
    }

    #[Route('/admin/wallets/{id}/toggle', name: 'app_admin_wallet_toggle', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminToggle(
        Wallet $wallet,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $selectedWalletId = (int) $request->request->get('selected_wallet_id', 0);

        if (!$this->isCsrfTokenValid('toggle-wallet-' . $wallet->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute(
                'app_admin_wallets',
                $selectedWalletId > 0 ? ['wallet' => $selectedWalletId] : []
            );
        }

        $wallet->toggleStatus();
        $em->flush();

        $this->addFlash('success', 'Statut du wallet mis à jour.');
        return $this->redirectToRoute(
            'app_admin_wallets',
            $selectedWalletId > 0 ? ['wallet' => $selectedWalletId] : []
        );
    }

    // ── Admin: AI Fraud Shield ────────────────────────────────────────────────

    #[Route('/admin/wallets/{id}/ai-fraud', name: 'app_admin_wallet_ai_fraud', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminAiFraud(Wallet $wallet, AdminWalletAIService $ai): JsonResponse
    {
        $user = $wallet->getUser();
        if (!$user) {
            return $this->json(['ok' => false, 'message' => 'Utilisateur introuvable.'], 404);
        }

        $result = $ai->analyzeUser($user);

        return $this->json([
            'ok'     => true,
            'user'   => $user->getFullName(),
            'level'  => $result['level'],
            'score'  => $result['score'],
            'color'  => $result['color'],
            'report' => $result['report'],
        ]);
    }

    // ── Admin: Transactions history (JSON) ────────────────────────────────────

    #[Route('/admin/wallets/{id}/history', name: 'app_admin_wallet_history', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminHistory(Wallet $wallet, TransactionRepository $transactionRepository): JsonResponse
    {
        $user = $wallet->getUser();
        if (!$user) {
            return $this->json(['ok' => false, 'message' => 'Utilisateur introuvable.'], 404);
        }

        $txs  = $transactionRepository->findByUserOrdered($user->getId());
        $data = [];
        foreach ($txs as $t) {
            $data[] = [
                'id'          => $t->getId(),
                'type'        => $t->getType(),
                'amount'      => $t->getAmount(),
                'description' => $t->getDescription(),
                'date'        => $t->getTransactionDate()?->format('d/m/Y H:i'),
            ];
        }

        return $this->json([
            'ok'           => true,
            'user'         => $user->getFullName(),
            'email'        => $user->getEmail(),
            'balance'      => $wallet->getBalance(),
            'transactions' => $data,
        ]);
    }

    // ── Admin: Recharge a wallet ──────────────────────────────────────────────

    #[Route('/admin/wallets/{id}/recharge', name: 'app_admin_wallet_recharge', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminRecharge(
        Wallet $wallet,
        Request $request,
        WalletService $walletService,
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('admin-recharge-' . $wallet->getId(), (string) $request->request->get('_token'))) {
            return $this->json(['ok' => false, 'message' => 'Token CSRF invalide.'], 400);
        }

        $amount = (float) $request->request->get('amount', 0);
        if ($amount <= 0 || $amount > 10000) {
            return $this->json(['ok' => false, 'message' => 'Montant invalide (1 – 10 000 DT).'], 400);
        }

        $user = $wallet->getUser();
        if (!$user) {
            return $this->json(['ok' => false, 'message' => 'Utilisateur introuvable.'], 404);
        }

        try {
            $walletService->recharge($user, $amount, 'Recharge par administrateur');
        } catch (\RuntimeException $e) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }

        return $this->json([
            'ok'         => true,
            'newBalance' => $wallet->getBalance(),
            'message'    => sprintf('%.2f DT crédités avec succès.', $amount),
        ]);
    }

    // ── Admin: Delete wallet ──────────────────────────────────────────────────

    #[Route('/admin/wallets/{id}/delete', name: 'app_admin_wallet_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminDelete(
        Wallet $wallet,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('delete-wallet-' . $wallet->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_wallets');
        }

        $em->remove($wallet);
        $em->flush();

        $this->addFlash('success', 'Wallet supprimé avec succès.');
        return $this->redirectToRoute('app_admin_wallets');
    }

    // ── Admin: Create wallet for a user ───────────────────────────────────────

    #[Route('/admin/wallets/create', name: 'app_admin_wallet_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminCreate(
        Request $request,
        UserRepository $userRepository,
        WalletRepository $walletRepository,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('admin-create-wallet', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_wallets');
        }

        $userId = (int) $request->request->get('user_id', 0);
        $user   = $userId > 0 ? $userRepository->find($userId) : null;

        if (!$user) {
            $this->addFlash('error', 'Utilisateur invalide.');
            return $this->redirectToRoute('app_admin_wallets');
        }

        if ($walletRepository->findByUser($user->getId())) {
            $this->addFlash('error', 'Cet utilisateur possède déjà un wallet.');
            return $this->redirectToRoute('app_admin_wallets');
        }

        $wallet = new Wallet();
        $wallet->setUser($user);
        $wallet->setBalance(0.0);
        $em->persist($wallet);
        $em->flush();

        $this->addFlash('success', sprintf('Wallet créé pour %s.', $user->getFullName()));
        return $this->redirectToRoute('app_admin_wallets');
    }
}
