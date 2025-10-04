<?php
header("Content-Type: application/json");

$valor = isset($_GET['valor']) ? intval($_GET['valor']) : null;
$transactionId = isset($_GET['transactionId']) ? $_GET['transactionId'] : null;

// Recebe o corpo JSON enviado via POST
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$trackingParameters = [
    "src" => $_GET['src'] ?? ($data['tracking']['src'] ?? ($_COOKIE['src'] ?? null)),
    "sck" => $_GET['sck'] ?? ($data['tracking']['sck'] ?? ($_COOKIE['sck'] ?? null)),
    "utm_source" => $_GET['utm_source'] ?? ($data['tracking']['utm_source'] ?? ($_COOKIE['utm_source'] ?? null)),
    "utm_campaign" => $_GET['utm_campaign'] ?? ($data['tracking']['utm_campaign'] ?? ($_COOKIE['utm_campaign'] ?? null)),
    "utm_medium" => $_GET['utm_medium'] ?? ($data['tracking']['utm_medium'] ?? ($_COOKIE['utm_medium'] ?? null)),
    "utm_content" => $_GET['utm_content'] ?? ($data['tracking']['utm_content'] ?? ($_COOKIE['utm_content'] ?? null)),
    "utm_term" => $_GET['utm_term'] ?? ($data['tracking']['utm_term'] ?? ($_COOKIE['utm_term'] ?? null)),
];

// Colocar token da UTMify aqui
$utmify_api_token = "ooFb2MRdPBTYUgeomKo7OJTBXlJ3mvM8ZFuq";

file_put_contents('log_conversao.txt', "Conversão registrada para ID $transactionId | Valor R$ $valor em ".date('Y-m-d H:i:s')."\n", FILE_APPEND);

$totalPriceInCents = $valor;
$gatewayFeeInCents = 0;
$userCommissionInCents = $totalPriceInCents - $gatewayFeeInCents;

$utmify_data = [
    "orderId" => $transactionId,
    "platform" => "API PIX",
    "paymentMethod" => strtolower($data['method'] ?? "pix"),
    "status" => "paid",
    "createdAt" => $data['createdAt'] ?? date('Y-m-d H:i:s'),
    "approvedDate" => date('Y-m-d H:i:s'),
    "refundedAt" => null,
    "customer" => [
        "name" => $data['customer']['name'] ?? "Cliente não informado",
        "email" => $data['customer']['email'] ?? "Email não informado",
        "phone" => $data['customer']['phone'] ?? "Telefone não informado",
        "document" => $data['customer']['cpf'] ?? "Documento não informado",
        "country" => "BR",
        "ip" => $_SERVER['REMOTE_ADDR'] ?? "IP não informado"
    ],
    "products" => [
        [
            "id" => $data['items'][0]['id'] ?? "Vakinha",
            "name" => $data['items'][0]['title'] ?? "Vakinha Katrine",
            "planId" => null,
            "planName" => null,
            "quantity" => 1,
            "priceInCents" => $data['items'][0]['unitPrice'] ?? $totalPriceInCents
        ]
    ],
    "trackingParameters" => $trackingParameters,
    "commission" => [
        "totalPriceInCents" => $totalPriceInCents * 100,
        "gatewayFeeInCents" => $gatewayFeeInCents,
        "userCommissionInCents" => $userCommissionInCents * 100,
    ],
    "isTest" => false
];

$utmify_url = "https://api.utmify.com.br/api-credentials/orders";
$ch_utmify = curl_init($utmify_url);
curl_setopt($ch_utmify, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch_utmify, CURLOPT_POST, true);
curl_setopt($ch_utmify, CURLOPT_POSTFIELDS, json_encode($utmify_data));
curl_setopt($ch_utmify, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "x-api-token: $utmify_api_token"
]);
$utmify_response = curl_exec($ch_utmify);
curl_close($ch_utmify);

file_put_contents('log_utmify.txt', "[$transactionId] UTMify Request: ".json_encode($utmify_data)." | Response: $utmify_response\n", FILE_APPEND);

echo json_encode(['success' => true, 'message' => 'UTMify enviado com sucesso']);
echo json_encode($data);