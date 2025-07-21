<?php
/**
 * Ponto de entrada principal do sistema
 * 
 * Redireciona para:
 * - Painel administrativo se o usuário estiver logado
 * - Tela de avaliação (screen1.php) se acessado por cliente
 * - Tela de login se tentar acessar área administrativa sem permissão
 */

// Inicia a sessão
session_start();

// Configurações básicas
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']));

// Verifica se é uma tentativa de acesso à área admin
$isAdminPath = strpos($_SERVER['REQUEST_URI'], '/admin/') !== false;

// Verifica o status de login
if (isset($_SESSION['logado']) && $_SESSION['logado'] === true) {
    // Usuário logado - redireciona para o dashboard
    if ($isAdminPath) {
        // Permanece na área admin se já estiver lá
        return;
    }
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
} elseif ($isAdminPath) {
    // Tentativa de acesso à área admin sem login
    header('Location: ' . BASE_URL . '/includes/login.php');
    exit;
} else {
    // Acesso público - inicia o fluxo de avaliação
    header('Location: ' . BASE_URL . '/screens/screen1.php');
    exit;
}