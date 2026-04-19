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

    /**
     * Smart Categorizer + Budget Coach.
     * Classifies each recent expense into a category using Gemini,
     * then produces a spending breakdown, a monthly budget plan, and
     * actionable recommendations.
     *
     * Returns an associative array:
     *   [
     *     'categories'      => [ ['name' => 'Santé', 'amount' => 120.0, 'percent' => 42, 'color' => '#..'], ... ],
     *     'totalSpending'   => 285.5,
     *     'totalRecharges'  => 400.0,
     *     'topCategory'     => 'Santé',
     *     'recommendations' => ['tip 1', 'tip 2', 'tip 3'],
     *     'summary'         => 'Texte encourageant...',
     *     'healthScore'     => 78,   // 0..100
     *     'error'           => null | string,
     *   ]
     */
    public function categorizeAndCoach(User $user): array
    {
        $wallet       = $this->walletService->getWallet($user);
        $transactions = $this->walletService->getTransactions($user);

        if (!$wallet) {
            return ['error' => 'Aucun portefeuille trouvé pour cet utilisateur.'];
        }

        // Filter expenses (payments/withdrawals) from the current month
        $monthStart = new \DateTime('first day of this month midnight');
        $expenses   = [];
        $totalSpending  = 0.0;
        $totalRecharges = 0.0;

        foreach ($transactions as $tx) {
            $date = $tx->getTransactionDate();
            if (!$date || $date < $monthStart) continue;

            if ($tx->getType() === Transaction::TYPE_RECHARGE) {
                $totalRecharges += $tx->getAmount();
            } else {
                $totalSpending += $tx->getAmount();
                $expenses[] = [
                    'id'          => $tx->getId(),
                    'amount'      => $tx->getAmount(),
                    'description' => $tx->getDescription() ?? '',
                    'date'        => $date->format('Y-m-d'),
                ];
            }
        }

        if (empty($expenses)) {
            return [
                'categories'      => [],
                'totalSpending'   => 0.0,
                'totalRecharges'  => $totalRecharges,
                'topCategory'     => null,
                'recommendations' => ['Aucune dépense ce mois-ci. Continuez à suivre vos recharges pour garder une trace claire.'],
                'summary'         => 'Ton wallet est calme ce mois-ci — profites-en pour définir un budget pour tes prochaines séances.',
                'healthScore'     => 100,
                'error'           => null,
            ];
        }

        $prompt = $this->buildCategorizerPrompt($user, $wallet, $expenses, $totalSpending, $totalRecharges);
        $raw    = $this->gemini->ask($prompt, temperature: 0.3, maxTokens: 4096, jsonMode: true);

        // Extract JSON block from model output
        $json = $this->extractJson($raw);
        if (!$json) {
            return [
                'error' => 'Réponse IA invalide. Détails : ' . mb_substr($raw, 0, 300),
                'raw'   => $raw,
            ];
        }

        // Normalize and add colors
        $palette = ['#00C2C7', '#4A90E2', '#E63F8C', '#F47B20', '#4ECB71', '#9B59B6', '#F5C842', '#1B3A6B'];
        $categories = [];
        foreach (($json['categories'] ?? []) as $i => $c) {
            $amount  = (float)($c['amount'] ?? 0);
            $percent = $totalSpending > 0 ? (int) round(($amount / $totalSpending) * 100) : 0;
            $categories[] = [
                'name'    => (string)($c['name'] ?? 'Autre'),
                'amount'  => round($amount, 2),
                'percent' => $percent,
                'color'   => $palette[$i % count($palette)],
            ];
        }

        // Sort by amount desc
        usort($categories, fn($a, $b) => $b['amount'] <=> $a['amount']);

        return [
            'categories'      => $categories,
            'totalSpending'   => round($totalSpending, 2),
            'totalRecharges'  => round($totalRecharges, 2),
            'topCategory'     => $categories[0]['name'] ?? null,
            'recommendations' => array_values(array_filter(
                array_map('strval', $json['recommendations'] ?? []),
                fn($r) => trim($r) !== ''
            )),
            'summary'         => (string)($json['summary'] ?? ''),
            'healthScore'     => max(0, min(100, (int)($json['healthScore'] ?? 70))),
            'error'           => null,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Prompt builders
    // ──────────────────────────────────────────────────────────────────────────

    private function buildCategorizerPrompt(User $user, Wallet $wallet, array $expenses, float $totalSpending, float $totalRecharges): string
    {
        $lines = [];
        foreach ($expenses as $e) {
            $lines[] = sprintf('  - id=%s | %s | %.2f TND | "%s"',
                $e['id'], $e['date'], $e['amount'], $e['description']);
        }
        $block = implode("\n", $lines);

        return <<<PROMPT
You are a financial coach for users of MindBloom, a mental wellness platform in Tunisia.
Classify each expense into ONE of these categories:
  - "Consultations"       (therapy sessions, psychologist bookings)
  - "Tests psychologiques" (paid assessments, tests)
  - "Produits bien-être"  (products, supplements, books)
  - "Événements"          (workshops, retreats, events)
  - "Abonnements"         (subscriptions, recurring)
  - "Autre"               (anything that doesn't fit)

User's current balance: {$wallet->getBalance()} TND
This month — total spending: {$totalSpending} TND, total recharges: {$totalRecharges} TND

Expenses to classify:
{$block}

Respond with ONLY a valid JSON object (no markdown, no code fences, no commentary), matching this exact schema:
{
  "categories": [
    { "name": "string (one of the 6 categories)", "amount": number }
  ],
  "recommendations": [
    "3 short actionable tips in French, each under 25 words"
  ],
  "summary": "2-3 warm sentences in French summarising the month + encouragement",
  "healthScore": integer 0-100 (100 = excellent balance between recharges and spending, reasonable diversification)
}

Rules:
- Aggregate amounts per category (do NOT list each transaction individually).
- Include only categories with amount > 0.
- Health score logic: penalize if spending > recharges, reward diversification, reward healthy balance.
- Recommendations must reference concrete numbers from the data when possible.
PROMPT;
    }

    /**
     * Extract and decode the first valid JSON object from a text blob.
     */
    private function extractJson(string $text): ?array
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```\s*$/i', '', trim($text));
        $text = trim($text);

        $data = json_decode($text, true);
        if (is_array($data)) {
            return $data;
        }

        $start = strpos($text, '{');
        if ($start === false) return null;

        $depth = 0; $len = strlen($text); $inStr = false; $end = null;
        for ($i = $start; $i < $len; $i++) {
            $ch = $text[$i];
            if ($ch === '\\' && $inStr) { $i++; continue; }
            if ($ch === '"') { $inStr = !$inStr; continue; }
            if ($inStr) continue;
            if ($ch === '{') $depth++;
            elseif ($ch === '}') { $depth--; if ($depth === 0) { $end = $i; break; } }
        }

        if ($end === null) return null;
        $data = json_decode(substr($text, $start, $end - $start + 1), true);
        return is_array($data) ? $data : null;
    }

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
