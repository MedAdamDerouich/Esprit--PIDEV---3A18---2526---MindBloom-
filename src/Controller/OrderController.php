<?php

namespace App\Controller;

use App\Entity\Facture;
use App\Repository\CommandeRepository;
use App\Repository\FactureRepository;
use App\Service\WalletService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/commande')]
#[IsGranted('ROLE_USER')]
class OrderController extends AbstractController
{
    #[Route('/passer', name: 'app_order_checkout', methods: ['GET', 'POST'])]
    public function checkout(Request $request, CommandeRepository $commandeRepository, EntityManagerInterface $entityManager, WalletService $walletService): Response
    {
        $user = $this->getUser();
        $cartItems = $commandeRepository->findCartByUser($user);

        if (empty($cartItems)) {
            $this->addFlash('warning', 'Votre panier est vide.');
            return $this->redirectToRoute('app_produit_index');
        }

        $subtotal = array_reduce($cartItems, fn($c, $i) => $c + $i->getTotal(), 0);
        $tva      = $subtotal * 0.07;
        $shipping = 2;
        $total    = $subtotal + $tva + $shipping;

        if ($request->isMethod('POST')) {
            $paiement = $request->request->get('paiement');

            // ── Wallet payment ──────────────────────────────────────────
            if ($paiement === 'WALLET') {
                $success = $walletService->deduct(
                    $user,
                    $total,
                    'Commande produits MindBloom'
                );

                if (!$success) {
                    $this->addFlash('error', sprintf(
                        'Solde insuffisant. Votre wallet contient %.2f DT et le total est %.2f DT.',
                        $walletService->getBalance($user),
                        $total
                    ));
                    return $this->redirectToRoute('app_order_checkout');
                }
            }

            $facture = new Facture();
            $facture->setUser($user);
            $facture->setMontantTotal($total);
            $facture->setAdresseLivraison($request->request->get('adresse'));
            $facture->setTypePaiement($paiement);

            foreach ($cartItems as $item) {
                $facture->addCommande($item);
                $produit = $item->getProduit();
                $produit->setQuantite($produit->getQuantite() - $item->getQuantite());
            }

            $entityManager->persist($facture);
            $entityManager->flush();

            $this->addFlash('success', 'Votre commande a été passée avec succès !');
            return $this->redirectToRoute('app_order_history');
        }

        return $this->render('order/checkout.html.twig', [
            'items'          => $cartItems,
            'subtotal'       => $subtotal,
            'tva'            => $tva,
            'shipping'       => $shipping,
            'total'          => $total,
            'walletBalance'  => $walletService->getBalance($user),
        ]);
    }

    #[Route('/historique', name: 'app_order_history', methods: ['GET'])]
    public function history(FactureRepository $factureRepository, Request $request): Response
    {
        $query = $request->query->get('q');
        if ($query) {
            $factures = $factureRepository->searchByUser($query, $this->getUser());
        } else {
            $factures = $factureRepository->findBy(['user' => $this->getUser()], ['dateFacture' => 'DESC']);
        }
        
        // Ensure commands with missing products don't crash the list rendering
        foreach ($factures as $facture) {
            foreach ($facture->getCommandes() as $commande) {
                try {
                    if ($commande->getProduit() && $commande->getProduit()->getNom()) {
                        // OK
                    } else {
                        $facture->getCommandes()->removeElement($commande);
                    }
                } catch (\Doctrine\ORM\EntityNotFoundException $e) {
                    $facture->getCommandes()->removeElement($commande);
                }
            }
        }

        return $this->render('order/history.html.twig', [
            'factures' => $factures,
            'searchTerm' => $query,
        ]);
    }

    #[Route('/{id}', name: 'app_order_show', methods: ['GET'])]
    public function show(Facture $facture): Response
    {
        if ($facture->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        // Filter valid commands (where product still exists)
        $validCommands = [];
        foreach ($facture->getCommandes() as $commande) {
            try {
                // Accessing a product property to trigger proxy loading
                if ($commande->getProduit() && $commande->getProduit()->getNom()) {
                    $validCommands[] = $commande;
                } else {
                    $facture->getCommandes()->removeElement($commande);
                }
            } catch (\Doctrine\ORM\EntityNotFoundException $e) {
                // remove from memory to prevent crash in template if we iterate over collection
                $facture->getCommandes()->removeElement($commande);
            }
        }

        $total = $facture->getMontantTotal();
        $shipping = 2;
        $subtotal = ($total - $shipping) / 1.07;
        $tva = $subtotal * 0.07;

        return $this->render('order/show.html.twig', [
            'facture' => $facture,
            'commandes' => $validCommands, // Pass filtered commands
            'subtotal' => $subtotal,
            'tva' => $tva,
            'shipping' => $shipping
        ]);
    }
}
