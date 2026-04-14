<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(): Response
    {
        $user = $this->getUser();

        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        if ($this->isGranted('ROLE_PSYCHOLOGUE')) {
            return $this->redirectToRoute('app_psychologue_dashboard');
        }

        return $this->redirectToRoute('app_patient_dashboard');
    }

    #[Route('/patient/dashboard', name: 'app_patient_dashboard')]
    public function patientDashboard(\App\Repository\ParticipationRepository $participationRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_PATIENT');
        
        $user = $this->getUser();
        $nextUpcomingParticipation = $participationRepo->findNextUpcomingEventForUser($user);
        
        return $this->render('patient/dashboard.html.twig', [
            'next_event' => $nextUpcomingParticipation ? $nextUpcomingParticipation->getEvenement() : null,
            'next_event_jours_restants' => $nextUpcomingParticipation ? (new \DateTime())->diff($nextUpcomingParticipation->getEvenement()->getDateDebut())->days : null,
        ]);
    }

    #[Route('/psychologue/dashboard', name: 'app_psychologue_dashboard')]
    public function psychologueDashboard(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_PSYCHOLOGUE');
        return $this->render('psychologue/dashboard.html.twig');
    }
}
