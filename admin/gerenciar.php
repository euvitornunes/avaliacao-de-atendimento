<?php
// admin/gerenciar.php

require_once '../config/database.php';
require_once '../includes/header.php';

// Verificar se usuário está logado e tem permissão
if (!isset($_SESSION['logado']) || $_SESSION['nivel_acesso'] != 2) {
    header('Location: ../includes/login.php');
    exit;
}

$db = new Database($_SESSION['empresa_id']);
$pdo = $db->connect();

// Função para sanitizar entradas
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Processar formulários
$erro = null;
$sucesso = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verificar CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Token de segurança inválido.");
        }

        // Adicionar/Editar Empresa
        if (isset($_POST['acao']) && $_POST['acao'] === 'empresa') {
            $nome = sanitize($_POST['nome'] ?? '');
            $dominio = sanitize($_POST['dominio'] ?? '');
            $empresa_id = isset($_POST['empresa_id']) ? (int)$_POST['empresa_id'] : null;

            if (empty($nome) || empty($dominio)) {
                throw new Exception("Nome e domínio são obrigatórios.");
            }

            if ($empresa_id) {
                // Atualizar empresa
                $stmt = $pdo->prepare("UPDATE empresas SET nome = :nome, dominio = :dominio WHERE id = :id");
                $stmt->execute(['nome' => $nome, 'dominio' => $dominio, 'id' => $empresa_id]);
                $sucesso = "Empresa atualizada com sucesso.";
            } else {
                // Adicionar nova empresa
                $stmt = $pdo->prepare("INSERT INTO empresas (nome, dominio) VALUES (:nome, :dominio)");
                $stmt->execute(['nome' => $nome, 'dominio' => $dominio]);
                $sucesso = "Empresa adicionada com sucesso.";
            }
        }

        // Adicionar/Editar Usuário
        if (isset($_POST['acao']) && $_POST['acao'] === 'usuario') {
            $nome = sanitize($_POST['nome'] ?? '');
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            $senha = $_POST['senha'] ?? '';
            $nivel_acesso = (int)$_POST['nivel_acesso'] ?? 1;
            $empresa_id = (int)$_POST['empresa_id'] ?? 0;
            $usuario_id = isset($_POST['usuario_id']) ? (int)$_POST['usuario_id'] : null;

            if (empty($nome) || empty($email) || empty($empresa_id)) {
                throw new Exception("Nome, e-mail e empresa são obrigatórios.");
            }

            if (!$usuario_id && empty($senha)) {
                throw new Exception("Senha é obrigatória para novo usuário.");
            }

            if ($usuario_id) {
                // Atualizar usuário
                $sql = "UPDATE usuarios SET nome = :nome, email = :email, nivel_acesso = :nivel_acesso, empresa_id = :empresa_id";
                if (!empty($senha)) {
                    $sql .= ", senha = :senha";
                }
                $sql .= " WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $params = [
                    'nome' => $nome,
                    'email' => $email,
                    'nivel_acesso' => $nivel_acesso,
                    'empresa_id' => $empresa_id,
                    'id' => $usuario_id
                ];
                if (!empty($senha)) {
                    $params['senha'] = password_hash($senha, PASSWORD_DEFAULT);
                }
                $stmt->execute($params);
                $sucesso = "Usuário atualizado com sucesso.";
            } else {
                // Adicionar novo usuário
                $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, nivel_acesso, empresa_id) VALUES (:nome, :email, :senha, :nivel_acesso, :empresa_id)");
                $stmt->execute([
                    'nome' => $nome,
                    'email' => $email,
                    'senha' => password_hash($senha, PASSWORD_DEFAULT),
                    'nivel_acesso' => $nivel_acesso,
                    'empresa_id' => $empresa_id
                ]);
                $sucesso = "Usuário adicionado com sucesso.";
            }
        }

        // Desativar Empresa
        if (isset($_POST['acao']) && $_POST['acao'] === 'desativar_empresa') {
            $empresa_id = (int)$_POST['empresa_id'];
            $stmt = $pdo->prepare("UPDATE empresas SET ativo = 0 WHERE id = :id");
            $stmt->execute(['id' => $empresa_id]);
            $sucesso = "Empresa desativada com sucesso.";
        }

        // Desativar Usuário
        if (isset($_POST['acao']) && $_POST['acao'] === 'desativar_usuario') {
            $usuario_id = (int)$_POST['usuario_id'];
            $stmt = $pdo->prepare("UPDATE usuarios SET ativo = 0 WHERE id = :id");
            $stmt->execute(['id' => $usuario_id]);
            $sucesso = "Usuário desativado com sucesso.";
        }

        // Regenerar CSRF token após ação
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    } catch (Exception $e) {
        $erro = $e->getMessage();
    }
}

// Listar empresas
$stmt_empresas = $pdo->query("SELECT * FROM empresas WHERE ativo = 1");
$empresas = $stmt_empresas->fetchAll(PDO::FETCH_ASSOC);

// Listar usuários
$stmt_usuarios = $pdo->query("
    SELECT u.*, e.nome AS empresa_nome 
    FROM usuarios u 
    JOIN empresas e ON u.empresa_id = e.id 
    WHERE u.ativo = 1
");
$usuarios = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários e Empresas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card { box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .table-responsive { margin-top: 20px; }
        .btn-sm { margin: 2px; }
        .modal-header { background-color: #4f46e5; color: white; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-cogs"></i> Gerenciar Usuários e Empresas</h2>

        <?php if ($erro): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?= $erro ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($sucesso): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?= $sucesso ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Empresas -->
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-building"></i> Empresas</h4>
            </div>
            <div class="card-body">
                <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#empresaModal">
                    <i class="fas fa-plus"></i> Nova Empresa
                </button>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Domínio</th>
                                <th>Data Cadastro</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($empresas as $empresa): ?>
                                <tr>
                                    <td><?= $empresa['id'] ?></td>
                                    <td><?= sanitize($empresa['nome']) ?></td>
                                    <td><?= sanitize($empresa['dominio']) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($empresa['data_cadastro'])) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#empresaModal"
                                            data-id="<?= $empresa['id'] ?>" data-nome="<?= sanitize($empresa['nome']) ?>" 
                                            data-dominio="<?= sanitize($empresa['dominio']) ?>">
                                            <i class="fas fa-edit"></i> Editar
                                        </button>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="acao" value="desativar_empresa">
                                            <input type="hidden" name="empresa_id" value="<?= $empresa['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Desativar empresa?')">
                                                <i class="fas fa-trash"></i> Desativar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Usuários -->
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-users"></i> Usuários</h4>
            </div>
            <div class="card-body">
                <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#usuarioModal">
                    <i class="fas fa-plus"></i> Novo Usuário
                </button>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>E-mail</th>
                                <th>Empresa</th>
                                <th>Nível Acesso</th>
                                <th>Data Cadastro</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td><?= $usuario['id'] ?></td>
                                    <td><?= sanitize($usuario['nome']) ?></td>
                                    <td><?= sanitize($usuario['email']) ?></td>
                                    <td><?= sanitize($usuario['empresa_nome']) ?></td>
                                    <td><?= $usuario['nivel_acesso'] == 2 ? 'Admin' : 'Usuário' ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($usuario['data_cadastro'])) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#usuarioModal"
                                            data-id="<?= $usuario['id'] ?>" data-nome="<?= sanitize($usuario['nome']) ?>" 
                                            data-email="<?= sanitize($usuario['email']) ?>" 
                                            data-nivel="<?= $usuario['nivel_acesso'] ?>" 
                                            data-empresa="<?= $usuario['empresa_id'] ?>">
                                            <i class="fas fa-edit"></i> Editar
                                        </button>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="acao" value="desativar_usuario">
                                            <input type="hidden" name="usuario_id" value="<?= $usuario['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Desativar usuário?')">
                                                <i class="fas fa-trash"></i> Desativar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Empresa -->
    <div class="modal fade" id="empresaModal" tabindex="-1" aria-labelledby="empresaModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="empresaModalLabel">Nova Empresa</h5>
ส่วน:                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="acao" value="empresa">
                        <input type="hidden" name="empresa_id" id="empresa_id">
                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome</label>
                            <input type="text" class="form-control" id="nome" name="nome" required>
                        </div>
                        <div class="mb-3">
                            <label for="dominio" class="form-label">Domínio</label>
                            <input type="text" class="form-control" id="dominio" name="dominio" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Usuário -->
    <div class="modal fade" id="usuarioModal" tabindex="-1" aria-labelledby="usuarioModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="usuarioModalLabel">Novo Usuário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="acao" value="usuario">
                        <input type="hidden" name="usuario_id" id="usuario_id">
                        <div class="mb-3">
                            <label for="nome_usuario" class="form-label">Nome</label>
                            <input type="text" class="form-control" id="nome_usuario" name="nome" required>
                        </div>
                        <div class="mb-3">
                            <label for="email_usuario" class="form-label">E-mail</label>
                            <input type="email" class="form-control" id="email_usuario" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="senha_usuario" class="form-label">Senha</label>
                            <input type="password" class="form-control" id="senha_usuario" name="senha">
                        </div>
                        <div class="mb-3">
                            <label for="nivel_acesso" class="form-label">Nível de Acesso</label>
                            <select class="form-select" id="nivel_acesso" name="nivel_acesso" required>
                                <option value="1">Usuário</option>
                                <option value="2">Administrador</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="empresa_usuario" class="form-label">Empresa</label>
                            <select class="form-select" id="empresa_usuario" name="empresa_id" required>
                                <?php foreach ($empresas as $empresa): ?>
                                    <option value="<?= $empresa['id'] ?>"><?= sanitize($empresa['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Preencher modais para edição
        const empresaModal = document.getElementById('empresaModal');
        empresaModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const modalTitle = empresaModal.querySelector('.modal-title');
            if (button.dataset.id) {
                modalTitle.textContent = 'Editar Empresa';
                empresaModal.querySelector('#empresa_id').value = button.dataset.id;
                empresaModal.querySelector('#nome').value = button.dataset.nome;
                empresaModal.querySelector('#dominio').value = button.dataset.dominio;
            } else {
                modalTitle.textContent = 'Nova Empresa';
                empresaModal.querySelector('#empresa_id').value = '';
                empresaModal.querySelector('#nome').value = '';
                empresaModal.querySelector('#dominio').value = '';
            }
        });

        const usuarioModal = document.getElementById('usuarioModal');
        usuarioModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const modalTitle = usuarioModal.querySelector('.modal-title');
            if (button.dataset.id) {
                modalTitle.textContent = 'Editar Usuário';
                usuarioModal.querySelector('#usuario_id').value = button.dataset.id;
                usuarioModal.querySelector('#nome_usuario').value = button.dataset.nome;
                usuarioModal.querySelector('#email_usuario').value = button.dataset.email;
                usuarioModal.querySelector('#nivel_acesso').value = button.dataset.nivel;
                usuarioModal.querySelector('#senha_usuario').placeholder = 'Deixe em branco para manter a senha atual';
                usuarioModal.querySelector('#empresa_usuario').value = button.dataset.empresa;
            } else {
                modalTitle.textContent = 'Novo Usuário';
                usuarioModal.querySelector('#usuario_id').value = '';
                usuarioModal.querySelector('#nome_usuario').value = '';
                usuarioModal.querySelector('#email_usuario').value = '';
                usuarioModal.querySelector('#senha_usuario').value = '';
                usuarioModal.querySelector('#senha_usuario').placeholder = 'Digite a senha';
                usuarioModal.querySelector('#nivel_acesso').value = '1';
                usuarioModal.querySelector('#empresa_usuario').value = '';
            }
        });
    </script>
</body>
</html>

<?php require_once '../includes/footer.php'; ?>