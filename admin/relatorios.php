<?php
// Inclui os arquivos necessários
require_once '../config/database.php';
require_once '../includes/header.php';

// Inicializa a conexão PDO
$db = new Database();
$pdo = $db->connect();

// Set the PHP timezone to match the database (UTC)
date_default_timezone_set('UTC');

// Verificar autenticação e permissões
if (session_status() === PHP_SESSION_NONE) session_start();

// Verifica se o usuário está logado e tem empresa_id
if (empty($_SESSION['logado'])) {
    header('Location: ../includes/login.php');
    exit;
}

// Obtém o ID da empresa do usuário logado
$empresa_id = (int)$_SESSION['empresa_id'];

// Verificar nível de acesso
if ($_SESSION['nivel_acesso'] != 2) {
    header('Location: dashboard.php');
    exit;
}

// Definir período padrão e processar filtros
$periodo = $_GET['periodo'] ?? 'custom';

// Definir datas com base no período selecionado
switch ($periodo) {
    case 'semana':
        $data_inicio = date('Y-m-d', strtotime('-1 week'));
        $data_fim = date('Y-m-d 23:59:59');
        break;
    case 'trimestre':
        $data_inicio = date('Y-m-d', strtotime('-3 months'));
        $data_fim = date('Y-m-d 23:59:59');
        break;
    default: // 'custom' como padrão
        $data_inicio = $_GET['data_inicio'] ?? date('Y-m-01', strtotime('-1 month'));
        $data_fim = $_GET['data_fim'] ?? date('Y-m-d');
        $data_inicio .= ' 00:00:00';
        $data_fim .= ' 23:59:59';
        break;
}

$setor = $_GET['setor'] ?? '';

// Consulta para relatório geral
try {
    // Total de avaliações válidas (1-4) para a empresa
    $sql_total = "SELECT COUNT(*) as total 
                 FROM avaliacoes a
                 LEFT JOIN funcionarios f ON a.funcionario_id = f.id
                 WHERE (f.empresa_id = :empresa_id OR a.funcionario_id IS NULL)
                 AND a.data_avaliacao BETWEEN :data_inicio AND :data_fim
                 AND a.avaliacao BETWEEN 1 AND 4";
    $stmt = $pdo->prepare($sql_total);
    $stmt->execute([
        ':empresa_id' => $empresa_id,
        ':data_inicio' => $data_inicio,
        ':data_fim' => $data_fim
    ]);
    $total_avaliacoes = $stmt->fetchColumn();

    // Avaliações por tipo (garantindo todos os tipos 1-4) para a empresa
    $sql_avaliacoes = "SELECT 
                        a.avaliacao, 
                        COUNT(*) as total,
                        (COUNT(*) * 100 / GREATEST(:total_avaliacoes, 1)) as porcentagem
                      FROM avaliacoes a
                      LEFT JOIN funcionarios f ON a.funcionario_id = f.id
                      WHERE (f.empresa_id = :empresa_id OR a.funcionario_id IS NULL)
                      AND a.data_avaliacao BETWEEN :data_inicio AND :data_fim
                      AND a.avaliacao BETWEEN 1 AND 4
                      GROUP BY a.avaliacao
                      ORDER BY a.avaliacao DESC";
    $stmt = $pdo->prepare($sql_avaliacoes);
    $stmt->execute([
        ':total_avaliacoes' => $total_avaliacoes,
        ':empresa_id' => $empresa_id,
        ':data_inicio' => $data_inicio,
        ':data_fim' => $data_fim
    ]);
    $avaliacoes_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Preencher avaliações faltantes (1-4)
    $avaliacoes = [];
    for ($i = 4; $i >= 1; $i--) {
        $found = false;
        foreach ($avaliacoes_raw as $av) {
            if ($av['avaliacao'] == $i) {
                $avaliacoes[] = $av;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $avaliacoes[] = ['avaliacao' => $i, 'total' => 0, 'porcentagem' => 0];
        }
    }

    // Média geral para a empresa
    $sql_media = "SELECT AVG(a.avaliacao) as media
                 FROM avaliacoes a
                 LEFT JOIN funcionarios f ON a.funcionario_id = f.id
                 WHERE (f.empresa_id = :empresa_id OR a.funcionario_id IS NULL)
                 AND a.data_avaliacao BETWEEN :data_inicio AND :data_fim
                 AND a.avaliacao BETWEEN 1 AND 4";
    $stmt = $pdo->prepare($sql_media);
    $stmt->execute([
        ':empresa_id' => $empresa_id,
        ':data_inicio' => $data_inicio,
        ':data_fim' => $data_fim
    ]);
    $media_geral = $stmt->fetchColumn();
    $media_geral = $media_geral ? number_format($media_geral, 1) : '0.0';

    // Top funcionários (com pelo menos 1 avaliação) para a empresa
    $sql_top = "SELECT 
                 f.nome, 
                 f.setor,
                 f.foto,
                 AVG(a.avaliacao) as media,
                 COUNT(a.id) as total_avaliacoes
               FROM avaliacoes a
               JOIN funcionarios f ON a.funcionario_id = f.id
               WHERE f.empresa_id = :empresa_id
               AND a.data_avaliacao BETWEEN :data_inicio AND :data_fim
               AND a.avaliacao BETWEEN 1 AND 4
               " . ($setor ? " AND f.setor = :setor" : "") . "
               GROUP BY a.funcionario_id
               HAVING COUNT(a.id) > 0
               ORDER BY media DESC
               LIMIT 5";
    
    $params = [
        ':empresa_id' => $empresa_id,
        ':data_inicio' => $data_inicio,
        ':data_fim' => $data_fim
    ];
    if ($setor) $params[':setor'] = $setor;
    
    $stmt = $pdo->prepare($sql_top);
    $stmt->execute($params);
    $top_funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Setores disponíveis (com avaliações no período) para a empresa
    $sql_setores = "SELECT DISTINCT f.setor
                   FROM funcionarios f
                   JOIN avaliacoes a ON a.funcionario_id = f.id
                   WHERE f.empresa_id = :empresa_id
                   AND a.data_avaliacao BETWEEN :data_inicio AND :data_fim
                   AND a.avaliacao BETWEEN 1 AND 4
                   ORDER BY f.setor";
    $stmt = $pdo->prepare($sql_setores);
    $stmt->execute([
        ':empresa_id' => $empresa_id,
        ':data_inicio' => $data_inicio,
        ':data_fim' => $data_fim
    ]);
    $setores = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Média por setor (incluindo todos os setores com avaliações) para a empresa
    $sql_media_setor = "SELECT 
                        f.setor,
                        AVG(a.avaliacao) as media,
                        COUNT(a.id) as total_avaliacoes
                      FROM avaliacoes a
                      JOIN funcionarios f ON a.funcionario_id = f.id
                      WHERE f.empresa_id = :empresa_id
                      AND a.data_avaliacao BETWEEN :data_inicio AND :data_fim
                      AND a.avaliacao BETWEEN 1 AND 4
                      GROUP BY f.setor
                      ORDER BY media DESC";
    
    $stmt = $pdo->prepare($sql_media_setor);
    $stmt->execute([
        ':empresa_id' => $empresa_id,
        ':data_inicio' => $data_inicio,
        ':data_fim' => $data_fim
    ]);
    $media_setores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Preparar dados para o gráfico
    $setores_chart = [];
    $medias_chart = [];
    foreach ($media_setores as $setor_data) {
        $setores_chart[] = $setor_data['setor'];
        $medias_chart[] = number_format($setor_data['media'], 1);
    }

    // Avaliações sem funcionário identificado para a empresa
    $sql_sem_funcionario = "SELECT COUNT(*) as total 
                           FROM avaliacoes a
                           LEFT JOIN funcionarios f ON a.funcionario_id = f.id
                           WHERE (f.empresa_id = :empresa_id OR a.funcionario_id IS NULL)
                           AND a.data_avaliacao BETWEEN :data_inicio AND :data_fim
                           AND (a.funcionario_id IS NULL OR a.sem_funcionario = 1)
                           AND a.avaliacao BETWEEN 1 AND 4";
    $stmt = $pdo->prepare($sql_sem_funcionario);
    $stmt->execute([
        ':empresa_id' => $empresa_id,
        ':data_inicio' => $data_inicio,
        ':data_fim' => $data_fim
    ]);
    $avaliacoes_sem_funcionario = $stmt->fetchColumn();

    // Dados para o gráfico de evolução (últimos 7 dias) para a empresa
    $data_7dias_atras = date('Y-m-d', strtotime('-7 days'));
    
    $sql_evolucao = "SELECT 
                     DATE(a.data_avaliacao) as dia,
                     AVG(a.avaliacao) as media
                   FROM avaliacoes a
                   LEFT JOIN funcionarios f ON a.funcionario_id = f.id
                   WHERE (f.empresa_id = :empresa_id OR a.funcionario_id IS NULL)
                   AND a.data_avaliacao BETWEEN :data_inicio AND :data_fim
                   AND a.avaliacao BETWEEN 1 AND 4
                   GROUP BY DATE(a.data_avaliacao)
                   ORDER BY dia";
    
    $stmt = $pdo->prepare($sql_evolucao);
    $stmt->execute([
        ':empresa_id' => $empresa_id,
        ':data_inicio' => $data_7dias_atras,
        ':data_fim' => $data_fim
    ]);
    $dados_evolucao = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Preparar dados para o gráfico de evolução
    $dias_evolucao = [];
    $medias_evolucao = [];
    
    // Criar array com todos os dias do período
    $periodo_dias = new DatePeriod(
        new DateTime($data_7dias_atras),
        new DateInterval('P1D'),
        new DateTime($data_fim)
    );
    
    foreach ($periodo_dias as $dia) {
        $dia_formatado = $dia->format('Y-m-d');
        $dias_evolucao[] = $dia->format('d/m');
        
        // Verificar se há dados para este dia
        $media_dia = null;
        foreach ($dados_evolucao as $dado) {
            if ($dado['dia'] == $dia_formatado) {
                $media_dia = $dado['media'];
                break;
            }
        }
        
        $medias_evolucao[] = $media_dia !== null ? number_format($media_dia, 1) : null;
    }

// Comentários recentes para a empresa
$sql_comentarios = "SELECT a.comentario, a.avaliacao, a.data_avaliacao, f.nome as funcionario
                  FROM avaliacoes a
                  LEFT JOIN funcionarios f ON a.funcionario_id = f.id
                  WHERE a.empresa_id = :empresa_id /* Adicionado o filtro de empresa_id */
                  AND a.data_avaliacao BETWEEN :data_inicio AND :data_fim
                  AND a.comentario IS NOT NULL
                  AND a.comentario != ''
                  ORDER BY a.data_avaliacao DESC
                  LIMIT 5";
$stmt = $pdo->prepare($sql_comentarios);
$stmt->execute([
    ':empresa_id' => $empresa_id, /* Passa o ID da empresa para a query */
    ':data_inicio' => $data_inicio,
    ':data_fim' => $data_fim
]);
$comentarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao gerar relatório: " . $e->getMessage());
}
?>

<style>
    .dashboard-container {
        max-width: 1200px;
        margin: 2rem auto;
        padding: 2rem;
        background: white;
        border-radius: 16px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.1);
    }
    
    .filter-section {
        background: #f8f9fa;
        padding: 1.5rem;
        border-radius: 12px;
        margin-bottom: 2rem;
    }
    
    .card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        margin-bottom: 2rem;
        transition: transform 0.3s ease;
    }
    
    .card:hover {
        transform: translateY(-5px);
    }
    
    .card-header {
        background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%);
        color: white;
        border-radius: 12px 12px 0 0 !important;
        font-weight: 600;
    }
    
    .chart-container {
        height: 300px;
        position: relative;
    }
    
    .funcionario-card {
        display: flex;
        align-items: center;
        padding: 1rem;
        border-bottom: 1px solid #eee;
    }
    
    .funcionario-img {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 1rem;
    }
    
    .rating-badge {
        background: #4facfe;
        color: white;
        border-radius: 20px;
        padding: 0.25rem 0.75rem;
        font-size: 0.875rem;
        font-weight: 600;
    }
    
    .progress {
        height: 10px;
        border-radius: 5px;
    }
    
    .progress-bar {
        background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%);
    }
    
    .emoji-legend {
        display: flex;
        justify-content: space-around;
        margin-top: 1rem;
    }
    
    .emoji-item {
        text-align: center;
    }
    
    .emoji {
        font-size: 1.5rem;
    }
    
    .stats-card {
        text-align: center;
        padding: 1.5rem;
    }
    
    .stats-number {
        font-size: 2.5rem;
        font-weight: 600;
        color: #4361ee;
    }
    
    .stats-label {
        color: #6c757d;
        font-size: 1rem;
    }
    
    .tab-content {
        padding: 1.5rem 0;
    }
    
    .nav-tabs .nav-link {
        color: #495057; /* Cor escura para melhor contraste */
        font-weight: 500;
    }
    
    .nav-tabs .nav-link.active {
        font-weight: 600;
        color: #4361ee; /* Cor azul para aba ativa */
        border-bottom: 3px solid #4361ee;
        background-color: transparent;
    }
    
    .word-cloud {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 8px;
        padding: 1rem;
    }
    
    .word-cloud span {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        background: #f0f0f0;
    }
    
    .nav-link,
    .nav-link:hover,
    .nav-link.active {
        color: black !important;
    } 
</style>

<div class="dashboard-container">
    <h1><i class="fas fa-chart-line"></i> Relatórios de Avaliação</h1>
    
    <div class="filter-section">
        <form method="get" class="row">
            <div class="col-md-2 mb-3">
                <label class="form-label">Período</label>
                <select name="periodo" class="form-control" onchange="this.form.submit()">
                    <option value="semana" <?= $periodo == 'semana' ? 'selected' : '' ?>>Esta Semana</option>
                    <option value="trimestre" <?= $periodo == 'trimestre' ? 'selected' : '' ?>>Este Trimestre</option>
                    <option value="custom" <?= $periodo == 'custom' ? 'selected' : '' ?>>Personalizado</option>
                </select>
            </div>
            <div class="col-md-3 mb-3 <?= $periodo != 'custom' ? 'd-none' : '' ?>">
                <label class="form-label">Data Início</label>
                <input type="date" name="data_inicio" class="form-control" value="<?= substr($data_inicio, 0, 10) ?>">
            </div>
            <div class="col-md-3 mb-3 <?= $periodo != 'custom' ? 'd-none' : '' ?>">
                <label class="form-label">Data Fim</label>
                <input type="date" name="data_fim" class="form-control" value="<?= substr($data_fim, 0, 10) ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Setor</label>
                <select name="setor" class="form-control">
                    <option value="">Todos os setores</option>
                    <?php foreach ($setores as $s): ?>
                        <option value="<?= $s ?>" <?= $setor == $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1 mb-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
            </div>
        </form>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stats-card">
                <div class="stats-number"><?= $total_avaliacoes ?></div>
                <div class="stats-label">Avaliações</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card">
                <div class="stats-number"><?= number_format($media_geral, 1) ?></div>
                <div class="stats-label">Média Geral</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card">
                <div class="stats-number"><?= $avaliacoes_sem_funcionario ?></div>
                <div class="stats-label">Não identificados</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card">
                <div class="stats-number">
                    <?= count($top_funcionarios) > 0 ? number_format($top_funcionarios[0]['media'], 1) : '0.0' ?>
                </div>
                <div class="stats-label">Melhor avaliação</div>
            </div>
        </div>
    </div>
    
    <ul class="nav nav-tabs" id="dashboardTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">Visão Geral</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="evolution-tab" data-bs-toggle="tab" data-bs-target="#evolution" type="button" role="tab">Evolução</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="comments-tab" data-bs-toggle="tab" data-bs-target="#comments" type="button" role="tab">Comentários</button>
        </li>
    </ul>
    
    <div class="tab-content" id="dashboardTabsContent">
        <div class="tab-pane fade show active" id="overview" role="tabpanel">
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            Distribuição das Avaliações
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="avaliacoesChart"></canvas>
                            </div>
                            <div class="emoji-legend">
                                <div class="emoji-item">
                                    <div class="emoji">😍</div>
                                    <div>Excelente</div>
                                </div>
                                <div class="emoji-item">
                                    <div class="emoji">😊</div>
                                    <div>Bom</div>
                                </div>
                                <div class="emoji-item">
                                    <div class="emoji">😐</div>
                                    <div>Regular</div>
                                </div>
                                <div class="emoji-item">
                                    <div class="emoji">😡</div>
                                    <div>Ruim</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            Média por Setor
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="setoresChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    Top 5 Funcionários
                </div>
                <div class="card-body">
                    <?php if (count($top_funcionarios) > 0): ?>
                        <?php foreach ($top_funcionarios as $func): ?>
                            <div class="funcionario-card">
                                <img src="../assets/images/funcionarios/<?= $func['foto'] ?>" class="funcionario-img" alt="<?= $func['nome'] ?>">
                                <div style="flex-grow: 1;">
                                    <h5><?= htmlspecialchars($func['nome']) ?></h5>
                                    <small class="text-muted"><?= htmlspecialchars($func['setor']) ?></small>
                                </div>
                                <div class="text-center">
                                    <div class="rating-badge"><?= number_format($func['media'], 1) ?></div>
                                    <small class="text-muted"><?= $func['total_avaliacoes'] ?> avaliações</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <p>Nenhum dado encontrado no período selecionado.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="tab-pane fade" id="evolution" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    Evolução das Avaliações (Últimos 7 Dias)
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="evolucaoChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="tab-pane fade" id="comments" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    Análise de Comentários
                </div>
                <div class="card-body">
                    <h5>Palavras mais frequentes</h5>
                    <div class="word-cloud">
                        <?php
                        // Simulação de nuvem de palavras (em produção, implementar análise real)
                        $palavras = [
                            'atendimento' => 12, 'bom' => 10, 'rápido' => 8, 'excelente' => 7,
                            'solução' => 6, 'problema' => 5, 'satisfeito' => 4, 'recomendo' => 3
                        ];
                        foreach ($palavras as $palavra => $frequencia) {
                            $tamanho = 12 + ($frequencia * 2);
                            echo "<span style='font-size:{$tamanho}px'>$palavra</span>";
                        }
                        ?>
                    </div>
                    
<div class="mt-4">
                        <h5>Comentários recentes</h5>
                        <?php
                        // Comentários recentes para a empresa
$sql_comentarios = "SELECT a.comentario, a.avaliacao, a.data_avaliacao, f.nome as funcionario
                  FROM avaliacoes a
                  LEFT JOIN funcionarios f ON a.funcionario_id = f.id
                  WHERE (f.empresa_id = :empresa_id OR a.funcionario_id IS NULL) /* Garante que avaliações com funcionário ou sem funcionário sejam da empresa correta */
                  AND a.data_avaliacao BETWEEN :data_inicio AND :data_fim
                  AND a.comentario IS NOT NULL
                  AND a.comentario != ''
                  ORDER BY a.data_avaliacao DESC
                  LIMIT 5";
$stmt = $pdo->prepare($sql_comentarios);
$stmt->execute([
    ':empresa_id' => $empresa_id,
    ':data_inicio' => $data_inicio,
    ':data_fim' => $data_fim
]);
$comentarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (count($comentarios) > 0) {
                            foreach ($comentarios as $comentario) {
                                $emoji = match($comentario['avaliacao']) {
                                    1 => '😡',
                                    2 => '😐',
                                    3 => '😊',
                                    4 => '😍',
                                    default => ''
                                };
                                echo "<div class='card mb-2'>";
                                echo "<div class='card-body'>";
                                echo "<div class='d-flex justify-content-between'>";
                                echo "<span class='me-3'>$emoji</span>";
                                echo "<div class='flex-grow-1'>" . htmlspecialchars($comentario['comentario']) . "</div>";
                                echo "<small class='text-muted text-end'>";
                                echo $comentario['funcionario'] ? htmlspecialchars($comentario['funcionario']) . ' - ' : '';
                                echo date('d/m/Y', strtotime($comentario['data_avaliacao']));
                                echo "</small>";
                                echo "</div>";
                                echo "</div>";
                                echo "</div>";
                            }
                        } else {
                            echo "<p class='text-center py-4'>Nenhum comentário encontrado no período selecionado.</p>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Incluir Chart.js e Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Gráfico de distribuição de avaliações
    const ctx1 = document.getElementById('avaliacoesChart').getContext('2d');
    const avaliacoesChart = new Chart(ctx1, {
        type: 'doughnut',
        data: {
            labels: ['Excelente (4)', 'Bom (3)', 'Regular (2)', 'Ruim (1)'],
            datasets: [{
                data: [
                    <?= isset($avaliacoes[3]) ? $avaliacoes[3]['total'] : 0 ?>,
                    <?= isset($avaliacoes[2]) ? $avaliacoes[2]['total'] : 0 ?>,
                    <?= isset($avaliacoes[1]) ? $avaliacoes[1]['total'] : 0 ?>,
                    <?= isset($avaliacoes[0]) ? $avaliacoes[0]['total'] : 0 ?>
                ],
                backgroundColor: [
                    '#1e90ff',
                    '#2ed573',
                    '#ffa502',
                    '#ff4757'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += context.raw + ' (' + Math.round(context.parsed * 100 / context.dataset.data.reduce((a, b) => a + b, 0)) + '%)';
                            return label;
                        }
                    }
                }
            }
        }
    });

    // Gráfico de média por setor
    const ctx2 = document.getElementById('setoresChart').getContext('2d');
    const setoresChart = new Chart(ctx2, {
        type: 'bar',
        data: {
            labels: <?= json_encode($setores_chart) ?>,
            datasets: [{
                label: 'Média de Avaliação',
                data: <?= json_encode($medias_chart) ?>,
                backgroundColor: 'rgba(79, 172, 254, 0.7)',
                borderColor: 'rgba(79, 172, 254, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: false,
                    min: 1,
                    max: 4
                }
            }
        }
    });

    // Gráfico de evolução
    const ctx3 = document.getElementById('evolucaoChart').getContext('2d');
    const evolucaoChart = new Chart(ctx3, {
        type: 'line',
        data: {
            labels: <?= json_encode($dias_evolucao) ?>,
            datasets: [{
                label: 'Média diária',
                data: <?= json_encode($medias_evolucao) ?>,
                backgroundColor: 'rgba(79, 172, 254, 0.2)',
                borderColor: 'rgba(79, 172, 254, 1)',
                borderWidth: 2,
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: false,
                    min: 1,
                    max: 4
                }
            }
        }
    });

    // Mostrar/ocultar campos de data quando período é customizado
    document.querySelector('select[name="periodo"]').addEventListener('change', function() {
        const isCustom = this.value === 'custom';
        document.querySelectorAll('input[name="data_inicio"], input[name="data_fim"]').forEach(el => {
            el.closest('.col-md-3').classList.toggle('d-none', !isCustom);
        });
    });
</script>

<?php include '../includes/footer.php'; ?>