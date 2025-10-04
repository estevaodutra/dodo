<?php
// Verificar se a requisição é POST e contém dados JSON
$input = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$input || !isset($input['transaction_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Requisição inválida'
    ]);
    exit;
}

// Obter o ID da transação
$transaction_id = $input['transaction_id'];

// Configurações da API Duckfy
$public_key = 'estevaodutra-pmss_m0411uvvl4qfj1xx';
$secret_key = 'szjki03mkyo61oo7bj6hsrkfb9kruktcqqp56l2impfw7d3h6hysm2u3ej3f7rln';
$api_url = "https://app.ninjapaybr.com/api/v1/gateway/transactions?id=$transaction_id";

// Fazer a requisição para a API do Duckfy
$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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
error_log('Duckfy - Verificação de Status (código ' . $info['http_code'] . '): ' . $response);
if ($error) {
    error_log('Duckfy - cURL Error: ' . $error);
}

// Verificar se a resposta foi bem-sucedida
if ($info['http_code'] !== 200) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao verificar status do pagamento',
        'http_code' => $info['http_code']
    ]);
    exit;
}

// Decodificar a resposta
$response_data = json_decode($response, true);

// Verificar se os dados da transação existem
if (!isset($response_data['status'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Dados da transação não encontrados'
    ]);
    exit;
}

// Retornar os dados da transação
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => $response_data
]);
