<?php

namespace App\Service\AI;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Base service to interact with the Google Gemini API.
 * Ported from Java AIService.java (MindBloom-USERetRDV).
 */
class GeminiService
{
    private const MODEL   = 'gemini-flash-latest';
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/' . self::MODEL . ':generateContent';

    public function __construct(
        private HttpClientInterface $http,
        #[Autowire('%env(GEMINI_API_KEY)%')] private string $apiKey,
    ) {}

    /**
     * Sends a prompt to Gemini and returns the text response.
     */
    public function ask(string $prompt, float $temperature = 0.7, int $maxTokens = 1024): string
    {
        try {
            $response = $this->http->request('POST', self::API_URL . '?key=' . $this->apiKey, [
                'json' => [
                    'contents'        => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'temperature'     => $temperature,
                        'maxOutputTokens' => $maxTokens,
                    ],
                ],
            ]);

            $data = $response->toArray();

            return $data['candidates'][0]['content']['parts'][0]['text']
                ?? 'Pas de réponse valide de l\'IA.';

        } catch (\Throwable $e) {
            return 'Erreur lors de l\'appel à l\'IA : ' . $e->getMessage();
        }
    }
}
