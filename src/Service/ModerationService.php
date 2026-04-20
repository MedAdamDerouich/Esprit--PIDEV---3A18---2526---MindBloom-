<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class ModerationService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private LoggerInterface $logger
    ) {}

    public function moderate(string $text): ModerationResult
    {
        try {
            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/moderations', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'omni-moderation-latest',
                    'input' => $text,
                ],
            ]);

            $data = $response->toArray();
            $result = $data['results'][0];

            $flaggedCategories = [];
            foreach ($result['categories'] as $category => $isFlagged) {
                if ($isFlagged) {
                    $flaggedCategories[] = $category;
                }
            }

            return new ModerationResult(
                $result['flagged'],
                $flaggedCategories
            );
        } catch (\Exception $e) {
            $this->logger->error('OpenAI Moderation API Error: ' . $e->getMessage());
            // Fallback: allow the comment if API is down to avoid blocking users
            return new ModerationResult(false, []);
        }
    }
}
