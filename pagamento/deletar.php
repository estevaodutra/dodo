<?php
require_once 'config.php';
require_once 'utils.php';

$transactionId = $_GET['transactionId'] ?? null;

if ($transactionId) {
    $pdo = dbConnect();
    $stmt = $pdo->prepare("DELETE FROM transacoes WHERE transaction_id = :id");
    $stmt->execute([':id' => $transactionId]);

    logMessage('log_verificar_pendentes.txt', "$transactionId - Removido via deletar.php");

    echo json_encode(['success' => true, 'message' => 'Transaction removida']);
} else {
    echo json_encode(['success' => false, 'message' => 'transactionId não informado']);
}
?>
