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
use App\Service\GeminiReportService;

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

    #[Route('/rapport/generer', name: 'app_resultat_test_generer_rapport', methods: ['GET'])]
    public function genererRapport(ResultatTestRepository $repository, GeminiReportService $geminiService): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour générer un rapport.');
        }

        $results = $repository->findBy(['patient' => $user], ['id' => 'DESC']);

        if (empty($results)) {
            $this->addFlash('warning', 'Aucun résultat de test trouvé. Veuillez d\'abord passer un test.');
            return $this->redirectToRoute('app_resultat_test_mes_resultats');
        }

        $todayDate = (new \DateTime())->format('d/MM/yyyy');
        $patientName = $user->getFullName(); // Assuming user has got getFullName, if not we will use something else

        $prompt = "Tu es un expert en santé mentale. Analyse les résultats des tests suivants du patient et génère un rapport analytique clair et détaillé sur son état mental et émotionnel. Fournis des recommandations basées sur les scores.\n\n";
        $prompt .= "Date du rapport: " . $todayDate . "\n";
        $prompt .= "Patient: " . $patientName . "\n";
        $prompt .= "Résultats des tests:\n";

        foreach ($results as $r) {
            if ($r->getTest()) {
                $prompt .= "- " . $r->getTest()->getNomTest() . ": score = " . $r->getScore() . "%";
                if ($r->getEtat()) {
                    $prompt .= ", état = " . $r->getEtat();
                }
                if ($r->getCommentaire()) {
                    $prompt .= ", commentaire = " . $r->getCommentaire();
                }
                $prompt .= "\n";
            }
        }

        $rapport = $geminiService->genererRapport($prompt);

        return $this->render('resultat_test/rapport.html.twig', [
            'rapport' => $rapport,
            'patient' => $user,
        ]);
    }
}