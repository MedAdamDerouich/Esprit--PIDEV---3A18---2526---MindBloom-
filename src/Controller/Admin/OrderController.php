<?php

namespace App\Controller\Admin;

use App\Entity\Facture;
use App\Repository\FactureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/commande')]
#[IsGranted('ROLE_ADMIN')]
class OrderController extends AbstractController
{
    #[Route('', name: 'app_admin_order_index', methods: ['GET'])]
    public function index(FactureRepository $factureRepository): Response
    {
        $factures = $factureRepository->findBy([], ['dateFacture' => 'DESC']);

        // Defensive check for missing products
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

        return $this->render('admin/order/index.html.twig', [
            'factures' => $factures,
        ]);
    }

    #[Route('/expedier/{id}', name: 'app_admin_order_ship', methods: ['POST'])]
    public function ship(Facture $facture, EntityManagerInterface $entityManager): Response
    {
        $facture->setStatutLivraison('ENVOYE');
        $entityManager->flush();

        $this->addFlash('success', 'Commande marquée comme expédiée.');
        return $this->redirectToRoute('app_admin_order_index');
    }

    #[Route('/annuler/{id}', name: 'app_admin_order_cancel', methods: ['POST'])]
    public function cancel(Facture $facture, EntityManagerInterface $entityManager): Response
    {
        $facture->setStatutLivraison('ANNULE');
        
        // Restaurer le stock
        foreach ($facture->getCommandes() as $commande) {
            $produit = $commande->getProduit();
            $produit->setQuantite($produit->getQuantite() + $commande->getQuantite());
        }

        $entityManager->flush();

        $this->addFlash('success', 'Commande annulée et stock restauré.');
        return $this->redirectToRoute('app_admin_order_index');
    }
}
