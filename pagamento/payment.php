<?php
require_once 'config.php';
require_once 'utils.php';

// Executa a verificação de pendentes antes de gerar novo PIX
include 'verificar_pendentes.php';

$utm_source = $_GET['utm_source'] ?? ($_COOKIE['utm_source'] ?? null);
$utm_medium = $_GET['utm_medium'] ?? ($_COOKIE['utm_medium'] ?? null);
$utm_campaign = $_GET['utm_campaign'] ?? ($_COOKIE['utm_campaign'] ?? null);
$utm_content = $_GET['utm_content'] ?? ($_COOKIE['utm_content'] ?? null);
$utm_term = $_GET['utm_term'] ?? ($_COOKIE['utm_term'] ?? null);

// Verificar se o valor foi passado como parâmetro
if (!isset($_GET['amount']) || empty($_GET['amount'])) {
    header("Location: index.php");
    exit;
}

$amount = floatval($_GET['amount']);

if ($amount < 15) {
    header("Location: index.php?error=min_amount");
    exit;
}

if ($amount > 1000) {
    header("Location: index.php?error=max_amount");
    exit;
}

$transaction_id = 'donation_' . time() . '_' . rand(1000, 9999);

$public_key = 'estevaodutra-pmss_m0411uvvl4qfj1xx';
$secret_key = 'szjki03mkyo61oo7bj6hsrkfb9kruktcqqp56l2impfw7d3h6hysm2u3ej3f7rln';
$api_url = 'https://app.ninjapaybr.com/api/v1/gateway/pix/receive';


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
        'document' => '81830000020'
    ],
    'products' => [
        [
            'id' => 'donation_' . time(),
            'name' => 'Pascal',
            'quantity' => 1,
            'price' => $amount
        ]
    ],
    'callbackUrl' => 'https://refugiopeludo.com/vakinha/wp-json/ninjapay/v1/webhook'
];

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

error_log('NinjaPay - Resposta (código ' . $info['http_code'] . '): ' . $response);
if ($error) {
    error_log('NinjaPay - cURL Error: ' . $error);
}

if ($info['http_code'] !== 201 && $info['http_code'] !== 200) {
    header("Location: index.php?error=api_error");
    exit;
}

$response_data = json_decode($response, true);

if (!isset($response_data['transactionId']) || !isset($response_data['pix']['code'])) {
    header("Location: index.php?error=incomplete_response");
    exit;
}

$transaction_id = $response_data['transactionId'];
$status = $response_data;
$pix_code = $response_data['pix']['code'];
$qrcode_url = isset($response_data['pix']['qrcode']) ? $response_data['pix']['qrcode'] : '';

$formatted_amount = number_format($amount, 2, ',', '.');

$created_at = time();
$timeLeft = 30 * 60;


$pdo = dbConnect();
$stmt = $pdo->prepare("INSERT INTO transacoes 
    (transaction_id, status, created_at, utm_source, utm_medium, utm_campaign, utm_content, utm_term) 
    VALUES (:transaction_id, 'PENDING', :created_at, :utm_source, :utm_medium, :utm_campaign, :utm_content, :utm_term)"
);

$stmt->execute([
    ':transaction_id' => $transaction_id,
    ':created_at' => $created_at,
    ':utm_source' => $utm_source ?? '',
    ':utm_medium' => $utm_medium ?? '',
    ':utm_campaign' => $utm_campaign ?? '',
    ':utm_content' => $utm_content ?? '',
    ':utm_term' => $utm_term ?? ''
]);

logMessage('log_verificar_pendentes.txt', "$transaction_id - Novo PIX gerado e salvo como PENDING");


?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento PIX - Doação para Talita</title>
    <link rel="icon" href="images/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Biblioteca QRCode.js para gerar QR codes -->
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.1/build/qrcode.min.js"></script>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background-color: #f5f5f5;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .pix-payment-page {
            background-color: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        .pix-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .vakinha-logo {
            display: flex;
            align-items: center;
        }
        
        .vakinha-logo img {
            height: 40px;
        }
        
        .secure-badge {
            display: flex;
            align-items: center;
            color: #4CAF50;
            font-size: 14px;
            background-color: #f0f9f0;
            padding: 5px 10px;
            border-radius: 20px;
        }
        
        .secure-badge i {
            margin-right: 5px;
        }
        
        .quase-la {
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            margin-top: 0;
            margin-bottom: 16px;
            color: #333;
        }
        
        .pix-message {
            background-color: #f0f8ff;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
            text-align: center;
            color: #0066cc;
            border: 1px solid #d1e9ff;
        }
        
        .pix-message p {
            margin: 0;
        }
        
        .pix-value {
            text-align: center;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 24px;
            color: #333;
        }
        
        .qr-code-container {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 16px;
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
            border: 1px solid #eee;
        }
        
        #qrcode {
            width: 200px;
            height: 200px;
            background-color: white;
            padding: 10px;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        
        #qrcode img, 
        #qrcode canvas {
            width: 100%;
            height: 100%;
        }
        
        .pix-code-container {
            margin-bottom: 16px;
            position: relative;
        }
        
        .pix-code {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: monospace;
            font-size: 14px;
            box-sizing: border-box;
            background-color: #f9f9f9;
            color: #333;
            resize: none;
            height: 80px;
            padding-right: 100px;
        }
        
        .copy-button {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: #00a859;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 12px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .copy-button:hover {
            background-color: #008a4b;
        }
        
        .copy-button-large {
            width: 100%;
            background-color: #00a859;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 16px;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            margin-bottom: 20px;
            transition: background-color 0.3s;
        }
        
        .copy-button-large:hover {
            background-color: #008a4b;
        }

        .copy-button-large i {
            margin-right: 8px;
        }

        .download-qrcode-button {
            width: 100%;
            background-color: #0066cc;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 16px;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            margin-bottom: 20px;
            transition: background-color 0.3s;
        }

        .download-qrcode-button:hover {
            background-color: #0055aa;
        }

        .download-qrcode-button i {
            margin-right: 8px;
        }
        
        .timer {
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            color: #ff3b30;
            margin-bottom: 24px;
        }
        
        .timer-warning {
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .pix-steps {
            margin-bottom: 24px;
        }
        
        .pix-step {
            display: flex;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        
        .step-icon {
            margin-right: 12px;
            flex-shrink: 0;
            width: 32px;
            height: 32px;
            background-color: #f0f9f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4CAF50;
        }
        
        .step-text {
            font-size: 14px;
            color: #555;
            flex: 1;
        }
        
        .payment-status {
            background-color: #e8f5e9;
            border-radius: 8px;
            padding: 16px;
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            border: 1px solid #c8e6c9;
        }
        
        .success-icon {
            width: 40px;
            height: 40px;
            background-color: #4CAF50;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            margin-right: 16px;
            flex-shrink: 0;
        }
        
        .success-content h3 {
            margin: 0 0 8px 0;
            color: #4CAF50;
        }
        
        .success-content p {
            margin: 0;
            color: #555;
        }
        
        .back-button {
            width: 100%;
            background-color: #f5f5f5;
            color: #555;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 12px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .back-button:hover {
            background-color: #e5e5e5;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .section-title h1 {
            font-size: 24px;
            color: #333;
            margin: 0;
        }
        
        /* Estilos para o spinner */
        .spinner-small {
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 8px;
            vertical-align: middle;
        }
        
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        
        <div class="pix-payment-page">
            <div class="pix-header">
                <div class="vakinha-logo">
                    <img src="images/logo.svg" alt="Logo">
                </div>
                <div class="secure-badge">
                    <i class="fas fa-lock"></i> Ambiente seguro
                </div>
            </div>
            
            <h2 class="quase-la">Quase lá...</h2>
            
            <div class="pix-message">
                <p>Sua ajuda significa muito! Obrigado por fazer parte dessa corrente do bem. ❤️</p>
            </div>
            
            <div class="pix-value">
                Valor: R$ <?php echo $formatted_amount; ?>
            </div>
            
            <div class="qr-code-container">
                <div id="qrcode"></div>
            </div>

            
            <div class="pix-code-container">
                <textarea class="pix-code" readonly id="pix-code-input"><?php echo $pix_code; ?></textarea>
            </div>
            
            <button class="copy-button-large" id="copy-button-large">
                <i class="fas fa-copy"></i> COPIAR CÓDIGO PIX
            </button>
            
            <div class="timer" id="payment-timer">
                <span id="timer-display" data-time-left="<?php echo $timeLeft; ?>">5:00</span>
            </div>
            
            <div class="pix-steps">
                <div class="pix-step">
                    <div class="step-icon">
                        <i class="fas fa-copy"></i>
                    </div>
                    <div class="step-text">Copie o código PIX acima</div>
                </div>
                <div class="pix-step">
                    <div class="step-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <div class="step-text">Abra seu aplicativo bancário e selecione a opção PIX Copia e Cola</div>
                </div>
                <div class="pix-step">
                    <div class="step-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="step-text">Cole o código e conclua o pagamento</div>
                </div>
            </div>
            
            <div id="payment-status" class="payment-status" style="display: none;">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <div class="success-content">
                    <h3>Pagamento Aprovado!</h3>
                    <p>Agradecemos sua contribuição. Você será redirecionado em alguns segundos...</p>
                </div>
            </div>
            
            
        </div>
    </div>
    
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Elementos da página
            const pixCodeInput = document.getElementById("pix-code-input");
            const copyButton = document.getElementById("copy-button");
            const copyButtonLarge = document.getElementById("copy-button-large");
            const timerDisplay = document.getElementById("timer-display");
            const backButton = document.getElementById("back-button");
            const qrcodeContainer = document.getElementById("qrcode");
            const downloadQRCodeButton = document.getElementById("download-qrcode-button");
            
            // Variáveis de controle
            let timeLeft = timerDisplay ? parseInt(timerDisplay.dataset.timeLeft || "1800") : 1800;
            const transactionId = "<?php echo $transaction_id; ?>";
            const pixCode = "<?php echo $pix_code; ?>";
            
            // Gerar QR code usando a biblioteca QRCode.js
            function generateQRCode() {
                if (!pixCode || !qrcodeContainer) {
                    return; // Não tem os elementos necessários
                }
                
                // Limpar o container
                qrcodeContainer.innerHTML = '';
                
                try {
                    // Método 1: Usar QRCode.toDataURL (mais compatível)
                    QRCode.toDataURL(pixCode, {
                        width: 200,
                        margin: 1,
                        color: {
                            dark: '#000000',
                            light: '#ffffff'
                        },
                        errorCorrectionLevel: 'H'
                    }, function(error, url) {
                        if (error) {
                            console.error('Erro ao gerar QR code com toDataURL:', error);
                            generateQRCodeAlternative();
                            return;
                        }
                        
                        // Criar uma imagem com o QR code
                        const img = document.createElement('img');
                        img.src = url;
                        img.alt = 'QR Code PIX';
                        img.style.maxWidth = '100%';
                        img.style.maxHeight = '100%';
                        qrcodeContainer.appendChild(img);
                    });
                } catch (err) {
                    console.error('Exceção ao gerar QR code:', err);
                    generateQRCodeAlternative();
                }
            }
            
            // Função de fallback para gerar QR code
            function generateQRCodeAlternative() {
                try {
                    // Método 2: Criar um novo elemento QRCode
                    new QRCode(qrcodeContainer, {
                        text: pixCode,
                        width: 180,
                        height: 180,
                        colorDark: "#000000",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.H
                    });
                } catch (err) {
                    console.error('Falha no método alternativo:', err);
                    
                    // Método 3: Último recurso - Google Charts API
                    const img = document.createElement('img');
                    img.src = 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=' + encodeURIComponent(pixCode) + '&choe=UTF-8';
                    img.alt = 'QR Code PIX';
                    img.style.maxWidth = '100%';
                    img.style.maxHeight = '100%';
                    qrcodeContainer.innerHTML = '';
                    qrcodeContainer.appendChild(img);
                }
            }
            
            // Função para copiar o código PIX
            function copyPixCode() {
                pixCodeInput.select();
                
                try {
                    // Usar API moderna de clipboard se disponível
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(pixCodeInput.value)
                            .then(() => {
                                showCopySuccess();
                            })
                            .catch(err => {
                                // Fallback para método mais antigo
                                document.execCommand('copy');
                                showCopySuccess();
                            });
                    } else {
                        // Fallback para método mais antigo
                        document.execCommand('copy');
                        showCopySuccess();
                    }
                } catch (err) {
                    console.error("Erro ao copiar:", err);
                    alert("Não foi possível copiar automaticamente. Por favor, copie manualmente.");
                }
            }
            
            // Mostrar feedback de sucesso ao copiar
            function showCopySuccess() {
                // Atualizar botão pequeno
                if (copyButton) {
                    copyButton.textContent = "Copiado!";
                    copyButton.style.backgroundColor = "#4CAF50";
                }
                
                // Atualizar botão grande
                copyButtonLarge.innerHTML = '<i class="fas fa-check"></i> COPIADO';
                copyButtonLarge.style.backgroundColor = "#4CAF50";
                
                // Restaurar após 2 segundos
                setTimeout(() => {
                    if (copyButton) {
                        copyButton.textContent = "Copiar";
                        copyButton.style.backgroundColor = "#00a859";
                    }
                    
                    copyButtonLarge.innerHTML = '<i class="fas fa-copy"></i> COPIAR CÓDIGO PIX';
                    copyButtonLarge.style.backgroundColor = "#00a859";
                }, 2000);
            }
            
            // Adicionar eventos para os botões de cópia
            if (copyButton) {
                copyButton.addEventListener("click", copyPixCode);
            }
            
            if (copyButtonLarge) {
                copyButtonLarge.addEventListener("click", copyPixCode);
            }
            
            // Adicionar evento para o botão voltar
            if (backButton) {
                backButton.addEventListener("click", function() {
                    window.location.href = "index.php";
                });
            }
            
            // Adicionar evento para o botão de download do QR code
            if (downloadQRCodeButton) {
                downloadQRCodeButton.addEventListener("click", function() {
                    // Capturar o QR code como imagem
                    const qrCodeImg = qrcodeContainer.querySelector("img");
                    if (qrCodeImg) {
                        // Criar um link para download
                        const link = document.createElement("a");
                        link.href = qrCodeImg.src;
                        link.download = "pix-qrcode.png";
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    } else {
                        alert("QR Code não disponível para download");
                    }
                });
            }
            
            // Timer de contagem regressiva
            function updateTimer() {
                if (!timerDisplay) return;
                
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                timerDisplay.textContent = `${minutes.toString().padStart(2, "0")}:${seconds.toString().padStart(2, "0")}`;
                
                // Adicionar aviso visual quando faltar menos de 5 minutos
                if (timeLeft <= 300) {
                    timerDisplay.classList.add("timer-warning");
                }
                
                if (timeLeft <= 0) {
                    clearInterval(timerInterval);
                    // Opcional: redirecionar ou mostrar mensagem de expiração
                    alert("O tempo para pagamento expirou. Você será redirecionado para a página inicial.");
                    window.location.href = "index.php";
                }
                
                timeLeft--;
            }
            
            // Iniciar o timer
            updateTimer();
            const timerInterval = setInterval(updateTimer, 1000);
            
            // Verificar status do pagamento
            function checkPaymentStatus() {
                fetch("check-payment-status.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({
                        transaction_id: transactionId
                    }),
                })
                .then(response => response.json())
                .then(data => {
                    console.log("Resposta da verificação de status:", data);
                    
                    if (data.success && data.data) {
                        const status = data.data.status;
                        const valor = data.data.amount;
                        const id = data.data.id;
                        console.log("Status do pagamento:", status);
                        
                        if (["OK", "PAID", "COMPLETED", "APPROVED"].includes(status.toUpperCase())) {
                            console.log("Pagamento aprovado!");
                            
                            const params = new URLSearchParams(window.location.search);
                            params.delete('amount');
                            
                            fetch(`obrigado.php?valor=${valor}&transactionId=${id}&${params.toString()}`)
                                .then(() => {
                                    fetch(`deletar.php?transactionId=${id}`)
                                        .then(() => {
                                            console.log("Transaction removida do banco de dados.");
                                        })
                                        .catch(err => {
                                            console.error("Erro ao remover transaction:", err);
                                        });
                                    
                                    showPaymentSuccess();
                                    clearInterval(statusInterval);
                                });
                        }
                    }
                })
                .catch(error => {
                    console.error("Erro ao verificar status:", error);
                });
            }
            
            // Mostrar mensagem de sucesso
            function showPaymentSuccess() {
                const paymentStatus = document.getElementById("payment-status");
                if (paymentStatus) {
                    paymentStatus.style.display = "flex";
                    
                    // Scroll para a mensagem de sucesso
                    paymentStatus.scrollIntoView({ behavior: "smooth", block: "center" });
                    
                }
            }
            
            // Verificar status a cada 10 segundos
            const statusInterval = setInterval(checkPaymentStatus, 10000);
            
            // Verificar imediatamente
            setTimeout(checkPaymentStatus, 1000);
            
            // Gerar QR code imediatamente
            generateQRCode();
        });
    </script>
</body>
</html>