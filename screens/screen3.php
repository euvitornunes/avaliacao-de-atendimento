<?php
// screens/screen3.php

// Configurações iniciais
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Inicia a sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica se temos os dados necessários
if (!isset($_POST['avaliacao']) || !isset($_SESSION['tenant_id'])) {
    header('Location: screen1.php');
    exit;
}

$avaliacao = (int)$_POST['avaliacao'];
$funcionario_id = isset($_POST['funcionario_id']) ? (int)$_POST['funcionario_id'] : null;
$sem_funcionario = isset($_POST['sem_funcionario']) ? (int)$_POST['sem_funcionario'] : 0;
$tenant_id = (int)$_SESSION['tenant_id'];

// Conexão com o banco de dados
require_once '../config/database.php';
$db = new Database();
$pdo = $db->connect();

// Verifica se o funcionário pertence à empresa correta (se foi selecionado)
if ($funcionario_id && !$sem_funcionario) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM funcionarios WHERE id = :funcionario_id AND empresa_id = :tenant_id");
        $stmt->bindParam(':funcionario_id', $funcionario_id);
        $stmt->bindParam(':tenant_id', $tenant_id);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            // Funcionário não pertence à empresa, redireciona
            header('Location: screen1.php');
            exit;
        }
    } catch(PDOException $e) {
        error_log("Erro ao verificar funcionário: " . $e->getMessage());
        header('Location: screen1.php');
        exit;
    }
}

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
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comentário de Avaliação</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @keyframes bounce-in {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.2); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .animate-bounce-in {
            animation: bounce-in 0.6s ease-out;
        }
        .gradient-bg {
            background: linear-gradient(135deg, #1e3a8a 0%, #6b7280 100%);
        }
        .glow-effect {
            background: linear-gradient(45deg, #3b82f6, #10b981, #f59e0b, #ef4444);
            background-size: 400%;
            animation: gradientShift 15s ease infinite;
        }
        .comment-textarea {
            transition: all 0.3s ease;
        }
        .comment-textarea:focus {
            border-color: #7c3aed;
            box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.3);
            transform: scale(1.02);
        }
        .submit-btn {
            transition: all 0.3s ease;
        }
        .submit-btn:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 8px 24px rgba(124, 58, 237, 0.4);
        }
        .submit-btn:active {
            animation: pulse 0.2s ease-in-out;
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
        .back-btn {
            transition: all 0.3s ease;
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
        }
        .back-btn:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center">
    <div class="max-w-2xl w-full mx-auto p-8 bg-white bg-opacity-90 rounded-3xl shadow-2xl transform transition-all duration-500 hover:shadow-[0_20px_60px_rgba(0,0,0,0.4)] animate-bounce-in relative">
        <!-- Badge da empresa -->
        <div class="tenant-badge">
            <i class="fas fa-building me-1"></i>
            <?= htmlspecialchars($empresa_nome) ?>
        </div>

        <!-- Botão Voltar no canto superior direito -->
        <a href="screen2.php" class="back-btn px-4 py-2 text-gray-700 font-medium border border-gray-400 rounded-full hover:bg-gray-100 focus:outline-none text-sm">
            <i class="fas fa-arrow-left me-1"></i> Voltar
        </a>

        <h2 class="text-3xl md:text-4xl font-extrabold text-center mb-10 text-transparent bg-clip-text bg-gradient-to-r from-purple-600 to-pink-600 font-['Poppins']">
            Gostaria de deixar um comentário?
        </h2>
        
        <form action="screen4.php" method="post">
            <input type="hidden" name="avaliacao" value="<?= $avaliacao ?>">
            <input type="hidden" name="funcionario_id" value="<?= $funcionario_id ?>">
            <input type="hidden" name="sem_funcionario" value="<?= $sem_funcionario ?>">
            <input type="hidden" name="tenant_id" value="<?= $tenant_id ?>">
            
            <div class="mb-6">
                <textarea 
                    name="comentario" 
                    class="comment-textarea w-full min-h-[150px] p-4 border-2 border-gray-300 rounded-xl text-gray-700 placeholder-gray-400 text-lg focus:outline-none"
                    placeholder="Seu comentário (opcional)"
                    maxlength="500"
                ></textarea>
                <div class="text-right text-sm text-gray-500 mt-1">Máximo 500 caracteres</div>
            </div>
            
            <div class="text-center mt-6">
                <button 
                    type="submit" 
                    class="submit-btn px-8 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-semibold rounded-full hover:from-purple-700 hover:to-indigo-700 focus:outline-none"
                >
                    <i class="fas fa-paper-plane me-2"></i> Enviar
                </button>
            </div>
            
            <div class="relative h-3 mt-10 bg-gray-200 rounded-full overflow-hidden glow-effect">
                <div class="absolute h-full bg-gradient-to-r from-indigo-500 to-teal-500 transition-all duration-700" style="width: 75%;"></div>
            </div>
        </form>
    </div>
</body>
</html>