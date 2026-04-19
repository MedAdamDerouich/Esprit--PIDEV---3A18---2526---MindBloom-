<?php

namespace App\Service\AI;

use App\Entity\Transaction;
use App\Entity\User;
use App\Service\WalletService;

/**
 * Admin-side AI Fraud Shield — ported from Java WalletManagementController.runAiRiskAnalysis.
 * Analyses a user's transaction history, age, and account age,
 * then returns a French risk report with level: Faible / Moyen / Élevé.
 */
class AdminWalletAIService
{
    public function __construct(
        private GeminiService $gemini,
        private WalletService $walletService,
    ) {}

    /**
     * @return array{level: string, report: string, score: int, color: string}
     */
    public function analyzeUser(User $user): array
    {
        $wallet       = $this->walletService->getWallet($user);
        $transactions = $this->walletService->getTransactions($user);

        if (!$wallet) {
            return [
                'level'  => 'Inconnu',
                'score'  => 0,
                'color'  => '#95A5A6',
                'report' => 'Aucun portefeuille trouvé pour cet utilisateur.',
            ];
        }

        $age = null;
        if (method_exists($user, 'getDateOfBirth') && $user->getDateOfBirth()) {
            $age = (new \DateTime())->diff($user->getDateOfBirth())->y;
        }

        $accountAgeDays = $user->getCreatedAt()
            ? (new \DateTime())->diff($user->getCreatedAt())->days
            : null;

        $totalRecharges   = 0.0;
        $totalWithdrawals = 0.0;
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
                    '  - %s de %.2f TND le %s (%s)',
                    $tx->getType(),
                    $tx->getAmount(),
                    $tx->getTransactionDate()?->format('Y-m-d H:i') ?? 'N/A',
                    $tx->getDescription() ?? ''
                );
            }
        }

        $recentBlock = $recentLines ? implode("\n", $recentLines) : '  Aucune transaction.';

        $fullName = $user->getFullName() ?? ('Utilisateur #' . $user->getId());
        $ageLine  = $age !== null ? "Âge : {$age} ans" : 'Âge : inconnu';
        $accLine  = $accountAgeDays !== null ? "Âge du compte : {$accountAgeDays} jours" : 'Âge du compte : inconnu';

        $prompt = <<<PROMPT
Tu es AI Fraud Shield (MindBloom). Réponds UNIQUEMENT en JSON valide, sans markdown.

Utilisateur : {$fullName} | {$ageLine} | {$accLine}
Wallet : solde {$wallet->getBalance()} TND, statut {$wallet->getStatus()}
Recharges : {$totalRecharges} TND ({$rechargeCount}) | Paiements : {$totalWithdrawals} TND ({$withdrawalCount})

Transactions récentes :
{$recentBlock}

JSON attendu (strictement) :
{"level":"Faible|Moyen|Élevé","score":0-100,"report":"3 phrases max en français : patterns observés, activités suspectes éventuelles, recommandation admin."}

Règles : Faible=0-33, Moyen=34-66, Élevé=67-100. Professionnel, sans émoji.
PROMPT;

        $raw  = $this->gemini->ask($prompt, temperature: 0.3, maxTokens: 2048, jsonMode: true);
        $data = $this->extractJson($raw);

        if (!$data || !isset($data['report'])) {
            // Try to salvage partial JSON — if level and score parsed, reconstruct
            if (is_array($data) && isset($data['level'], $data['score'])) {
                $data['report'] = 'Rapport indisponible (réponse tronquée).';
            } else {
                return [
                    'level'  => 'Inconnu',
                    'score'  => 0,
                    'color'  => '#95A5A6',
                    'report' => 'Réponse IA invalide : ' . json_last_error_msg() . ' — ' . mb_substr($raw, 0, 500),
                ];
            }
        }

        $level = (string)($data['level'] ?? 'Inconnu');
        $score = max(0, min(100, (int)($data['score'] ?? 0)));
        $color = match (true) {
            $level === 'Faible' => '#27AE60',
            $level === 'Moyen'  => '#F39C12',
            $level === 'Élevé'  => '#E74C3C',
            default             => '#8E44AD',
        };

        return [
            'level'  => $level,
            'score'  => $score,
            'color'  => $color,
            'report' => (string) $data['report'],
        ];
    }

    private function extractJson(string $text): ?array
    {
        $text = trim($text);

        // Strip markdown fences
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```\s*$/i', '', trim($text));
        $text = trim($text);

        // Try direct decode first (jsonMode should give clean JSON)
        $data = json_decode($text, true);
        if (is_array($data)) {
            return $data;
        }

        // Fallback: find the outermost { } using a depth counter
        $start = strpos($text, '{');
        if ($start === false) {
            return null;
        }

        $depth = 0;
        $len   = strlen($text);
        $inStr = false;
        $end   = null;

        for ($i = $start; $i < $len; $i++) {
            $ch = $text[$i];
            if ($ch === '\\' && $inStr) { $i++; continue; }
            if ($ch === '"') { $inStr = !$inStr; continue; }
            if ($inStr) continue;
            if ($ch === '{') $depth++;
            elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) { $end = $i; break; }
            }
        }

        if ($end === null) {
            return null;
        }

        $data = json_decode(substr($text, $start, $end - $start + 1), true);
        return is_array($data) ? $data : null;
    }
}
