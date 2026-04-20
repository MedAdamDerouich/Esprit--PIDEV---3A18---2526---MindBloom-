<?php

$token = 'TON_TOKEN_FONNTE_ICI';
$phone = '21612345678'; // Ton vrai numéro

$ch = curl_init('https://api.fonnte.com/send');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: ' . $token
    ],
    CURLOPT_POSTFIELDS => [
        'target' => $phone,
        'message' => 'Test MindBloom',
        'countryCode' => '216',
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: " . $httpCode . "\n";
echo "Response: " . $response . "\n";