<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class GeminiService
{
    private $httpClient;
    private $apiKey;

    public function __construct(HttpClientInterface $httpClient, ParameterBagInterface $params)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $params->get('app.gemini_api_key');
    }

    public function generateResponse(string $prompt): string
    {
        if (!$this->apiKey || $this->apiKey === 'YOUR_GEMINI_API_KEY_HERE') {
            return "Veuillez configurer votre clé API Gemini dans le fichier .env pour activer cette fonctionnalité.";
        }

        // List of models to try if one fails with 404 (Ordered by stability/performance)
        $models = [
            'gemini-2.5-flash-lite', 
            'gemini-1.5-flash', 
            'gemini-1.5-flash-latest', 
            'gemini-pro', 
            'gemini-pro-latest',
            'gemini-3.1-flash-lite-preview'
        ];
        $lastError = "";

        foreach ($models as $modelName) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/" . $modelName . ":generateContent";

            try {
                $response = $this->httpClient->request('POST', $url, [
                    'headers' => [
                        'x-goog-api-key' => $this->apiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $prompt]
                                ]
                            ]
                        ]
                    ]
                ]);

                if ($response->getStatusCode() === 200) {
                    $data = $response->toArray();
                    return $data['candidates'][0]['content']['parts'][0]['text'] ?? "L'IA n'a pas pu générer de réponse.";
                }
                
                if ($response->getStatusCode() !== 404) {
                    $lastError = "Erreur " . $response->getStatusCode();
                    break;
                }
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                if (str_contains($lastError, '404')) {
                    continue; // Try next model
                }
                break;
            }
        }

        return "Désolé, une erreur technique est survenue (404 persistent). Vérifiez que le modèle est disponible pour votre clé. Dernière erreur : " . $lastError;
    }

    /**
     * Get JSON recommendations for products
     */
    public function getProductRecommendations(array $currentCartItems, array $allAvailableProducts): array
    {
        $cartNames = array_map(fn($item) => $item->getProduit()->getNom(), $currentCartItems);
        $availableNames = array_map(fn($p) => $p->getNom(), $allAvailableProducts);
        
        // Remove items already in cart from available list
        $filteredAvailable = array_diff($availableNames, $cartNames);
        
        $prompt = "En tant qu'expert en pharmacie et parapharmacie pour MindBloom, analyse ce panier : [" . implode(", ", $cartNames) . "].\n" .
                  "Parmi ces produits disponibles : [" . implode(", ", array_slice($filteredAvailable, 0, 50)) . "], suggère 2 à 3 produits COMPLÉMENTAIRES pertinents.\n" .
                  "Réponds TOUJOURS au format JSON suivant : { \"recommandations\": [\"NomProduit1\", \"NomProduit2\"] }.\n" .
                  "Ne suggère que des noms de produits présents dans la liste des produits disponibles.";

        $jsonResponse = $this->generateJsonResponse($prompt);
        
        return json_decode($jsonResponse, true) ?? ['recommandations' => []];
    }

    private function generateJsonResponse(string $prompt): string
    {
        if (!$this->apiKey || $this->apiKey === 'YOUR_GEMINI_API_KEY_HERE') {
            return "{\"recommandations\": []}";
        }

        $models = ['gemini-2.5-flash-lite', 'gemini-1.5-flash', 'gemini-pro'];
        
        foreach ($models as $modelName) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/" . $modelName . ":generateContent";

            try {
                $response = $this->httpClient->request('POST', $url, [
                    'headers' => [
                        'x-goog-api-key' => $this->apiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'contents' => [['parts' => [['text' => $prompt]]]],
                        'generationConfig' => [
                            'response_mime_type' => 'application/json'
                        ]
                    ]
                ]);

                if ($response->getStatusCode() === 200) {
                    $data = $response->toArray();
                    return $data['candidates'][0]['content']['parts'][0]['text'] ?? "{\"recommandations\": []}";
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        return "{\"recommandations\": []}";
    }

    public function getCartInsights(array $items): string
    {
        $products = array_map(fn($i) => "- " . $i['produit']->getNom() . " (Prix: " . $i['produit']->getPrix() . " DT)", $items);
        $productsStr = implode("\n", $products);

        $prompt = "En tant qu'assistant bien-être MindBloom, analyse ce panier d'achat :\n" . $productsStr . 
                  "\nDonne 3 conseils de santé ou astuces d'utilisation pour ces produits. Sois concis et inspirant.";

        return $this->generateResponse($prompt);
    }

    public function getOrderAnalytics(array $orders): string
    {
        $stats = "Nombre total de commandes : " . count($orders) . "\n";
        $totalRev = array_sum(array_map(fn($o) => $o->getMontantTotal(), $orders));
        $stats .= "Chiffre d'affaires : " . $totalRev . " DT\n";

        $prompt = "En tant qu'expert en business intelligence, analyse ces statistiques de ventes pour MindBloom :\n" . $stats .
                  "\nDonne une analyse rapide des tendances et suggère une action marketing pour booster les ventes le mois prochain. Sois professionnel et stratégique.";

        return $this->generateResponse($prompt);
    }

    public function getStockAnalysis(array $lowStockItems): string
    {
        if (empty($lowStockItems)) {
            return "Tous vos stocks sont optimaux. Aucune action immédiate n'est requise.";
        }

        $itemsStr = implode("\n", array_map(fn($p) => "- " . $p->getNom() . " (Stock actuel: " . $p->getQuantite() . ", Seuil: " . $p->getStockSeuil() . ")", $lowStockItems));

        $prompt = "En tant qu'expert en logistique pharmaceutique pour MindBloom, analyse ces alertes de stock critique :\n" . $itemsStr .
                  "\nDonne un avis sur les priorités de réapprovisionnement et suggère si certains produits devraient être mis en avant ou commandés en urgence.";

        return $this->generateResponse($prompt);
    }
}
