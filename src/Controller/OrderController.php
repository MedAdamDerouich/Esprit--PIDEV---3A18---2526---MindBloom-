<?php

namespace App\Controller;

use App\Entity\Facture;
use App\Repository\CommandeRepository;
use App\Repository\FactureRepository;
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
    public function checkout(Request $request, CommandeRepository $commandeRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $cartItems = $commandeRepository->findCartByUser($user);

        if (empty($cartItems)) {
            $this->addFlash('warning', 'Votre panier est vide.');
            return $this->redirectToRoute('app_produit_index');
        }

        if ($request->isMethod('POST')) {
            $subtotal = 0;
            foreach ($cartItems as $item) {
                $subtotal += $item->getTotal();
            }

            $tva = $subtotal * 0.07;
            $shipping = 2;
            $total = $subtotal + $tva + $shipping;

            $facture = new Facture();
            $facture->setUser($user);
            $facture->setMontantTotal($total);
            $facture->setAdresseLivraison($request->request->get('adresse'));
            $facture->setTypePaiement($request->request->get('paiement'));
            
            foreach ($cartItems as $item) {
                $facture->addCommande($item);
                // Reduire le stock
                $produit = $item->getProduit();
                $produit->setQuantite($produit->getQuantite() - $item->getQuantite());
            }

            $entityManager->persist($facture);
            $entityManager->flush();

            $this->addFlash('success', 'Votre commande a été passée avec succès !');
            return $this->redirectToRoute('app_order_history');
        }

        $subtotal = array_reduce($cartItems, fn($c, $i) => $c + $i->getTotal(), 0);
        $tva = $subtotal * 0.07;
        $shipping = 2;
        $total = $subtotal + $tva + $shipping;

        return $this->render('order/checkout.html.twig', [
            'items' => $cartItems,
            'subtotal' => $subtotal,
            'tva' => $tva,
            'shipping' => $shipping,
            'total' => $total
        ]);
    }

    #[Route('/historique', name: 'app_order_history', methods: ['GET'])]
    public function history(FactureRepository $factureRepository): Response
    {
        return $this->render('order/history.html.twig', [
            'factures' => $factureRepository->findBy(['user' => $this->getUser()], ['dateFacture' => 'DESC'])
        ]);
    }

    #[Route('/{id}', name: 'app_order_show', methods: ['GET'])]
    public function show(Facture $facture): Response
    {
        if ($facture->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $total = $facture->getMontantTotal();
        $shipping = 2;
        $subtotal = ($total - $shipping) / 1.07;
        $tva = $subtotal * 0.07;

        return $this->render('order/show.html.twig', [
            'facture' => $facture,
            'subtotal' => $subtotal,
            'tva' => $tva,
            'shipping' => $shipping
        ]);
    }
}
