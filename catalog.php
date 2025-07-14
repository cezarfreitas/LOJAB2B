<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] !== "cliente_b2b") {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Catálogo B2B</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; background: #f5f5f5; }
        .header { background: #2c5aa0; color: white; padding: 1rem; border-radius: 10px; margin-bottom: 2rem; }
        .card { background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 1rem; }
        .btn { padding: 0.5rem 1rem; background: #2c5aa0; color: white; text-decoration: none; border-radius: 5px; display: inline-block; margin: 0.25rem; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Catálogo B2B</h1>
        <p>Bem-vindo, <?php echo htmlspecialchars($_SESSION["user_name"]); ?>!</p>
    </div>
    
    <div class="card">
        <h2>🛒 Sistema em Desenvolvimento</h2>
        <p>O catálogo de produtos está sendo desenvolvido.</p>
        <p><strong>Cliente:</strong> <?php echo htmlspecialchars($_SESSION["client_id"] ?? "Não definido"); ?></p>
        
        <hr style="margin: 1rem 0;">
        
        <a href="debug.php" class="btn">🔧 Debug Sistema</a>
        <a href="index.php" class="btn">🏠 Página Inicial</a>
        <a href="login.php?logout=1" class="btn" style="background: #dc3545;">🚪 Logout</a>
    </div>
    
    <div class="card">
        <h3>🛍️ Funcionalidades Futuras</h3>
        <ul>
            <li>Catálogo de chinelos com filtros</li>
            <li>Variantes de cor e tamanho</li>
            <li>Carrinho de compras</li>
            <li>Sistema de pedidos B2B</li>
            <li>Preços personalizados</li>
            <li>Histórico de compras</li>
        </ul>
    </div>
</body>
</html>