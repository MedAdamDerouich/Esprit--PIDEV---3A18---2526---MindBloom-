<?php

namespace App\Service;

class WhatsAppService
{
    private string $token;

    public function __construct()
    {
        $this->token = $_ENV['FONNTE_TOKEN'] ?? getenv('FONNTE_TOKEN');
    }

    public function sendMessage(string $phone, string $message): bool
    {
        $phone = ltrim($phone, '+');

        $data = http_build_query([
            'target'      => $phone,
            'message'     => $message,
            'countryCode' => '216',
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
        
        error_log('Fonnte response: ' . $response);
        error_log('Fonnte phone: ' . $phone);
        error_log('Fonnte token: ' . $this->token);

        if (!$response) return false;

        $data = json_decode($response, true);
        return isset($data['status']) && $data['status'] === true;
    }

    public function sendReminderMessage(
        string $phone,
        string $patientName,
        string $psyName,
        string $date,
        string $heure,
        string $adresse
    ): bool {
        $message = "Bonjour {$patientName} 👋\n\n"
            . "MindBloom vous rappelle votre rendez-vous :\n\n"
            . "👨‍⚕️ Avec Dr. {$psyName}\n"
            . "📅 Date : {$date}\n"
            . "🕒 Heure : {$heure}\n"
            . "📍 Adresse : {$adresse}\n\n"
            . "Merci d'être à l'heure 😊\n"
            . "— MindBloom";

        return $this->sendMessage($phone, $message);
    }
}
