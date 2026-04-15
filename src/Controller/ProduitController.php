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
    public function index(ProduitRepository $produitRepository, Request $request): Response
    {
        $query = $request->query->get('q');
        $page = $request->query->getInt('page', 1);
        $limit = 6;

        $qb = $produitRepository->createQueryBuilder('p');

        if ($query) {
            $qb->where('p.nom LIKE :q OR p.description LIKE :q')
               ->setParameter('q', '%' . $query . '%');
        }

        $allProduits = $qb->getQuery()->getResult();
        $totalProduits = count($allProduits);
        $maxPages = ceil($totalProduits / $limit);

        $qb->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);
        
        $produits = $qb->getQuery()->getResult();

        return $this->render('produit/index.html.twig', [
            'produits' => $produits,
            'searchTerm' => $query,
            'currentPage' => $page,
            'maxPages' => $maxPages
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
            // Bad word filter
            $comment = $feedback->getCommentaire();
            if ($comment) {
                // censor the word 'louay'
                $comment = str_ireplace('louay', '****', $comment);
                $feedback->setCommentaire($comment);
            }

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

    #[Route('/avis/{id}/modifier', name: 'app_produit_feedback_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function editFeedback(Feedback $feedback, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($feedback->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier cet avis.');
        }

        $form = $this->createForm(FeedbackType::class, $feedback);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $comment = $feedback->getCommentaire();
            if ($comment) {
                // censor the word 'louay'
                $comment = str_ireplace('louay', '****', $comment);
                $feedback->setCommentaire($comment);
            }

            $entityManager->flush();
            $this->addFlash('success', 'Votre avis a été modifié !');
            return $this->redirectToRoute('app_produit_show', ['id' => $feedback->getProduit()->getId()]);
        }

        return $this->render('produit/feedback_edit.html.twig', [
            'feedback' => $feedback,
            'produit' => $feedback->getProduit(),
            'form' => $form->createView(),
        ]);
    }

    #[Route('/avis/{id}/supprimer', name: 'app_produit_feedback_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function deleteFeedback(Feedback $feedback, EntityManagerInterface $entityManager): Response
    {
        if ($feedback->getUser() !== $this->getUser()) {
             throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer cet avis.');
        }

        $produitId = $feedback->getProduit()->getId();
        $entityManager->remove($feedback);
        $entityManager->flush();

        $this->addFlash('success', 'Votre avis a été supprimé.');

        return $this->redirectToRoute('app_produit_show', ['id' => $produitId]);
    }
}
