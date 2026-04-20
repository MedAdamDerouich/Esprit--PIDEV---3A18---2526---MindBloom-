<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Participation;
use App\Repository\EventRepository;
use App\Repository\ParticipationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/patient/evenements')]
#[IsGranted('ROLE_PATIENT')]
class EventPatientController extends AbstractController
{
    #[Route('', name: 'app_patient_event_index', methods: ['GET'])]
    public function index(EventRepository $eventRepository, Request $request, PaginatorInterface $paginator): Response
    {
        $qb = $eventRepository->findActifsQb();

        $events = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            3
        );

        return $this->render('patient/event/index.html.twig', [
            'events' => $events,
            'total'  => $events->getTotalItemCount(),
        ]);
    }

    #[Route('/{id}', name: 'app_patient_event_show', methods: ['GET'])]
    public function show(Event $event, ParticipationRepository $participationRepo): Response
    {
        $user = $this->getUser();
        $isRegistered = $participationRepo->isUserRegistered($event, $user);
        $participantsCount = $participationRepo->countConfirmedByEvent($event);

        return $this->render('patient/event/show.html.twig', [
            'event' => $event,
            'isRegistered' => $isRegistered,
            'participantsCount' => $participantsCount
        ]);
    }

    #[Route('/{id}/participer', name: 'app_patient_event_participate', methods: ['POST'])]
    public function participate(
        Request $request,
        Event $event,
        ParticipationRepository $participationRepo,
        EntityManagerInterface $em,
        \App\Service\ParticipationEmailService $emailService,
        \App\Service\WalletService $walletService
    ): Response {
        $user = $this->getUser();

        // Vérifier si l'événement est actif
        if (!$event->isActif()) {
            $this->addFlash('error', '❌ Cet événement n\'est plus actif.');
            return $this->redirectToRoute('app_patient_event_show', ['id' => $event->getId()]);
        }

        // Vérifier si déjà inscrit
        if ($participationRepo->isUserRegistered($event, $user)) {
            $this->addFlash('warning', '⚠️ Vous êtes déjà inscrit à cet événement.');
            return $this->redirectToRoute('app_patient_event_show', ['id' => $event->getId()]);
        }

        // Vérifier la capacité
        if (!$event->isPlacesDisponibles()) {
            $this->addFlash('error', '❌ Désolé, il n\'y a plus de places disponibles pour cet événement.');
            return $this->redirectToRoute('app_patient_event_show', ['id' => $event->getId()]);
        }

        // Déduire le solde du Wallet si l'événement est payant
        $prix = $event->getPrix() ?? 0.0;
        if ($prix > 0) {
            $deductError = $walletService->deduct($user, $prix, 'Paiement événement : ' . $event->getTitre());
            if ($deductError === 'suspended') {
                $this->addFlash('error', '❌ Votre portefeuille MindBloom est suspendu.');
                return $this->redirectToRoute('app_patient_event_show', ['id' => $event->getId()]);
            } elseif ($deductError === 'insufficient') {
                $this->addFlash('error', '❌ Solde insuffisant pour participer à cet événement (' . number_format($prix, 2) . ' DT nécessaires).');
                return $this->redirectToRoute('app_patient_event_show', ['id' => $event->getId()]);
            }
        }

        // Créer la participation
        $participation = new Participation();
        $participation->setEvenement($event);
        $participation->setUser($user);
        $participation->setStatut(Participation::STATUT_INSCRIT);

        $em->persist($participation);
        $em->flush();

        // Envoi de l'email de confirmation (avec PDF)
        try {
            $emailService->sendParticipationConfirmation($participation);
        } catch (\Throwable $e) {
            $this->addFlash('warning', '⚠️ L\'inscription est confirmée mais l\'email n\'a pas pu être envoyé.');
        }

        $this->addFlash('success', '🎉 Inscription confirmée! Vous participez à "' . $event->getTitre() . '"');
        return $this->redirectToRoute('app_patient_event_my_participations');
    }

    #[Route('/mes-participations/liste', name: 'app_patient_event_my_participations', methods: ['GET'])]
    public function myParticipations(ParticipationRepository $participationRepo): Response
    {
        $user = $this->getUser();
        $participations = $participationRepo->findByUser($user);

        return $this->render('patient/event/my_participations.html.twig', [
            'participations' => $participations
        ]);
    }

    #[Route('/mes-participations/{id}/ticket', name: 'app_patient_event_ticket', methods: ['GET'])]
    public function downloadTicket(
        Participation $participation,
        \App\Service\PdfTicketService $pdfTicketService
    ): Response {
        // Sécurité : Vérifier que le billet appartient bien à l'utilisateur connecté
        if ($participation->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Ce billet ne vous appartient pas.');
        }

        try {
            $pdfBytes = $pdfTicketService->generateTicketPdf($participation);
        } catch (\Throwable $e) {
            $this->addFlash('error', '❌ Impossible de générer le billet PDF pour le moment.' . ($this->getParameter('kernel.environment') === 'dev' ? ' Détail: ' . $e->getMessage() : ''));
            return $this->redirectToRoute('app_patient_event_my_participations');
        }

        if (!$pdfBytes) {
            $this->addFlash('error', '❌ Impossible de générer le billet PDF pour le moment.');
            return $this->redirectToRoute('app_patient_event_my_participations');
        }

        $filename = 'Billet_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $participation->getEvenement()->getTitre()) . '.pdf';

        return new Response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    #[Route('/{id}/annuler', name: 'app_patient_event_cancel', methods: ['POST'])]
    public function cancel(
        Request $request,
        Event $event,
        ParticipationRepository $participationRepo,
        EntityManagerInterface $em,
        \App\Service\WalletService $walletService
    ): Response {
        $user = $this->getUser();

        $participation = $participationRepo->findOneByEventAndUser($event, $user);

        if (!$participation) {
            $this->addFlash('error', '❌ Vous n\'êtes pas inscrit à cet événement.');
            return $this->redirectToRoute('app_patient_event_my_participations');
        }

        // Vérifier le token CSRF
        if (!$this->isCsrfTokenValid('cancel' . $event->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', '❌ Token de sécurité invalide.');
            return $this->redirectToRoute('app_patient_event_my_participations');
        }

        // Remboursement si l'événement n'a pas encore commencé
        $prix = $event->getPrix() ?? 0.0;
        $now = new \DateTime();
        $isRefunded = false;
        
        if ($prix > 0 && $event->getDateDebut() > $now) {
            $walletService->recharge($user, $prix, 'Remboursement annulation : ' . $event->getTitre());
            $isRefunded = true;
        }

        // Supprimer la participation
        $em->remove($participation);
        $em->flush();

        if ($isRefunded) {
            $this->addFlash('success', '✅ Votre participation a été annulée et ' . number_format($prix, 2) . ' DT ont été remboursés sur votre portefeuille.');
        } else {
            $this->addFlash('success', '✅ Votre participation à "' . $event->getTitre() . '" a été annulée.');
        }
        
        return $this->redirectToRoute('app_patient_event_my_participations');
    }
}
