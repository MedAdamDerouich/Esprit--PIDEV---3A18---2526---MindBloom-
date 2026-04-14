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
    public function index(TestRepository $testRepository, ResultatTestRepository $resultatRepo): Response
    {
        $tests = $testRepository->findAll();
        
        // Global statistics for charts
        $statesDistribution = $resultatRepo->createQueryBuilder('r')
            ->select('r.etat as label, COUNT(r.id) as count')
            ->where("r.etat IS NOT NULL")
            ->andWhere("r.etat != ''")
            ->groupBy('r.etat')
            ->getQuery()
            ->getArrayResult();

        $categoryDistribution = $testRepository->createQueryBuilder('t')
            ->select('t.categorie as label, COUNT(t.id) as count')
            ->where("t.categorie IS NOT NULL")
            ->andWhere("t.categorie != ''")
            ->groupBy('t.categorie')
            ->getQuery()
            ->getArrayResult();

        return $this->render('admin/test/index.html.twig', [
            'tests' => $tests,
            'statesDist' => json_encode($statesDistribution),
            'catDist' => json_encode($categoryDistribution),
        ]);
    }

    #[Route('/export', name: 'app_admin_test_export', methods: ['GET'])]
    public function exportCsv(TestRepository $testRepository, ResultatTestRepository $resultatRepo): Response
    {
        $tests = $testRepository->findAll();
        $csvContent = "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
        $csvContent .= "ID;Nom du Test;Categorie;Date Creation;Dimensions (min);Nb Questions;Nb Participations\n";
        
        foreach ($tests as $t) {
            $patientCount = $resultatRepo->createQueryBuilder('r')
                ->select('COUNT(DISTINCT r.patient)')
                ->where('r.test = :test')
                ->setParameter('test', $t)
                ->getQuery()
                ->getSingleScalarResult();
                
            $date = $t->getDateTest() ? $t->getDateTest()->format('Y-m-d') : '';
            $qs = count($t->getQuestions());
            
            $nom = str_replace('"', '""', (string)$t->getNomTest());
            $cat = str_replace('"', '""', (string)$t->getCategorie());
            $csvContent .= sprintf("%d;\"%s\";\"%s\";%s;%d;%d;%d\n", $t->getId(), $nom, $cat, $date, $t->getDuree(), $qs, $patientCount);
        }

        return new Response($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="MindBloom_Tests_Export.csv"',
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
