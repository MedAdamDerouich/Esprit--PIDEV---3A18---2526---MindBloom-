<?php

namespace App\Service;

class SentimentService
{
    public function __construct() {}

    public function analyzeBatch(array $comments): array
    {
        $results = [];
        foreach ($comments as $id => $text) {
            $results[$id] = $this->analyze($text);
        }
        return $results;
    }

    public function analyze(string $text): SentimentResult
    {
        $text = mb_strtolower($text);
        
        $positiveWords = ['bon', 'excellent', 'super', 'incroyable', 'top', 'aime', 'parfait', 'merveilleux', 'génial', 'bravo', 'recommande', 'rapide', 'efficace', 'satisfait', 'merci', 'wow', 'magnifique', 'propre', 'qualité'];
        $negativeWords = ['mauvais', 'nul', 'déçu', 'problème', 'lent', 'cher', 'naze', 'arnaque', 'pire', 'horrible', 'casse', 'manque', 'nul', 'mécontent', 'regrette', 'évitez', 'catastrophe', 'bas', 'médiocre', 'honteux'];

        $posCount = 0;
        $negCount = 0;

        foreach ($positiveWords as $word) {
            if (str_contains($text, $word)) {
                $posCount++;
            }
        }

        foreach ($negativeWords as $word) {
            if (str_contains($text, $word)) {
                $negCount++;
            }
        }

        if ($posCount > $negCount) {
            return new SentimentResult('positive', 90);
        } elseif ($negCount > $posCount) {
            return new SentimentResult('negative', 10);
        }

        return new SentimentResult('neutral', 50);
    }
}
