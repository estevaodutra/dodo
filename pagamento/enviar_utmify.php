<?php
function enviarParaUtmify($transactionId, $trackingParameters, $valor) {
    $utmify_data = [
        "orderId" => $transactionId,
        "platform" => "API PIX",
        "paymentMethod" => "pix",
        "status" => "paid",
        "createdAt" => date('Y-m-d H:i:s'),
        "approvedDate" => date('Y-m-d H:i:s'),
        "customer" => [
            "name" => "Desconhecido",
            "email" => "não informado",
            "phone" => "não informado",
            "document" => "não informado",
            "country" => "BR",
            "ip" => $_SERVER['REMOTE_ADDR'] ?? "IP não informado"
        ],
        "products" => [
            [
                "id" => $transactionId,
                "name" => "Vakinha",
                "planId" => null,
                "planName" => null,
                "quantity" => 1,
                "priceInCents" => $valor * 100
            ]
        ],
        "trackingParameters" => $trackingParameters,
        "commission" => [
            "totalPriceInCents" => $valor * 100,
            "gatewayFeeInCents" => 0,
            "userCommissionInCents" => $valor * 100
        ],
        "isTest" => false
    ];

    $ch = curl_init(UTMIFY_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($utmify_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "x-api-token: " . UTMIFY_TOKEN
    ]);
    $utmify_response = curl_exec($ch);
    curl_close($ch);

    logMessage('log_utmify.txt', "[$transactionId] UTMify Request: " . json_encode($utmify_data) . " | Response: $utmify_response");
}
?>
