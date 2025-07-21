<?php
// screens/register.php

// Configurações iniciais
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Inicia a sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Conexão com o banco de dados
require_once '../config/database.php';
$db = new Database();
$conn = $db->connect();

$erros = [];
$sucesso = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validações
    if (empty($_POST['empresa_nome'])) {
        $erros[] = "O nome da empresa é obrigatório";
    }
    
    if (empty($_POST['nome'])) {
        $erros[] = "Seu nome é obrigatório";
    }
    
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $erros[] = "E-mail inválido";
    }
    
    if (strlen($_POST['senha']) < 6) {
        $erros[] = "A senha deve ter pelo menos 6 caracteres";
    }
    
    if ($_POST['senha'] !== $_POST['confirmar_senha']) {
        $erros[] = "As senhas não coincidem";
    }

    if (empty($erros)) {
        try {
            $conn->beginTransaction();
            
            // 1. Criar a empresa
            $query = "INSERT INTO empresas (nome, dominio) VALUES (:nome, :dominio)";
            $stmt = $conn->prepare($query);
            
            $dominio = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $_POST['empresa_nome']));
            $stmt->bindParam(':nome', $_POST['empresa_nome']);
            $stmt->bindParam(':dominio', $dominio);
            
            $stmt->execute();
            $empresa_id = $conn->lastInsertId();
            
            // 2. Criar usuário admin - QUERY CORRIGIDA
            $query = "INSERT INTO usuarios 
                     (email, senha, nome, nivel_acesso, ativo, empresa_id) 
                     VALUES 
                     (:email, :senha, :nome, 2, 1, :empresa_id)";
                     
            $stmt = $conn->prepare($query);
            
            $senha_hash = password_hash($_POST['senha'], PASSWORD_BCRYPT);
            
            $stmt->bindParam(':email', $_POST['email']);
            $stmt->bindParam(':senha', $senha_hash);
            $stmt->bindParam(':nome', $_POST['nome']);
            $stmt->bindParam(':empresa_id', $empresa_id);
            
            $stmt->execute();
            
            $conn->commit();
            $sucesso = true;
            
            // Redireciona para login após 3 segundos
            header("Refresh: 3; url=login.php");
        } catch(PDOException $e) {
            $conn->rollBack();
            $erros[] = "Erro no cadastro: " . $e->getMessage();
            error_log("Erro no registro: " . $e->getMessage());
        }
    }
}

include '../includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-lg">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Cadastro de Nova Empresa</h4>
                </div>
                <div class="card-body">
                    <?php if ($sucesso): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            Cadastro realizado com sucesso! Você será redirecionado para a página de login.
                        </div>
                    <?php else: ?>
                        <?php if (!empty($erros)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($erros as $erro): ?>
                                        <li><?= htmlspecialchars($erro) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <h5 class="mb-3 text-primary">Dados da Empresa</h5>
                            <div class="mb-3">
                                <label for="empresa_nome" class="form-label">Nome da Empresa *</label>
                                <input type="text" class="form-control" id="empresa_nome" name="empresa_nome" 
                                       value="<?= htmlspecialchars($_POST['empresa_nome'] ?? '') ?>" required>
                            </div>

                            <hr class="my-4">

                            <h5 class="mb-3 text-primary">Dados do Administrador</h5>
                            <div class="mb-3">
                                <label for="nome" class="form-label">Seu Nome Completo *</label>
                                <input type="text" class="form-control" id="nome" name="nome" 
                                       value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">E-mail *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="senha" class="form-label">Senha *</label>
                                <input type="password" class="form-control" id="senha" name="senha" required>
                                <small class="text-muted">Mínimo de 6 caracteres</small>
                            </div>

                            <div class="mb-4">
                                <label for="confirmar_senha" class="form-label">Confirmar Senha *</label>
                                <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" required>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-user-plus me-2"></i> Cadastrar
                                </button>
                            </div>
                        </form>

                        <div class="mt-3 text-center">
                            <p>Já tem uma conta? <a href="login.php" class="text-decoration-none">Faça login aqui</a></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
include '../includes/footer.php';
?>