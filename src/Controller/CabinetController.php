<?php

namespace App\Controller;

use App\Entity\Cabinet;
use App\Form\CabinetType;
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
        $isNew = false;

        if (!$cabinet) {
            $cabinet = new Cabinet();
            $cabinet->setPsychologue($user);
            $isNew = true;
        }

        // Créer le formulaire Symfony
        $form = $this->createForm(CabinetType::class, $cabinet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($isNew) {
                $cabinetRepository->addCabinet($cabinet);
                $this->addFlash('success', 'Cabinet ajouté avec succès.');
            } else {
                $cabinetRepository->updateCabinet($cabinet);
                $this->addFlash('success', 'Cabinet modifié avec succès.');
            }

            return $this->redirectToRoute('app_cabinet');
        }

        return $this->render('psychologue/ajout_cabinet.html.twig', [
            'form' => $form->createView(),
            'cabinet' => $cabinet->getIdCabinet() ? $cabinet : null, // keep null if new for UI logic
        ]);
    }
}
