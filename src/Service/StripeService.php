<?php

namespace App\Service;

use Stripe\Charge;
use Stripe\Stripe;

class StripeService
{
    private const EXCHANGE_RATE_TND_USD = 0.32;

    public function __construct(private string $secretKey) {}

    /**
     * Charge a card via Stripe test token (tok_visa).
     * Mirrors the Java AddEditWalletController Stripe logic exactly.
     *
     * @throws \Stripe\Exception\ApiErrorException
     * @throws \RuntimeException
     */
    public function charge(float $amountTnd, string $description): Charge
    {
        Stripe::setApiKey($this->secretKey);

        $amountUsd   = $amountTnd * self::EXCHANGE_RATE_TND_USD;
        $amountCents = (int) round($amountUsd * 100);

        if ($amountCents < 50) {
            throw new \RuntimeException('Montant trop faible (minimum ~2 DT).');
        }

        return Charge::create([
            'amount'      => $amountCents,
            'currency'    => 'usd',
            'source'      => 'tok_visa',
            'description' => $description,
        ]);
    }
}
