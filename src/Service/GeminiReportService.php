<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeminiReportService
{
    private $client;
    private $apiKey;

    public function __construct(HttpClientInterface $client, string $apiKey = 'YOUR_API_KEY_HERE')
    {
        $this->client = $client;
        $this->apiKey = $apiKey;
    }

    public function genererRapport(string $prompt): string
    {
        if (empty($this->apiKey) || $this->apiKey === 'YOUR_API_KEY_HERE') {
            return "Erreur: Clé API Google Gemini manquante (GEMINI_API_KEY_3).";
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=' . $this->apiKey;

        try {
            $response = $this->client->request('POST', $url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'topK' => 40,
                        'topP' => 0.95,
                        'maxOutputTokens' => 1024,
                    ]
                ]
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                return "Erreur lors de l'appel à l'API Gemini (Code HTTP $statusCode): " . $response->getContent(false);
            }

            $data = $response->toArray();
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return $data['candidates'][0]['content']['parts'][0]['text'];
            }

            return "Erreur: format de réponse inattendu de l'API Gemini.";

        } catch (\Exception $e) {
            return "Erreur : " . $e->getMessage();
        }
    }
}
