<?php

namespace App\Service;

readonly class ModerationResult
{
    public function __construct(
        public bool $flagged,
        public array $categories
    ) {}

    public function getReason(): string
    {
        return implode(', ', $this->categories);
    }
}
