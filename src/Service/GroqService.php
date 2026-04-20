<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GroqService
{
    private $httpClient;
    private $apiKey;
    private const MODEL = 'llama-3.3-70b-versatile';

    public function __construct(HttpClientInterface $httpClient, string $groqApiKey)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $groqApiKey;
    }

    public function generateResponse(string $prompt, string $systemPrompt = "Tu es un assistant expert pour MindBloom, une boutique de bien-être et parapharmacie au naturel."): string
    {
        if (!$this->apiKey) {
            return "Clé API Groq non configurée.";
        }

        try {
            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => self::MODEL,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 1024,
                ],
                'verify_peer' => false,
            ]);

            $data = $response->toArray();
            return $data['choices'][0]['message']['content'] ?? "Désolé, je n'ai pas pu générer de réponse.";

        } catch (\Exception $e) {
            return "Erreur Groq : " . substr($e->getMessage(), 0, 100);
        }
    }

    public function chat(string $userMessage, array $history = []): string
    {
        $systemPrompt = "Tu es un assistant expert pour MindBloom. " .
                        "Aide l'utilisateur avec ses produits de bien-être, ses commandes ou sa santé au naturel. " .
                        "Sois chaleureux, concis et professionnel.";
        
        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($history as $msg) {
            $messages[] = $msg;
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        try {
            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => self::MODEL,
                    'messages' => $messages,
                    'temperature' => 0.7,
                    'max_tokens' => 512,
                ],
                'verify_peer' => false,
            ]);

            $data = $response->toArray();
            return $data['choices'][0]['message']['content'] ?? "Désolé, je n'ai pas pu générer de réponse.";
        } catch (\Exception $e) {
            return "Une erreur est survenue, réessayez dans un instant.";
        }
    }

    public function generateJsonResponse(string $prompt, string $systemPrompt = "Tu es un assistant expert pour MindBloom. Réponds UNIQUEMENT en format JSON."): array
    {
        if (!$this->apiKey) {
            return [];
        }

        try {
            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => self::MODEL,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.2,
                ],
                'verify_peer' => false,
            ]);

            return $response->toArray();

        } catch (\Exception $e) {
            return [];
        }
    }

    public function generateDescription(string $productName): string
    {
        return $this->generateResponse(
            "Rédige une description attractive pour : " . $productName,
            "Tu es un rédacteur marketing expert pour MindBloom. Rédige une description produit concise (40-60 mots) et bienveillante."
        );
    }

    /**
     * Replaces Gemini getProductRecommendations
     */
    public function getProductRecommendations(array $currentCartItems, array $allAvailableProducts): array
    {
        $cartNames = array_map(fn($item) => $item->getProduit()->getNom(), $currentCartItems);
        $availableNames = array_map(fn($p) => $p->getNom(), $allAvailableProducts);
        $filteredAvailable = array_diff($availableNames, $cartNames);
        
        $prompt = "Analyse ce panier : [" . implode(", ", $cartNames) . "].\n" .
                  "Suggère 2 à 3 produits COMPLÉMENTAIRES pertinents parmi : [" . implode(", ", array_slice($filteredAvailable, 0, 50)) . "].\n" .
                  "Format JSON: { \"recommandations\": [\"NomProduit1\", \"NomProduit2\"] }";

        $data = $this->generateJsonResponse($prompt, "Tu es un expert en parapharmacie. Réponds uniquement en JSON.");
        
        if (isset($data['choices'][0]['message']['content'])) {
            return json_decode($data['choices'][0]['message']['content'], true) ?? ['recommandations' => []];
        }

        return ['recommandations' => []];
    }

    public function getCartInsights(array $items): string
    {
        $products = array_map(fn($i) => "- " . $i['produit']->getNom(), $items);
        $prompt = "Analyse ce panier :\n" . implode("\n", $products) . "\nDonne 3 conseils de santé concis.";
        return $this->generateResponse($prompt);
    }

    public function getOrderAnalytics(array $orders): string
    {
        $stats = "Commandes: " . count($orders) . " | Revenu: " . array_sum(array_map(fn($o) => $o->getMontantTotal(), $orders)) . " DT";
        return $this->generateResponse("Analyse ces stats et donne une stratégie marketing: " . $stats, "Tu es un expert en business intelligence stratégique.");
    }

    public function getStockAnalysis(array $lowStockItems): string
    {
        if (empty($lowStockItems)) return "Stocks optimaux.";
        $itemsStr = implode("\n", array_map(fn($p) => "- " . $p->getNom() . " (Stock: " . $p->getQuantite() . ")", $lowStockItems));
        return $this->generateResponse("Analyse ces ruptures de stock prioritaires:\n" . $itemsStr, "Tu es un expert en logistique pharmaceutique.");
    }

    /**
     * Replicates Java handleMarketingAnalysis
     */
    public function analyzeMarketingFeedback(array $allFeedbacks): array
    {
        if (empty($allFeedbacks)) return [];

        $comments = array_map(fn($f) => "Note: " . $f->getNote() . "/5 - Commentaire: " . $f->getCommentaire(), $allFeedbacks);
        $commentsStr = implode("\n", array_slice($comments, 0, 50)); // Limit to first 50 for context window

        $prompt = "Analyse ces avis clients pour MindBloom :\n" . $commentsStr . "\n" .
                  "Réponds TOUJOURS en JSON avec ce format exact :\n" .
                  "{\n" .
                  "  \"pointsForts\": {\"Thème1\": 15, \"Thème2\": 10},\n" .
                  "  \"problemes\": {\"Thème1\": 5, \"Thème2\": 3},\n" .
                  "  \"synthèse\": \"Texte de synthèse...\",\n" .
                  "  \"sentimentScore\": {\"positif\": 20, \"neutre\": 5, \"negatif\": 2}\n" .
                  "}";

        $data = $this->generateJsonResponse($prompt, "Tu es un expert en marketing et analyse de sentiment. Réponds uniquement en JSON.");
        
        if (isset($data['choices'][0]['message']['content'])) {
            return json_decode($data['choices'][0]['message']['content'], true) ?? [];
        }

        return [];
    }
}
