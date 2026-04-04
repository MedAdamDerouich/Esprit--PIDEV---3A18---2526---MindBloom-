<?php

namespace App\Controller;

use App\Repository\CabinetRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PatientCabinetController extends AbstractController
{
    #[Route('/patient/cabinets', name: 'app_patient_cabinets_index')]
    public function index(CabinetRepository $cabinetRepository): Response
    {
        // On récupère tous les cabinets disponibles avec leur psychologue rattaché
        $cabinets = $cabinetRepository->findAllWithPsychologue();

        return $this->render('patient/liste_cabinets.html.twig', [
            'cabinets' => $cabinets,
        ]);
    }
}
