<?php 
// screens/screen1.php

// Configurações iniciais
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Inicia a sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica se o tenant_id está na URL (para links compartilháveis)
$tenant_id = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : 0;

// Se não tiver na URL, tenta pegar da sessão (para embutir em outros sistemas)
if (!$tenant_id && isset($_SESSION['tenant_id'])) {
    $tenant_id = (int)$_SESSION['tenant_id'];
}

// Se ainda não tiver, redireciona para página de identificação
if (!$tenant_id) {
    header('Location: tenant_identification.php');
    exit;
}

// Armazena na sessão para as próximas telas
$_SESSION['tenant_id'] = $tenant_id;

// Conexão com o banco de dados para verificar se o tenant existe
require_once '../config/database.php';
$db = new Database();
$conn = $db->connect();

try {
    $stmt = $conn->prepare("SELECT id FROM empresas WHERE id = :tenant_id AND ativo = 1");
    $stmt->bindParam(':tenant_id', $tenant_id);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        header('Location: tenant_not_found.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("ERRO screen1: " . $e->getMessage());
    header('Location: error.php');
    exit;
}

include '../includes/header.php'; 
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avaliação de Atendimento</title>
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
            50% { transform: scale(1.1); }
        }
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .animate-bounce-in {
            animation: bounce-in 0.6s ease-out;
        }
        .emoji-button {
            transition: all 0.3s ease;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2));
        }
        .emoji-button:hover {
            transform: scale(1.3) rotate(10deg);
            filter: drop-shadow(0 8px 12px rgba(0,0,0,0.3));
        }
        .emoji-button:active {
            transform: scale(1.1);
            animation: pulse 0.2s ease-in-out;
        }
        .gradient-bg {
            background: linear-gradient(135deg, #1e3a8a 0%, #6b7280 100%);
        }
        .glow-effect {
            background: linear-gradient(45deg, #3b82f6, #10b981, #f59e0b, #ef4444);
            background-size: 400%;
            animation: gradientShift 15s ease infinite;
        }
        .tenant-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255,255,255,0.9);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center p-6">
    <div class="tenant-badge">
        <i class="fas fa-building me-2"></i>
        <?php 
        try {
            $stmt = $conn->prepare("SELECT nome FROM empresas WHERE id = :tenant_id");
            $stmt->bindParam(':tenant_id', $tenant_id);
            $stmt->execute();
            $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
            echo htmlspecialchars($empresa['nome'] ?? 'Empresa');
        } catch (PDOException $e) {
            echo "Empresa";
        }
        ?>
    </div>

    <div class="w-full max-w-3xl mx-auto p-12 bg-white bg-opacity-90 rounded-3xl shadow-2xl transform transition-all duration-500 hover:shadow-[0_20px_60px_rgba(0,0,0,0.4)] animate-bounce-in relative">
        <h2 class="text-4xl font-extrabold text-center mb-16 text-transparent bg-clip-text bg-gradient-to-r from-purple-600 to-pink-600 font-['Poppins']">
            Como foi o seu atendimento hoje?
        </h2>
        
        <div class="grid grid-cols-4 gap-6 mb-16">
            <form action="screen2.php" method="post">
                <input type="hidden" name="avaliacao" value="1">
                <input type="hidden" name="tenant_id" value="<?= $tenant_id ?>">
                <button type="submit" class="emoji-button text-6xl bg-red-100 rounded-full p-4 hover:bg-red-200 focus:outline-none transition-colors duration-300">😡</button>
                <p class="text-center mt-2 text-gray-600">Péssimo</p>
            </form>
            <form action="screen2.php" method="post">
                <input type="hidden" name="avaliacao" value="2">
                <input type="hidden" name="tenant_id" value="<?= $tenant_id ?>">
                <button type="submit" class="emoji-button text-6xl bg-yellow-100 rounded-full p-4 hover:bg-yellow-200 focus:outline-none transition-colors duration-300">😐</button>
                <p class="text-center mt-2 text-gray-600">Regular</p>
            </form>
            <form action="screen2.php" method="post">
                <input type="hidden" name="avaliacao" value="3">
                <input type="hidden" name="tenant_id" value="<?= $tenant_id ?>">
                <button type="submit" class="emoji-button text-6xl bg-green-100 rounded-full p-4 hover:bg-green-200 focus:outline-none transition-colors duration-300">😊</button>
                <p class="text-center mt-2 text-gray-600">Bom</p>
            </form>
            <form action="screen2.php" method="post">
                <input type="hidden" name="avaliacao" value="4">
                <input type="hidden" name="tenant_id" value="<?= $tenant_id ?>">
                <button type="submit" class="emoji-button text-6xl bg-blue-100 rounded-full p-4 hover:bg-blue-200 focus:outline-none transition-colors duration-300">😍</button>
                <p class="text-center mt-2 text-gray-600">Excelente</p>
            </form>
        </div>
        
        <div class="relative h-4 bg-gray-200 rounded-full overflow-hidden glow-effect">
            <div class="absolute h-full bg-gradient-to-r from-indigo-500 to-teal-500 transition-all duration-700" style="width: 25%;"></div>
        </div>
    </div>

    <script>
        // Efeito de confirmação ao passar o mouse sobre os emojis
        document.querySelectorAll('.emoji-button').forEach(button => {
            button.addEventListener('mouseover', function() {
                const label = this.nextElementSibling;
                label.classList.add('font-bold', 'text-black');
            });
            
            button.addEventListener('mouseout', function() {
                const label = this.nextElementSibling;
                label.classList.remove('font-bold', 'text-black');
            });
        });
    </script>
</body>
</html>

<?php 
include '../includes/footer.php';
?>