<?php
// admin/perfil.php

// Configurações iniciais
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Verificar autenticação
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_secure'   => true,
        'cookie_httponly' => true,
        'use_strict_mode' => true
    ]);
}

if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['empresa_id'])) {
    header('Location: ../index.php');
    exit;
}

// Conexão com banco de dados
require_once '../config/database.php';
$db = new Database();
$pdo = $db->connect();

// Obter dados do usuário
try {
    $stmt = $pdo->prepare("
        SELECT u.*, e.nome as empresa_nome 
        FROM usuarios u
        JOIN empresas e ON u.empresa_id = e.id
        WHERE u.id = :usuario_id AND u.empresa_id = :empresa_id
    ");
    $stmt->bindParam(':usuario_id', $_SESSION['usuario_id']);
    $stmt->bindParam(':empresa_id', $_SESSION['empresa_id']);
    $stmt->execute();
    
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        header('Location: ../includes/logout.php');
        exit;
    }
} catch (PDOException $e) {
    die("Erro ao carregar perfil: " . $e->getMessage());
}

// Variáveis para mensagens
$mensagem = '';
$erro = '';

// Processar formulário de atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $senha_atual = $_POST['senha_atual'] ?? '';
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    
    // Validações
    if (empty($nome)) {
        $erro = 'O nome é obrigatório.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'E-mail inválido.';
    } elseif (!empty($nova_senha) && strlen($nova_senha) < 6) {
        $erro = 'A nova senha deve ter pelo menos 6 caracteres.';
    } elseif (!empty($nova_senha) && $nova_senha !== $confirmar_senha) {
        $erro = 'As senhas não coincidem.';
    }
    
    // Verificar senha atual se for alterar a senha
    if (empty($erro) && !empty($nova_senha)) {
        if (!password_verify($senha_atual, $usuario['senha'])) {
            $erro = 'A senha atual está incorreta.';
        }
    }
    
    // Atualizar no banco de dados
    if (empty($erro)) {
        try {
            $params = [
                ':nome' => $nome,
                ':email' => $email,
                ':usuario_id' => $_SESSION['usuario_id'],
                ':empresa_id' => $_SESSION['empresa_id']
            ];
            
            $sql = "UPDATE usuarios SET nome = :nome, email = :email";
            
            // Adicionar senha se for alterada
            if (!empty($nova_senha)) {
                $sql .= ", senha = :senha";
                $params[':senha'] = password_hash($nova_senha, PASSWORD_DEFAULT);
            }
            
            $sql .= " WHERE id = :usuario_id AND empresa_id = :empresa_id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $mensagem = 'Perfil atualizado com sucesso!';
            
            // Atualizar dados na sessão
            $_SESSION['usuario_nome'] = $nome;
            $_SESSION['usuario_email'] = $email;
            
            // Recarregar dados do usuário
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = :usuario_id");
            $stmt->bindParam(':usuario_id', $_SESSION['usuario_id']);
            $stmt->execute();
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $erro = 'Erro ao atualizar perfil: ' . $e->getMessage();
        }
    }
}

require_once '../includes/header.php';
?>

<style>
    .profile-container {
        max-width: 800px;
        margin: 2rem auto;
        padding: 2rem;
        background: white;
        border-radius: 16px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        border: 1px solid #e9ecef;
    }
    
    .profile-header {
        text-align: center;
        margin-bottom: 2rem;
        position: relative;
    }
    
    .profile-avatar {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        object-fit: cover;
        margin: 0 auto 1rem;
        border: 4px solid #4facfe;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        background-color: #f8f9fa;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        color: #495057;
    }
    
    .profile-form .form-group {
        margin-bottom: 1.5rem;
    }
    
    .profile-form .form-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: #495057;
    }
    
    .profile-form .form-control {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid #ced4da;
        border-radius: 8px;
        font-size: 1rem;
        transition: all 0.3s ease;
    }
    
    .profile-form .form-control:focus {
        border-color: #4facfe;
        box-shadow: 0 0 0 3px rgba(79, 172, 254, 0.2);
        outline: none;
    }
    
    .btn-save {
        background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%);
        color: white;
        border: none;
        padding: 0.75rem 2rem;
        font-size: 1rem;
        font-weight: 500;
        border-radius: 50px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(79, 172, 254, 0.3);
        cursor: pointer;
    }
    
    .btn-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(79, 172, 254, 0.4);
    }
    
    .alert {
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        border: 1px solid transparent;
    }
    
    .alert-success {
        background-color: #d4edda;
        border-color: #c3e6cb;
        color: #155724;
    }
    
    .alert-danger {
        background-color: #f8d7da;
        border-color: #f5c6cb;
        color: #721c24;
    }
    
    .password-section {
        background: #f8f9fa;
        padding: 1.5rem;
        border-radius: 12px;
        margin-top: 2rem;
        border: 1px solid #e9ecef;
    }
    
    .section-title {
        font-size: 1.25rem;
        margin-bottom: 1rem;
        color: #343a40;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .text-muted {
        color: #6c757d;
        font-size: 0.875rem;
    }
    
    .text-center {
        text-align: center;
    }
    
    .mt-4 {
        margin-top: 1.5rem;
    }
    
    .company-badge {
        display: inline-block;
        background: #e9ecef;
        padding: 0.25rem 0.75rem;
        border-radius: 50px;
        font-size: 0.875rem;
        color: #495057;
        margin-top: 0.5rem;
    }
    
    .avatar-initials {
        font-size: 3rem;
        font-weight: bold;
        color: #495057;
    }
</style>

<div class="profile-container">
    <div class="profile-header">
        <div class="profile-avatar">
            <div class="avatar-initials">
                <?= strtoupper(substr($usuario['nome'], 0, 1)) ?>
            </div>
        </div>
        <h2>Meu Perfil</h2>
        <p class="text-muted">Gerencie suas informações de conta</p>
        <div class="company-badge">
            <i class="fas fa-building"></i> <?= htmlspecialchars($usuario['empresa_nome']) ?>
        </div>
    </div>
    
    <?php if ($mensagem): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>
            <?= htmlspecialchars($mensagem) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($erro): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?= htmlspecialchars($erro) ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" class="profile-form">
        <div class="form-group">
            <label class="form-label">Nome Completo</label>
            <input type="text" name="nome" class="form-control" 
                   value="<?= htmlspecialchars($usuario['nome']) ?>" required>
        </div>
        
        <div class="form-group">
            <label class="form-label">E-mail</label>
            <input type="email" name="email" class="form-control" 
                   value="<?= htmlspecialchars($usuario['email']) ?>" required>
        </div>
        
        <div class="password-section">
            <div class="section-title">
                <i class="fas fa-lock"></i>
                <span>Alterar Senha</span>
            </div>
            <p class="text-muted">Deixe em branco se não quiser alterar a senha</p>
            
            <div class="form-group">
                <label class="form-label">Senha Atual</label>
                <input type="password" name="senha_atual" class="form-control" 
                       placeholder="Necessária para alterar a senha">
            </div>
            
            <div class="form-group">
                <label class="form-label">Nova Senha</label>
                <input type="password" name="nova_senha" class="form-control" 
                       placeholder="Mínimo 6 caracteres">
            </div>
            
            <div class="form-group">
                <label class="form-label">Confirmar Nova Senha</label>
                <input type="password" name="confirmar_senha" class="form-control" 
                       placeholder="Repita a nova senha">
            </div>
        </div>
        
        <div class="text-center mt-4">
            <button type="submit" class="btn-save">
                <i class="fas fa-save me-2"></i> Salvar Alterações
            </button>
        </div>
    </form>
</div>

<?php 
include '../includes/footer.php';
?>