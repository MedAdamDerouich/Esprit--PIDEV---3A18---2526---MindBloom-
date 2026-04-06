<?php

namespace App\Controller;

use App\Entity\Test;
use App\Repository\TestRepository;
use App\Repository\ResultatTestRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/tests')]
#[IsGranted('ROLE_ADMIN')]
class AdminTestController extends AbstractController
{
    #[Route('', name: 'app_admin_test_index', methods: ['GET'])]
    public function index(TestRepository $testRepository): Response
    {
        return $this->render('admin/test/index.html.twig', [
            'tests' => $testRepository->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_test_show', methods: ['GET'])]
    public function show(Test $test, ResultatTestRepository $resultatRepo): Response
    {
        // Count how many patients took this test (using DISTINCT to count each patient once)
        $patientCount = $resultatRepo->createQueryBuilder('r')
            ->select('COUNT(DISTINCT r.patient)')
            ->where('r.test = :test')
            ->setParameter('test', $test)
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('admin/test/show.html.twig', [
            'test' => $test,
            'patientCount' => $patientCount,
        ]);
    }
}
