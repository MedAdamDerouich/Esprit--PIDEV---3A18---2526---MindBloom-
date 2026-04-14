<?php

namespace App\Controller\Api;

use App\Entity\Event;
use App\Form\EventType;
use App\Repository\EventRepository;
use App\Repository\ParticipationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * API REST Admin — Gestion des événements.
 * Endpoints préfixés /api/admin/evenements
 */
#[Route('/api/admin/evenements')]
class EventAdminApiController extends AbstractController
{
    // ─── LIST (GET) ── recherche optionnelle via ?search= ─────────────

    #[Route('', name: 'api_admin_event_index', methods: ['GET'])]
    public function index(Request $request, EventRepository $eventRepository): JsonResponse
    {
        $search = $request->query->get('search', '');

        $events = $search
            ? $eventRepository->search($search)
            : $eventRepository->findBy([], ['dateCreation' => 'DESC']);

        $data = array_map(fn(Event $e) => $this->serializeEvent($e), $events);

        return $this->json([
            'success' => true,
            'count'   => count($data),
            'data'    => $data,
        ]);
    }

    // ─── SHOW (GET /{id}) ─────────────────────────────────────────────

    #[Route('/{id}', name: 'api_admin_event_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Event $event, ParticipationRepository $participationRepo): JsonResponse
    {
        $participants = $participationRepo->findConfirmedByEvent($event);

        return $this->json([
            'success'      => true,
            'data'         => $this->serializeEvent($event),
            'participants' => array_map(fn($p) => [
                'id'              => $p->getId(),
                'user'            => $p->getUser() ? [
                    'id'       => $p->getUser()->getId(),
                    'fullName' => $p->getUser()->getFullName(),
                    'email'    => $p->getUser()->getEmail(),
                    'phone'    => $p->getUser()->getPhone(),
                ] : null,
                'dateInscription' => $p->getDateInscription()?->format('Y-m-d H:i:s'),
                'statut'          => $p->getStatut(),
            ], $participants),
        ]);
    }

    // ─── CREATE (POST) ────────────────────────────────────────────────

    #[Route('', name: 'api_admin_event_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): JsonResponse
    {
        $event = new Event();

        // Accepter JSON ou form-data
        $data = $this->extractRequestData($request);

        $event->setTitre($data['titre'] ?? '');
        $event->setDescription($data['description'] ?? null);
        $event->setLieu($data['lieu'] ?? null);
        $event->setOrganisateur($data['organisateur'] ?? null);
        $event->setStatut($data['statut'] ?? Event::STATUT_ACTIF);

        if (!empty($data['dateDebut'])) {
            $event->setDateDebut(new \DateTime($data['dateDebut']));
        }
        if (!empty($data['dateFin'])) {
            $event->setDateFin(new \DateTime($data['dateFin']));
        }
        if (isset($data['capaciteMax'])) {
            $event->setCapaciteMax((int) $data['capaciteMax']);
        }
        if (isset($data['prix'])) {
            $event->setPrix((float) $data['prix']);
        }

        // Gestion photo upload
        $this->handlePhotoUpload($request, $event, $slugger);

        // Validation
        $errors = $this->validateEvent($event);
        if (!empty($errors)) {
            return $this->json(['success' => false, 'errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        $em->persist($event);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Événement "' . $event->getTitre() . '" créé avec succès!',
            'data'    => $this->serializeEvent($event),
        ], Response::HTTP_CREATED);
    }

    // ─── UPDATE (PUT/PATCH /{id}) ─────────────────────────────────────

    #[Route('/{id}', name: 'api_admin_event_update', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    public function update(Request $request, Event $event, EntityManagerInterface $em, SluggerInterface $slugger): JsonResponse
    {
        $data = $this->extractRequestData($request);

        if (isset($data['titre'])) {
            $event->setTitre($data['titre']);
        }
        if (array_key_exists('description', $data)) {
            $event->setDescription($data['description']);
        }
        if (array_key_exists('lieu', $data)) {
            $event->setLieu($data['lieu']);
        }
        if (array_key_exists('organisateur', $data)) {
            $event->setOrganisateur($data['organisateur']);
        }
        if (isset($data['statut'])) {
            $event->setStatut($data['statut']);
        }
        if (!empty($data['dateDebut'])) {
            $event->setDateDebut(new \DateTime($data['dateDebut']));
        }
        if (!empty($data['dateFin'])) {
            $event->setDateFin(new \DateTime($data['dateFin']));
        }
        if (isset($data['capaciteMax'])) {
            $event->setCapaciteMax((int) $data['capaciteMax']);
        }
        if (isset($data['prix'])) {
            $event->setPrix((float) $data['prix']);
        }

        // Gestion photo upload
        $this->handlePhotoUpload($request, $event, $slugger);

        $errors = $this->validateEvent($event);
        if (!empty($errors)) {
            return $this->json(['success' => false, 'errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Événement "' . $event->getTitre() . '" modifié avec succès!',
            'data'    => $this->serializeEvent($event),
        ]);
    }

    // ─── DELETE (DELETE /{id}) ─────────────────────────────────────────

    #[Route('/{id}', name: 'api_admin_event_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Event $event, EntityManagerInterface $em): JsonResponse
    {
        // Supprimer la photo si elle existe
        if ($event->getPhoto()) {
            $photoPath = $this->getParameter('kernel.project_dir') . '/public/uploads/events/' . $event->getPhoto();
            if (file_exists($photoPath)) {
                unlink($photoPath);
            }
        }

        $titre = $event->getTitre();
        $em->remove($event);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Événement "' . $titre . '" supprimé avec succès!',
        ]);
    }

    // ─── PARTICIPANTS (GET /{id}/participants) ────────────────────────

    #[Route('/{id}/participants', name: 'api_admin_event_participants', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function participants(Event $event, ParticipationRepository $participationRepo): JsonResponse
    {
        $participants = $participationRepo->findConfirmedByEvent($event);

        return $this->json([
            'success'    => true,
            'event'      => [
                'id'    => $event->getId(),
                'titre' => $event->getTitre(),
            ],
            'count'      => count($participants),
            'participants' => array_map(fn($p) => [
                'id'              => $p->getId(),
                'user'            => $p->getUser() ? [
                    'id'       => $p->getUser()->getId(),
                    'fullName' => $p->getUser()->getFullName(),
                    'email'    => $p->getUser()->getEmail(),
                    'phone'    => $p->getUser()->getPhone(),
                ] : null,
                'dateInscription' => $p->getDateInscription()?->format('Y-m-d H:i:s'),
                'statut'          => $p->getStatut(),
                'commentaire'     => $p->getCommentaire(),
            ], $participants),
        ]);
    }

    // ─── STATISTIQUES (GET /stats) ────────────────────────────────────

    #[Route('/stats', name: 'api_admin_event_stats', methods: ['GET'], priority: 10)]
    public function stats(EventRepository $eventRepository, ParticipationRepository $participationRepo): JsonResponse
    {
        $allEvents = $eventRepository->findAll();
        $actifs    = $eventRepository->findActifs();
        $upcoming  = $eventRepository->findUpcoming(5);

        $totalParticipants = 0;
        foreach ($allEvents as $event) {
            $totalParticipants += $participationRepo->countConfirmedByEvent($event);
        }

        return $this->json([
            'success' => true,
            'stats'   => [
                'totalEvents'       => count($allEvents),
                'evenementsActifs'  => count($actifs),
                'totalParticipants' => $totalParticipants,
                'prochains'         => array_map(fn(Event $e) => $this->serializeEvent($e), $upcoming),
            ],
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
            'dateCreation'    => $event->getDateCreation()?->format('Y-m-d H:i:s'),
            'statut'          => $event->getStatut(),
            'photo'           => $event->getPhoto(),
            'prix'            => $event->getPrix(),
            'isGratuit'       => $event->isGratuit(),
            'nbParticipants'  => $event->countParticipants(),
            'placesRestantes' => $event->getPlacesRestantes(),
            'isActif'         => $event->isActif(),
        ];
    }

    private function extractRequestData(Request $request): array
    {
        $contentType = $request->headers->get('Content-Type', '');

        // JSON
        if (str_contains($contentType, 'application/json')) {
            return json_decode($request->getContent(), true) ?? [];
        }

        // form-data / x-www-form-urlencoded
        return $request->request->all();
    }

    private function handlePhotoUpload(Request $request, Event $event, SluggerInterface $slugger): void
    {
        $photoFile = $request->files->get('photo') ?? $request->files->get('photoFile');
        if (!$photoFile) {
            return;
        }

        // Supprimer l'ancienne photo si elle existe
        if ($event->getPhoto()) {
            $oldPath = $this->getParameter('kernel.project_dir') . '/public/uploads/events/' . $event->getPhoto();
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $photoFile->guessExtension();

        try {
            $photoFile->move(
                $this->getParameter('kernel.project_dir') . '/public/uploads/events',
                $newFilename
            );
            $event->setPhoto($newFilename);
        } catch (FileException $e) {
            // Silently continue — photo is optional
        }
    }

    private function validateEvent(Event $event): array
    {
        $errors = [];

        if (!$event->getTitre() || strlen($event->getTitre()) < 3) {
            $errors[] = 'Le titre doit contenir au moins 3 caractères';
        }
        if (!$event->getDateDebut()) {
            $errors[] = 'La date de début est obligatoire';
        }
        if ($event->getDateFin() && $event->getDateDebut() && $event->getDateFin() <= $event->getDateDebut()) {
            $errors[] = 'La date de fin doit être postérieure à la date de début';
        }
        if ($event->getCapaciteMax() !== null && $event->getCapaciteMax() <= 0) {
            $errors[] = 'La capacité maximale doit être positive';
        }
        if ($event->getPrix() !== null && $event->getPrix() < 0) {
            $errors[] = 'Le prix doit être positif ou zéro';
        }

        return $errors;
    }
}
