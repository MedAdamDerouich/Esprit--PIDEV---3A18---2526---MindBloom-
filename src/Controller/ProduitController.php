<?php

namespace App\Controller;

use App\Entity\Feedback;
use App\Entity\Produit;
use App\Form\FeedbackType;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/produit')]
class ProduitController extends AbstractController
{
    #[Route('', name: 'app_produit_index', methods: ['GET'])]
    public function index(ProduitRepository $produitRepository): Response
    {
        return $this->render('produit/index.html.twig', [
            'produits' => $produitRepository->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'app_produit_show', methods: ['GET'])]
    public function show(Produit $produit): Response
    {
        return $this->render('produit/show.html.twig', [
            'produit' => $produit,
        ]);
    }

    #[Route('/{id}/avis/nouveau', name: 'app_produit_feedback_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function addFeedback(Produit $produit, Request $request, EntityManagerInterface $entityManager): Response
    {
        $feedback = new Feedback();
        $form = $this->createForm(FeedbackType::class, $feedback);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $feedback->setProduit($produit);
            $feedback->setUser($this->getUser());
            $feedback->setDateFeedback(new \DateTime());

            $entityManager->persist($feedback);
            $entityManager->flush();

            $this->addFlash('success', 'Votre avis a été ajouté avec succès !');

            return $this->redirectToRoute('app_produit_show', ['id' => $produit->getId()]);
        }

        return $this->render('produit/feedback_new.html.twig', [
            'produit' => $produit,
            'form' => $form->createView(),
        ]);
    }
}
