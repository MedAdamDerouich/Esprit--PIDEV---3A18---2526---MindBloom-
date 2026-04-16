<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\Participation;
use App\Entity\User;
use Nucleos\DompdfBundle\Factory\DompdfFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Génération de billets PDF via le bundle Symfony Nucleos Dompdf.
 */
class PdfTicketService
{
    public function __construct(
        private readonly DompdfFactoryInterface $dompdfFactory,
        private readonly LoggerInterface $logger,
        private readonly QrCodeService $qrCodeService,
    ) {}

    /**
     * Génère un PDF billet pour une participation.
     * Retourne les bytes du PDF ou null en cas d'erreur.
     */
    public function generateTicketPdf(Participation $participation): ?string
    {
        $qrDataUri = null;
        try {
            $qrDataUri = $this->qrCodeService->generateQrCodeDataUri($participation);
        } catch (\Throwable $e) {
            // On ne bloque pas la génération du billet si le QR code échoue.
            $this->logger->warning('QR code indisponible, génération du PDF sans QR: ' . $e->getMessage());
        }

        $html = $this->buildTicketHtml($participation, $qrDataUri);
        return $this->generatePdfFromHtml($html);
    }

    /**
     * Génère un PDF depuis du contenu HTML via Dompdf.
     */
    public function generatePdfFromHtml(string $htmlContent): ?string
    {
        try {
            $dompdf = $this->dompdfFactory->create();
            $dompdf->loadHtml($htmlContent, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $pdfBytes = $dompdf->output();

            if (!is_string($pdfBytes) || $pdfBytes === '') {
                $this->logger->error('Erreur PDF Dompdf : contenu PDF vide retourné par Dompdf.');
                return null;
            }

            $this->logger->info('PDF généré via Nucleos Dompdf (' . strlen($pdfBytes) . ' bytes)');
            return $pdfBytes;

        } catch (\Throwable $e) {
            $this->logger->error('Erreur PDF Dompdf : ' . $e->getMessage());
            error_log('Erreur PDF Dompdf : ' . $e->getMessage());
            throw new \RuntimeException('Erreur Dompdf: ' . $e->getMessage(), 0, $e);
        }
    }

    // ─── HTML du billet (identique au JavaFX) ─────────────────────────

    private function buildTicketHtml(Participation $participation, ?string $qrDataUri): string
    {
        $event = $participation->getEvenement();
        $user  = $participation->getUser();

        $ref      = sprintf('MB-%06d', $participation->getId());
        $prenom   = $this->getPrenom($user?->getFullName());
        $titre    = $this->esc($event->getTitre());
        $lieu     = $event->getLieu() ? $this->esc($event->getLieu()) : 'N/A';
        $debut    = $event->getDateDebut() ? $event->getDateDebut()->format('d/m/Y à H:i') : 'N/A';
        $fin      = $event->getDateFin() ? $event->getDateFin()->format('d/m/Y à H:i') : 'N/A';
        $org      = $event->getOrganisateur() ? $this->esc($event->getOrganisateur()) : 'N/A';
        $cap      = $event->getCapaciteMax() !== null ? $event->getCapaciteMax() . ' places' : 'Illimitée';
        $desc     = ($event->getDescription() && $event->getDescription() !== '') ? $this->esc($event->getDescription()) : '-';
        $isPaid   = $event->getPrix() !== null && $event->getPrix() > 0.0;
        $prix     = $isPaid ? number_format($event->getPrix(), 2) . ' DT' : 'Gratuit';
        $prixColor = $isPaid ? '#e67e22' : '#27ae60';
        $nom      = $this->esc($user?->getFullName() ?? '');
        $email    = $this->esc($user?->getEmail() ?? '');
        $phone    = $user?->getPhone();

        $phoneTr = ($phone && $phone !== '')
            ? "<tr><td>Tél</td><td>" . $this->esc($phone) . "</td></tr>"
            : '';

        $qrCodeHtml = '';
        if ($qrDataUri) {
            $qrCodeHtml = "<div style='text-align:center; padding:10px;'><img src='{$qrDataUri}' alt='QR Code Billet' style='width:160px; height:160px; border:2px solid #e9ecef; border-radius:8px;' /></div>";
        }

        return <<<HTML
<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'>
<style>
@page{margin:0;}
body{font-family:Arial,sans-serif;background:#fff;margin:0;padding:0;color:#2c3e50;}
.ticket{width:100%;margin:0;background:#fff;border-radius:0;box-shadow:none;overflow:hidden;}
.hdr{background:linear-gradient(135deg,#4A90E2,#2C3E50);padding:28px 36px;text-align:center;}
.hdr h1{color:#fff;margin:0;font-size:24px;font-weight:900;}
.hdr p{color:rgba(255,255,255,.8);margin:5px 0 0;font-size:12px;}
.badge{display:inline-block;background:rgba(255,255,255,.2);color:#fff;border-radius:20px;padding:5px 16px;font-size:11px;font-weight:700;margin-top:10px;border:1px solid rgba(255,255,255,.4);}
.body{padding:26px 36px;}
.ev-title{background:linear-gradient(135deg,#4A90E2,#2C3E50);color:#fff;border-radius:8px;padding:14px 20px;font-size:16px;font-weight:800;text-align:center;margin-bottom:18px;}
.ref{text-align:center;background:#eef6ff;border-radius:6px;padding:10px;font-size:12px;font-weight:700;color:#2c3e50;margin:10px 0 18px;}
.sec{font-size:11px;font-weight:700;color:#4A90E2;text-transform:uppercase;letter-spacing:.8px;margin:14px 0 6px;}
table{width:100%;border-collapse:collapse;margin-bottom:14px;}
tr:nth-child(even) td{background:#f8f9fa;}
td{padding:8px 12px;border:1px solid #e9ecef;font-size:11px;}
td:first-child{font-weight:700;color:#2c3e50;background:#eef6ff;width:34%;}
.wb{background:#e8f5e9;border-left:4px solid #27ae60;border-radius:6px;padding:14px 18px;margin:14px 0;}
.wb p{margin:0;color:#2e7d32;font-size:11px;line-height:1.7;}
.ftr{background:#f4f6f9;text-align:center;padding:14px;font-size:10px;color:#95a5a6;border-top:1px solid #eee;}
</style></head><body>
<div class='ticket'>
<div class='hdr'><h1>MindBloom</h1><p>Plateforme de bien-être mental</p>
<span class='badge'>INSCRIPTION CONFIRMÉE</span></div>
<div class='body'>
<div class='ev-title'>{$titre}</div>
<p style='font-size:14px;font-weight:700;'>Bonjour {$prenom},</p>
<p style='font-size:12px;color:#555;line-height:1.7;margin-bottom:14px;'>
Votre inscription a été <strong>confirmée avec succès</strong>.</p>
<div class='ref'>Référence : {$ref}</div>
{$qrCodeHtml}
<p class='sec'>Détails de l'événement</p>
<table>
<tr><td>Description</td><td style='font-style:italic;'>{$desc}</td></tr>
<tr><td>Date début</td><td>{$debut}</td></tr>
<tr><td>Date fin</td><td>{$fin}</td></tr>
<tr><td>Lieu</td><td>{$lieu}</td></tr>
<tr><td>Organisateur</td><td>{$org}</td></tr>
<tr><td>Capacité</td><td>{$cap}</td></tr>
<tr><td>Prix</td><td style='color:{$prixColor};font-weight:700;'>{$prix}</td></tr>
</table>
<p class='sec'>Participant</p>
<table>
<tr><td>Nom</td><td><strong>{$nom}</strong></td></tr>
<tr><td>Email</td><td>{$email}</td></tr>
{$phoneTr}
</table>
<div class='wb'><p><strong>Vous êtes les bienvenus !</strong><br/>
Toute l'équipe MindBloom vous accueille chaleureusement.
Nous avons hâte de vous retrouver !</p></div>
</div>
<div class='ftr'>© 2026 MindBloom - Envoyé à : {$email}</div>
</div></body></html>
HTML;
    }

    private function getPrenom(?string $fullName): string
    {
        if (!$fullName || trim($fullName) === '') {
            return 'cher(e) participant(e)';
        }
        $parts = preg_split('/\s+/', trim($fullName));
        $p = $parts[0];
        return mb_strtoupper(mb_substr($p, 0, 1)) . mb_strtolower(mb_substr($p, 1));
    }

    private function esc(?string $t): string
    {
        if ($t === null) {
            return '';
        }
        return htmlspecialchars($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
