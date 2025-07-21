<?php
// Inicia a sessão para verificar se o usuário está logado
session_start();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Controle</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 100%;
            text-align: center;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .nav-links {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .nav-links a {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        .nav-links a:hover {
            background-color: #0056b3;
        }
        .logout {
            background-color: #dc3545;
        }
        .logout:hover {
            background-color: #c82333;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Painel de Controle</h1>
        <div class="nav-links">
            <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                <!-- Links disponíveis para usuários logados -->
                <a href="admin/dashboard.php">Dashboard Admin</a>
                <a href="admin/funcionarios.php">Funcionários</a>
                <a href="admin/relatorios.php">Relatórios</a>
                <a href="admin/perfil.php">Perfil</a>
                <a href="includes/logout.php" class="logout">Sair</a>
            <?php else: ?>
                <!-- Link para login se o usuário não estiver logado -->
                <a href="includes/login.php">Fazer Login</a>
				<a href="includes/register.php">Registrar-se</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>