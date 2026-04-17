<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Gère les codes de réinitialisation de mot de passe.
 * Port PHP du PasswordResetService.java (MindBloom-USERetRDV).
 * Stockage : cache Symfony (TTL 15 min).
 */
class PasswordResetService
{
    private const CODE_LENGTH    = 6;
    private const EXPIRY_SECONDS = 15 * 60;
    private const CACHE_PREFIX   = 'pwd_reset_';

    public function __construct(private readonly CacheInterface $cache) {}

    /** Génère un code à 6 chiffres et le stocke pour cet email. */
    public function generateCode(string $email): string
    {
        $key = $this->key($email);
        $this->cache->delete($key);

        $code = '';
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= random_int(0, 9);
        }

        $this->cache->get($key, function (ItemInterface $item) use ($code) {
            $item->expiresAfter(self::EXPIRY_SECONDS);
            return $code;
        });

        return $code;
    }

    /** Vérifie que le code est valide et non expiré. */
    public function validateCode(string $email, string $code): bool
    {
        $stored = $this->cache->get($this->key($email), fn (ItemInterface $i) => null);
        return $stored !== null && hash_equals($stored, trim($code));
    }

    /** Supprime le code après utilisation. */
    public function consumeCode(string $email): void
    {
        $this->cache->delete($this->key($email));
    }

    private function key(string $email): string
    {
        return self::CACHE_PREFIX . sha1(strtolower(trim($email)));
    }
}
