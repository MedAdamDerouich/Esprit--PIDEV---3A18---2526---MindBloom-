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

    #[Route('/rapport/pdf', name: 'app_resultat_test_rapport_pdf', methods: ['GET'])]
    public function rapportPdf(ResultatTestRepository $repository, GeminiReportService $geminiService, \App\Service\PdfTicketService $pdfService): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour générer un rapport PDF.');
        }

        $results = $repository->findBy(['patient' => $user], ['id' => 'DESC']);
        if (empty($results)) {
            $this->addFlash('warning', 'Aucun résultat de test trouvé.');
            return $this->redirectToRoute('app_resultat_test_mes_resultats');
        }

        $todayDate = (new \DateTime())->format('d/m/Y');
        $patientName = $user->getFullName();

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

        $rapportText = $geminiService->genererRapport($prompt);
        
        // Escape content except for newlines
        $safeRapportText = htmlspecialchars($rapportText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Convert Markdown-like **bold** to <strong> and newlines to <br> to format nicely in the PDF
        $safeRapportText = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $safeRapportText);
        $safeRapportText = nl2br($safeRapportText);

        $htmlContent = <<<HTML
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background: #ffffff; padding: 40px; color: #2c3e50; }
                .header { border-bottom: 3px solid #8E2DE2; padding-bottom: 20px; margin-bottom: 30px; }
                .header h1 { color: #2C3E50; font-size: 32px; margin: 0; }
                .header p { color: #7F8C8D; font-size: 14px; margin-top: 5px; }
                .content { font-size: 14px; line-height: 1.8; color: #34495E; text-align: justify; }
                .footer { margin-top: 50px; text-align: center; font-size: 10px; color: #95a5a6; border-top: 1px solid #eeeeee; padding-top: 20px; }
                .warning { background: #FFF9E6; padding: 15px; border-left: 4px solid #F1C40F; margin-top: 30px; font-size: 12px; color: #7F8C8D; }
                .warning strong { color: #D4AC0D; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Rapport Analytique MindBloom</h1>
                <p>Généré automatiquement par IA - Patient: {$patientName} - Date: {$todayDate}</p>
            </div>
            
            <div class="content">
                {$safeRapportText}
            </div>

            <div class="warning">
                <strong>⚠️ Clause de non-responsabilité:</strong> Ce rapport est généré par intelligence artificielle pour vous aider à mieux comprendre votre état. Il ne remplace en aucun cas un diagnostic ou un conseil médical professionnel. En cas de détresse, veuillez consulter un professionnel de la santé.
            </div>

            <div class="footer">
                © 2026 MindBloom - Plateforme de bien-être mental
            </div>
        </body>
        </html>
        HTML;

        $pdfBytes = $pdfService->generatePdfFromHtml($htmlContent);

        if (!$pdfBytes) {
            $this->addFlash('error', 'Erreur lors de la génération du PDF. Veuillez vérifier votre clé API APDF.');
            return $this->redirectToRoute('app_resultat_test_mes_resultats');
        }

        return new Response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Rapport_Analytique_' . date('Y_m_d') . '.pdf"',
        ]);
    }

    #[Route('/rapport/email', name: 'app_resultat_test_rapport_email', methods: ['GET'])]
    public function rapportEmail(ResultatTestRepository $repository, GeminiReportService $geminiService, \App\Service\PdfTicketService $pdfService, \Symfony\Component\Mailer\MailerInterface $mailer): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        $results = $repository->findBy(['patient' => $user], ['id' => 'DESC']);
        if (empty($results)) {
            $this->addFlash('warning', 'Aucun résultat de test trouvé.');
            return $this->redirectToRoute('app_resultat_test_mes_resultats');
        }

        $todayDate = (new \DateTime())->format('d/m/Y');
        $patientName = $user->getFullName();

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
            }
        }

        $rapportText = $geminiService->genererRapport($prompt);
        $safeRapportText = htmlspecialchars($rapportText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $safeRapportText = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $safeRapportText);
        $safeRapportText = nl2br($safeRapportText);

        $htmlContent = <<<HTML
        <!DOCTYPE html>
        <html lang="fr">
        <head><meta charset="UTF-8"><style>body{font-family:sans-serif;padding:40px;}</style></head>
        <body>
            <h2>Rapport Analytique MindBloom</h2>
            <p><strong>Patient:</strong> {$patientName} | <strong>Date:</strong> {$todayDate}</p>
            <div style="text-align: justify; line-height: 1.6;">{$safeRapportText}</div>
        </body>
        </html>
        HTML;

        $pdfBytes = $pdfService->generatePdfFromHtml($htmlContent);

        if ($pdfBytes) {
            $email = (new \Symfony\Component\Mime\Email())
                ->from('mindbloom.platform@gmail.com')
                ->to($user->getEmail())
                ->subject('Votre Rapport d\'Analyse MindBloom (IA)')
                ->html('<p>Bonjour ' . $patientName . ',</p><p>Veuillez trouver ci-joint votre rapport analytique généré par notre Intelligence Artificielle.</p><p>Cordialement,<br>L\'équipe MindBloom</p>')
                ->attach($pdfBytes, 'Rapport_MindBloom.pdf', 'application/pdf');

            try {
                $mailer->send($email);
                $this->addFlash('success', '✅ Le rapport PDF a été envoyé avec succès à votre adresse email !');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de l\'envoi de l\'email.');
            }
        } else {
            $this->addFlash('error', 'Erreur de génération du fichier PDF.');
        }

        return $this->redirectToRoute('app_resultat_test_generer_rapport');
    }
}