<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\Produit;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/panier')]
#[IsGranted('ROLE_USER')]
class CartController extends AbstractController
{
    #[Route('', name: 'app_cart_index', methods: ['GET'])]
    public function index(CommandeRepository $commandeRepository): Response
    {
        $cartItems = $commandeRepository->findCartByUser($this->getUser());
        $subtotal = 0;
        foreach ($cartItems as $item) {
            $subtotal += $item->getTotal();
        }

        $tva = $subtotal * 0.07;
        $shipping = $subtotal > 0 ? 2 : 0;
        $total = $subtotal + $tva + $shipping;

        return $this->render('cart/index.html.twig', [
            'items' => $cartItems,
            'subtotal' => $subtotal,
            'tva' => $tva,
            'shipping' => $shipping,
            'total' => $total,
        ]);
    }

    #[Route('/ajouter/{id}', name: 'app_cart_add', methods: ['POST'])]
    public function add(Produit $produit, Request $request, CommandeRepository $commandeRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $quantite = $request->request->getInt('quantite', 1);

        if ($quantite <= 0) {
            $this->addFlash('error', 'Quantité invalide.');
            return $this->redirect($request->headers->get('referer', $this->generateUrl('app_produit_index')));
        }

        if ($produit->getQuantite() < $quantite) {
            $this->addFlash('error', 'Stock insuffisant.');
            return $this->redirect($request->headers->get('referer', $this->generateUrl('app_produit_index')));
        }

        // Check if item already in cart
        $existing = $commandeRepository->findOneBy([
            'user' => $user,
            'produit' => $produit,
            'facture' => null
        ]);

        if ($existing) {
            $existing->setQuantite($existing->getQuantite() + $quantite);
        } else {
            $item = new Commande();
            $item->setUser($user);
            $item->setProduit($produit);
            $item->setQuantite($quantite);
            $entityManager->persist($item);
        }

        $entityManager->flush();
        $this->addFlash('success', 'Produit ajouté au panier !');

        return $this->redirect($request->headers->get('referer', $this->generateUrl('app_produit_index')));
    }

    #[Route('/supprimer/{id}', name: 'app_cart_remove', methods: ['POST'])]
    public function remove(Commande $item, EntityManagerInterface $entityManager): Response
    {
        if ($item->getUser() !== $this->getUser() || $item->getFacture() !== null) {
            throw $this->createAccessDeniedException();
        }

        $entityManager->remove($item);
        $entityManager->flush();
        $this->addFlash('success', 'Produit retiré du panier.');

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/quantite/{id}', name: 'app_cart_update_quantity', methods: ['POST'])]
    public function updateQuantity(Commande $item, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($item->getUser() !== $this->getUser() || $item->getFacture() !== null) {
            throw $this->createAccessDeniedException();
        }

        $quantite = $request->request->getInt('quantite');
        if ($quantite > 0) {
            if ($item->getProduit()->getQuantite() < $quantite) {
                $this->addFlash('error', 'Stock insuffisant pour cette quantité.');
            } else {
                $item->setQuantite($quantite);
                $entityManager->flush();
            }
        }

        return $this->redirectToRoute('app_cart_index');
    }

    public function cartCount(CommandeRepository $commandeRepository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return new Response('0');
        }
        $cartItems = $commandeRepository->findCartByUser($user);
        $count = 0;
        foreach ($cartItems as $item) {
            $count += $item->getQuantite();
        }
        return new Response((string)$count);
    }
    #[Route('/ai-conseils', name: 'app_cart_ai', methods: ['GET'])]
    public function aiInsights(\App\Service\GroqService $groq, CommandeRepository $commandeRepository): Response
    {
        $cartItems = $commandeRepository->findCartByUser($this->getUser());
        if (empty($cartItems)) {
            return new Response("Ajoutez des produits au panier pour recevoir des conseils personnalisés.");
        }

        // Format items for the service
        $itemsData = array_map(fn($item) => ['produit' => $item->getProduit()], $cartItems);
        
        $insights = $groq->getCartInsights($itemsData);
        return new Response($insights);
    }
    #[Route('/ai-chat', name: 'app_cart_chat', methods: ['POST'])]
    public function aiChat(\Symfony\Component\HttpFoundation\Request $request, \App\Service\GroqService $groq): Response
    {
        $data = json_decode($request->getContent(), true);
        $message = $data['message'] ?? '';
        $history = $data['history'] ?? [];

        if (empty($message)) {
            return $this->json(['error' => 'Message vide'], 400);
        }

        $reply = $groq->chat($message, $history);
        return $this->json(['reply' => $reply]);
    }

    #[Route('/ai-recommandations', name: 'app_cart_ai_recommendations', methods: ['GET'])]
    public function aiRecommendations(\App\Service\GroqService $groq, \App\Repository\ProduitRepository $produitRepository, CommandeRepository $commandeRepository): Response
    {
        $cartItems = $commandeRepository->findCartByUser($this->getUser());
        if (empty($cartItems)) {
            return $this->json(['recommandations' => []]);
        }

        $allProducts = $produitRepository->findAll();
        $recommendationNames = $groq->getProductRecommendations($cartItems, $allProducts);
        
        $recommendedProducts = [];
        if (isset($recommendationNames['recommandations'])) {
            foreach ($recommendationNames['recommandations'] as $name) {
                $p = $produitRepository->findOneBy(['nom' => $name]);
                if ($p) {
                    $recommendedProducts[] = [
                        'id' => $p->getId(),
                        'nom' => $p->getNom(),
                        'prix' => $p->getPrix(),
                        'image' => $p->getImage(),
                    ];
                }
            }
        }

        return $this->json($recommendedProducts);
    }
}
