<?php

namespace App\Controller;

use App\Repository\CreneauRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
}
