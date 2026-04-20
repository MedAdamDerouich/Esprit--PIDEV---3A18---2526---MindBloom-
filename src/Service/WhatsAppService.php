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
        // Sanitize phone number to keep only digits
        $phone = preg_replace('/[^0-9]/', '', $phone);
        // Automatically prepend 216 if it's a local 8-digit Tunisian number
        if (strlen($phone) === 8) {
            $phone = '216' . $phone;
        }

        $ch = curl_init('https://api.fonnte.com/send');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $this->token
            ],
            CURLOPT_POSTFIELDS => [
                'target' => $phone,
                'message' => $message,
                'delay' => '2',
            ]
        ]);

        $response = @file_get_contents('https://api.fonnte.com/send', false, $context);
        
    

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
