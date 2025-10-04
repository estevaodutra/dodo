<?php
// Verificar se o código PIX foi fornecido
if (!isset($_GET['code']) || empty($_GET['code'])) {
    header("HTTP/1.1 400 Bad Request");
    echo "Código PIX não fornecido";
    exit;
}

// Obter o código PIX
$pix_code = $_GET['code'];

// Verificar se a biblioteca GD está disponível
if (!extension_loaded('gd')) {
    header("HTTP/1.1 500 Internal Server Error");
    echo "Biblioteca GD não disponível";
    exit;
}

// Incluir a biblioteca QR Code
require_once 'vendor/phpqrcode/qrlib.php';

// Definir o tipo de conteúdo como imagem PNG
header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="pix-qrcode.png"');

// Gerar o QR code diretamente para a saída
QRcode::png($pix_code, null, QR_ECLEVEL_L, 10, 2);
