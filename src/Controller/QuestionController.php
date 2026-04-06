<?php

namespace App\Controller;

use App\Entity\Question;
use App\Form\QuestionType;
use App\Repository\QuestionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/question')]
class QuestionController extends AbstractController
{
    #[Route('/', name: 'app_question_index', methods: ['GET'])]
    public function index(QuestionRepository $repository): Response
    {
        return $this->render('question/index.html.twig', [
            'questions' => $repository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_question_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $entity = new Question();
        $form = $this->createForm(QuestionType::class, $entity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($entity);
            $entityManager->flush();

            return $this->redirectToRoute('app_question_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('question/new.html.twig', [
            'question' => $entity,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_question_show', methods: ['GET'])]
    public function show(Question $entity): Response
    {
        return $this->render('question/show.html.twig', [
            'question' => $entity,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_question_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Question $entity, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(QuestionType::class, $entity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_question_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('question/edit.html.twig', [
            'question' => $entity,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_question_delete', methods: ['POST'])]
    public function delete(Request $request, Question $entity, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$entity->getId(), $request->request->get('_token'))) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_question_index', [], Response::HTTP_SEE_OTHER);
    }
}