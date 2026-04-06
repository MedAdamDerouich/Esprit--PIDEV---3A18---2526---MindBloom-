<?php

namespace App\Controller;

use App\Entity\ResultatTest;
use App\Form\ResultatTestType;
use App\Repository\ResultatTestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/resultat_test')]
class ResultatTestController extends AbstractController
{
    #[Route('/', name: 'app_resultat_test_index', methods: ['GET'])]
    public function index(ResultatTestRepository $repository): Response
    {
        return $this->render('resultat_test/index.html.twig', [
            'resultat_tests' => $repository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_resultat_test_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $entity = new ResultatTest();
        $form = $this->createForm(ResultatTestType::class, $entity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($entity);
            $entityManager->flush();

            return $this->redirectToRoute('app_resultat_test_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('resultat_test/new.html.twig', [
            'resultat_test' => $entity,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/mes-resultats', name: 'app_resultat_test_mes_resultats', methods: ['GET'])]
    public function mesResultats(ResultatTestRepository $repository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour voir vos résultats.');
        }

        return $this->render('resultat_test/mes_resultats.html.twig', [
            'resultat_tests' => $repository->findBy(['patient' => $user], ['id' => 'DESC']),
        ]);
    }

    #[Route('/{id}', name: 'app_resultat_test_show', methods: ['GET'])]
    public function show(ResultatTest $entity): Response
    {
        return $this->render('resultat_test/show.html.twig', [
            'resultat_test' => $entity,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_resultat_test_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ResultatTest $entity, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ResultatTestType::class, $entity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_resultat_test_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('resultat_test/edit.html.twig', [
            'resultat_test' => $entity,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_resultat_test_delete', methods: ['POST'])]
    public function delete(Request $request, ResultatTest $entity, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$entity->getId(), $request->request->get('_token'))) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_resultat_test_index', [], Response::HTTP_SEE_OTHER);
    }
}