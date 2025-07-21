<?php
// screens/screen4.php

// Configurações iniciais
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Inicia a sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica se temos os dados necessários
if (!isset($_POST['avaliacao']) || !isset($_POST['tenant_id'])) {
    header('Location: screen1.php');
    exit;
}

$avaliacao = (int)$_POST['avaliacao'];
$funcionario_id = isset($_POST['funcionario_id']) ? (int)$_POST['funcionario_id'] : null;
$comentario = isset($_POST['comentario']) ? trim($_POST['comentario']) : '';
$sem_funcionario = isset($_POST['sem_funcionario']) ? (int)$_POST['sem_funcionario'] : 0;
$tenant_id = (int)$_POST['tenant_id'];

// Conexão com o banco de dados
require_once '../config/database.php';
$db = new Database();
$pdo = $db->connect();

// Busca nome da empresa para exibição
$empresa_nome = 'Empresa';
try {
    $stmt = $pdo->prepare("SELECT nome FROM empresas WHERE id = :tenant_id");
    $stmt->bindParam(':tenant_id', $tenant_id);
    $stmt->execute();
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($empresa) {
        $empresa_nome = $empresa['nome'];
    }
} catch(PDOException $e) {
    error_log("Erro ao buscar nome da empresa: " . $e->getMessage());
}

// Registra a avaliação no banco de dados
try {
    $sql = "INSERT INTO avaliacoes 
            (avaliacao, funcionario_id, comentario, sem_funcionario, data_avaliacao) 
            VALUES 
            (:avaliacao, :funcionario_id, :comentario, :sem_funcionario, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':avaliacao', $avaliacao, PDO::PARAM_INT);
    
    if ($funcionario_id && !$sem_funcionario) {
        // Verifica novamente se o funcionário pertence à empresa
        $check = $pdo->prepare("SELECT id FROM funcionarios WHERE id = :funcionario_id AND empresa_id = :tenant_id");
        $check->bindParam(':funcionario_id', $funcionario_id);
        $check->bindParam(':tenant_id', $tenant_id);
        $check->execute();
        
        if ($check->rowCount() > 0) {
            $stmt->bindParam(':funcionario_id', $funcionario_id, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':funcionario_id', null, PDO::PARAM_NULL);
            $sem_funcionario = 1;
        }
    } else {
        $stmt->bindValue(':funcionario_id', null, PDO::PARAM_NULL);
    }
    
    $stmt->bindParam(':comentario', $comentario);
    $stmt->bindParam(':sem_funcionario', $sem_funcionario, PDO::PARAM_INT);
    $stmt->execute();
    
    // Log de sucesso
    error_log("Avaliação registrada para empresa ID: $tenant_id");
    
} catch(PDOException $e) {
    error_log("Erro ao registrar avaliação: " . $e->getMessage());
    // Não redireciona para manter a experiência do usuário
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agradecimento pela Avaliação</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @keyframes bounce-in {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.2); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }
        @keyframes scaleIn {
            0% { transform: scale(0); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .animate-bounce-in {
            animation: bounce-in 0.6s ease-out;
        }
        .animate-scale-in {
            animation: scaleIn 0.8s ease-out;
        }
        .gradient-bg {
            background: linear-gradient(135deg, #1e3a8a 0%, #6b7280 100%);
        }
        .glow-effect {
            background: linear-gradient(45deg, #3b82f6, #10b981, #f59e0b, #ef4444);
            background-size: 400%;
            animation: gradientShift 15s ease infinite;
        }
        .checkmark {
            filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.3));
        }
        .tenant-badge {
            position: absolute;
            top: 1.5rem;
            left: 1.5rem;
            background: rgba(255,255,255,0.9);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center">
    <div class="max-w-xl w-full mx-auto p-8 bg-white bg-opacity-90 rounded-3xl shadow-2xl transform transition-all duration-500 hover:shadow-[0_20px_60px_rgba(0,0,0,0.4)] animate-bounce-in relative">
        <!-- Badge da empresa -->
        <div class="tenant-badge">
            <i class="fas fa-building me-1"></i>
            <?= htmlspecialchars($empresa_nome) ?>
        </div>

        <h2 class="text-3xl md:text-4xl font-extrabold text-center mb-8 text-transparent bg-clip-text bg-gradient-to-r from-purple-600 to-pink-600 font-['Poppins']">
            Obrigado pela sua avaliação!
        </h2>
        <div class="checkmark text-7xl md:text-8xl text-green-500 mx-auto mb-6 animate-scale-in">
            <i class="fas fa-check-circle"></i>
        </div>
        <p class="text-center text-gray-600 text-lg mb-8 font-semibold">
            Sua opinião foi registrada com sucesso!
        </p>
        
        <div class="relative h-3 bg-gray-200 rounded-full overflow-hidden glow-effect">
            <div class="absolute h-full bg-gradient-to-r from-indigo-500 to-teal-500 transition-all duration-700" style="width: 100%;"></div>
        </div>
    </div>

    <script>
        // Redireciona para a tela inicial com o tenant_id após 5 segundos
        setTimeout(function() {
            window.location.href = 'screen1.php?tenant_id=<?= $tenant_id ?>';
        }, 5000);
    </script>
</body>
</html>