<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EvenementAiService
{
    private const GEMINI_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=';
    private const MAX_CHARS = 240;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly ?string $geminiApiKey,
    ) {}

    public function genererDescription(string $titre, string $lieu, string $organisateur, string $descBase): string
    {
        if (($this->geminiApiKey ?? '') === '') {
            throw new \RuntimeException('GEMINI_API_KEY non configurée.');
        }

        $prompt = $this->buildPrompt($titre, $lieu, $organisateur, $descBase);

        $response = $this->httpClient->request('POST', self::GEMINI_URL . $this->geminiApiKey, [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'contents' => [[
                    'parts' => [[
                        'text' => $prompt,
                    ]],
                ]],
            ],
            'timeout' => 30,
        ]);

        if ($response->getStatusCode() !== 200) {
            $body = $response->getContent(false);
            $this->logger->error('Erreur API Gemini (event description): ' . $body);
            throw new \RuntimeException('Erreur API Gemini HTTP ' . $response->getStatusCode());
        }

        $data = $response->toArray(false);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (!is_string($text) || trim($text) === '') {
            throw new \RuntimeException('Réponse Gemini vide.');
        }

        $text = trim($text);
        if (mb_strlen($text) > self::MAX_CHARS) {
            $text = mb_substr($text, 0, self::MAX_CHARS - 3) . '...';
        }

        return $text;
    }

    private function buildPrompt(string $titre, string $lieu, string $organisateur, string $descBase): string
    {
        $lines = [];
        $lines[] = 'Tu es un assistant specialise dans la redaction de descriptions d\'evenements pour une plateforme de bien-etre mental appelee MindBloom.';
        $lines[] = '';
        $lines[] = 'Genere une description professionnelle, engageante et bienveillante pour l\'evenement suivant :';

        if ($titre !== '') {
            $lines[] = '- Titre : ' . $titre;
        }
        if ($lieu !== '') {
            $lines[] = '- Lieu : ' . $lieu;
        }
        if ($organisateur !== '') {
            $lines[] = '- Organisateur : ' . $organisateur;
        }
        if ($descBase !== '') {
            $lines[] = '- Description de base : ' . $descBase;
        }

        $lines[] = '';
        $lines[] = 'Contraintes :';
        $lines[] = '- La description doit faire entre 150 et 240 caracteres maximum.';
        $lines[] = '- Utilise un ton chaleureux, positif et professionnel.';
        $lines[] = '- Inclus un appel a l\'action a la fin.';
        $lines[] = '- Reponds uniquement avec la description, sans titre ni explication.';

        return implode("\n", $lines);
    }
}
