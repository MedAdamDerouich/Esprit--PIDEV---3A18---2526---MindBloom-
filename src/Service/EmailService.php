<?php

namespace App\Service;

use App\Entity\Facture;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class EmailService
{
    private MailerInterface $mailer;
    private ParameterBagInterface $params;
    private PdfService $pdfService;

    public function __construct(MailerInterface $mailer, ParameterBagInterface $params, PdfService $pdfService)
    {
        $this->mailer = $mailer;
        $this->params = $params;
        $this->pdfService = $pdfService;
    }

    /**
     * Sends an email notification to the customer about their order status.
     * 
     * @param Facture $facture The order entity
     * @param string $status The new status (Facture::STATUS_SHIPPED or Facture::STATUS_CANCELLED)
     * @return bool True if sent successfully, false otherwise
     */
    public function sendOrderStatusEmail(Facture $facture, string $status): bool
    {
        $user = $facture->getUser();
        if (!$user || !$user->getEmail()) {
            return false;
        }

        $subject = ($status === Facture::STATUS_SHIPPED) 
            ? 'Votre commande MindBloom a été expédiée ! 📦' 
            : 'Mise à jour de votre commande MindBloom ❌';

        $email = (new TemplatedEmail())
            ->from('mindbloom.platform@gmail.com')
            ->to($user->getEmail())
            ->subject($subject)
            ->htmlTemplate('email/order_status.html.twig');

        $context = [
            'facture' => $facture,
            'status' => $status,
            'user_image_exists' => false,
            'product_images' => []
        ];

        $publicDir = $this->params->get('kernel.project_dir') . '/public';

        // Embed Profile Image
        if ($user->getProfileImage()) {
            $profilePath = $publicDir . '/uploads/profiles/' . $user->getProfileImage();
            if (file_exists($profilePath)) {
                $email->embedFromPath($profilePath, 'user_profile');
                $context['user_image_exists'] = true;
            }
        }

        // Embed Product Images for shipments
        if ($status === Facture::STATUS_SHIPPED) {
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
        }

        $email->context($context);

        // Generate and attach PDF (Invoice)
        try {
            $pdfContent = $this->pdfService->generateInvoicePdf($facture);
            $filename = ($status === Facture::STATUS_CANCELLED) 
                ? sprintf('facture-annulee-%s.pdf', $facture->getId())
                : sprintf('facture-%s.pdf', $facture->getId());
            
            $email->attach($pdfContent, $filename, 'application/pdf');
        } catch (\Exception $e) {
            // Log PDF generation failure but continue with email
        }

        try {
            $this->mailer->send($email);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
