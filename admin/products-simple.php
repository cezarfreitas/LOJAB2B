<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] !== "admin") {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";
require_once "../config/functions.php";

$success = "";
$error = "";

// Processar formulário
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $db = new Database();
        
        $nome = sanitizeInput($_POST["nome"]);
        $preco = floatval($_POST["preco"]);
        $categoria = sanitizeInput($_POST["categoria"]);
        
        // Criar tabela se não existir
        $db->query("CREATE TABLE IF NOT EXISTS produtos_simples (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(200) NOT NULL,
            preco DECIMAL(10,2) NOT NULL,
            categoria VARCHAR(100),
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        $db->query("INSERT INTO produtos_simples (nome, preco, categoria) VALUES (?, ?, ?)", 
                  [$nome, $preco, $categoria]);
        
        $success = "Produto criado com sucesso!";
    } catch (Exception $e) {
        $error = "Erro: " . $e->getMessage();
    }
}

// Buscar produtos
$produtos = [];
try {
    $db = new Database();
    $stmt = $db->query("SELECT * FROM produtos_simples ORDER BY data_criacao DESC");
    $produtos = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Erro ao buscar produtos: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Produtos - Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; background: #f5f5f5; }
        .header { background: #667eea; color: white; padding: 1rem; border-radius: 10px; margin-bottom: 2rem; }
        .card { background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 1rem; }
        .btn { padding: 0.5rem 1rem; background: #667eea; color: white; text-decoration: none; border-radius: 5px; display: inline-block; margin: 0.25rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
        .form-group input, .form-group select { width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 5px; }
        .alert-success { background: #d4edda; color: #155724; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
        .alert-error { background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Gestão de Produtos (Simples)</h1>
        <a href="dashboard.php" class="btn">← Dashboard</a>
    </div>
    
    <?php if ($success): ?>
        <div class="alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="card">
        <h2>➕ Novo Produto</h2>
        <form method="POST">
            <div class="form-group">
                <label>Nome do Produto:</label>
                <input type="text" name="nome" required placeholder="Ex: Chinelo Conforto Masculino">
            </div>
            <div class="form-group">
                <label>Preço (R$):</label>
                <input type="number" step="0.01" name="preco" required placeholder="0.00">
            </div>
            <div class="form-group">
                <label>Categoria:</label>
                <select name="categoria">
                    <option value="Chinelos Básicos">Chinelos Básicos</option>
                    <option value="Chinelos Conforto">Chinelos Conforto</option>
                    <option value="Chinelos Esportivos">Chinelos Esportivos</option>
                    <option value="Chinelos Luxo">Chinelos Luxo</option>
                </select>
            </div>
            <button type="submit" class="btn">💾 Salvar Produto</button>
        </form>
    </div>
    
    <div class="card">
        <h2>📦 Produtos Cadastrados</h2>
        <?php if (empty($produtos)): ?>
            <p>Nenhum produto cadastrado ainda.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Preço</th>
                        <th>Categoria</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produtos as $produto): ?>
                        <tr>
                            <td><?php echo $produto["id"]; ?></td>
                            <td><?php echo htmlspecialchars($produto["nome"]); ?></td>
                            <td><?php echo formatPrice($produto["preco"]); ?></td>
                            <td><?php echo htmlspecialchars($produto["categoria"]); ?></td>
                            <td><?php echo date("d/m/Y", strtotime($produto["data_criacao"])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>