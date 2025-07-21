<?php
/**
 * Cabeçalho completo com estilos incorporados
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configurações de segurança
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Definição da URL base de forma absoluta
if (!defined('BASE_URL')) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    
    // Determinar o caminho físico da raiz da aplicação (onde 'index.php' e pastas como 'admin', 'includes' estão)
    // A partir de 'includes/header.php', '..' leva para a raiz da aplicação.
    $app_root_physical = realpath(__DIR__ . '/../');

    // Determinar o caminho físico da raiz do documento do servidor web
    $document_root_physical = realpath($_SERVER['DOCUMENT_ROOT']);

    // Calcular o caminho relativo da aplicação em relação à raiz do documento do servidor
    // Ex: se app_root_physical é /var/www/html/avaliacao-saas e document_root_physical é /var/www/html
    // $relative_path se torna /avaliacao-saas
    $relative_path = str_replace($document_root_physical, '', $app_root_physical);
    $relative_path = str_replace('\\', '/', $relative_path); // Garante barras normais no Windows
    $relative_path = rtrim($relative_path, '/'); // Remove barra final

    define('BASE_URL', "$protocol://$host$relative_path");
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de Avaliação de Atendimento Profissional">
    
    <title><?= htmlspecialchars($pageTitle ?? 'Sistema de Avaliação') ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #4361ee;
            --primary-dark: #3a56d4;
            --secondary-color: #3f37c9;
            --dark-color: #212529;
            --gray-dark: #343a40;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: var(--dark-color);
            line-height: 1.6;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .bg-gradient-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }
        
        .navbar {
            padding: 0.8rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-weight: 600;
            font-size: 1.25rem;
            color: white !important;
        }
        
        .nav-link {
            font-weight: 500;
            padding: 0.5rem 1rem;
            color: rgba(255,255,255,0.8) !important;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover {
            color: white !important;
            transform: translateY(-2px);
        }
        
        .dropdown-menu {
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        
        main {
            flex: 1;
            padding: 2rem 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        @media (max-width: 768px) {
            .navbar-collapse {
                padding: 1rem 0;
            }
        }
    </style>
</head>
<body>
    <?php if (!in_array(basename($_SERVER['SCRIPT_NAME']), ['screen1.php', 'screen4.php', 'login.php'])): ?>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-gradient-primary">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL ?>/admin/dashboard.php">
                    <i class="fas fa-star me-2"></i>
                    <span>Avaliação Atendimento</span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarMain">
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                        <?php if(isset($_SESSION['logado']) && $_SESSION['logado'] === true): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?= BASE_URL ?>/admin/dashboard.php">
                                    <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?= BASE_URL ?>/admin/funcionarios.php">
                                    <i class="fas fa-users me-1"></i> Funcionários
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?= BASE_URL ?>/admin/relatorios.php">
                                    <i class="fas fa-chart-bar me-1"></i> Relatórios
                                </a>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-user-circle me-1"></i> <?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário') ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/perfil.php">
                                        <i class="fas fa-user-edit me-1"></i> Perfil
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/includes/logout.php">
                                        <i class="fas fa-sign-out-alt me-1"></i> Sair
                                    </a></li>
                                </ul>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    <?php endif; ?>

    <main class="py-4">