<?php

namespace App\Controller;

use App\Entity\Cabinet;
use App\Repository\CabinetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PSYCHOLOGUE')]
class CabinetController extends AbstractController
{
    #[Route('/psychologue/cabinet', name: 'app_cabinet')]
    public function index(CabinetRepository $cabinetRepository): Response
    {
        $user = $this->getUser();
        
        // Fetch the cabinet associated with the logged in psychologist
        $cabinet = $cabinetRepository->findOneBy(['psychologue' => $user]);

        return $this->render('psychologue/cabinet.html.twig', [
            'cabinet' => $cabinet,
        ]);
    }

    #[Route('/psychologue/cabinet/supprimer', name: 'app_cabinet_supprimer')]
    public function supprimer(EntityManagerInterface $em, CabinetRepository $cabinetRepository): Response
    {
        $user = $this->getUser();
        $cabinet = $cabinetRepository->findOneBy(['psychologue' => $user]);

        if ($cabinet) {
            $em->remove($cabinet);
            $em->flush();
            $this->addFlash('success', 'Cabinet supprimé avec succès.');
        }

        return $this->redirectToRoute('app_cabinet');
    }

    #[Route('/psychologue/cabinet/editer', name: 'app_cabinet_editer', methods: ['GET', 'POST'])]
    public function editer(Request $request, CabinetRepository $cabinetRepository): Response
    {
        $user = $this->getUser();
        $cabinet = $cabinetRepository->findOneBy(['psychologue' => $user]);

        if ($request->isMethod('POST')) {
            $nomCabinet = $request->request->get('nomCabinet');
            $adresse = $request->request->get('adresseCabinet');
            $specialite = $request->request->get('specialiteCabinet');
            $telephone = $request->request->get('telephoneCabinet');
            $description = $request->request->get('descriptionCabinet');

            if (empty($nomCabinet)) {
                $this->addFlash('error', 'Le nom du cabinet est obligatoire !');
                return $this->render('psychologue/ajout_cabinet.html.twig', [
                    'cabinet' => $cabinet,
                ]);
            }

            if (!$cabinet) {
                // Equivalent de "if (cabinet == null)"
                $newCabinet = new Cabinet();
                $newCabinet->setPsychologue($user);
                $newCabinet->setNomCabinet($nomCabinet);
                $newCabinet->setAdresse($adresse);
                $newCabinet->setSpecialite($specialite);
                $newCabinet->setTelephone($telephone);
                $newCabinet->setDescription($description);

                $cabinetRepository->addCabinet($newCabinet);
                $this->addFlash('success', 'Cabinet ajouté avec succès.');
            } else {
                // Equivalent de "else (modification)"
                $cabinet->setNomCabinet($nomCabinet);
                $cabinet->setAdresse($adresse);
                $cabinet->setSpecialite($specialite);
                $cabinet->setTelephone($telephone);
                $cabinet->setDescription($description);

                $cabinetRepository->updateCabinet($cabinet);
                $this->addFlash('success', 'Cabinet modifié avec succès.');
            }

            return $this->redirectToRoute('app_cabinet');
        }

        return $this->render('psychologue/ajout_cabinet.html.twig', [
            'cabinet' => $cabinet,
        ]);
    }
}
