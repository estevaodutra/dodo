<?php
require_once 'config.php';
require_once 'utils.php';

$pdo = dbConnect();

$stmt = $pdo->query("SELECT * FROM transacoes WHERE status = 'PENDING'");
$transacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($transacoes as $transacao) {
    
    $transaction_id = $transacao['transaction_id'];
    $created_at = $transacao['created_at'];
    $trackingParameters = [
    "utm_campaign" => $transacao['utm_campaign'] ?? null,
    "utm_content" => $transacao['utm_content'] ?? null,
    "utm_medium" => $transacao['utm_medium'] ?? null,
    "utm_source" => $transacao['utm_source'] ?? null,
    "utm_term" => $transacao['utm_term'] ?? null
    ];

    $tempo_decorrido = time() - $created_at;

    $api_url = "https://app.ninjapaybr.com/api/v1/gateway/transactions?id=$transaction_id";
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-public-key: ' . DUCKFY_PUBLIC_KEY,
        'x-secret-key: ' . DUCKFY_SECRET_KEY
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    $status = $data['status'] ?? null;
    $valor = $data['amount'] ?? null;

    if ($status === 'COMPLETED') {
        include_once 'enviar_utmify.php';
        enviarParaUtmify($transaction_id, $trackingParameters, $valor);
        
        $pdo->prepare("DELETE FROM transacoes WHERE transaction_id = :id")
            ->execute([':id' => $transaction_id]);

        logMessage('log_verificar_pendentes.txt', "$transaction_id - PAGO e enviado para UTMify", $trackingParameters);
    } elseif ($tempo_decorrido >= 1000) {
        $pdo->prepare("DELETE FROM transacoes WHERE transaction_id = :id")->execute([':id' => $transaction_id]);
        logMessage('log_verificar_pendentes.txt', "$transaction_id - Expirado e removido");
    }
}
?>
