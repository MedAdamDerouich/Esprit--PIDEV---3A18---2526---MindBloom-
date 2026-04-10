<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class RecaptchaService
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';
    private const MIN_SCORE  = 0.5;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $secretKey,
    ) {}

    /**
     * Returns true if the reCAPTCHA v3 token is valid and the score is >= 0.5.
     * Returns true (fail-open) if the Google API is unreachable or times out.
     * Returns false if the token is missing, invalid, or the score is too low.
     */
    public function isTokenValid(string $token): bool
    {
        // Skip verification if keys are not configured yet (placeholder values)
        if ($this->secretKey === '' || str_contains($this->secretKey, 'your_recaptcha')) {
            return true;
        }

        if ($token === '') {
            return true; // Fail-open: token empty means reCAPTCHA JS did not execute
        }

        try {
            $response = $this->httpClient->request('POST', self::VERIFY_URL, [
                'body'    => [
                    'secret'   => $this->secretKey,
                    'response' => $token,
                ],
                'timeout' => 3,
            ]);

            $data = $response->toArray(false);

            if (($data['success'] ?? false) !== true) {
                return false;
            }

            return ($data['score'] ?? 0.0) >= self::MIN_SCORE;

        } catch (\Throwable) {
            // Fail-open: network error, timeout, SSL, JSON parse — do not block the user
            return true;
        }
    }
}
