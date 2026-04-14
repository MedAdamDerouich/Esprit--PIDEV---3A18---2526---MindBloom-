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

        // On récupère les créneaux du cabinet groupés par date
        $groupedCreneaux = [];
        if ($cabinet->getPsychologue()) {
            $creneaux = $creneauRepository->findBy(
                ['cabinet' => $cabinet],
                ['dateCreneau' => 'ASC', 'heureDebut' => 'ASC']
            );

            foreach ($creneaux as $c) {
                $dateKey = $c->getDateCreneau()->format('Y-m-d');
                if (!isset($groupedCreneaux[$dateKey])) {
                    $groupedCreneaux[$dateKey] = [
                        'date' => $c->getDateCreneau(),
                        'slots' => []
                    ];
                }
                $groupedCreneaux[$dateKey]['slots'][] = $c;
            }
        }

        return $this->render('patient/reserver.html.twig', [
            'cabinet' => $cabinet,
            'groupedCreneaux' => $groupedCreneaux,
        ]);
    }


    #[Route('/patient/availability/{date}', name: 'app_patient_availability')]
    public function checkAvailability(string $date, \App\Repository\CreneauRepository $creneauRepository): Response
    {
        try {
            $selectedDate = new \DateTime($date);
        } catch (\Exception $e) {
            return new Response('Date invalide', 400);
        }

        // On cherche tous les créneaux disponibles pour cette date
        $creneaux = $creneauRepository->findBy(
            ['dateCreneau' => $selectedDate, 'disponible' => true],
            ['heureDebut' => 'ASC']
        );

        // Grouper les créneaux par cabinet/psychologue
        $grouped = [];
        foreach ($creneaux as $c) {
            $cabinet = $c->getCabinet();
            if (!$cabinet) continue;
            
            $id = $cabinet->getIdCabinet();
            if (!isset($grouped[$id])) {
                $grouped[$id] = [
                    'cabinet' => $cabinet,
                    'slots' => []
                ];
            }
            $grouped[$id]['slots'][] = $c;
        }

        return $this->render('patient/_availability_modal.html.twig', [
            'grouped' => $grouped,
            'selectedDate' => $selectedDate
        ]);
    }
}

