<?php
require_once '../config/database.php';
require_once '../includes/header.php';

// Inicializa a conexão PDO
$db = new Database();
$pdo = $db->connect();

// Iniciação segura da sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificação robusta de autenticação
if (empty($_SESSION['logado']) || empty($_SESSION['empresa_id'])) {
    header('Location: ../includes/login.php');
    exit;
}

$empresa_id = (int)$_SESSION['empresa_id'];

// Verifica se o ID do funcionário foi passado
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: funcionarios.php');
    exit;
}

$funcionario_id = (int)$_GET['id'];

try {
    // Busca os dados do funcionário, verificando a empresa
    $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$funcionario_id, $empresa_id]);
    $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$funcionario) {
        header('Location: funcionarios.php');
        exit;
    }

    // Busca as avaliações do funcionário
    $stmt = $pdo->prepare("SELECT * FROM avaliacoes WHERE funcionario_id = ? ORDER BY data_avaliacao DESC");
    $stmt->execute([$funcionario_id]);
    $avaliacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcula estatísticas
    $total_avaliacoes = count($avaliacoes);
    $soma_avaliacoes = 0;
    $contagem_notas = [1 => 0, 2 => 0, 3 => 0, 4 => 0]; // Ajustado para escala de 1 a 4

    foreach ($avaliacoes as $av) {
        $soma_avaliacoes += $av['avaliacao'];
        $contagem_notas[$av['avaliacao']]++;
    }

    $media_avaliacoes = $total_avaliacoes > 0 ? round($soma_avaliacoes / $total_avaliacoes, 2) : 0;
} catch (PDOException $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}

// Só inclui o header se não for impressão
if (!isset($_GET['print'])) {
    require_once '../includes/header.php';
}
?>

<style>
    /* Estilos gerais */
    .report-container {
        max-width: 1200px;
        margin: 2rem auto;
        padding: 2rem;
        background: white;
        border-radius: 16px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.1);
    }
    
    /* Estilos para impressão */
    @media print {
        body * {
            visibility: hidden;
        }
        .report-container, .report-container * {
            visibility: visible;
        }
        .report-container {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            box-shadow: none;
            border-radius: 0;
            padding: 20px;
        }
        .no-print {
            display: none !important;
        }
        .page-break {
            page-break-after: always;
        }
    }

    .header-section {
        display: flex;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid #eee;
    }
    
    .funcionario-img {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 50%;
        margin-right: 2rem;
        border: 4px solid #fff;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    .stats-section {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background: #f8f9fa;
        padding: 1.5rem;
        border-radius: 12px;
        text-align: center;
    }
    
    .stat-value {
        font-size: 2.5rem;
        font-weight: 600;
        margin: 0.5rem 0;
        color: #4facfe;
    }
    
    .rating-distribution {
        display: flex;
        align-items: center;
        margin-bottom: 1rem;
    }
    
    .rating-star {
        width: 100px;
        font-weight: 600;
        color: #ffc107;
    }
    
    .rating-bar-container {
        flex-grow: 1;
        height: 20px;
        background: #f0f0f0;
        border-radius: 10px;
        margin: 0 1rem;
        overflow: hidden;
    }
    
    .rating-bar {
        height: 100%;
        background: #4facfe;
        border-radius: 10px;
    }
    
    .review-card {
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        border-radius: 12px;
        background: #f8f9fa;
        border-left: 4px solid #4facfe;
    }
    
    .review-rating {
        color: #ffc107;
        font-weight: 600;
    }
    
    .no-comment {
        color: #999;
        font-style: italic;
    }
</style>

<div class="report-container">
    <a href="funcionarios.php" class="btn-back no-print" style="background: #4facfe; color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; display: inline-block; margin-bottom: 1.5rem;">Voltar para Funcionários</a>
    <button onclick="window.print()" class="btn-print no-print" style="background: #6c757d; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 6px; cursor: pointer; margin-bottom: 1.5rem;">Imprimir Relatório</button>
    
    <div class="header-section">
        <?php if ($funcionario['foto']): ?>
            <img src="../assets/images/funcionarios/<?= htmlspecialchars($funcionario['foto']) ?>" class="funcionario-img">
        <?php else: ?>
            <div class="funcionario-img" style="background: #f0f0f0;"></div>
        <?php endif; ?>
        
        <div class="funcionario-info">
            <h1 style="margin: 0; font-size: 2rem; color: #333;"><?= htmlspecialchars($funcionario['nome']) ?></h1>
            <p style="margin: 0.5rem 0 0; color: #666;"><strong>Setor:</strong> <?= htmlspecialchars($funcionario['setor']) ?></p>
            <p style="margin: 0.5rem 0 0; color: #666;"><strong>Status:</strong> <span style="color: <?= $funcionario['ativo'] ? '#28a745' : '#dc3545' ?>"><?= $funcionario['ativo'] ? 'Ativo' : 'Inativo' ?></span></p>
        </div>
    </div>
    
    <div class="stats-section">
        <div class="stat-card">
            <div style="font-size: 1rem; color: #666;">Total de Avaliações</div>
            <div class="stat-value"><?= $total_avaliacoes ?></div>
        </div>
        
        <div class="stat-card">
            <div style="font-size: 1rem; color: #666;">Média de Avaliações</div>
            <div class="stat-value"><?= $media_avaliacoes ?></div>
        </div>
        
        <div class="stat-card">
            <div style="font-size: 1rem; color: #666;">Última Avaliação</div>
            <div class="stat-value">
                <?= $total_avaliacoes > 0 ? $avaliacoes[0]['avaliacao'] : 'N/A' ?>
            </div>
        </div>
    </div>
    
    <h2>Distribuição das Notas</h2>
    <?php for ($i = 4; $i >= 1; $i--): ?>
        <div class="rating-distribution">
            <div class="rating-star"><?= str_repeat('★', $i) ?></div>
            <div class="rating-bar-container">
                <div class="rating-bar" style="width: <?= $total_avaliacoes > 0 ? ($contagem_notas[$i] / $total_avaliacoes) * 100 : 0 ?>%"></div>
            </div>
            <div class="rating-count"><?= $contagem_notas[$i] ?> (<?= $total_avaliacoes > 0 ? round(($contagem_notas[$i] / $total_avaliacoes) * 100, 1) : 0 ?>%)</div>
        </div>
    <?php endfor; ?>
    
    <div style="margin-top: 2rem;">
        <h2>Avaliações Recentes</h2>
        
        <?php if ($total_avaliacoes > 0): ?>
            <?php foreach ($avaliacoes as $avaliacao): ?>
                <div class="review-card">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <div class="review-rating"><?= str_repeat('★', $avaliacao['avaliacao']) ?></div>
                        <div style="color: #666; font-size: 0.875rem;"><?= date('d/m/Y H:i', strtotime($avaliacao['data_avaliacao'])) ?></div>
                    </div>
                    <div style="margin-top: 0.5rem; color: #333; <?= empty($avaliacao['comentario']) ? 'color: #999; font-style: italic;' : '' ?>">
                        <?= !empty($avaliacao['comentario']) ? htmlspecialchars($avaliacao['comentario']) : 'Nenhum comentário foi deixado.' ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="review-card">
                <div style="color: #999; font-style: italic;">Este funcionário ainda não recebeu avaliações.</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
// Só inclui o footer se não for impressão
if (!isset($_GET['print'])) {
    require_once '../includes/footer.php';
}
?>