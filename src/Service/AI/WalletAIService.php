<?php

namespace App\Service\AI;

use App\Entity\User;
use App\Entity\Wallet;
use App\Entity\Transaction;
use App\Service\WalletService;

/**
 * AI-powered wallet analysis service.
 * Ported from Java AIService.java (MindBloom-USERetRDV) — wallet-related prompts.
 *
 * Features:
 *   - analyzeFraudRisk()   : detects suspicious transaction patterns
 *   - getFinancialInsight() : summarises spending and gives advice
 */
class WalletAIService
{
    public function __construct(
        private GeminiService $gemini,
        private WalletService $walletService,
    ) {}

    /**
     * Analyzes fraud risk for the given user based on their wallet and transaction history.
     * Returns an AI-generated risk report.
     */
    public function analyzeFraudRisk(User $user): string
    {
        $wallet       = $this->walletService->getWallet($user);
        $transactions = $this->walletService->getTransactions($user);

        if (!$wallet) {
            return 'Aucun portefeuille trouvé pour cet utilisateur.';
        }

        $prompt = $this->buildFraudPrompt($user, $wallet, $transactions);

        return $this->gemini->ask($prompt, temperature: 0.3, maxTokens: 512);
    }

    /**
     * Generates personalised financial insights for the given user.
     * Returns an AI-generated spending summary and advice.
     */
    public function getFinancialInsight(User $user): string
    {
        $wallet       = $this->walletService->getWallet($user);
        $transactions = $this->walletService->getTransactions($user);

        if (!$wallet) {
            return 'Aucun portefeuille trouvé pour cet utilisateur.';
        }

        $prompt = $this->buildInsightPrompt($user, $wallet, $transactions);

        return $this->gemini->ask($prompt, temperature: 0.7, maxTokens: 512);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Prompt builders
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @param Transaction[] $transactions
     */
    private function buildFraudPrompt(User $user, Wallet $wallet, array $transactions): string
    {
        $accountAgeDays = $user->getCreatedAt()
            ? (new \DateTime())->diff($user->getCreatedAt())->days
            : '?';

        $totalRecharges   = 0;
        $totalWithdrawals = 0;
        $rechargeCount    = 0;
        $withdrawalCount  = 0;
        $recentLines      = [];

        foreach ($transactions as $tx) {
            if ($tx->getType() === Transaction::TYPE_RECHARGE) {
                $totalRecharges += $tx->getAmount();
                $rechargeCount++;
            } else {
                $totalWithdrawals += $tx->getAmount();
                $withdrawalCount++;
            }

            if (count($recentLines) < 10) {
                $recentLines[] = sprintf(
                    '  - [%s] %s : %.2f TND (%s)',
                    $tx->getTransactionDate()?->format('Y-m-d H:i') ?? 'N/A',
                    $tx->getType(),
                    $tx->getAmount(),
                    $tx->getDescription() ?? ''
                );
            }
        }

        $recentBlock = $recentLines
            ? implode("\n", $recentLines)
            : '  Aucune transaction récente.';

        return <<<PROMPT
Analyze this user's risk profile for potential fraud on the MindBloom mental wellness platform.

User info:
- Account age: {$accountAgeDays} days
- Wallet status: {$wallet->getStatus()}
- Current balance: {$wallet->getBalance()} TND
- Total recharges: {$totalRecharges} TND ({$rechargeCount} transactions)
- Total withdrawals / payments: {$totalWithdrawals} TND ({$withdrawalCount} transactions)

Last 10 transactions:
{$recentBlock}

Provide a concise fraud risk assessment in French:
1. Risk level (Faible / Moyen / Élevé)
2. Key suspicious patterns observed (if any)
3. Recommendation for the platform administrator
Keep it under 150 words.
PROMPT;
    }

    /**
     * @param Transaction[] $transactions
     */
    private function buildInsightPrompt(User $user, Wallet $wallet, array $transactions): string
    {
        $now        = new \DateTime();
        $monthStart = new \DateTime('first day of this month midnight');

        $monthSpend   = 0.0;
        $monthRecharge = 0.0;
        $lastMonthSpend = 0.0;

        $prevMonthStart = (clone $monthStart)->modify('-1 month');
        $prevMonthEnd   = (clone $monthStart)->modify('-1 second');

        foreach ($transactions as $tx) {
            $date = $tx->getTransactionDate();
            if (!$date) continue;

            if ($date >= $monthStart) {
                if ($tx->getType() !== Transaction::TYPE_RECHARGE) {
                    $monthSpend += $tx->getAmount();
                } else {
                    $monthRecharge += $tx->getAmount();
                }
            } elseif ($date >= $prevMonthStart && $date <= $prevMonthEnd) {
                if ($tx->getType() !== Transaction::TYPE_RECHARGE) {
                    $lastMonthSpend += $tx->getAmount();
                }
            }
        }

        $trend = '';
        if ($lastMonthSpend > 0) {
            $pct   = round((($monthSpend - $lastMonthSpend) / $lastMonthSpend) * 100);
            $trend = $pct >= 0
                ? "hausse de {$pct}% par rapport au mois dernier"
                : "baisse de " . abs($pct) . "% par rapport au mois dernier";
        }

        return <<<PROMPT
Generate personalised financial insights for a MindBloom mental wellness platform user.

Wallet summary:
- Current balance: {$wallet->getBalance()} TND
- Spending this month: {$monthSpend} TND
- Recharges this month: {$monthRecharge} TND
- Last month spending: {$lastMonthSpend} TND
- Trend: {$trend}

Write a short, warm, encouraging financial insight in French (under 120 words):
1. Brief summary of this month's activity
2. One practical tip or observation
3. An encouraging closing sentence about investing in mental wellness
PROMPT;
    }
}
