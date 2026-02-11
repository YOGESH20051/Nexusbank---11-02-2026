<?php
function sendWhatsApp($phone, $message){

    $data = json_encode([
        "phone" => $phone,
        "message" => $message
    ]);

    $ch = curl_init("http://localhost:3000/send");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}
