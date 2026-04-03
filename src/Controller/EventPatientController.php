<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Participation;
use App\Repository\EventRepository;
use App\Repository\ParticipationRepository;
use Doctrine\ORM\EntityManagerInterface;
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
    public function index(EventRepository $eventRepository): Response
    {
        // Récupérer tous les événements actifs avec places disponibles
        $events = $eventRepository->findActifs();

        return $this->render('patient/event/index.html.twig', [
            'events' => $events
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
        EntityManagerInterface $em
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

        // Créer la participation
        $participation = new Participation();
        $participation->setEvenement($event);
        $participation->setUser($user);
        $participation->setStatut(Participation::STATUT_CONFIRME);

        // QR Code désactivé (colonne non présente dans la base)
        // $qrCode = 'QR-' . strtoupper(substr(md5(uniqid()), 0, 10));
        // $participation->setQrCode($qrCode);

        $em->persist($participation);
        $em->flush();

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

    #[Route('/{id}/annuler', name: 'app_patient_event_cancel', methods: ['POST'])]
    public function cancel(
        Request $request,
        Event $event,
        ParticipationRepository $participationRepo,
        EntityManagerInterface $em
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

        // Supprimer la participation
        $em->remove($participation);
        $em->flush();

        $this->addFlash('success', '✅ Votre participation à "' . $event->getTitre() . '" a été annulée.');
        return $this->redirectToRoute('app_patient_event_my_participations');
    }
}
