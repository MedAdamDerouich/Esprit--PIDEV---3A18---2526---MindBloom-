<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class RecaptchaService
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $secretKey,
    ) {}

    /**
     * Returns true if the reCAPTCHA v2 token is valid (checkbox completed).
     * Returns true (fail-open) if the Google API is unreachable or times out.
     * Returns false if the token is missing or invalid.
     */
    public function isTokenValid(string $token): bool
    {
        if ($this->secretKey === '' || str_contains($this->secretKey, 'your_recaptcha')) {
            return true;
        }

        if ($token === '') {
            return false; // v2: empty token means user did not complete the checkbox
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

            return ($data['success'] ?? false) === true;

        } catch (\Throwable) {
            return true; // Fail-open on network errors
        }
    }
}
