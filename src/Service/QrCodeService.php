<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\Participation;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service QR Code via API externe (même logique que le JavaFX) :
 *  - Primaire  : https://api.qrserver.com/v1/create-qr-code/  (POST, gratuit)
 *  - Fallback  : https://quickchart.io/qr  (GET, gratuit)
 */
class QrCodeService
{
    private const QR_PRIMARY  = 'https://api.qrserver.com/v1/create-qr-code/';
    private const QR_FALLBACK = 'https://quickchart.io/qr';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Génère les bytes PNG du QR code pour une participation.
     */
    public function generateQrCodeBytes(Participation $participation, int $sizePx = 250): ?string
    {
        $content = $this->buildQrContent($participation);
        return $this->fetchBytes($content, $sizePx);
    }

    /**
     * Retourne une data-URI base64 du QR code (pour inclusion dans du HTML).
     */
    public function generateQrCodeDataUri(Participation $participation, int $sizePx = 250): ?string
    {
        $bytes = $this->generateQrCodeBytes($participation, $sizePx);
        if ($bytes === null) {
            return null;
        }
        return 'data:image/png;base64,' . base64_encode($bytes);
    }

    /**
     * Génère un QR code depuis un texte libre.
     */
    public function generateFromText(string $text, int $sizePx = 250): ?string
    {
        return $this->fetchBytes($text, $sizePx);
    }

    // ─── Fetch bytes — essaie qrserver (POST) puis quickchart (GET) ────

    private function fetchBytes(string $content, int $sizePx): ?string
    {
        // Tentative 1 : qrserver.com via POST
        $result = $this->fetchQrServer($content, $sizePx);
        if ($result !== null && strlen($result) > 500) {
            $this->logger->info('QR code généré via qrserver.com (' . strlen($result) . ' bytes)');
            return $result;
        }

        // Tentative 2 : quickchart.io via GET
        $result = $this->fetchQuickChart($content, $sizePx);
        if ($result !== null && strlen($result) > 500) {
            $this->logger->info('QR code généré via quickchart.io (' . strlen($result) . ' bytes)');
            return $result;
        }

        $this->logger->error('Échec génération QR code sur les deux APIs');
        return null;
    }

    private function fetchQrServer(string $content, int $sizePx): ?string
    {
        try {
            $response = $this->httpClient->request('POST', self::QR_PRIMARY, [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'User-Agent'   => 'MindBloom/1.0',
                ],
                'body' => [
                    'data'   => $content,
                    'size'   => $sizePx . 'x' . $sizePx,
                    'format' => 'png',
                    'ecc'    => 'M',
                    'margin' => '1',
                ],
                'timeout' => 12,
                'verify_peer' => false,
                'verify_host' => false,
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->warning('qrserver HTTP ' . $response->getStatusCode());
                return null;
            }

            return $response->getContent();
        } catch (\Throwable $e) {
            $this->logger->warning('qrserver erreur : ' . $e->getMessage());
            return null;
        }
    }

    private function fetchQuickChart(string $content, int $sizePx): ?string
    {
        try {
            $response = $this->httpClient->request('GET', self::QR_FALLBACK, [
                'query' => [
                    'text'    => $content,
                    'width'   => $sizePx,
                    'height'  => $sizePx,
                    'margin'  => 2,
                    'ecLevel' => 'M',
                ],
                'headers' => [
                    'User-Agent' => 'MindBloom/1.0',
                ],
                'timeout' => 12,
                'verify_peer' => false,
                'verify_host' => false,
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->warning('quickchart HTTP ' . $response->getStatusCode());
                return null;
            }

            return $response->getContent();
        } catch (\Throwable $e) {
            $this->logger->warning('quickchart erreur : ' . $e->getMessage());
            return null;
        }
    }

    // ─── Construction du contenu QR (format iCalendar) ────────────────

    public function buildQrContent(Participation $participation): string
    {
        $event = $participation->getEvenement();
        $user  = $participation->getUser();

        $isPaid = $event->getPrix() !== null && $event->getPrix() > 0.0;
        $prix   = $isPaid ? number_format($event->getPrix(), 2) . ' DT' : 'Gratuit';
        $ref    = sprintf('MB%06d', $participation->getId());

        $dtStart = $event->getDateDebut() !== null
            ? $event->getDateDebut()->format('Ymd\THis')
            : '20260101T090000';
        $dtEnd = $event->getDateFin() !== null
            ? $event->getDateFin()->format('Ymd\THis')
            : ($event->getDateDebut() !== null
                ? (clone $event->getDateDebut())->modify('+2 hours')->format('Ymd\THis')
                : '20260101T110000');

        $titre = $this->icalSafe($event->getTitre());
        $lieu  = $this->icalSafe($event->getLieu());
        $nom   = $this->icalSafe($user ? $user->getFullName() : '');
        $org   = $this->icalSafe($event->getOrganisateur());

        $description = "Ref: {$ref} | Participant: {$nom} | Organisateur: {$org} | Prix: {$prix} | Statut: CONFIRME";

        return "BEGIN:VCALENDAR\r\n"
             . "VERSION:2.0\r\n"
             . "PRODID:-//MindBloom//Billet//FR\r\n"
             . "BEGIN:VEVENT\r\n"
             . "UID:{$ref}@mindbloom\r\n"
             . "SUMMARY:{$titre}\r\n"
             . "DTSTART:{$dtStart}\r\n"
             . "DTEND:{$dtEnd}\r\n"
             . "LOCATION:{$lieu}\r\n"
             . "DESCRIPTION:{$description}\r\n"
             . "STATUS:CONFIRMED\r\n"
             . "END:VEVENT\r\n"
             . "END:VCALENDAR";
    }

    private function icalSafe(?string $s): string
    {
        if ($s === null) {
            return '';
        }
        return str_replace(
            ['\\', ';', ',', "\n", "\r", '@', 'http', 'www.'],
            ['\\\\', '\\;', '\\,', '\\n', '', '', '', ''],
            $s
        );
    }
}
