<?php
// Inclui os arquivos necessários
require_once '../config/database.php';
require_once '../includes/header.php';

// Inicializa a conexão PDO
$db = new Database();
$pdo = $db->connect();

// Verifica se a sessão não está ativa antes de iniciar
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificação de autenticação e permissões
if (empty($_SESSION['logado'])) {
    header('Location: ../includes/login.php');
    exit;
}
// Obtém o ID da empresa do usuário logado
$empresa_id = (int)$_SESSION['empresa_id'];

// Verifica se o usuário tem permissão de administrador
if ($_SESSION['nivel_acesso'] != 2) {
    header('Location: dashboard.php');
    exit;
}

// Operações CRUD
$mensagem = '';
$funcionario = ['id' => '', 'nome' => '', 'foto' => '', 'setor' => '', 'ativo' => 1, 'empresa_id' => $empresa_id];

// Criar ou Atualizar funcionário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $nome = trim($_POST['nome']);
    $setor = trim($_POST['setor']);
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    
    // Upload da foto
    $foto = $_POST['foto_atual'] ?? '';
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == UPLOAD_ERR_OK) {
        $diretorio = '../assets/images/funcionarios/';
        
        // Criar diretório se não existir
        if (!file_exists($diretorio)) {
            mkdir($diretorio, 0777, true);
        }
        
        $extensao = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $nome_arquivo = uniqid() . '.' . $extensao;
        $caminho_completo = $diretorio . $nome_arquivo;
        
        // Verificar se é uma imagem válida
        $check = getimagesize($_FILES['foto']['tmp_name']);
        if ($check !== false) {
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $caminho_completo)) {
                // Remove a foto antiga se existir
                if (!empty($foto) && file_exists($diretorio . $foto)) {
                    unlink($diretorio . $foto);
                }
                $foto = $nome_arquivo;
            } else {
                $mensagem = 'Erro ao salvar a imagem. Verifique as permissões do diretório.';
            }
        } else {
            $mensagem = 'O arquivo enviado não é uma imagem válida.';
        }
    }
    
    try {
        if ($id > 0) {
            // Verifica se o funcionário pertence à empresa antes de atualizar
            $stmt = $pdo->prepare("SELECT id FROM funcionarios WHERE id = ? AND empresa_id = ?");
            $stmt->execute([$id, $empresa_id]);
            if ($stmt->rowCount() === 0) {
                throw new Exception("Funcionário não encontrado ou não pertence à sua empresa.");
            }
            
            // Atualizar
            $sql = "UPDATE funcionarios SET nome = ?, foto = ?, setor = ?, ativo = ? WHERE id = ? AND empresa_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nome, $foto, $setor, $ativo, $id, $empresa_id]);
            $mensagem = 'Funcionário atualizado com sucesso!';
        } else {
            // Criar
            $sql = "INSERT INTO funcionarios (nome, foto, setor, ativo, empresa_id, data_cadastro) VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nome, $foto, $setor, $ativo, $empresa_id]);
            $mensagem = 'Funcionário cadastrado com sucesso!';
        }
    } catch (PDOException $e) {
        $mensagem = 'Erro ao salvar funcionário: ' . $e->getMessage();
    } catch (Exception $e) {
        $mensagem = $e->getMessage();
    }
}

// Deletar funcionário
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    try {
        // Verifica se o funcionário pertence à empresa antes de deletar
        $stmt = $pdo->prepare("SELECT foto FROM funcionarios WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$id, $empresa_id]);
        $foto = $stmt->fetchColumn();
        
        if ($foto === false) {
            throw new Exception("Funcionário não encontrado ou não pertence à sua empresa.");
        }
        
        // Depois deleta o registro
        $stmt = $pdo->prepare("DELETE FROM funcionarios WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$id, $empresa_id]);
        
        // Remove o arquivo da foto se existir
        if ($foto) {
            $diretorio = '../assets/images/funcionarios/';
            if (file_exists($diretorio . $foto)) {
                unlink($diretorio . $foto);
            }
        }
        
        $mensagem = 'Funcionário removido com sucesso!';
    } catch (PDOException $e) {
        $mensagem = 'Erro ao remover funcionário: ' . $e->getMessage();
    } catch (Exception $e) {
        $mensagem = $e->getMessage();
    }
}

// Editar funcionário - carrega os dados
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$id, $empresa_id]);
        $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$funcionario) {
            $mensagem = 'Funcionário não encontrado ou não pertence à sua empresa.';
            $funcionario = ['id' => '', 'nome' => '', 'foto' => '', 'setor' => '', 'ativo' => 1, 'empresa_id' => $empresa_id];
        }
    } catch (PDOException $e) {
        $mensagem = 'Erro ao carregar funcionário: ' . $e->getMessage();
    }
}

// Listar todos os funcionários da empresa
try {
    $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE empresa_id = ? ORDER BY ativo DESC, nome");
    $stmt->execute([$empresa_id]);
    $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensagem = 'Erro ao listar funcionários: ' . $e->getMessage();
    $funcionarios = [];
}
?>

<style>
    .admin-container {
        max-width: 1200px;
        margin: 2rem auto;
        padding: 2rem;
        background: white;
        border-radius: 16px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.1);
    }
    
    .form-section {
        background: #f8f9fa;
        padding: 2rem;
        border-radius: 12px;
        margin-bottom: 2rem;
    }
    
    .table-section {
        overflow-x: auto;
    }
    
    .table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .table th, .table td {
        padding: 1rem;
        text-align: left;
        border-bottom: 1px solid #dee2e6;
    }
    
    .table th {
        background: #f8f9fa;
        font-weight: 600;
    }
    
    .table tr:hover {
        background: #f8f9fa;
    }
    
    .status-active {
        color: #28a745;
        font-weight: 500;
    }
    
    .status-inactive {
        color: #dc3545;
        font-weight: 500;
    }
    
    .funcionario-img {
        width: 50px;
        height: 50px;
        object-fit: cover;
        border-radius: 50%;
    }
    
    .btn-action {
        padding: 0.5rem 1rem;
        border-radius: 6px;
        font-size: 0.875rem;
        margin-right: 0.5rem;
    }
    
    .btn-edit {
        background: #4facfe;
        color: white;
        border: none;
    }
    
    .btn-delete {
        background: #dc3545;
        color: white;
        border: none;
    }
    
    .btn-new {
        background: #28a745;
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        margin-bottom: 1.5rem;
    }
    
    .btn-submit {
        background: #4facfe;
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        margin-top: 1rem;
    }
    
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    .form-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
    }
    
    .form-control {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #ced4da;
        border-radius: 6px;
        font-size: 1rem;
    }
    
    .form-control:focus {
        border-color: #4facfe;
        box-shadow: 0 0 0 3px rgba(79, 172, 254, 0.2);
        outline: none;
    }
    
    .form-check {
        display: flex;
        align-items: center;
    }
    
    .form-check-input {
        margin-right: 0.5rem;
    }
    
    .alert {
        padding: 1rem;
        border-radius: 6px;
        margin-bottom: 1.5rem;
    }
    
    .alert-success {
        background: #d4edda;
        color: #155724;
    }
    
    .alert-danger {
        background: #f8d7da;
        color: #721c24;
    }
    
    .img-preview {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 50%;
        margin-bottom: 1rem;
        border: 3px solid #fff;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
</style>

<div class="admin-container">
    <h1>Gerenciar Funcionários</h1>
    
    <?php if ($mensagem): ?>
        <div class="alert <?= strpos($mensagem, 'sucesso') !== false ? 'alert-success' : 'alert-danger' ?>">
            <?= $mensagem ?>
        </div>
    <?php endif; ?>
    
    <div class="form-section">
        <h2><?= $funcionario['id'] ? 'Editar' : 'Adicionar' ?> Funcionário</h2>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= $funcionario['id'] ?>">
            <input type="hidden" name="foto_atual" value="<?= $funcionario['foto'] ?>">
            
            <div class="form-group">
                <label class="form-label">Nome</label>
                <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($funcionario['nome']) ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Setor</label>
                <input type="text" name="setor" class="form-control" value="<?= htmlspecialchars($funcionario['setor']) ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Foto</label>
                <?php if ($funcionario['foto']): ?>
                    <div>
                        <img src="../assets/images/funcionarios/<?= $funcionario['foto'] ?>" class="img-preview" id="imgPreview">
                    </div>
                <?php else: ?>
                    <div>
                        <img src="data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==" class="img-preview" id="imgPreview">
                    </div>
                <?php endif; ?>
                <input type="file" name="foto" class="form-control" accept="image/*" onchange="previewImage(this)">
            </div>
            
            <div class="form-check">
                <input type="checkbox" name="ativo" class="form-check-input" id="ativo" <?= $funcionario['ativo'] ? 'checked' : '' ?>>
                <label class="form-label" for="ativo">Ativo</label>
            </div>
            
            <button type="submit" class="btn btn-submit">Salvar</button>
            
            <?php if ($funcionario['id']): ?>
                <a href="funcionarios.php" class="btn btn-outline-secondary">Cancelar</a>
            <?php endif; ?>
        </form>
    </div>
    
    <div class="table-section">
        <h2>Lista de Funcionários</h2>
        
        <table class="table">
            <thead>
                <tr>
                    <th>Foto</th>
                    <th>Nome</th>
                    <th>Setor</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($funcionarios as $f): ?>
                <tr>
                    <td>
                        <?php if ($f['foto']): ?>
                            <img src="../assets/images/funcionarios/<?= $f['foto'] ?>" class="funcionario-img">
                        <?php else: ?>
                            <div class="funcionario-img" style="background: #f0f0f0;"></div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($f['nome']) ?></td>
                    <td><?= htmlspecialchars($f['setor']) ?></td>
                    <td>
                        <span class="<?= $f['ativo'] ? 'status-active' : 'status-inactive' ?>">
                            <?= $f['ativo'] ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </td>
                    <td>
                        <a href="funcionarios.php?edit=<?= $f['id'] ?>" class="btn btn-action btn-edit">Editar</a>
                        <a href="funcionarios.php?delete=<?= $f['id'] ?>" class="btn btn-action btn-delete" onclick="return confirm('Tem certeza que deseja excluir este funcionário?')">Excluir</a>
                        <a href="relatorio_funcionario.php?id=<?= $f['id'] ?>" class="btn btn-action" style="background: #6c757d; color: white;">Relatório</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Preview da imagem antes de enviar
    function previewImage(input) {
        const preview = document.getElementById('imgPreview');
        const file = input.files[0];
        const reader = new FileReader();
        
        reader.onloadend = function() {
            preview.src = reader.result;
        }
        
        if (file) {
            reader.readAsDataURL(file);
        } else {
            preview.src = "data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==";
        }
    }
    
    // Confirmação antes de deletar
    function confirmDelete() {
        return confirm('Tem certeza que deseja excluir este funcionário?');
    }
</script>

<?php include '../includes/footer.php'; ?>