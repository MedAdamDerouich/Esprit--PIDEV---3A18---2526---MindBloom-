<?php

namespace App\Controller;

use App\Repository\CabinetRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PatientCabinetController extends AbstractController
{
    #[Route('/patient/cabinets', name: 'app_patient_cabinets_index')]
    public function index(CabinetRepository $cabinetRepository, \Symfony\Component\HttpFoundation\Request $request): Response
    {
        $cabinets = $cabinetRepository->findAllWithPsychologue();

        // Pagination setup
        $page = $request->query->getInt('page', 1);
        $limit = 3;
        $totalCabinets = count($cabinets);
        $totalPages = (int) ceil($totalCabinets / $limit);
        
        if ($page < 1) $page = 1;
        if ($totalPages > 0 && $page > $totalPages) $page = $totalPages;

        $offset = ($page - 1) * $limit;
        
        // Handle empty case
        $paginatedCabinets = $totalCabinets > 0 ? array_slice($cabinets, $offset, $limit) : [];

        return $this->render('patient/liste_cabinets.html.twig', [
            'cabinets' => $paginatedCabinets,
            'currentPage' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/patient/cabinets/{id}/reserver', name: 'app_patient_cabinet_reserver')]
    public function reserver(int $id, CabinetRepository $cabinetRepository, \App\Repository\CreneauRepository $creneauRepository): Response
    {
        $cabinet = $cabinetRepository->find($id);

        if (!$cabinet) {
            throw $this->createNotFoundException('Cabinet introuvable');
        }

        // On récupère les créneaux du cabinet
        $creneaux = [];
        if ($cabinet->getPsychologue()) {
            $creneaux = $creneauRepository->findBy(
                ['cabinet' => $cabinet],
                ['dateCreneau' => 'ASC', 'heureDebut' => 'ASC']
            );
        }

        return $this->render('patient/reserver.html.twig', [
            'cabinet' => $cabinet,
            'creneaux' => $creneaux,
        ]);
    }
}
