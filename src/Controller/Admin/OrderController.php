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
        return $this->render('admin/order/index.html.twig', [
            'factures' => $factureRepository->findBy([], ['dateFacture' => 'DESC']),
        ]);
    }

    #[Route('/{id}/expedier', name: 'app_admin_order_ship', methods: ['POST'])]
    public function ship(Facture $facture, EntityManagerInterface $entityManager): Response
    {
        $facture->setStatutLivraison('ENVOYE');
        $entityManager->flush();
        $this->addFlash('success', 'Commande marquée comme expédiée.');

        return $this->redirectToRoute('app_admin_order_index');
    }

    #[Route('/{id}/annuler', name: 'app_admin_order_cancel', methods: ['POST'])]
    public function cancel(Facture $facture, EntityManagerInterface $entityManager): Response
    {
        $facture->setStatutLivraison('ANNULE');
        // Restore stock? Usually yes
        foreach ($facture->getCommandes() as $item) {
            $produit = $item->getProduit();
            $produit->setQuantite($produit->getQuantite() + $item->getQuantite());
        }
        $entityManager->flush();
        $this->addFlash('success', 'Commande annulée et stock restauré.');

        return $this->redirectToRoute('app_admin_order_index');
    }
}
