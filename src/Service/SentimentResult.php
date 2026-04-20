<?php

namespace App\Service;

readonly class SentimentResult
{
    public function __construct(
        public string $label,
        public int $score
    ) {}

    public function getEmoji(): string
    {
        return match ($this->label) {
            'positive' => '😊',
            'negative' => '😞',
            default    => '😐',
        };
    }
}
