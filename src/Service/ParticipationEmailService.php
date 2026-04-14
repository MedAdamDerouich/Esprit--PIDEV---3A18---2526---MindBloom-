<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\Participation;
use Psr\Log\LoggerInterface;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Service email de confirmation de participation.
 * Utilise = PHPMailer (exactement la même logique d'envoi SMTP direct que JavaFX).
 * Gmail SMTP : mindbloom.platform@gmail.com
 */
class ParticipationEmailService
{
    private const GMAIL_EMAIL = 'mindbloom.platform@gmail.com';
    private const GMAIL_APP_PASSWORD = 'tmdetyjtfnylfqln';
    private const SMTP_HOST = 'smtp.gmail.com';
    private const SMTP_PORT = 587;
    private const SENDER_NAME = 'MindBloom';

    public function __construct(
        private readonly PdfTicketService $pdfTicketService,
        private readonly QrCodeService $qrCodeService,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Envoie l'email de confirmation avec le billet PDF en pièce jointe.
     */
    public function sendParticipationConfirmation(Participation $participation): void
    {
        $event = $participation->getEvenement();
        $user  = $participation->getUser();

        if (!$user || !$user->getEmail()) {
            $this->logger->warning('Pas d\'email pour la participation #' . $participation->getId());
            return;
        }

        // 1. Générer le PDF du billet (qui inclut déjà le QR Code à l'intérieur de lui-même)
        $pdfBytes = $this->pdfTicketService->generateTicketPdf($participation);
        
        // 1B. Générer le QR Code à afficher NATIVEMENT dans l'email HTML !
        $qrDataUri = $this->qrCodeService->generateQrCodeDataUri($participation);

        // 2. Construire l'email avec PHPMailer
        $mail = new PHPMailer(true); // true enable exceptions
        $mail->CharSet = 'UTF-8';

        try {
            // Configuration du serveur (Comme dans JavaFX)
            $mail->isSMTP();
            $mail->Host       = self::SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = self::GMAIL_EMAIL;
            $mail->Password   = self::GMAIL_APP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = self::SMTP_PORT;

            // Désactiver la vérification SSL (très important pour WAMP local)
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true
                ]
            ];

            // Destinataire
            $mail->setFrom(self::GMAIL_EMAIL, self::SENDER_NAME);
            $mail->addAddress($user->getEmail());

            // Contenu
            $mail->isHTML(true);
            $mail->Subject = 'MindBloom — Confirmation : ' . $event->getTitre();
            $mail->Body    = $this->buildEmailHtml($participation, $qrDataUri);

            // 3. Attacher le PDF si disponible
            if ($pdfBytes !== null && strlen($pdfBytes) > 0) {
                $pdfName = 'Billet_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $event->getTitre()) . '.pdf';
                // Ajouter le binaire directement depuis la chaîne :
                $mail->addStringAttachment($pdfBytes, $pdfName, 'base64', 'application/pdf');
                $this->logger->info("PDF attaché ({$pdfName}, " . strlen($pdfBytes) . " bytes)");
            } else {
                $this->logger->warning('PDF non généré — email envoyé sans pièce jointe');
            }

            // Exécution de l'envoi
            $mail->send();
            $this->logger->info('Email envoyé à : ' . $user->getEmail());

        } catch (PHPMailerException $e) {
            $this->logger->error('Erreur envoi email (PHPMailer) : ' . $mail->ErrorInfo);
            throw new \Exception('Erreur envoi email: ' . $mail->ErrorInfo);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur inattendue email : ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Envoie un email de rappel avant l'événement.
     */
    public function sendRappelEmail(Participation $participation, int $joursRestants): void
    {
        $event = $participation->getEvenement();
        $user  = $participation->getUser();

        if (!$user || !$user->getEmail()) {
            return;
        }

        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';

        try {
            $mail->isSMTP();
            $mail->Host       = self::SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = self::GMAIL_EMAIL;
            $mail->Password   = self::GMAIL_APP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = self::SMTP_PORT;

            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true
                ]
            ];

            $mail->setFrom(self::GMAIL_EMAIL, self::SENDER_NAME);
            $mail->addAddress($user->getEmail());

            $mail->isHTML(true);
            $mail->Subject = 'Rappel MindBloom — ' . $event->getTitre() . ' dans ' . $joursRestants . ' jour(s)';
            $mail->Body    = $this->buildRappelHtml($participation, $joursRestants);

            $mail->send();
            $this->logger->info('Email rappel envoyé à : ' . $user->getEmail());

        } catch (\Throwable $e) {
            $this->logger->error('Erreur envoi rappel : ' . $e->getMessage());
        }
    }

    // ─── HTML EMAIL (identique au JavaFX) ─────────────────────────────

    private function buildEmailHtml(Participation $participation, ?string $qrDataUri): string
    {
        $event = $participation->getEvenement();
        $user  = $participation->getUser();

        $prenom  = $this->getPrenom($user?->getFullName());
        $titre   = $this->esc($event->getTitre());
        $lieu    = $event->getLieu() ? $this->esc($event->getLieu()) : 'N/A';
        $debut   = $event->getDateDebut() ? $event->getDateDebut()->format('d/m/Y à H:i') : 'N/A';
        $fin     = $event->getDateFin() ? $event->getDateFin()->format('d/m/Y à H:i') : 'N/A';
        $org     = $event->getOrganisateur() ? $this->esc($event->getOrganisateur()) : 'N/A';
        $cap     = $event->getCapaciteMax() !== null ? $event->getCapaciteMax() . ' places' : 'Illimitée';
        $desc    = ($event->getDescription() && $event->getDescription() !== '') ? $this->esc($event->getDescription()) : '-';
        $isPaid  = $event->getPrix() !== null && $event->getPrix() > 0.0;
        $prix    = $isPaid ? number_format($event->getPrix(), 2) . ' DT' : 'Gratuit';
        $prixStyle = $isPaid ? 'color:#e67e22;font-weight:700;' : 'color:#27ae60;font-weight:700;';
        $nom     = $this->esc($user?->getFullName() ?? '');
        $email   = $this->esc($user?->getEmail() ?? '');
        $phone   = $user?->getPhone();

        $phoneTr = ($phone && $phone !== '')
            ? "<tr><td>Tél</td><td>" . $this->esc($phone) . "</td></tr>"
            : '';

        $qrCodeHtml = '';
        if ($qrDataUri) {
            $qrCodeHtml = "<div style='text-align:center; margin-top:20px;'><img src='{$qrDataUri}' alt='QR Code Billet' style='width:150px; height:150px; border-radius:10px; border:3px solid #4A90E2;' /></div>";
        }

        return <<<HTML
<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'>
<style>
body{font-family:'Segoe UI',Arial,sans-serif;background:#f0f4f8;margin:0;padding:20px;}
.wrap{max-width:620px;margin:0 auto;background:#fff;border-radius:20px;box-shadow:0 8px 32px rgba(0,0,0,.13);overflow:hidden;}
.hdr{background:linear-gradient(135deg,#4A90E2 0%,#2C3E50 100%);padding:40px;text-align:center;}
.hdr h1{color:#fff;margin:0;font-size:28px;font-weight:900;}
.hdr p{color:rgba(255,255,255,.8);margin:6px 0 0;font-size:13px;}
.badge{display:inline-block;background:rgba(255,255,255,.18);color:#fff;border-radius:20px;padding:7px 20px;font-size:12px;font-weight:700;margin-top:14px;border:1px solid rgba(255,255,255,.35);}
.body{padding:36px 40px;}
.ev-title{background:linear-gradient(135deg,#4A90E2,#2C3E50);color:#fff;border-radius:12px;padding:18px 24px;font-size:18px;font-weight:800;text-align:center;margin-bottom:24px;}
.sec{font-size:12px;font-weight:700;color:#4A90E2;text-transform:uppercase;letter-spacing:.8px;margin:16px 0 8px;}
.tbl{width:100%;border-collapse:collapse;margin-bottom:20px;}
.tbl tr:nth-child(even) td{background:#f8f9fa;}
.tbl td{padding:10px 16px;border:1px solid #e9ecef;font-size:13px;}
.tbl td:first-child{font-weight:700;color:#2c3e50;background:#eef6ff;width:38%;}
.wb{background:#e8f5e9;border-left:5px solid #27ae60;border-radius:10px;padding:18px 22px;margin:16px 0;}
.wb p{margin:0;color:#2e7d32;font-size:13px;line-height:1.75;}
.note{background:#fff8e1;border-left:5px solid #f39c12;border-radius:8px;padding:14px 18px;font-size:12px;color:#7d6608;margin-top:16px;}
.ftr{background:#f4f6f9;text-align:center;padding:20px;font-size:11px;color:#95a5a6;border-top:1px solid #eee;line-height:1.8;}
</style></head><body><div class='wrap'>
<div class='hdr'><h1>MindBloom</h1><p>Plateforme de bien-être mental</p>
<span class='badge'>Inscription confirmée</span></div>
<div class='body'>
<p style='font-size:20px;font-weight:800;color:#2c3e50;margin-bottom:10px;'>Bonjour {$prenom}</p>
<p style='font-size:14px;color:#555;line-height:1.8;margin-bottom:22px;'>
Nous sommes ravis de vous accueillir !<br>
Votre inscription a été <strong>confirmée avec succès</strong>.</p>
<div class='ev-title'>{$titre}</div>
<p class='sec'>Détails de l'événement</p>
<table class='tbl'>
<tr><td>Description</td><td style='font-style:italic;'>{$desc}</td></tr>
<tr><td>Date de début</td><td>{$debut}</td></tr>
<tr><td>Date de fin</td><td>{$fin}</td></tr>
<tr><td>Lieu</td><td>{$lieu}</td></tr>
<tr><td>Organisateur</td><td>{$org}</td></tr>
<tr><td>Capacité</td><td>{$cap}</td></tr>
<tr><td>Prix</td><td style='{$prixStyle}'>{$prix}</td></tr>
</table>
<p class='sec'>Informations participant</p>
<table class='tbl'>
<tr><td>Nom</td><td><strong>{$nom}</strong></td></tr>
<tr><td>Email</td><td>{$email}</td></tr>
{$phoneTr}
</table>
<div class='wb'><p><strong>Vous êtes les bienvenus !</strong><br/>
Toute l'équipe MindBloom vous accueille chaleureusement.
Nous avons hâte de vous retrouver !</p></div>
{$qrCodeHtml}
<div class='note'>Retrouvez en pièce jointe votre billet PDF avec QR code.</div>
</div>
<div class='ftr'>© 2026 MindBloom — Ne répondez pas à cet email automatique.<br>
Envoyé à : {$email}</div>
</div></body></html>
HTML;
    }

    // ─── HTML RAPPEL (identique au JavaFX) ────────────────────────────

    private function buildRappelHtml(Participation $participation, int $joursRestants): string
    {
        $event = $participation->getEvenements();
        $user  = $participation->getUser();

        $prenom  = $this->getPrenom($user?->getFullName());
        $dateStr = $event->getDateDebut() ? $event->getDateDebut()->format('d/m/Y à H:i') : 'N/A';
        $lieu    = $event->getLieu() ? $this->esc($event->getLieu()) : 'N/A';
        $titre   = $this->esc($event->getTitre());
        $urgence = $joursRestants <= 1 ? 'Demain !' : ($joursRestants <= 3 ? 'Très bientôt' : 'Bientôt');
        $email   = $this->esc($user?->getEmail() ?? '');
        $prixStr = ($event->getPrix() !== null && $event->getPrix() > 0)
            ? number_format($event->getPrix(), 2) . ' DT'
            : 'Gratuit';

        return <<<HTML
<!DOCTYPE html><html><head><meta charset='UTF-8'>
<style>
body{font-family:Arial,sans-serif;background:#f0f4f8;margin:0;padding:20px;}
.wrap{max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.12);}
.hdr{background:linear-gradient(135deg,#f39c12,#e67e22);padding:36px 40px;text-align:center;}
.hdr h1{color:#fff;margin:0;font-size:24px;}
.hdr p{color:rgba(255,255,255,.85);margin:8px 0 0;font-size:13px;}
.badge{display:inline-block;background:rgba(255,255,255,.2);color:#fff;border-radius:20px;padding:6px 18px;font-size:12px;font-weight:700;margin-top:12px;border:1px solid rgba(255,255,255,.4);}
.body{padding:32px 40px;}
.countdown{text-align:center;background:linear-gradient(135deg,#f39c12,#e67e22);border-radius:12px;padding:20px;margin:16px 0;}
.countdown-num{font-size:48px;font-weight:900;color:#fff;}
.countdown-lbl{color:rgba(255,255,255,.9);font-size:13px;}
.info-box{background:#fff8e1;border-left:4px solid #f39c12;border-radius:8px;padding:18px 22px;margin:16px 0;font-size:13px;color:#444;line-height:1.9;}
.ftr{background:#f8f9fa;padding:18px;text-align:center;font-size:11px;color:#999;border-top:1px solid #eee;}
</style></head><body><div class='wrap'>
<div class='hdr'><h1>Rappel événement</h1>
<p>MindBloom</p>
<span class='badge'>{$urgence}</span></div>
<div class='body'>
<p style='font-size:18px;font-weight:700;color:#2c3e50;'>Bonjour {$prenom}</p>
<p style='font-size:13px;color:#555;'>Vous avez un événement qui approche !</p>
<div class='countdown'>
<div class='countdown-num'>{$joursRestants}</div>
<div class='countdown-lbl'>jour(s) restant(s)</div>
</div>
<div class='info-box'>
<strong>{$titre}</strong><br>
{$dateStr}<br>
{$lieu}<br>
{$prixStr}
</div>
</div>
<div class='ftr'>© 2026 MindBloom<br>
Envoyé à : {$email}</div>
</div></body></html>
HTML;
    }

    // ─── Utilitaires ─────────────────────────────────────────────────

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
