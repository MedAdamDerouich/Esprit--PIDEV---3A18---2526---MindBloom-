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
}
