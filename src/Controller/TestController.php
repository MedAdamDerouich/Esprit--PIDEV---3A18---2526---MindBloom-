<?php

namespace App\Controller;

use App\Entity\Test;
use App\Entity\ResultatTest;
use App\Entity\Reponse;
use App\Form\TestType;
use App\Repository\TestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/test')]
class TestController extends AbstractController
{
    #[Route('/', name: 'app_test_index', methods: ['GET'])]
    public function index(TestRepository $repository): Response
    {
        return $this->render('test/index.html.twig', [
            'tests' => $repository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_test_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_PSYCHOLOGUE')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $entity = new Test();
        $form = $this->createForm(TestType::class, $entity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->getUser()) {
                $entity->setUser($this->getUser());
            }
            $entityManager->persist($entity);
            $entityManager->flush();

            return $this->redirectToRoute('app_test_builder', ['id' => $entity->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('test/new.html.twig', [
            'test' => $entity,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/manage', name: 'app_test_manage', methods: ['GET'])]
    #[IsGranted('ROLE_PSYCHOLOGUE')]
    public function manage(TestRepository $repository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        return $this->render('test/manage.html.twig', [
            'tests' => $repository->findBy(['user' => $user]),
        ]);
    }

    #[Route('/{id}', name: 'app_test_show', methods: ['GET'])]
    public function show(Test $entity): Response
    {
        return $this->render('test/show.html.twig', [
            'test' => $entity,
            'questions' => $entity->getQuestions(),
        ]);
    }

    #[Route('/{id}/builder', name: 'app_test_builder', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_PSYCHOLOGUE')]
    public function builder(Request $request, Test $test, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            $questionTexte = $request->request->get('texte_question');
            
            if (!empty($questionTexte)) {
                $question = new \App\Entity\Question();
                $question->setTexteQuestion($questionTexte);
                $question->setTest($test);
                $entityManager->persist($question);

                // Readd responses dynamically
                $reponsesTextes = $request->request->all('reponse');
                $reponsesValeurs = $request->request->all('valeur');
                
                if (is_array($reponsesTextes)) {
                    foreach ($reponsesTextes as $key => $texte) {
                        if (!empty($texte)) {
                            $valeur = isset($reponsesValeurs[$key]) ? (int) $reponsesValeurs[$key] : 0;
                            $reponse = new \App\Entity\Reponse();
                            $reponse->setTexteReponse($texte);
                            $reponse->setValeur($valeur);
                            $reponse->setQuestion($question);
                            $entityManager->persist($reponse);
                        }
                    }
                }

                $entityManager->flush();
                $this->addFlash('success', 'Question ajoutée avec succès !');
                return $this->redirectToRoute('app_test_builder', ['id' => $test->getId()]);
            }
        }

        return $this->render('test/builder.html.twig', [
            'test' => $test,
            'questions' => $test->getQuestions(),
        ]);
    }

    #[Route('/{id}/submit', name: 'app_test_submit', methods: ['POST'])]
    public function submitTest(Request $request, Test $test, EntityManagerInterface $entityManager): Response
    {
        $scoreTotal = 0;
        
        $questions = $test->getQuestions();
        foreach ($questions as $question) {
            $answerId = $request->request->get('question_' . $question->getId());
            if ($answerId) {
                $reponse = $entityManager->getRepository(Reponse::class)->find($answerId);
                if ($reponse && $reponse->getQuestion() === $question) {
                    $scoreTotal += $reponse->getValeur();
                }
            }
        }
        
        $resultat = new ResultatTest();
        // Validation ensures score is <= 100 or 0-100 check. Java entity had 0-100 limit, let's clamp it.
        $finalScore = max(0, min(100, $scoreTotal));
        $resultat->setScore($finalScore);
        $resultat->setTest($test);
        
        if ($finalScore > 80) {
            $resultat->setCommentaire("Votre score indique un très bon niveau de bien-être.");
            $resultat->setEtat("Excellent");
        } elseif ($finalScore > 50) {
            $resultat->setCommentaire("Votre bien-être est modéré, continuez à en prendre soin.");
            $resultat->setEtat("Moyen");
        } else {
            $resultat->setCommentaire("Il serait peut-être bénéfique d'en parler à un psychologue.");
            $resultat->setEtat("À surveiller");
        }
        
        if ($this->getUser()) {
            $resultat->setPatient($this->getUser());
        }
        
        $entityManager->persist($resultat);
        $entityManager->flush();
        
        $this->addFlash('success', 'Test terminé. Votre résultat a été enregistré avec succès !');
        
        return $this->redirectToRoute('app_resultat_test_show', ['id' => $resultat->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/edit', name: 'app_test_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_PSYCHOLOGUE')]
    public function edit(Request $request, Test $entity, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TestType::class, $entity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_test_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('test/edit.html.twig', [
            'test' => $entity,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_test_delete', methods: ['POST'])]
    #[IsGranted('ROLE_PSYCHOLOGUE')]
    public function delete(Request $request, Test $entity, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$entity->getId(), $request->request->get('_token'))) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_test_index', [], Response::HTTP_SEE_OTHER);
    }
}