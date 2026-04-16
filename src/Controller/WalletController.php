<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Wallet;
use App\Repository\TransactionRepository;
use App\Repository\WalletRepository;
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

        return $this->render('admin/wallets/index.html.twig', [
            'wallets' => $wallets,
            'selectedWallet' => $selectedWallet,
            'selectedTransactions' => $selectedTransactions,
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
}
