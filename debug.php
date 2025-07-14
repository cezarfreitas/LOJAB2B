<?php
// debug.php - Coloque na pasta /loja/ para ver os erros
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h1>🔍 Debug - Sistema B2B</h1>";
echo "<p>Data/Hora: " . date('d/m/Y H:i:s') . "</p>";

// Verificar PHP
echo "<h2>✅ Verificações Básicas</h2>";
echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";
echo "<p><strong>MySQL Disponível:</strong> " . (extension_loaded('pdo_mysql') ? '✅ Sim' : '❌ Não') . "</p>";

// Verificar arquivos
echo "<h2>📁 Verificação de Arquivos</h2>";
$arquivos = [
    'config/database.php',
    'config/functions.php', 
    'admin/dashboard.php',
    'admin/products.php',
    'login.php',
    'index.php'
];

foreach ($arquivos as $arquivo) {
    $existe = file_exists($arquivo);
    $status = $existe ? '✅' : '❌';
    echo "<p>{$status} {$arquivo}</p>";
}

// Testar conexão com banco
echo "<h2>🗄️ Teste de Banco de Dados</h2>";
try {
    if (file_exists('config/database.php')) {
        require_once 'config/database.php';
        echo "<p>✅ Arquivo database.php carregado</p>";
        
        $db = new Database();
        echo "<p>✅ Classe Database instanciada</p>";
        
        $stmt = $db->query("SELECT 1 as test");
        echo "<p>✅ Conexão com banco estabelecida</p>";
        
    } else {
        echo "<p>❌ Arquivo config/database.php não encontrado</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Erro no banco: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Testar sessão
echo "<h2>🔐 Teste de Sessão</h2>";
session_start();
$_SESSION['teste'] = 'funcionando';
if (isset($_SESSION['teste'])) {
    echo "<p>✅ Sessões funcionando</p>";
} else {
    echo "<p>❌ Problema com sessões</p>";
}

// Verificar permissões
echo "<h2>📂 Verificação de Permissões</h2>";
$pastas = ['uploads', 'uploads/products', 'admin'];
foreach ($pastas as $pasta) {
    if (is_dir($pasta)) {
        $permissao = is_writable($pasta) ? '✅ Escrita OK' : '⚠️ Sem escrita';
        echo "<p>{$permissao} {$pasta}/</p>";
    } else {
        echo "<p>❌ Pasta {$pasta}/ não existe</p>";
    }
}

echo "<hr>";
echo "<h2>🔧 Próximos Passos</h2>";
echo "<p><a href='create-simple-system.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 Criar Sistema Simples</a></p>";
echo "<p><a href='login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔐 Tentar Login</a></p>";
echo "<p><a href='admin/dashboard.php' style='background: #6f42c1; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>📊 Tentar Dashboard</a></p>";

echo "<hr>";
echo "<p><small>Se ainda houver erro 500, verifique os logs do Apache/Nginx ou entre em contato.</small></p>";
?>