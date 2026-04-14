<?php

namespace App\Controller;

use App\Repository\CabinetRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PatientCabinetController extends AbstractController
{
    #[Route('/patient/cabinets', name: 'app_patient_cabinets_index')]
    public function index(
        CabinetRepository $cabinetRepository, 
        \Symfony\Component\HttpFoundation\Request $request, 
        \App\Repository\CreneauRepository $creneauRepository,
        PaginatorInterface $paginator
    ): Response
    {
        $cabinetsQuery = $cabinetRepository->findAllWithPsychologueQuery();

        // Extraire les dates ayant des créneaux disponibles
        $creneauxDispos = $creneauRepository->findBy(['disponible' => true]);
        $availableDates = [];
        $today = new \DateTime('today');
        foreach($creneauxDispos as $c) {
            $date = $c->getDateCreneau();
            if ($date >= $today) {
                 $availableDates[] = $date->format('Y-m-d');
            }
        }
        $availableDates = array_values(array_unique($availableDates));


        // Pagination using KnpPaginatorBundle
        $pagination = $paginator->paginate(
            $cabinetsQuery, // source data (array or query)
            $request->query->getInt('page', 1), // current page number
            3 // limit per page
        );

        return $this->render('patient/liste_cabinets.html.twig', [
            'cabinets' => $pagination,
            'availableDates' => $availableDates,
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

