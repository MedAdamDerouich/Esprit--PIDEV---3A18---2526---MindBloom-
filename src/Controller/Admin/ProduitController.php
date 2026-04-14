<?php

namespace App\Controller\Admin;

use App\Entity\Produit;
use App\Form\ProduitType;
use App\Repository\ProduitRepository;
use App\Repository\FeedbackRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/produit')]
#[IsGranted('ROLE_ADMIN')]
class ProduitController extends AbstractController
{
    #[Route('', name: 'app_admin_produit_index', methods: ['GET'])]
    public function index(ProduitRepository $produitRepository, Request $request): Response
    {
        $q = $request->query->get('q');
        $tri = $request->query->get('tri', 'default');

        $qb = $produitRepository->createQueryBuilder('p');

        if ($q) {
            $qb->andWhere('p.nom LIKE :q OR p.description LIKE :q')
               ->setParameter('q', '%' . $q . '%');
        }

        if ($tri === 'prix_asc') {
            $qb->orderBy('p.prix', 'ASC');
        } elseif ($tri === 'prix_desc') {
            $qb->orderBy('p.prix', 'DESC');
        } else {
            $qb->orderBy('p.id', 'DESC');
        }

        $produits = $qb->getQuery()->getResult();

        // Calculate Stats
        $stats = [
            'total' => count($produits),
            'outOfStock' => 0,
            'lowStock' => 0,
            'totalValue' => 0
        ];

        foreach ($produits as $p) {
            $stats['totalValue'] += ($p->getQuantite() * $p->getPrix());
            if ($p->getQuantite() == 0) {
                $stats['outOfStock']++;
            } elseif ($p->getQuantite() <= 10) {
                $stats['lowStock']++;
            }
        }

        return $this->render('admin/produit/index.html.twig', [
            'produits' => $produits,
            'q' => $q,
            'tri' => $tri,
            'stats' => $stats
        ]);
    }

    #[Route('/new', name: 'app_admin_produit_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $produit = new Produit();
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('produit_images_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'Impossible de sauvegarder l\'image');
                }

                $produit->setImage($newFilename);
            }

            $entityManager->persist($produit);
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_produit_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/produit/new.html.twig', [
            'produit' => $produit,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_produit_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Produit $produit, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('produit_images_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'Impossible de sauvegarder l\'image');
                }

                $produit->setImage($newFilename);
            }

            $entityManager->flush();

            return $this->redirectToRoute('app_admin_produit_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/produit/edit.html.twig', [
            'produit' => $produit,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_produit_delete', methods: ['POST'])]
    public function delete(Request $request, Produit $produit, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$produit->getId(), $request->request->get('_token'))) {
            $entityManager->remove($produit);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_admin_produit_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/feedbacks', name: 'app_admin_produit_feedbacks', methods: ['GET'])]
    public function feedbacks(Produit $produit): Response
    {
        return $this->render('admin/produit/feedbacks.html.twig', [
            'produit' => $produit,
            'feedbacks' => $produit->getFeedbacks(),
        ]);
    }

    #[Route('/feedback/{id}/delete', name: 'app_admin_feedback_delete', methods: ['POST'])]
    public function deleteFeedback(Request $request, \App\Entity\Feedback $feedback, EntityManagerInterface $entityManager): Response
    {
        $produitId = $feedback->getProduit()->getId();
        if ($this->isCsrfTokenValid('delete_feedback'.$feedback->getId(), $request->request->get('_token'))) {
            $entityManager->remove($feedback);
            $entityManager->flush();
            $this->addFlash('success', 'Feedback supprimé avec succès.');
        }

        // Redirect back to either the admin feedbacks list or the product show if referer is public
        $referer = $request->headers->get('referer');
        if (str_contains($referer, '/admin/produit')) {
            return $this->redirectToRoute('app_admin_produit_feedbacks', ['id' => $produitId]);
        }

        return $this->redirectToRoute('app_produit_show', ['id' => $produitId]);
    }
}
