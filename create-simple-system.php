<?php
// create-simple-system.php - Sistema básico funcional
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🛠️ Criando Sistema Básico</h1>";

// Criar pastas necessárias
$pastas = ['admin', 'config', 'uploads', 'uploads/products'];
foreach ($pastas as $pasta) {
    if (!is_dir($pasta)) {
        mkdir($pasta, 0755, true);
        echo "<p>✅ Pasta {$pasta}/ criada</p>";
    } else {
        echo "<p>ℹ️ Pasta {$pasta}/ já existe</p>";
    }
}

// Criar database.php simples
$databaseContent = '<?php
class Database {
    private $host = "localhost";
    private $dbname = "catalogo_chinelos";
    private $username = "root";
    private $password = "";
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8", 
                $this->username, 
                $this->password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            die("Erro de conexão: " . $e->getMessage());
        }
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function getLastInsertId() {
        return $this->pdo->lastInsertId();
    }
}
?>';

if (!file_exists('config/database.php')) {
    file_put_contents('config/database.php', $databaseContent);
    echo "<p>✅ config/database.php criado</p>";
}

// Criar functions.php simples
$functionsContent = '<?php
function formatPrice($price) {
    return "R$ " . number_format(floatval($price), 2, ",", ".");
}

function sanitizeInput($input) {
    return trim(htmlspecialchars($input, ENT_QUOTES, "UTF-8"));
}
?>';

if (!file_exists('config/functions.php')) {
    file_put_contents('config/functions.php', $functionsContent);
    echo "<p>✅ config/functions.php criado</p>";
}

// Criar admin/dashboard.php simples
$dashboardContent = '<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] !== "admin") {
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; background: #f5f5f5; }
        .header { background: #667eea; color: white; padding: 1rem; border-radius: 10px; margin-bottom: 2rem; }
        .card { background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 1rem; }
        .btn { padding: 0.5rem 1rem; background: #667eea; color: white; text-decoration: none; border-radius: 5px; display: inline-block; margin: 0.25rem; }
        .btn:hover { background: #5a6fd8; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Dashboard Administrativo</h1>
        <p>Bem-vindo, <?php echo htmlspecialchars($_SESSION["user_name"]); ?>!</p>
    </div>
    
    <div class="card">
        <h2>🚀 Sistema B2B Funcionando!</h2>
        <p>Dashboard administrativo carregado com sucesso.</p>
        <p><strong>Usuário:</strong> <?php echo htmlspecialchars($_SESSION["user_email"]); ?></p>
        <p><strong>Tipo:</strong> <?php echo htmlspecialchars($_SESSION["user_type"]); ?></p>
        
        <hr style="margin: 1rem 0;">
        
        <a href="products-simple.php" class="btn">👡 Produtos (Simples)</a>
        <a href="../debug.php" class="btn">🔧 Debug Sistema</a>
        <a href="../index.php" class="btn">🏠 Página Inicial</a>
        <a href="../login.php?logout=1" class="btn" style="background: #dc3545;">🚪 Logout</a>
    </div>
    
    <div class="card">
        <h3>📊 Funcionalidades</h3>
        <ul>
            <li>✅ Dashboard funcionando</li>
            <li>✅ Sistema de login</li>
            <li>✅ Gestão básica de produtos</li>
            <li>🔄 Estoque (em desenvolvimento)</li>
            <li>🔄 Pedidos (em desenvolvimento)</li>
            <li>🔄 Clientes B2B (em desenvolvimento)</li>
        </ul>
    </div>
</body>
</html>';

if (!file_exists('admin/dashboard.php')) {
    file_put_contents('admin/dashboard.php', $dashboardContent);
    echo "<p>✅ admin/dashboard.php criado</p>";
}

// Criar admin/products-simple.php
$productsContent = '<?php
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
</html>';

if (!file_exists('admin/products-simple.php')) {
    file_put_contents('admin/products-simple.php', $productsContent);
    echo "<p>✅ admin/products-simple.php criado</p>";
}

// Criar login simples se não existir
if (!file_exists('login.php')) {
    $loginContent = '<?php
session_start();

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST["email"];
    $senha = $_POST["senha"];
    
    // Login simples (em produção, use hash de senha)
    if ($email === "admin@empresa.com" && $senha === "admin123") {
        $_SESSION["user_id"] = 1;
        $_SESSION["user_name"] = "Administrador";
        $_SESSION["user_email"] = $email;
        $_SESSION["user_type"] = "admin";
        
        header("Location: admin/dashboard.php");
        exit;
    } else {
        $error = "Email ou senha incorretos";
    }
}

if (isset($_GET["logout"])) {
    session_destroy();
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Login - Sistema B2B</title>
    <style>
        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; }
        .login-box { background: white; padding: 2rem; border-radius: 10px; width: 400px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        h1 { text-align: center; color: #333; margin-bottom: 2rem; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
        input { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        .btn { width: 100%; padding: 0.75rem; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; }
        .btn:hover { background: #5a6fd8; }
        .error { background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
        .demo { background: #e3f2fd; padding: 1rem; border-radius: 5px; margin-top: 1rem; text-align: center; }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>🔐 Login Admin</h1>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" required value="admin@empresa.com">
            </div>
            <div class="form-group">
                <label>Senha:</label>
                <input type="password" name="senha" required value="admin123">
            </div>
            <button type="submit" class="btn">Entrar</button>
        </form>
        
        <div class="demo">
            <strong>Credenciais de Demo:</strong><br>
            Email: admin@empresa.com<br>
            Senha: admin123
        </div>
        
        <div style="text-align: center; margin-top: 1rem;">
            <a href="index.php" style="color: #667eea;">← Voltar ao início</a> | 
            <a href="debug.php" style="color: #667eea;">Debug</a>
        </div>
    </div>
</body>
</html>';
    
    file_put_contents('login.php', $loginContent);
    echo "<p>✅ login.php criado</p>";
}

echo "<hr>";
echo "<h2>🎉 Sistema Criado com Sucesso!</h2>";
echo "<p>Agora você pode:</p>";
echo "<ul>";
echo "<li><a href='debug.php'>🔍 Verificar se tudo está funcionando</a></li>";
echo "<li><a href='login.php'>🔐 Fazer login (admin@empresa.com / admin123)</a></li>";
echo "<li><a href='admin/dashboard.php'>📊 Acessar dashboard</a></li>";
echo "</ul>";

echo "<p><strong>✨ Se ainda houver erro 500, acesse debug.php primeiro!</strong></p>";
?>