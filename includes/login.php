<?php
// includes/login.php

// Configuração detalhada de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Controle de sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Conexão com banco de dados
require_once '../config/database.php';
$db = new Database();
$pdo = $db->connect();

// Processamento do formulário
$erro = null;
$loginAttempts = $_SESSION['login_attempts'] ?? 0;
$lastAttempt = $_SESSION['last_attempt'] ?? 0;

// Limite de tentativas (5 em 15 minutos)
if ($loginAttempts >= 5 && (time() - $lastAttempt) < 900) {
    $remainingTime = ceil((900 - (time() - $lastAttempt)) / 60);
    $erro = "Muitas tentativas de login. Tente novamente em {$remainingTime} minutos.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {

        // Obter e sanitizar dados
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $senha = $_POST['senha'] ?? '';
        $lembrar = isset($_POST['lembrar']);
        
        // Validações
        if (empty($email)) {
            throw new Exception("Por favor, insira um e-mail.");
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Por favor, insira um e-mail válido.");
        }
        
        if (empty($senha)) {
            throw new Exception("Por favor, insira sua senha.");
        }
        
        // Consulta segura ao banco (incluindo empresa_id)
        $stmt = $pdo->prepare("
            SELECT 
                u.id, 
                u.nome, 
                u.email, 
                u.senha, 
                u.nivel_acesso, 
                u.ativo,
                u.empresa_id,
                e.nome as empresa_nome,
                e.dominio as empresa_dominio
            FROM usuarios u
            JOIN empresas e ON u.empresa_id = e.id
            WHERE u.email = :email 
            LIMIT 1
        ");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            // Delay simulado para evitar enumeração de usuários
            usleep(rand(200000, 500000));
            throw new Exception("Credenciais inválidas.");
        }
        
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verificação de conta ativa
        if (!$usuario['ativo']) {
            throw new Exception("Sua conta está desativada. Contate o administrador.");
        }

        // Verificação de senha
        if (password_verify($senha, $usuario['senha'])) {
            // Resetar contador de tentativas
            unset($_SESSION['login_attempts']);
            unset($_SESSION['last_attempt']);
            
            // Configuração completa da sessão com dados do tenant
            $_SESSION = [
                'logado' => true,
                'usuario_id' => $usuario['id'],
                'usuario_nome' => $usuario['nome'],
                'usuario_email' => $usuario['email'],
                'nivel_acesso' => $usuario['nivel_acesso'],
                'empresa_id' => $usuario['empresa_id'],
                'empresa_nome' => $usuario['empresa_nome'],
                'empresa_dominio' => $usuario['empresa_dominio'],
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'last_login' => time(),
                'csrf_token' => bin2hex(random_bytes(32)) // Novo token após login
            ];
            
            session_regenerate_id(true);
            
            // Cookie de lembrar e-mail (opcional e seguro)
            if ($lembrar) {
                $cookieValue = base64_encode($usuario['email']);
                setcookie('lembrar_email', $cookieValue, [
                    'expires' => time() + (86400 * 30), // 30 dias
                    'path' => '/',
                    'domain' => $_SERVER['HTTP_HOST'],
                    'secure' => isset($_SERVER['HTTPS']),
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);
            } else {
                // Remover cookie se existir
                setcookie('lembrar_email', '', [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'domain' => $_SERVER['HTTP_HOST']
                ]);
            }
            
            // Redirecionamento seguro para o dashboard
            header('Location: ../admin/dashboard.php');
            exit;
        } else {
            // Incrementar contador de tentativas
            $_SESSION['login_attempts'] = $loginAttempts + 1;
            $_SESSION['last_attempt'] = time();
            
            throw new Exception("Senha incorreta. Por favor, tente novamente.");
        }
        
    } catch (Exception $e) {
        $erro = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de Avaliação SaaS - Área de Login">
    <title>Login - Sistema de Avaliação SaaS</title>
    
    <!-- Pré-carregamento de recursos -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" as="style">
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-hover: #4338ca;
            --error-color: #dc3545;
            --success-color: #28a745;
        }
        body { 
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        .login-container { 
            max-width: 420px;
            width: 100%;
            margin: 0 auto;
            animation: fadeIn 0.5s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.12);
        }
        .card-header {
            background-color: var(--primary-color);
            color: white;
            padding: 1.5rem;
            text-align: center;
            border-bottom: none;
            position: relative;
        }
        .card-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), rgba(255,255,255,0.5));
        }
        .card-body {
            padding: 2rem;
            background-color: #fff;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.5rem;
            transition: all 0.2s ease;
        }
        .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
            transform: translateY(-2px);
        }
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(79, 70, 229, 0.25);
        }
        .input-group-text {
            background-color: #f8f9fa;
            transition: background-color 0.2s ease;
        }
        .input-group:focus-within .input-group-text {
            background-color: #e9ecef;
        }
        .tenant-logo {
            max-height: 40px;
            margin-bottom: 1rem;
        }
        .password-toggle {
            cursor: pointer;
            transition: color 0.2s ease;
        }
        .password-toggle:hover {
            color: var(--primary-color);
        }
        .progress-container {
            height: 4px;
            background-color: #e9ecef;
            margin-top: 0.5rem;
            border-radius: 2px;
            overflow: hidden;
            display: none;
        }
        .progress-bar {
            height: 100%;
            background-color: var(--primary-color);
            width: 0%;
            transition: width 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-card card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="fas fa-shield-alt me-2"></i> Acesso ao Sistema</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($erro)): ?>
                        <div class="alert alert-danger alert-dismissible fade show mb-4">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        
                        <div class="mb-4">
                            <label for="email" class="form-label fw-bold">E-mail</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= htmlspecialchars(
                                           $_POST['email'] ?? 
                                           (isset($_COOKIE['lembrar_email']) ? base64_decode($_COOKIE['lembrar_email']) : ''), 
                                           ENT_QUOTES, 'UTF-8') ?>" 
                                       placeholder="seu@email.com" required autofocus>
                                <div class="invalid-feedback">
                                    Por favor, insira um e-mail válido.
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="senha" class="form-label fw-bold">Senha</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="senha" name="senha" 
                                       placeholder="Sua senha" required>
                                <span class="input-group-text password-toggle" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </span>
                                <div class="invalid-feedback">
                                    Por favor, insira sua senha.
                                </div>
                            </div>
                            <div class="progress-container" id="passwordStrength">
                                <div class="progress-bar" id="passwordStrengthBar"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="lembrar" name="lembrar" 
                                <?= isset($_COOKIE['lembrar_email']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="lembrar">Lembrar meu e-mail</label>
                        </div>
                        
                        <div class="d-grid gap-2 mb-3">
                            <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                <i class="fas fa-sign-in-alt me-2"></i> Entrar
                            </button>
                        </div>
                        
                        <div class="text-center">
                            <a href="recuperar_senha.php" class="text-decoration-none text-muted small">
                                <i class="fas fa-key me-1"></i> Esqueceu sua senha?
                            </a>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-center py-3 bg-transparent">
                    <p class="text-muted small mb-0">
                        Sistema de Avaliação SaaS &copy; <?= date('Y') ?>
                        <span class="mx-2">•</span>
                        <a href="../screens/tenant_identification.php" class="text-decoration-none">
                            <i class="fas fa-building me-1"></i> Acesso Cliente
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
    <script>
        // Validação do formulário
        (function() {
            'use strict';
            
            // Fetch all the forms we want to apply custom Bootstrap validation styles to
            var forms = document.querySelectorAll('.needs-validation');
            
            // Loop over them and prevent submission
            Array.prototype.slice.call(forms)
                .forEach(function(form) {
                    form.addEventListener('submit', function(event) {
                        if (!form.checkValidity()) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        
                        form.classList.add('was-validated');
                    }, false);
                });
        })();
        
        // Mostrar/ocultar senha
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('senha');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });
        
        // Feedback visual durante digitação
        document.getElementById('senha').addEventListener('input', function() {
            const strengthBar = document.getElementById('passwordStrengthBar');
            const strengthContainer = document.getElementById('passwordStrength');
            const password = this.value;
            
            if (password.length > 0) {
                strengthContainer.style.display = 'block';
                
                // Simples validação de força da senha (pode ser melhorada)
                let strength = 0;
                if (password.length > 7) strength += 30;
                if (password.match(/[A-Z]/)) strength += 20;
                if (password.match(/[0-9]/)) strength += 20;
                if (password.match(/[^A-Za-z0-9]/)) strength += 30;
                
                strength = Math.min(strength, 100);
                strengthBar.style.width = strength + '%';
                
                // Mudar cor baseado na força
                if (strength < 40) {
                    strengthBar.style.backgroundColor = 'var(--error-color)';
                } else if (strength < 70) {
                    strengthBar.style.backgroundColor = '#ffc107';
                } else {
                    strengthBar.style.backgroundColor = 'var(--success-color)';
                }
            } else {
                strengthContainer.style.display = 'none';
            }
        });
        
        // Foco no campo de e-mail ao carregar
        document.addEventListener('DOMContentLoaded', function() {
            const emailField = document.getElementById('email');
            if (emailField.value === '') {
                emailField.focus();
            } else {
                document.getElementById('senha').focus();
            }
        });
    </script>
</body>
</html>