<?php
// admin/dashboard.php

// 1. Configuração de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// 2. Controle de sessão seguro
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_secure'   => true,
        'cookie_httponly' => true,
        'use_strict_mode' => true
    ]);
}

// 3. Verificação de login e tenant
if (empty($_SESSION['logado']) || $_SESSION['logado'] !== true || empty($_SESSION['empresa_id'])) {
    header('Location: ../index.php');
    exit;
}

$empresa_id = (int)$_SESSION['empresa_id'];
$usuario_id = (int)$_SESSION['usuario_id'];

// 4. Definição da BASE_URL se não existir
if (!defined('BASE_URL')) {
    $base = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'];
    $path = str_replace('/admin/dashboard.php', '', $_SERVER['SCRIPT_NAME']);
    define('BASE_URL', rtrim($base . $path, '/'));
}

// 5. Conexão com banco de dados
require_once __DIR__ . '/../config/database.php';
$db = new Database();
$pdo = $db->connect();

// 6. Buscar dados com tratamento completo e filtro por tenant
$dados = [
    'total' => 0,
    'media' => '0.0',
    'avaliacoes' => [],
    'erro' => null,
    'funcionarios' => [],
    'info_empresa' => [],
    'avaliacoes_por_dia' => [],
    'media_por_funcionario' => []
];

try {
    // Informações da empresa
    $stmt = $pdo->prepare("SELECT nome, dominio FROM empresas WHERE id = :empresa_id");
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();
    $dados['info_empresa'] = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dados['info_empresa']) {
        throw new Exception("Empresa não encontrada no banco de dados");
    }

    // Total de avaliações da empresa (consulta corrigida)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM avaliacoes a
        LEFT JOIN funcionarios f ON a.funcionario_id = f.id
        WHERE (f.empresa_id = :empresa_id OR a.funcionario_id IS NULL)
    ");
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();
    $dados['total'] = (int)$stmt->fetchColumn();
    
    // Média da empresa (consulta corrigida)
    $stmt = $pdo->prepare("
        SELECT IFNULL(AVG(a.avaliacao), 0) as media 
        FROM avaliacoes a
        LEFT JOIN funcionarios f ON a.funcionario_id = f.id
        WHERE (f.empresa_id = :empresa_id OR a.funcionario_id IS NULL)
    ");
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();
    $dados['media'] = number_format((float)$stmt->fetchColumn(), 1);
    
    // Últimas avaliações da empresa (consulta corrigida)
    $sql_avaliacoes = "
        SELECT 
            a.*, 
            IFNULL(f.nome, 'Atendente não informado') as funcionario_nome,
            IFNULL(f.foto, 'default.jpg') as funcionario_foto
        FROM avaliacoes a
        LEFT JOIN funcionarios f ON a.funcionario_id = f.id AND f.empresa_id = :empresa_id
        WHERE (f.empresa_id = :empresa_id OR a.funcionario_id IS NULL)
    ";

    if (!empty($_GET['funcionario_id'])) {
        $funcionario_id = (int)$_GET['funcionario_id'];
        $sql_avaliacoes .= " AND a.funcionario_id = :funcionario_id";
    }

    $sql_avaliacoes .= " ORDER BY a.data_avaliacao DESC LIMIT 5";

    $stmt = $pdo->prepare($sql_avaliacoes);
    $stmt->bindParam(':empresa_id', $empresa_id);
    
    if (!empty($_GET['funcionario_id'])) {
        $stmt->bindParam(':funcionario_id', $funcionario_id);
    }
    
    $stmt->execute();
    $dados['avaliacoes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Lista de funcionários para filtro
    $stmt = $pdo->prepare("SELECT id, nome FROM funcionarios WHERE empresa_id = :empresa_id AND ativo = 1 ORDER BY nome");
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();
    $dados['funcionarios'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Avaliações por dia (últimos 7 dias) para gráfico
    $stmt = $pdo->prepare("
        SELECT 
            DATE(a.data_avaliacao) as dia,
            COUNT(*) as total,
            IFNULL(AVG(a.avaliacao), 0) as media
        FROM avaliacoes a
        LEFT JOIN funcionarios f ON a.funcionario_id = f.id AND f.empresa_id = :empresa_id
        WHERE (f.empresa_id = :empresa_id OR a.funcionario_id IS NULL)
        AND a.data_avaliacao >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(a.data_avaliacao)
        ORDER BY dia ASC
    ");
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();
    $dados['avaliacoes_por_dia'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Média por funcionário
    $stmt = $pdo->prepare("
        SELECT 
            f.id,
            f.nome,
            f.foto,
            IFNULL(AVG(a.avaliacao), 0) as media,
            COUNT(a.id) as total_avaliacoes
        FROM funcionarios f
        LEFT JOIN avaliacoes a ON f.id = a.funcionario_id
        WHERE f.empresa_id = :empresa_id AND f.ativo = 1
        GROUP BY f.id, f.nome, f.foto
        HAVING total_avaliacoes > 0
        ORDER BY media DESC
        LIMIT 5
    ");
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();
    $dados['media_por_funcionario'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $dados['erro'] = "Erro ao carregar dados do sistema: " . $e->getMessage();
    error_log("ERRO Dashboard: " . $e->getMessage());
} catch (Exception $e) {
    $dados['erro'] = $e->getMessage();
}

// 7. Incluir header
require_once __DIR__ . '/../includes/header.php';
?>

<!-- 8. Conteúdo principal -->
<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>
            <i class="fas fa-tachometer-alt me-2"></i>
            Dashboard de Avaliações
            <small class="text-muted"><?= htmlspecialchars($dados['info_empresa']['nome'] ?? '') ?></small>
        </h1>
        <div>
            <a href="relatorios.php" class="btn btn-outline-primary me-2">
                <i class="fas fa-chart-bar me-1"></i> Relatórios
            </a>
            <a href="funcionarios.php" class="btn btn-outline-secondary">
                <i class="fas fa-users me-1"></i> Funcionários
            </a>
        </div>
    </div>

    <?php if ($dados['erro']): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?= htmlspecialchars($dados['erro']) ?>
            <a href="javascript:location.reload()" class="float-end">
                <i class="fas fa-sync-alt"></i> Tentar novamente
            </a>
        </div>
    <?php endif; ?>

    <div class="row mb-4">
        <!-- Card Total de Avaliações -->
        <div class="col-md-4 mb-3">
            <div class="card h-100 border-primary">
                <div class="card-body text-center">
                    <h5 class="card-title text-primary">
                        <i class="fas fa-list-alt me-2"></i>Total de Avaliações
                    </h5>
                    <p class="display-4 text-primary"><?= number_format($dados['total'], 0, ',', '.') ?></p>
                    <p class="text-muted">Desde o início</p>
                </div>
            </div>
        </div>
        
        <!-- Card Média Geral -->
        <div class="col-md-4 mb-3">
            <div class="card h-100 border-success">
                <div class="card-body text-center">
                    <h5 class="card-title text-success">
                        <i class="fas fa-star me-2"></i>Média Geral
                    </h5>
                    <p class="display-4 text-success"><?= $dados['media'] ?></p>
                    <div class="d-flex justify-content-center">
                        <?php
                        $media = (float)$dados['media'];
                        $stars = min(4, max(1, round($media)));
                        for ($i = 1; $i <= 4; $i++): ?>
                            <i class="fas fa-star <?= $i <= $stars ? 'text-warning' : 'text-secondary' ?>"></i>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Card Link de Avaliação -->
        <div class="col-md-4 mb-3">
            <div class="card h-100 border-info">
                <div class="card-body text-center">
                    <h5 class="card-title text-info">
                        <i class="fas fa-link me-2"></i>Seu Link de Avaliação
                    </h5>
                    <div class="input-group mb-3">
                        <input type="text" id="linkAvaliacao" class="form-control" 
                               value="<?= BASE_URL ?>/screens/screen1.php?tenant_id=<?= $empresa_id ?>" readonly>
                        <button class="btn btn-info" onclick="copiarLink()">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <small class="text-muted">Compartilhe este link para coletar avaliações</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráfico e Filtros -->
    <div class="row mb-4">
        <div class="col-md-8 mb-3">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line me-2"></i>Desempenho Semanal
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="graficoAvaliacoes" height="250"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-3">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-filter me-2"></i>Filtrar Dados
                    </h5>
                </div>
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Período</label>
                            <select name="periodo" class="form-select">
                                <option value="7">Últimos 7 dias</option>
                                <option value="15">Últimos 15 dias</option>
                                <option value="30">Últimos 30 dias</option>
                                <option value="0">Todo o período</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Atendente</label>
                            <select name="funcionario_id" class="form-select">
                                <option value="">Todos os atendentes</option>
                                <?php foreach ($dados['funcionarios'] as $funcionario): ?>
                                    <option value="<?= $funcionario['id'] ?>" <?= isset($_GET['funcionario_id']) && $_GET['funcionario_id'] == $funcionario['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($funcionario['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12 mt-4">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-1"></i> Aplicar Filtros
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Melhores Atendentes -->
    <?php if (!empty($dados['media_por_funcionario'])): ?>
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">
                <i class="fas fa-trophy me-2"></i>Melhores Atendentes
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($dados['media_por_funcionario'] as $funcionario): ?>
                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <img src="../assets/images/funcionarios/<?= htmlspecialchars($funcionario['foto']) ?>" 
                                 class="rounded-circle mb-3" width="100" height="100" 
                                 alt="<?= htmlspecialchars($funcionario['nome']) ?>"
                                 onerror="this.src='../assets/images/funcionarios/default.jpg'">
                            <h5><?= htmlspecialchars($funcionario['nome']) ?></h5>
                            <div class="mb-2">
                                <?php
                                $stars = min(4, max(1, round($funcionario['media'])));
                                for ($i = 1; $i <= 4; $i++): ?>
                                    <i class="fas fa-star <?= $i <= $stars ? 'text-warning' : 'text-secondary' ?>"></i>
                                <?php endfor; ?>
                                <span class="ms-2"><?= number_format($funcionario['media'], 1) ?></span>
                            </div>
                            <p class="text-muted"><?= $funcionario['total_avaliacoes'] ?> avaliações</p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Últimas Avaliações -->
    <div class="card">
        <div class="card-header bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i>Últimas Avaliações
                </h5>
                <a href="relatorios.php?filtro=avaliacoes" class="btn btn-sm btn-outline-primary">
                    Ver Todas
                </a>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($dados['avaliacoes'])): ?>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Nenhuma avaliação cadastrada ainda.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Data</th>
                                <th>Atendente</th>
                                <th>Avaliação</th>
                                <th>Comentário</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dados['avaliacoes'] as $avaliacao): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($avaliacao['data_avaliacao'])) ?></td>
                                <td>
                                    <?php if ($avaliacao['funcionario_foto'] !== 'default.jpg'): ?>
                                        <img src="../assets/images/funcionarios/<?= htmlspecialchars($avaliacao['funcionario_foto']) ?>" 
                                             class="rounded-circle me-2" width="30" height="30" 
                                             alt="<?= htmlspecialchars($avaliacao['funcionario_nome']) ?>"
                                             onerror="this.src='../assets/images/funcionarios/default.jpg'">
                                    <?php endif; ?>
                                    <?= htmlspecialchars($avaliacao['funcionario_nome']) ?>
                                </td>
                                <td>
                                    <?php
                                    $emojis = ['😡' => 1, '😐' => 2, '😊' => 3, '😍' => 4];
                                    $emoji = array_search($avaliacao['avaliacao'], $emojis);
                                    echo $emoji ?: 'N/A';
                                    ?>
                                    <small class="text-muted ms-2">(<?= $avaliacao['avaliacao'] ?>/4)</small>
                                </td>
                                <td>
                                    <?php if (!empty($avaliacao['comentario'])): ?>
                                        <button class="btn btn-sm btn-outline-secondary" 
                                                data-bs-toggle="tooltip" 
                                                title="<?= htmlspecialchars($avaliacao['comentario']) ?>">
                                            <i class="fas fa-comment"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">Sem comentário</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Scripts para gráficos e interações -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Copiar link de avaliação
    function copiarLink() {
        const link = document.getElementById('linkAvaliacao');
        link.select();
        link.setSelectionRange(0, 99999);
        document.execCommand('copy');
        
        const btn = event.currentTarget;
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Copiado!';
        
        setTimeout(() => {
            btn.innerHTML = originalHTML;
        }, 2000);
    }
    
    // Gráfico de avaliações por dia
    const ctx = document.getElementById('graficoAvaliacoes').getContext('2d');
    const grafico = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: [<?= implode(',', array_map(function($item) { 
                return "'" . date('d/m', strtotime($item['dia'])) . "'"; 
            }, $dados['avaliacoes_por_dia'])) ?>],
            datasets: [
                {
                    label: 'Número de Avaliações',
                    data: [<?= implode(',', array_column($dados['avaliacoes_por_dia'], 'total')) ?>],
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    label: 'Média Diária',
                    data: [<?= implode(',', array_column($dados['avaliacoes_por_dia'], 'media')) ?>],
                    backgroundColor: 'rgba(255, 99, 132, 0.7)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1,
                    type: 'line',
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Número de Avaliações'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    min: 0,
                    max: 4,
                    title: {
                        display: true,
                        text: 'Média (0-4)'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
    
    // Ativar tooltips
    document.addEventListener('DOMContentLoaded', function() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>

<?php 
// 9. Incluir footer
require_once __DIR__ . '/../includes/footer.php';
?>