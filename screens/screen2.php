<?php
// screens/screen2.php

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
$tenant_id = (int)$_SESSION['tenant_id'];

// Conexão com o banco de dados
require_once '../config/database.php';
$db = new Database();
$pdo = $db->connect();

// Busca funcionários ativos apenas da empresa atual
$funcionarios = [];
try {
    $sql = "SELECT id, nome, foto, setor 
            FROM funcionarios 
            WHERE ativo = 1 AND empresa_id = :empresa_id 
            ORDER BY setor, nome";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':empresa_id', $tenant_id);
    $stmt->execute();
    $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Erro ao buscar funcionários: " . $e->getMessage());
}

// Verifica se veio do botão "Não sei dizer"
$sem_funcionario = isset($_POST['sem_funcionario']) ? (int)$_POST['sem_funcionario'] : 0;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seleção de Funcionário</title>
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
        .employee-card {
            transition: all 0.3s ease;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2));
        }
        .employee-card:hover {
            transform: translateY(-8px) scale(1.05);
            filter: drop-shadow(0 8px 12px rgba(0,0,0,0.3));
            border-color: #7c3aed;
        }
        .employee-card.selected {
            border-color: #7c3aed;
            background: linear-gradient(45deg, #f3e8ff, #e9d5ff);
        }
        .employee-img {
            transition: transform 0.3s ease;
        }
        .employee-card:hover .employee-img {
            transform: scale(1.1);
        }
        .gradient-bg {
            background: linear-gradient(135deg, #1e3a8a 0%, #6b7280 100%);
        }
        .glow-effect {
            background: linear-gradient(45deg, #3b82f6, #10b981, #f59e0b, #ef4444);
            background-size: 400%;
            animation: gradientShift 15s ease infinite;
        }
        .skip-btn {
            transition: all 0.3s ease;
        }
        .skip-btn:hover {
            background: linear-gradient(90deg, #6b7280, #4b5563);
            transform: scale(1.05);
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
    <div class="max-w-4xl w-full mx-auto p-8 bg-white bg-opacity-90 rounded-3xl shadow-2xl transform transition-all duration-500 hover:shadow-[0_20px_60px_rgba(0,0,0,0.4)] animate-bounce-in relative">
        <!-- Badge da empresa -->
        <div class="tenant-badge">
            <i class="fas fa-building me-1"></i>
            <?php 
            try {
                $stmt = $pdo->prepare("SELECT nome FROM empresas WHERE id = :tenant_id");
                $stmt->bindParam(':tenant_id', $tenant_id);
                $stmt->execute();
                $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
                echo htmlspecialchars($empresa['nome'] ?? 'Empresa');
            } catch (PDOException $e) {
                echo "Empresa";
            }
            ?>
        </div>

        <!-- Botão Voltar no canto superior direito -->
        <a href="screen1.php" class="back-btn px-4 py-2 text-gray-700 font-medium border border-gray-400 rounded-full hover:bg-gray-100 focus:outline-none text-sm">
            <i class="fas fa-arrow-left me-1"></i> Voltar
        </a>

        <h2 class="text-3xl md:text-4xl font-extrabold text-center mb-10 text-transparent bg-clip-text bg-gradient-to-r from-purple-600 to-pink-600 font-['Poppins']">
            Quem te atendeu?
        </h2>
        
        <form action="screen3.php" method="post">
            <input type="hidden" name="avaliacao" value="<?= $avaliacao ?>">
            <input type="hidden" name="tenant_id" value="<?= $tenant_id ?>">
            <input type="hidden" name="sem_funcionario" value="0" id="sem_funcionario">
            
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 mb-10">
                <?php if (!empty($funcionarios)): ?>
                    <?php foreach($funcionarios as $func): ?>
                    <label class="employee-card text-center p-4 bg-gray-50 rounded-xl border-2 border-transparent cursor-pointer">
                        <input type="radio" name="funcionario_id" value="<?= $func['id'] ?>" class="hidden">
                        <img src="../assets/images/funcionarios/<?= htmlspecialchars($func['foto']) ?>" 
                             alt="<?= htmlspecialchars($func['nome']) ?>" 
                             class="employee-img w-24 h-24 object-cover rounded-full mx-auto mb-3 border-3 border-white shadow-md"
                             onerror="this.src='../assets/images/funcionarios/default.jpg'">
                        <h5 class="text-lg font-semibold text-gray-800 mb-1"><?= htmlspecialchars($func['nome']) ?></h5>
                        <small class="text-gray-500 text-sm"><?= htmlspecialchars($func['setor']) ?></small>
                    </label>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full text-center py-8">
                        <i class="fas fa-users-slash text-4xl text-gray-400 mb-3"></i>
                        <p class="text-gray-600">Nenhum funcionário cadastrado</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="text-center mt-6">
                <button type="button" id="btnNaoSei" class="skip-btn px-6 py-3 text-gray-700 font-semibold border-2 border-gray-500 rounded-full hover:text-white focus:outline-none">
                    <i class="fas fa-question-circle me-1"></i> Não sei dizer
                </button>
                <button type="submit" id="btnSubmit" class="hidden px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-semibold rounded-full hover:from-purple-700 hover:to-indigo-700 focus:outline-none transition-colors duration-300">
                    <i class="fas fa-arrow-right me-1"></i> Continuar
                </button>
            </div>
            
            <div class="relative h-3 mt-10 bg-gray-200 rounded-full overflow-hidden glow-effect">
                <div class="absolute h-full bg-gradient-to-r from-indigo-500 to-teal-500 transition-all duration-700" style="width: 50%;"></div>
            </div>
        </form>
    </div>

    <script>
        // Seleção de funcionário
        document.querySelectorAll('.employee-card').forEach(card => {
            const radio = card.querySelector('input[type="radio"]');
            card.addEventListener('click', () => {
                document.querySelectorAll('.employee-card').forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                radio.checked = true;
                document.getElementById('sem_funcionario').value = '0';
                document.getElementById('btnSubmit').classList.remove('hidden');
                document.getElementById('btnNaoSei').classList.add('hidden');
            });
        });

        // Botão "Não sei dizer"
        document.getElementById('btnNaoSei').addEventListener('click', () => {
            document.getElementById('sem_funcionario').value = '1';
            document.querySelector('form').submit();
        });

        // Fallback para imagens que não carregam
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.employee-img').forEach(img => {
                img.onerror = function() {
                    this.src = '../assets/images/funcionarios/default.jpg';
                };
            });
        });
    </script>
</body>
</html>