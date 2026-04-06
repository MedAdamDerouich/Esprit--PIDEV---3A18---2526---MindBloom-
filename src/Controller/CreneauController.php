<?php

namespace App\Controller;

use App\Entity\Creneau;
use App\Form\CreneauType;
use App\Repository\CabinetRepository;
use App\Repository\CreneauRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CreneauController extends AbstractController
{
    #[Route('/psychologue/creneaux', name: 'app_creneau_index')]
    public function index(CreneauRepository $creneauRepository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $creneaux = $creneauRepository->getCreneauxByPsychologue($user->getId());

        // Grouper par date string e.g YYYY-MM-DD (identique à Collectors.groupingBy)
        $groupedCreneaux = [];
        foreach ($creneaux as $creneau) {
            if ($creneau->getDateCreneau()) {
                $dateKey = $creneau->getDateCreneau()->format('Y-m-d');
                $groupedCreneaux[$dateKey][] = $creneau;
            }
        }

        return $this->render('psychologue/liste_creneaux.html.twig', [
            'groupedCreneaux' => $groupedCreneaux,
        ]);
    }

    #[Route('/psychologue/creneaux/{id}/supprimer', name: 'app_creneau_supprimer', methods: ['POST'])]
    public function supprimer(\App\Entity\Creneau $creneau, CreneauRepository $creneauRepository, \Symfony\Component\HttpFoundation\Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete' . $creneau->getId(), $request->request->get('_token'))) {
            $creneauRepository->deleteCreneau($creneau);
            $this->addFlash('success', 'Créneau supprimé avec succès.');
        }

        return $this->redirectToRoute('app_creneau_index');
    }

    #[Route('/psychologue/creneaux/nouveau', name: 'app_creneau_nouveau', methods: ['GET', 'POST'])]
    #[Route('/psychologue/creneaux/{id}/editer', name: 'app_creneau_editer', methods: ['GET', 'POST'])]
    public function editer(
        ?Creneau $creneau,
        Request $request,
        CreneauRepository $creneauRepository,
        CabinetRepository $cabinetRepository
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $cabinet = $cabinetRepository->findOneBy(['psychologue' => $user]);
        if (!$cabinet) {
            $this->addFlash('error', 'Vous devez créer un cabinet avant d\'ajouter des créneaux.');
            return $this->redirectToRoute('app_cabinet');
        }

        $isNew = false;
        if (!$creneau) {
            $creneau = new Creneau();
            $creneau->setCabinet($cabinet);
            $creneau->setDisponible(true);
            $isNew = true;
        }

        $form = $this->createForm(CreneauType::class, $creneau);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            // Validation 3 (Java) : interdire les heures passées aujourd'hui
            $today = new \DateTime('today');
            if ($creneau->getDateCreneau()->format('Y-m-d') === $today->format('Y-m-d')) {
                $now = new \DateTime();
                if ($creneau->getHeureDebut()->format('H:i') < $now->format('H:i')) {
                    $this->addFlash('error', 'Vous ne pouvez pas choisir une heure passée pour aujourd\'hui !');
                    return $this->render('psychologue/ajout_creneau.html.twig', [
                        'form' => $form->createView(),
                        'isNew' => $isNew,
                    ]);
                }
            }

            if ($isNew) {
                $creneauRepository->addCreneau($creneau);
                $this->addFlash('success', 'Créneau ajouté avec succès !');
            } else {
                $creneauRepository->updateCreneau($creneau);
                $this->addFlash('success', 'Créneau modifié avec succès !');
            }

            return $this->redirectToRoute('app_creneau_index');
        }

        return $this->render('psychologue/ajout_creneau.html.twig', [
            'form' => $form->createView(),
            'isNew' => $isNew,
        ]);
    }
}
