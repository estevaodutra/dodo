<?php
// Arquivo para gerar o código PIX

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Obter dados da requisição
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Verificar se os dados são válidos
if (!isset($data['amount']) || empty($data['amount'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valor não informado']);
    exit;
}

$amount = floatval($data['amount']);

// Verificar valor mínimo
if ($amount < 20) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valor mínimo é R$ 5,00']);
    exit;
}

// Verificar valor máximo
if ($amount > 700) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valor máximo é R$ 1.000,00']);
    exit;
}

// Gerar um identificador único para a transação
$transaction_id = 'donation_' . time() . '_' . rand(1000, 9999);

// Configurações da API Duckfy
$public_key = 'estevaodutra-pmss_m0411uvvl4qfj1xx';
$secret_key = 'szjki03mkyo61oo7bj6hsrkfb9kruktcqqp56l2impfw7d3h6hysm2u3ej3f7rln';
$api_url = 'https://app.ninjapaybr.com/api/v1/gateway/pix/receive';

// Preparar os dados para a API
$api_data = [
    'identifier' => $transaction_id,
    'amount' => $amount,
    'shippingFee' => 0,
    'extraFee' => 0,
    'discount' => 0,
    'client' => [
        'name' => 'Doador',
        'email' => 'doador@exemplo.com',
        'phone' => '(00) 00000-0000',
        'document' => '00000000000' // CPF fictício
    ],
    'products' => [
        [
            'id' => 'donation_' . time(),
            'name' => 'Doação para Talita e Família',
            'quantity' => 1,
            'price' => $amount
        ]
    ],
    'callbackUrl' => 'https://doeprojetosalvarpets.site/webhook.php' // Substitua pela URL do seu webhook
];

// Fazer a requisição para a API do NinjaPay
$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($api_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'x-public-key: ' . $public_key,
    'x-secret-key: ' . $secret_key
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$response = curl_exec($ch);
$info = curl_getinfo($ch);
$error = curl_error($ch);
curl_close($ch);

// Log da resposta para depuração
error_log('NinjaPay - Resposta (código ' . $info['http_code'] . '): ' . $response);
if ($error) {
    error_log('NinjaPay - cURL Error: ' . $error);
}

// Verificar se a resposta foi bem-sucedida
if ($info['http_code'] !== 201 && $info['http_code'] !== 200) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao gerar PIX',
        'error' => $error,
        'response' => json_decode($response, true)
    ]);
    exit;
}

// Decodificar a resposta
$response_data = json_decode($response, true);

// Verificar se os campos necessários existem na resposta
if (!isset($response_data['transactionId']) || !isset($response_data['pix']['code'])) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Resposta da API incompleta',
        'response' => $response_data
    ]);
    exit;
}

// Extrair os dados do PIX da resposta
$transaction_id = $response_data['transactionId'];
$status = $response_data['status'];
$pix_code = $response_data['pix']['code'];
$qrcode_url = isset($response_data['pix']['qrcode']) ? $response_data['pix']['qrcode'] : '';

// Iniciar a sessão para armazenar os dados do PIX
session_start();

// Salvar os dados do PIX na sessão
$_SESSION['pixData'] = [
    'transactionId' => $transaction_id,
    'pixCode' => $pix_code,
    'qrCodeImage' => $qrcode_url,
    'amount' => $amount,
    'status' => $status,
    'createdAt' => time()
];

// Retornar os dados do PIX
echo json_encode([
    'success' => true,
    'data' => [
        'transaction_id' => $transaction_id,
        'pix_code' => $pix_code,
        'qrcode_url' => $qrcode_url,
        'status' => $status
    ]
]);
