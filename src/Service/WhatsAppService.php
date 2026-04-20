<?php

namespace App\Service;

class WhatsAppService
{
    private string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function sendMessage(string $phone, string $message): bool
{
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) === 8) {
        $phone = '216' . $phone;
    }

    $data = http_build_query([
        'target'  => $phone,
        'message' => $message,
        'delay'   => '2',
    ]);

    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Authorization: " . $this->token . "\r\n"
                       . "Content-Type: application/x-www-form-urlencoded\r\n"
                       . "Content-Length: " . strlen($data) . "\r\n",
            'content' => $data,
            'timeout' => 10,
        ],
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ]
    ]);

    $response = @file_get_contents('https://api.fonnte.com/send', false, $context);

    // DEBUG
    echo "Token: " . $this->token . "\n";
    echo "Phone: " . $phone . "\n";
    echo "Response: " . $response . "\n";

    if (!$response) return false;
    $result = json_decode($response, true);
    return isset($result['status']) && $result['status'] === true;
}

    public function sendReminderMessage(
        string $phone,
        string $patientName,
        string $psyName,
        string $date,
        string $heure,
        string $adresse
    ): bool {
        $googleMapsLink = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($adresse);

        $message = "Bonjour {$patientName} 👋\n\n"
            . "MindBloom vous rappelle votre rendez-vous :\n\n"
            . "👨‍⚕️ Avec Dr. {$psyName}\n"
            . "📅 Date : {$date}\n"
            . "🕒 Heure : {$heure}\n"
            . "📍 Adresse : {$adresse}\n\n"
            . "🗺️ Voir l'itinéraire : " . $googleMapsLink . "\n\n"
            . "Merci d'être à l'heure 😊\n"
            . "— MindBloom";

        return $this->sendMessage($phone, $message);
    }
}