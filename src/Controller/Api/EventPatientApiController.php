<?php

namespace App\Controller\Api;

use App\Entity\Event;
use App\Entity\Participation;
use App\Repository\EventRepository;
use App\Repository\ParticipationRepository;
use App\Service\ParticipationEmailService;
use App\Service\PdfTicketService;
use App\Service\QrCodeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * API REST Patient — Consultation events et gestion des participations.
 * Endpoints préfixés /api/patient/evenements
 */
#[Route('/api/patient/evenements')]
class EventPatientApiController extends AbstractController
{
    // ─── LIST (GET) — Uniquement événements actifs avec places disponibles ──

    #[Route('', name: 'api_patient_event_index', methods: ['GET'])]
    public function index(EventRepository $eventRepository): JsonResponse
    {
        $events = $eventRepository->findActifs();

        $data = array_map(fn(Event $e) => $this->serializeEvent($e), $events);

        return $this->json([
            'success' => true,
            'count'   => count($data),
            'data'    => $data,
        ]);
    }

    // ─── SHOW (GET /{id}) ─────────────────────────────────────────────

    #[Route('/{id}', name: 'api_patient_event_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Event $event, ParticipationRepository $participationRepo): JsonResponse
    {
        $user = $this->getUser();
        $isRegistered = false;

        if ($user) {
            $isRegistered = $participationRepo->isUserRegistered($event, $user);
        }

        $participantsCount = $participationRepo->countConfirmedByEvent($event);

        return $this->json([
            'success'           => true,
            'data'              => $this->serializeEvent($event),
            'isRegistered'      => $isRegistered,
            'participantsCount' => $participantsCount,
            'placesRestantes'   => $event->getPlacesRestantes(),
        ]);
    }

    // ─── PARTICIPER (POST /{id}/participer) ───────────────────────────

    #[Route('/{id}/participer', name: 'api_patient_event_participate', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_PATIENT', message: 'API: Vous devez être patient pour participer.')]
    public function participate(
        Event $event,
        ParticipationRepository $participationRepo,
        EntityManagerInterface $em,
        ParticipationEmailService $emailService
    ): JsonResponse {
        $user = $this->getUser();

        // 1. Vérifications (Actif, Déjà inscrit, Capacité)
        if (!$event->isActif()) {
            return $this->json(['success' => false, 'message' => 'Cet événement n\'est plus actif.'], Response::HTTP_BAD_REQUEST);
        }

        if ($participationRepo->isUserRegistered($event, $user)) {
            return $this->json(['success' => false, 'message' => 'Vous êtes déjà inscrit à cet événement.'], Response::HTTP_CONFLICT);
        }

        if (!$event->isPlacesDisponibles()) {
            return $this->json(['success' => false, 'message' => 'Il n\'y a plus de places disponibles.'], Response::HTTP_BAD_REQUEST);
        }

        // 2. Création de la participation
        $participation = new Participation();
        $participation->setEvenement($event);
        $participation->setUser($user);
        $participation->setStatut(Participation::STATUT_INSCRIT);
        // Le commentaire peut être extrait de la Request si besoin

        $em->persist($participation);
        $em->flush();

        // 3. Envoi de l'email de confirmation (inclut la génération PDF ticket + QR)
        $emailError = null;
        try {
            $emailService->sendParticipationConfirmation($participation);
            $emailSent = true;
        } catch (\Throwable $e) {
            // L'inscription est validée même si l'email échoue
            $emailSent = false;
            $emailError = $e->getMessage();
        }

        return $this->json([
            'success'   => true,
            'message'   => 'Inscription confirmée!',
            'emailSent' => $emailSent,
            'emailError'=> $emailError,
            'data'      => [
                'participationId' => $participation->getId(),
                'eventId'         => $event->getId(),
                'eventTitre'      => $event->getTitre(),
                'dateInscription' => $participation->getDateInscription()->format('Y-m-d H:i:s'),
                'statut'          => $participation->getStatut(),
            ],
        ], Response::HTTP_CREATED);
    }

    // ─── MES PARTICIPATIONS (GET /mes-participations) ─────────────────

    #[Route('/mes-participations', name: 'api_patient_event_my_participations', methods: ['GET'], priority: 10)]
    #[IsGranted('ROLE_PATIENT')]
    public function myParticipations(ParticipationRepository $participationRepo): JsonResponse
    {
        $user = $this->getUser();
        $participations = $participationRepo->findByUser($user);

        $data = array_map(fn(Participation $p) => [
            'id'              => $p->getId(),
            'dateInscription' => $p->getDateInscription()?->format('Y-m-d H:i:s'),
            'statut'          => $p->getStatut(),
            'commentaire'     => $p->getCommentaire(),
            'event'           => $this->serializeEvent($p->getEvenement()),
        ], $participations);

        return $this->json([
            'success' => true,
            'count'   => count($data),
            'data'    => $data,
        ]);
    }

    // ─── TÉLÉCHARGER LE BILLET PDF (GET /participation/{id}/billet) ───

    #[Route('/participation/{id}/billet', name: 'api_patient_event_ticket_pdf', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_PATIENT')]
    public function downloadPdfTicket(
        Participation $participation,
        PdfTicketService $pdfTicketService
    ): Response {
        // Vérifier que c'est bien la participation de l'utilisateur
        if (!$this->getUser() || $participation->getUser()?->getId() !== $this->getUser()->getId()) {
            return $this->json(['success' => false, 'message' => 'Accès non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        $pdfBytes = $pdfTicketService->generateTicketPdf($participation);

        if (!$pdfBytes) {
            return $this->json(['success' => false, 'message' => 'Erreur lors de la génération du billet.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $response = new Response($pdfBytes);
        $response->headers->set('Content-Type', 'application/pdf');
        
        $filename = 'Billet_MB_' . $participation->getId() . '.pdf';
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    // ─── OBTENIR LE QR CODE (GET /participation/{id}/qr) ──────────────

    #[Route('/participation/{id}/qr', name: 'api_patient_event_ticket_qr', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_PATIENT')]
    public function getQrCode(
        Participation $participation,
        QrCodeService $qrCodeService
    ): Response {
        if (!$this->getUser() || $participation->getUser()?->getId() !== $this->getUser()->getId()) {
            return $this->json(['success' => false, 'message' => 'Accès non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        $qrDataUri = $qrCodeService->generateQrCodeDataUri($participation);

        if (!$qrDataUri) {
            return $this->json(['success' => false, 'message' => 'Erreur lors de la génération du QR code.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'success' => true,
            'qrCode'  => $qrDataUri, // format: "data:image/png;base64,....."
        ]);
    }

    // ─── ANNULER PARTICIPATION (DELETE /participation/{id}) ───────────

    #[Route('/participation/{id}', name: 'api_patient_event_cancel', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_PATIENT')]
    public function cancel(
        Participation $participation,
        EntityManagerInterface $em
    ): JsonResponse {
        // Vérifier que la participation appartient bien à l'utilisateur
        if (!$this->getUser() || $participation->getUser()?->getId() !== $this->getUser()->getId()) {
            return $this->json(['success' => false, 'message' => 'Accès non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        $eventTitle = $participation->getEvenement()->getTitre();

        // 1. Suppression définitive
        $em->remove($participation);
        $em->flush();

        // Ou 2. Soft delete (Mise à jour du statut)
        // $participation->setStatut(Participation::STATUT_ANNULE);
        // $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Votre participation à "' . $eventTitle . '" a été annulée.',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════════════════════════════════

    private function serializeEvent(Event $event): array
    {
        return [
            'id'              => $event->getId(),
            'titre'           => $event->getTitre(),
            'description'     => $event->getDescription(),
            'dateDebut'       => $event->getDateDebut()?->format('Y-m-d H:i:s'),
            'dateFin'         => $event->getDateFin()?->format('Y-m-d H:i:s'),
            'lieu'            => $event->getLieu(),
            'capaciteMax'     => $event->getCapaciteMax(),
            'organisateur'    => $event->getOrganisateur(),
            'photo'           => $event->getPhoto(),
            'prix'            => $event->getPrix(),
            'isGratuit'       => $event->isGratuit(),
            'nbParticipants'  => $event->countParticipants(),
            'placesRestantes' => $event->getPlacesRestantes(),
        ];
    }
}
