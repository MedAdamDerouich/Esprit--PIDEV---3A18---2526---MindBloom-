<?php

namespace App\Controller\Admin;

use App\Entity\Facture;
use App\Repository\FactureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
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
        foreach ($factures as $facture) {
            $totalValue += $facture->getMontantTotal();
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
            'lowStockItems' => $lowStockProducts
        ]);
    }

    #[Route('/ai-stock', name: 'app_admin_stock_ai', methods: ['GET'])]
    public function aiStockAnalysis(\App\Service\GeminiService $geminiService, \App\Repository\ProduitRepository $produitRepository): Response
    {
        $allProducts = $produitRepository->findAll();
        $lowStockProducts = array_filter($allProducts, fn($p) => $p->getQuantite() <= $p->getStockSeuil());
        
        $insights = $geminiService->getStockAnalysis($lowStockProducts);
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

            $totalSpent = array_reduce($userOrders, fn($carry, $f) => $carry + $f->getMontantTotal(), 0);
            
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
    public function ship(Facture $facture, EntityManagerInterface $entityManager, MailerInterface $mailer, \App\Service\PdfService $pdfService): Response
    {
        $facture->setStatutLivraison('ENVOYE');
        $entityManager->flush();

        if ($facture->getUser() && $facture->getUser()->getEmail()) {
            $user = $facture->getUser();
            $email = (new TemplatedEmail())
                ->from('mindbloom.platform@gmail.com')
                ->to($user->getEmail())
                ->subject('Votre commande MindBloom a été expédiée ! 📦')
                ->htmlTemplate('email/order_status.html.twig');

            $context = [
                'facture' => $facture,
                'status' => 'ENVOYE',
                'user_image_exists' => false,
                'product_images' => []
            ];

            $publicDir = $this->getParameter('kernel.project_dir') . '/public';

            // Embed Profile Image
            if ($user->getProfileImage()) {
                $profilePath = $publicDir . '/uploads/profiles/' . $user->getProfileImage();
                if (file_exists($profilePath)) {
                    $email->embedFromPath($profilePath, 'user_profile');
                    $context['user_image_exists'] = true;
                }
            }

            // Embed Product Images
            foreach ($facture->getCommandes() as $commande) {
                $produit = $commande->getProduit();
                if ($produit && $produit->getImage()) {
                    $prodPath = $publicDir . '/uploads/produits/' . $produit->getImage();
                    if (file_exists($prodPath)) {
                        $email->embedFromPath($prodPath, 'product_' . $produit->getId());
                        $context['product_images'][$produit->getId()] = true;
                    }
                }
            }

            $email->context($context);

            // Generate and attach PDF
            $pdfContent = $pdfService->generateInvoicePdf($facture);
            $email->attach($pdfContent, sprintf('facture-%s.pdf', $facture->getId()), 'application/pdf');

            try {
                $mailer->send($email);
                $this->addFlash('success', 'Commande marquée comme expédiée. Un email avec images et facture PDF a été envoyé.');
            } catch (\Exception $e) {
                $this->addFlash('warning', 'Commande expédiée, mais l\'email n\'a pas pu être envoyé. Vérifiez votre configuration MAILER_DSN.');
            }
        }
        return $this->redirectToRoute('app_admin_order_index');
    }

    #[Route('/annuler/{id}', name: 'app_admin_order_cancel', methods: ['POST'])]
    public function cancel(Facture $facture, EntityManagerInterface $entityManager, MailerInterface $mailer, \App\Service\PdfService $pdfService): Response
    {
        $facture->setStatutLivraison('ANNULE');
        
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

        if ($facture->getUser() && $facture->getUser()->getEmail()) {
            $user = $facture->getUser();
            $email = (new TemplatedEmail())
                ->from('mindbloom.platform@gmail.com')
                ->to($user->getEmail())
                ->subject('Annulation de votre commande MindBloom ❌')
                ->htmlTemplate('email/order_status.html.twig');

            $context = [
                'facture' => $facture,
                'status' => 'ANNULE',
                'user_image_exists' => false,
                'product_images' => []
            ];

            $publicDir = $this->getParameter('kernel.project_dir') . '/public';

            if ($user->getProfileImage()) {
                $profilePath = $publicDir . '/uploads/profiles/' . $user->getProfileImage();
                if (file_exists($profilePath)) {
                    $email->embedFromPath($profilePath, 'user_profile');
                    $context['user_image_exists'] = true;
                }
            }

            $email->context($context);

            // Attach PDF even on cancellation if needed
            $pdfContent = $pdfService->generateInvoicePdf($facture);
            $email->attach($pdfContent, sprintf('facture-annulee-%s.pdf', $facture->getId()), 'application/pdf');

            try {
                $mailer->send($email);
                $this->addFlash('success', 'Commande annulée et stock restauré. Un email a été envoyé au client.');
            } catch (\Exception $e) {
                $this->addFlash('warning', 'Commande annulée, mais l\'email n\'a pas pu être envoyé. Vérifiez votre configuration MAILER_DSN.');
            }
        } else {
            $this->addFlash('success', 'Commande annulée et stock restauré.');
        }

        return $this->redirectToRoute('app_admin_order_index');
    }
    #[Route('/ai-analyse', name: 'app_admin_order_ai', methods: ['GET'])]
    public function aiAnalytics(\App\Service\GeminiService $geminiService, FactureRepository $factureRepository): Response
    {
        $factures = $factureRepository->findAll();
        if (empty($factures)) {
            return new Response("Pas de données suffisantes pour une analyse IA.");
        }
        
        $insights = $geminiService->getOrderAnalytics($factures);
        return new Response($insights);
    }

    #[Route('/ai-analyse/pdf', name: 'app_admin_order_ai_pdf', methods: ['GET'])]
    public function aiAnalyticsPdf(\App\Service\GeminiService $geminiService, \App\Service\PdfService $pdfService, FactureRepository $factureRepository): Response
    {
        $factures = $factureRepository->findAll();
        if (empty($factures)) {
            return new Response("Pas de données.");
        }
        
        $insights = $geminiService->getOrderAnalytics($factures);
        $pdfContent = $pdfService->generateAiReportPdf($insights);

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="rapport-ia-mindbloom.pdf"'
        ]);
    }
}
