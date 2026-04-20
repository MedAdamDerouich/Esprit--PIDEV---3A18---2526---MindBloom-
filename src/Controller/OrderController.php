<?php

namespace App\Controller;

use App\Entity\Facture;
use App\Repository\CommandeRepository;
use App\Repository\FactureRepository;
use App\Service\WalletService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/commande')]
#[IsGranted('ROLE_USER')]
class OrderController extends AbstractController
{
    #[Route('/passer', name: 'app_order_checkout', methods: ['GET', 'POST'])]
    public function checkout(Request $request, CommandeRepository $commandeRepository, EntityManagerInterface $entityManager, WalletService $walletService): Response
    {
        $user = $this->getUser();
        $cartItems = $commandeRepository->findCartByUser($user);

        if (empty($cartItems)) {
            $this->addFlash('warning', 'Votre panier est vide.');
            return $this->redirectToRoute('app_produit_index');
        }

        $subtotal = array_reduce($cartItems, fn($c, $i) => $c + $i->getTotal(), 0);
        $tva      = $subtotal * 0.07;
        $shipping = 2;
        $total    = $subtotal + $tva + $shipping;

        if ($request->isMethod('POST')) {
            $paiement = $request->request->get('paiement');

            // ── Wallet payment ──────────────────────────────────────────
            if ($paiement === 'WALLET') {
                $success = $walletService->deduct(
                    $user,
                    $total,
                    'Commande produits MindBloom'
                );

                if (!$success) {
                    $this->addFlash('error', sprintf(
                        'Solde insuffisant. Votre wallet contient %.2f DT et le total est %.2f DT.',
                        $walletService->getBalance($user),
                        $total
                    ));
                    return $this->redirectToRoute('app_order_checkout');
                }
            }

            $facture = new Facture();
            $facture->setUser($user);
            $facture->setMontantTotal($total);
            $facture->setAdresseLivraison($request->request->get('adresse'));
            $facture->setTypePaiement($paiement);

            foreach ($cartItems as $item) {
                $facture->addCommande($item);
                $produit = $item->getProduit();
                $produit->setQuantite($produit->getQuantite() - $item->getQuantite());
            }

            $entityManager->persist($facture);
            $entityManager->flush();

            $this->addFlash('success', 'Votre commande a été passée avec succès !');
            return $this->redirectToRoute('app_order_history');
        }

        return $this->render('order/checkout.html.twig', [
            'items'          => $cartItems,
            'subtotal'       => $subtotal,
            'tva'            => $tva,
            'shipping'       => $shipping,
            'total'          => $total,
            'walletBalance'  => $walletService->getBalance($user),
        ]);
    }

    #[Route('/historique', name: 'app_order_history', methods: ['GET'])]
    public function history(FactureRepository $factureRepository, Request $request): Response
    {
        $tri = $request->query->get('tri', 'date_desc');
        $statut = $request->query->get('statut', '');
        $search = $request->query->get('q', '');

        $qb = $factureRepository->createQueryBuilder('f')
            ->where('f.user = :user')
            ->setParameter('user', $this->getUser());

        // --- Filtrage par statut ---
        if ($statut) {
            if ($statut === Facture::STATUS_CANCELLED) {
                $qb->andWhere('f.statutLivraison = :statut OR f.statutLivraison IS NULL OR f.statutLivraison = \'\'')
                   ->setParameter('statut', $statut);
            } else {
                $qb->andWhere('f.statutLivraison = :statut')
                   ->setParameter('statut', $statut);
            }
        }

        // --- Recherche par mot-clé (ID ou Adresse) ---
        if ($search) {
            $qb->andWhere('f.id = :searchNumeric OR f.adresseLivraison LIKE :searchText')
               ->setParameter('searchNumeric', is_numeric($search) ? (int)$search : 0)
               ->setParameter('searchText', '%' . $search . '%');
        }

        // --- Tri ---
        if ($tri === 'date_asc') {
            $qb->orderBy('f.dateFacture', 'ASC');
        } elseif ($tri === 'prix_desc') {
            $qb->orderBy('f.montantTotal', 'DESC');
        } elseif ($tri === 'prix_asc') {
            $qb->orderBy('f.montantTotal', 'ASC');
        } else {
            $qb->orderBy('f.dateFacture', 'DESC');
        }

        $factures = $qb->getQuery()->getResult();
        
        // Ensure commands with missing products don't crash the list rendering
        foreach ($factures as $facture) {
            foreach ($facture->getCommandes() as $commande) {
                try {
                    if ($commande->getProduit() && $commande->getProduit()->getNom()) {
                        // OK
                    } else {
                        $facture->getCommandes()->removeElement($commande);
                    }
                } catch (\Doctrine\ORM\EntityNotFoundException $e) {
                    $facture->getCommandes()->removeElement($commande);
                }
            }
        }

        return $this->render('order/history.html.twig', [
            'factures' => $factures,
            'tri'      => $tri,
            'statut'   => $statut,
            'search'   => $search,
            'STATUS_PENDING' => Facture::STATUS_PENDING,
            'STATUS_SHIPPED' => Facture::STATUS_SHIPPED,
            'STATUS_CANCELLED' => Facture::STATUS_CANCELLED,
        ]);
    }

    #[Route('/{id}', name: 'app_order_show', methods: ['GET'])]
    public function show(Facture $facture): Response
    {
        if ($facture->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        // Filter valid commands (where product still exists)
        $validCommands = [];
        foreach ($facture->getCommandes() as $commande) {
            try {
                // Accessing a product property to trigger proxy loading
                if ($commande->getProduit() && $commande->getProduit()->getNom()) {
                    $validCommands[] = $commande;
                } else {
                    $facture->getCommandes()->removeElement($commande);
                }
            } catch (\Doctrine\ORM\EntityNotFoundException $e) {
                // remove from memory to prevent crash in template if we iterate over collection
                $facture->getCommandes()->removeElement($commande);
            }
        }

        $total = $facture->getMontantTotal();
        $shipping = 2;
        $subtotal = ($total - $shipping) / 1.07;
        $tva = $subtotal * 0.07;

        return $this->render('order/show.html.twig', [
            'facture' => $facture,
            'commandes' => $validCommands, // Pass filtered commands
            'subtotal' => $subtotal,
            'tva' => $tva,
            'shipping' => $shipping
        ]);
    }

    #[Route('/pdf/{id}', name: 'app_order_pdf', methods: ['GET'])]
    public function downloadPdf(Facture $facture, \App\Service\PdfService $pdfService): Response
    {
        if ($facture->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $pdfContent = $pdfService->generateInvoicePdf($facture);

        return new Response(
            $pdfContent,
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="facture-%s.pdf"', $facture->getId())
            ]
        );
    }

    #[Route('/annuler/{id}', name: 'app_order_cancel', methods: ['POST'])]
    public function patientCancel(Facture $facture, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        if ($facture->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($facture->getStatutLivraison() !== Facture::STATUS_PENDING) {
            $this->addFlash('error', 'Vous ne pouvez plus annuler cette commande.');
            return $this->redirectToRoute('app_order_history');
        }

        $facture->setStatutLivraison(Facture::STATUS_CANCELLED);

        // Restore stock
        foreach ($facture->getCommandes() as $commande) {
            $produit = $commande->getProduit();
            if ($produit) {
                try {
                    $produit->setQuantite($produit->getQuantite() + $commande->getQuantite());
                } catch (\Doctrine\ORM\EntityNotFoundException $e) {
                    // ignore
                }
            }
        }

        $entityManager->flush();

        // Send confirmation email
        if ($facture->getUser() && $facture->getUser()->getEmail()) {
            $user = $facture->getUser();
            $email = (new TemplatedEmail())
                ->from('mindbloom.platform@gmail.com')
                ->to($user->getEmail())
                ->subject('Confirmation d\'annulation de votre commande MindBloom ❌')
                ->htmlTemplate('email/order_status.html.twig');

            $context = [
                'facture' => $facture,
                'status' => Facture::STATUS_CANCELLED,
                'user_image_exists' => false,
                'product_images' => []
            ];

            // Embed Profile Image
            $publicDir = $this->getParameter('kernel.project_dir') . '/public';
            if ($user->getProfileImage()) {
                $profilePath = $publicDir . '/uploads/profiles/' . $user->getProfileImage();
                if (file_exists($profilePath)) {
                    $email->embedFromPath($profilePath, 'user_profile');
                    $context['user_image_exists'] = true;
                }
            }

            $email->context($context);

            try {
                $mailer->send($email);
            } catch (\Exception $e) {
                // Silently fail if mailer not configured
            }
        }

        $this->addFlash('success', 'Votre commande a été annulée avec succès.');

        return $this->redirectToRoute('app_order_history');
    }
}
