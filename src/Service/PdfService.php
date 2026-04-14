<?php

namespace App\Service;

use App\Entity\Facture;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Twig\Environment;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class PdfService
{
    private $twig;
    private $projectDir;

    public function __construct(
        Environment $twig, 
        #[Autowire(param: 'kernel.project_dir')] string $projectDir
    )
    {
        $this->twig = $twig;
        $this->projectDir = $projectDir;
    }

    public function generateInvoicePdf(Facture $facture): string
    {
        $validCommands = [];
        foreach ($facture->getCommandes() as $commande) {
            try {
                if ($commande->getProduit() && $commande->getProduit()->getNom()) {
                    $validCommands[] = $commande;
                }
            } catch (\Doctrine\ORM\EntityNotFoundException $e) { }
        }

        $total = $facture->getMontantTotal();
        $shipping = 2;
        $subtotal = ($total - $shipping) / 1.07;
        $tva = $subtotal * 0.07;
        
        $publicDir = $this->projectDir . '/public';

        $html = $this->twig->render('order/pdf.html.twig', [
            'facture' => $facture,
            'commandes' => $validCommands,
            'subtotal' => $subtotal,
            'tva' => $tva,
            'shipping' => $shipping,
            'publicDir' => $publicDir
        ]);

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        $options->set('chroot', $publicDir);
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
