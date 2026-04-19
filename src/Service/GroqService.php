<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GroqService
{
    private $httpClient;
    private $apiKey;

    public function __construct(HttpClientInterface $httpClient, string $groqApiKey)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $groqApiKey;
    }

    public function generateDescription(string $productName): string
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
                    'model' => 'llama-3.3-70b-versatile',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => "Tu es un rédacteur marketing expert pour MindBloom, une boutique de bien-être au naturel. Rédige une description produit attractive, concise (40-60 mots) et bienveillante."
                        ],
                        [
                            'role' => 'user',
                            'content' => "Rédige une description pour le produit suivant : " . $productName
                        ]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 200,
                ],
                'verify_peer' => false,
            ]);

            $data = $response->toArray();
            return $data['choices'][0]['message']['content'] ?? "Désolé, je n'ai pas pu générer de description.";

        } catch (\Exception $e) {
            return "Erreur Groq : " . substr($e->getMessage(), 0, 100);
        }
    }
}
