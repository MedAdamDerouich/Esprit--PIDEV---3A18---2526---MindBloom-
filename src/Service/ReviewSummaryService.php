<?php

namespace App\Service;

use App\Entity\Produit;
use App\Repository\FeedbackRepository;
use App\Service\GeminiService;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class ReviewSummaryService
{
    public function __construct(
        private GeminiService $geminiService,
        private CacheInterface $cache,
        private FeedbackRepository $feedbackRepository
    ) {}

    public function summarize(Produit $produit): string
    {
        $cacheKey = 'product_summary_v2_' . $produit->getId();

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($produit) {
            $item->expiresAfter(3600);

            $feedbacks = $this->feedbackRepository->findApprovedByProduct($produit, 50);

            if (empty($feedbacks)) {
                return "Aucun avis disponible pour ce produit.";
            }

            $comments = "";
            foreach ($feedbacks as $feedback) {
                $comments .= "- " . $feedback->getCommentaire() . "\n";
            }

            $prompt = "En tant qu'assistant MindBloom, résume ces avis clients pour le produit '" . $produit->getNom() . "'. Fais un résumé synthétique de 3 à 5 phrases en français soulignant les points forts et faibles.\n\nAvis :\n" . $comments;

            try {
                return $this->geminiService->generateResponse($prompt);
            } catch (\Exception $e) {
                return "Le résumé IA est momentanément indisponible.";
            }
        });
    }

    public function invalidateCache(Produit $produit): void
    {
        $this->cache->delete('product_summary_v2_' . $produit->getId());
    }
}
