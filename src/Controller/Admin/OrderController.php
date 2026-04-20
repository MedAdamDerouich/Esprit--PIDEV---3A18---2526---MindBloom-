<?php

namespace App\Controller\Admin;

use App\Entity\Facture;
use App\Repository\FactureRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/commande')]
#[IsGranted('ROLE_ADMIN')]
class OrderController extends AbstractController
{
    #[Route('', name: 'app_admin_order_index', methods: ['GET'])]
    public function index(FactureRepository $factureRepository, \App\Repository\ProduitRepository $produitRepository, \Symfony\Component\HttpFoundation\Request $request): Response
    {
        $q = $request->query->get('q');
        
        // Stock Alert Logic
        $allProducts = $produitRepository->findAll();
        $lowStockProducts = array_filter($allProducts, fn($p) => $p->getQuantite() <= $p->getStockSeuil());

        if ($q) {
            $qb = $factureRepository->createQueryBuilder('f')
                ->leftJoin('f.user', 'u')
                ->where('u.fullName LIKE :q OR u.email LIKE :q OR f.id = :id')
                ->setParameter('q', '%' . $q . '%')
                ->setParameter('id', is_numeric($q) ? (int)$q : 0)
                ->orderBy('f.dateFacture', 'DESC');
            $factures = $qb->getQuery()->getResult();
        } else {
            $factures = $factureRepository->findBy([], ['dateFacture' => 'DESC']);
        }

        $totalValue = 0;
        $countPending = 0;
        $countShipped = 0;
        $countCancelled = 0;

        foreach ($factures as $facture) {
            $status = $facture->getStatutLivraison();
            if ($status === Facture::STATUS_PENDING) $countPending++;
            elseif ($status === Facture::STATUS_SHIPPED) $countShipped++;
            elseif ($status === Facture::STATUS_CANCELLED || empty($status)) $countCancelled++;

            // Only count non-cancelled and non-empty orders in total revenue
            if ($status !== Facture::STATUS_CANCELLED && !empty($status)) {
                $totalValue += $facture->getMontantTotal();
            }

            foreach ($facture->getCommandes() as $commande) {
                try {
                    if ($commande->getProduit() && $commande->getProduit()->getNom()) {
                    } else {
                        $facture->getCommandes()->removeElement($commande);
                    }
                } catch (\Doctrine\ORM\EntityNotFoundException $e) {
                    $facture->getCommandes()->removeElement($commande);
                }
            }
        }

        return $this->render('admin/order/index.html.twig', [
            'factures' => $factures,
            'totalValue' => $totalValue,
            'q' => $q,
            'lowStockCount' => count($lowStockProducts),
            'lowStockItems' => $lowStockProducts,
            'STATUS_PENDING' => Facture::STATUS_PENDING,
            'STATUS_SHIPPED' => Facture::STATUS_SHIPPED,
            'STATUS_CANCELLED' => Facture::STATUS_CANCELLED,
            'countPending' => $countPending,
            'countShipped' => $countShipped,
            'countCancelled' => $countCancelled,
            'countAll' => count($factures),
        ]);
    }

    #[Route('/ai-stock', name: 'app_admin_stock_ai', methods: ['GET'])]
    public function aiStockAnalysis(\App\Service\GroqService $groq, \App\Repository\ProduitRepository $produitRepository): Response
    {
        $allProducts = $produitRepository->findAll();
        $lowStockProducts = array_filter($allProducts, fn($p) => $p->getQuantite() <= $p->getStockSeuil());
        
        $insights = $groq->getStockAnalysis($lowStockProducts);
        return new Response($insights);
    }

    #[Route('/patients', name: 'app_admin_order_customers', methods: ['GET'])]
    public function customers(FactureRepository $factureRepository, \App\Repository\UserRepository $userRepository, \Symfony\Component\HttpFoundation\Request $request): Response
    {
        $q = $request->query->get('q');
        $tri = $request->query->get('tri', 'total_desc');

        // Logic to get users with their total spent
        // We can do this with a custom query in FactureRepository or just process in PHP for simplicity if user count is low
        $users = $userRepository->findAll();
        $customerStats = [];

        foreach ($users as $user) {
            if ($user->getRoles() && in_array('ADMIN', $user->getRoles())) continue;

            $userOrders = $factureRepository->findBy(['user' => $user]);
            if (count($userOrders) === 0 && !$q) continue; // Skip users with no orders unless searching

            // Only sum orders that are NOT cancelled
            $totalSpent = array_reduce($userOrders, function($carry, $f) {
                return $f->getStatutLivraison() !== Facture::STATUS_CANCELLED ? $carry + $f->getMontantTotal() : $carry;
            }, 0);
            
            // Search filter
            if ($q && !str_contains(strtolower($user->getFullName() ?? ''), strtolower($q)) && !str_contains(strtolower($user->getEmail() ?? ''), strtolower($q))) {
                continue;
            }

            $customerStats[] = [
                'user' => $user,
                'orderCount' => count($userOrders),
                'totalSpent' => $totalSpent
            ];
        }

        // Sorting
        usort($customerStats, function($a, $b) use ($tri) {
            if ($tri === 'total_asc') return $a['totalSpent'] <=> $b['totalSpent'];
            if ($tri === 'total_desc') return $b['totalSpent'] <=> $a['totalSpent'];
            if ($tri === 'orders_desc') return $b['orderCount'] <=> $a['orderCount'];
            return 0;
        });

        return $this->render('admin/order/customers.html.twig', [
            'customers' => $customerStats,
            'q' => $q,
            'tri' => $tri
        ]);
    }

    #[Route('/expedier/{id}', name: 'app_admin_order_ship', methods: ['POST'])]
    public function ship(Facture $facture, EntityManagerInterface $entityManager, EmailService $emailService): Response
    {
        $facture->setStatutLivraison(Facture::STATUS_SHIPPED);
        $entityManager->flush();

        if ($emailService->sendOrderStatusEmail($facture, Facture::STATUS_SHIPPED)) {
            $this->addFlash('success', 'Commande marquée comme expédiée. Un email avec images et facture PDF a été envoyé.');
        } else {
            $this->addFlash('warning', 'Commande expédiée, mais l\'email n\'a pas pu être envoyé. Vérifiez votre configuration MAILER_DSN.');
        }

        return $this->redirectToRoute('app_admin_order_index');
    }

    #[Route('/annuler/{id}', name: 'app_admin_order_cancel', methods: ['POST'])]
    public function cancel(Facture $facture, EntityManagerInterface $entityManager, EmailService $emailService): Response
    {
        $facture->setStatutLivraison(Facture::STATUS_CANCELLED);
        
        foreach ($facture->getCommandes() as $commande) {
            $produit = $commande->getProduit();
            if ($produit) {
                try {
                    $produit->setQuantite($produit->getQuantite() + $commande->getQuantite());
                } catch (\Doctrine\ORM\EntityNotFoundException $e) {
                    // Product was deleted, just skip stock restoration
                }
            }
        }

        $entityManager->flush();

        if ($emailService->sendOrderStatusEmail($facture, Facture::STATUS_CANCELLED)) {
            $this->addFlash('success', 'Commande annulée et stock restauré. Un email a été envoyé au client.');
        } else {
            $this->addFlash('success', 'Commande annulée et stock restauré (l\'email n\'a pas pu être envoyé).');
        }

        return $this->redirectToRoute('app_admin_order_index');
    }
    #[Route('/ai-analyse', name: 'app_admin_order_ai', methods: ['GET'])]
    public function aiAnalytics(\App\Service\GroqService $groq, FactureRepository $factureRepository): Response
    {
        $factures = $factureRepository->findAll();
        if (empty($factures)) {
            return new Response("Pas de données suffisantes pour une analyse IA.");
        }
        
        $insights = $groq->getOrderAnalytics($factures);
        return new Response($insights);
    }

    #[Route('/ai-analyse/pdf', name: 'app_admin_order_ai_pdf', methods: ['GET'])]
    public function aiAnalyticsPdf(\App\Service\GroqService $groq, \App\Service\PdfService $pdfService, FactureRepository $factureRepository): Response
    {
        $factures = $factureRepository->findAll();
        if (empty($factures)) {
            return new Response("Pas de données.");
        }
        
        $insights = $groq->getOrderAnalytics($factures);
        $pdfContent = $pdfService->generateAiReportPdf($insights);

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="rapport-ia-mindbloom.pdf"'
        ]);
    }
}
