<?php

namespace App\Controller;

use App\Entity\Event;
use App\Form\EventType;
use App\Repository\EventRepository;
use App\Repository\ParticipationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/evenements')]
#[IsGranted('ROLE_ADMIN')]
class EventController extends AbstractController
{
    #[Route('', name: 'app_admin_event_index', methods: ['GET'])]
    public function index(Request $request, EventRepository $eventRepository): Response
    {
        $search = $request->query->get('search', '');
        
        if ($search) {
            $events = $eventRepository->search($search);
        } else {
            $events = $eventRepository->findBy([], ['dateCreation' => 'DESC']);
        }

        return $this->render('admin/event/index.html.twig', [
            'events' => $events,
            'search' => $search
        ]);
    }

    #[Route('/nouveau', name: 'app_admin_event_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $event = new Event();
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gestion de l'upload de photo
            $photoFile = $form->get('photoFile')->getData();
            if ($photoFile) {
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
                    $this->addFlash('error', 'Erreur lors du téléchargement de la photo: ' . $e->getMessage());
                }
            }

            $em->persist($event);
            $em->flush();

            $this->addFlash('success', '✅ Événement "' . $event->getTitre() . '" créé avec succès!');
            return $this->redirectToRoute('app_admin_event_index');
        }

        return $this->render('admin/event/new.html.twig', [
            'event' => $event,
            'form' => $form
        ]);
    }

    #[Route('/{id}', name: 'app_admin_event_show', methods: ['GET'])]
    public function show(Event $event, ParticipationRepository $participationRepo): Response
    {
        $participants = $participationRepo->findConfirmedByEvent($event);

        return $this->render('admin/event/show.html.twig', [
            'event' => $event,
            'participants' => $participants
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_admin_event_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Event $event, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gestion de l'upload de photo
            $photoFile = $form->get('photoFile')->getData();
            if ($photoFile) {
                // Supprimer l'ancienne photo si elle existe
                if ($event->getPhoto()) {
                    $oldPhotoPath = $this->getParameter('kernel.project_dir') . '/public/uploads/events/' . $event->getPhoto();
                    if (file_exists($oldPhotoPath)) {
                        unlink($oldPhotoPath);
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
                    $this->addFlash('error', 'Erreur lors du téléchargement de la photo: ' . $e->getMessage());
                }
            }

            $em->flush();

            $this->addFlash('success', '✅ Événement "' . $event->getTitre() . '" modifié avec succès!');
            return $this->redirectToRoute('app_admin_event_index');
        }

        return $this->render('admin/event/edit.html.twig', [
            'event' => $event,
            'form' => $form
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_admin_event_delete', methods: ['POST'])]
    public function delete(Request $request, Event $event, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $event->getId(), $request->request->get('_token'))) {
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

            $this->addFlash('success', '🗑️ Événement "' . $titre . '" supprimé avec succès!');
        }

        return $this->redirectToRoute('app_admin_event_index');
    }

    #[Route('/{id}/participants', name: 'app_admin_event_participants', methods: ['GET'])]
    public function participants(Event $event, ParticipationRepository $participationRepo): Response
    {
        $participants = $participationRepo->findConfirmedByEvent($event);

        return $this->render('admin/event/participants.html.twig', [
            'event' => $event,
            'participants' => $participants
        ]);
    }
}
