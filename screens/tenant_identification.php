<?php
// screens/tenant_identification.php

// Configurações iniciais
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Inicia a sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_secure'   => true,
        'cookie_httponly' => true,
        'use_strict_mode' => true
    ]);
}

// Conexão com o banco de dados
require_once '../config/database.php';
$db = new Database();
$conn = $db->connect();

// Processar formulário de identificação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identificador = trim($_POST['identificador']);
    
    try {
        // Buscar empresa por ID, nome ou domínio
        $stmt = $conn->prepare("
            SELECT id, nome, dominio 
            FROM empresas 
            WHERE 
                id = :id OR 
                nome LIKE :nome OR 
                dominio LIKE :dominio AND
                ativo = 1
            LIMIT 1
        ");
        
        // Verifica se o identificador é numérico (ID)
        if (is_numeric($identificador)) {
            $stmt->bindValue(':id', (int)$identificador);
        } else {
            $stmt->bindValue(':id', 0);
        }
        
        $stmt->bindValue(':nome', "%$identificador%");
        $stmt->bindValue(':dominio', "%$identificador%");
        $stmt->execute();
        
        $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($empresa) {
            $_SESSION['tenant_id'] = $empresa['id'];
            header('Location: screen1.php');
            exit;
        } else {
            $erro = "Nenhuma empresa encontrada com esse identificador";
        }
    } catch (PDOException $e) {
        error_log("ERRO tenant_identification: " . $e->getMessage());
        $erro = "Erro ao buscar empresa. Por favor, tente novamente.";
    }
}

include '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Identificação da Empresa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out forwards;
        }
        .gradient-bg {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
        }
        .card-hover {
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md mx-auto animate-fade-in">
        <div class="card-hover bg-white rounded-xl shadow-xl overflow-hidden">
            <div class="bg-blue-600 py-4 px-6">
                <h2 class="text-2xl font-bold text-white text-center">
                    <i class="fas fa-building me-2"></i>Identificação da Empresa
                </h2>
            </div>
            
            <div class="p-6">
                <?php if (!empty($erro)): ?>
                    <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= htmlspecialchars($erro) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="space-y-4">
                    <div>
                        <label for="identificador" class="block text-sm font-medium text-gray-700 mb-1">
                            Digite o ID, nome ou domínio da empresa:
                        </label>
                        <input 
                            type="text" 
                            id="identificador" 
                            name="identificador" 
                            required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Ex: 123, Sua Empresa ou dominio"
                            autofocus
                        >
                    </div>
                    
                    <div class="pt-2">
                        <button 
                            type="submit" 
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition duration-200"
                        >
                            <i class="fas fa-arrow-right me-2"></i> Continuar
                        </button>
                    </div>
                </form>
                
                <div class="mt-6 pt-4 border-t border-gray-200">
                    <p class="text-sm text-gray-600 text-center">
                        Não sabe o identificador? Entre em contato com o administrador da sua empresa.
                    </p>
                </div>
            </div>
        </div>
        
        <div class="mt-6 text-center text-white text-sm">
            <p>Sistema de Avaliação SaaS &copy; <?= date('Y') ?></p>
        </div>
    </div>

    <script>
        // Foco automático no campo de identificação
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('identificador').focus();
        });
    </script>
</body>
</html>

<?php 
include '../includes/footer.php';
?>