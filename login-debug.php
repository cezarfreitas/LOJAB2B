<?php
// login-debug.php - Debug específico para problemas de login
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Login - Sistema B2B</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            margin: 2rem;
            background: #f5f5f5;
            line-height: 1.6;
        }
        .test-section {
            background: white;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { color: #17a2b8; font-weight: bold; }
        h1 { color: #333; text-align: center; }
        h2 { color: #667eea; border-bottom: 2px solid #eee; padding-bottom: 0.5rem; }
        pre { background: #f8f9fa; padding: 1rem; border-radius: 5px; overflow-x: auto; }
        .login-form {
            background: #e3f2fd;
            padding: 1.5rem;
            border-radius: 10px;
            margin: 1rem 0;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .btn {
            padding: 0.75rem 1.5rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 0.5rem;
        }
        .btn:hover { background: #5a6fd8; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        .code-block {
            background: #2d3748;
            color: #e2e8f0;
            padding: 1rem;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>🔍 Debug de Login - Diagnóstico Completo</h1>

    <!-- Teste 1: Verificação de Arquivos -->
    <div class="test-section">
        <h2>📁 1. Verificação de Arquivos</h2>
        <?php
        $files = [
            'config/database.php' => 'Configuração do banco',
            'config/functions.php' => 'Funções auxiliares',
            'login.php' => 'Página de login'
        ];

        foreach ($files as $file => $desc) {
            if (file_exists($file)) {
                echo "<p class='success'>✅ {$desc}: {$file}</p>";
            } else {
                echo "<p class='error'>❌ {$desc}: {$file} - FALTANDO</p>";
            }
        }
        ?>
    </div>

    <!-- Teste 2: Conexão com Banco -->
    <div class="test-section">
        <h2>🗄️ 2. Teste de Conexão com Banco</h2>
        <?php
        try {
            if (file_exists('config/database.php')) {
                require_once 'config/database.php';
                echo "<p class='success'>✅ Arquivo database.php carregado</p>";
                
                $db = new Database();
                echo "<p class='success'>✅ Classe Database instanciada</p>";
                
                // Testar conexão
                $stmt = $db->query("SELECT 1 as test");
                echo "<p class='success'>✅ Conexão com banco estabelecida</p>";
                
                // Verificar se tabela usuarios existe
                try {
                    $stmt = $db->query("DESCRIBE usuarios");
                    echo "<p class='success'>✅ Tabela 'usuarios' existe</p>";
                    
                    // Contar usuários
                    $stmt = $db->query("SELECT COUNT(*) as total FROM usuarios");
                    $result = $stmt->fetch();
                    echo "<p class='info'>📊 Total de usuários na tabela: {$result['total']}</p>";
                    
                } catch (Exception $e) {
                    echo "<p class='error'>❌ Tabela 'usuarios' não existe: " . $e->getMessage() . "</p>";
                    echo "<p class='warning'>⚠️ Execute o script de criação da estrutura do banco</p>";
                }
                
            } else {
                echo "<p class='error'>❌ Arquivo config/database.php não encontrado</p>";
            }
        } catch (Exception $e) {
            echo "<p class='error'>❌ Erro na conexão: " . $e->getMessage() . "</p>";
        }
        ?>
    </div>

    <!-- Teste 3: Verificar Usuário Admin -->
    <div class="test-section">
        <h2>👤 3. Verificação do Usuário Admin</h2>
        <?php
        if (isset($db)) {
            try {
                // Verificar se usuário admin existe
                $stmt = $db->query("SELECT * FROM usuarios WHERE email = 'admin@empresa.com'");
                $admin = $stmt->fetch();
                
                if ($admin) {
                    echo "<p class='success'>✅ Usuário admin encontrado</p>";
                    echo "<p><strong>ID:</strong> {$admin['id']}</p>";
                    echo "<p><strong>Nome:</strong> {$admin['nome']}</p>";
                    echo "<p><strong>Email:</strong> {$admin['email']}</p>";
                    echo "<p><strong>Tipo:</strong> {$admin['tipo']}</p>";
                    echo "<p><strong>Ativo:</strong> " . ($admin['ativo'] ? 'Sim' : 'Não') . "</p>";
                    
                    // Testar senha
                    if (password_verify('admin123', $admin['senha'])) {
                        echo "<p class='success'>✅ Senha 'admin123' confere</p>";
                    } else {
                        echo "<p class='error'>❌ Senha 'admin123' NÃO confere</p>";
                        echo "<p class='warning'>⚠️ Hash da senha no banco: " . substr($admin['senha'], 0, 20) . "...</p>";
                    }
                } else {
                    echo "<p class='error'>❌ Usuário admin NÃO encontrado</p>";
                    echo "<p class='warning'>⚠️ Será necessário criar o usuário admin</p>";
                }
                
                // Listar todos os usuários
                echo "<h3>👥 Todos os usuários no sistema:</h3>";
                $stmt = $db->query("SELECT id, nome, email, tipo, ativo FROM usuarios");
                $users = $stmt->fetchAll();
                
                if (empty($users)) {
                    echo "<p class='warning'>⚠️ Nenhum usuário encontrado na tabela</p>";
                } else {
                    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 1rem 0;'>";
                    echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Nome</th><th>Email</th><th>Tipo</th><th>Ativo</th></tr>";
                    foreach ($users as $user) {
                        $activeStatus = $user['ativo'] ? 'Sim' : 'Não';
                        echo "<tr><td>{$user['id']}</td><td>{$user['nome']}</td><td>{$user['email']}</td><td>{$user['tipo']}</td><td>{$activeStatus}</td></tr>";
                    }
                    echo "</table>";
                }
                
            } catch (Exception $e) {
                echo "<p class='error'>❌ Erro ao verificar usuário: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p class='error'>❌ Conexão com banco não estabelecida</p>";
        }
        ?>
    </div>

    <!-- Teste 4: Funções -->
    <div class="test-section">
        <h2>⚙️ 4. Verificação de Funções</h2>
        <?php
        if (file_exists('config/functions.php')) {
            require_once 'config/functions.php';
            echo "<p class='success'>✅ Functions.php carregado</p>";
            
            // Testar funções críticas
            $functions = ['password_verify', 'filter_var', 'htmlspecialchars'];
            foreach ($functions as $func) {
                if (function_exists($func)) {
                    echo "<p class='success'>✅ Função {$func} disponível</p>";
                } else {
                    echo "<p class='error'>❌ Função {$func} NÃO disponível</p>";
                }
            }
            
            // Testar validação de email
            if (function_exists('filter_var')) {
                $testEmail = 'admin@empresa.com';
                $isValid = filter_var($testEmail, FILTER_VALIDATE_EMAIL);
                if ($isValid) {
                    echo "<p class='success'>✅ Validação de email funcionando</p>";
                } else {
                    echo "<p class='error'>❌ Validação de email com problema</p>";
                }
            }
        } else {
            echo "<p class='error'>❌ Arquivo config/functions.php não encontrado</p>";
        }
        ?>
    </div>

    <!-- Teste 5: Sessão -->
    <div class="test-section">
        <h2>🔐 5. Teste de Sessão</h2>
        <?php
        echo "<p><strong>Session Status:</strong> ";
        switch (session_status()) {
            case PHP_SESSION_DISABLED:
                echo "<span class='error'>DESABILITADO</span>";
                break;
            case PHP_SESSION_NONE:
                echo "<span class='warning'>NENHUMA</span>";
                break;
            case PHP_SESSION_ACTIVE:
                echo "<span class='success'>ATIVA</span>";
                break;
        }
        echo "</p>";
        
        echo "<p><strong>Session ID:</strong> " . (session_id() ?: 'Nenhum') . "</p>";
        
        // Testar variável de sessão
        $_SESSION['test'] = 'funcionando';
        if (isset($_SESSION['test']) && $_SESSION['test'] === 'funcionando') {
            echo "<p class='success'>✅ Variáveis de sessão funcionando</p>";
        } else {
            echo "<p class='error'>❌ Problema com variáveis de sessão</p>";
        }
        
        // Verificar se está logado
        if (isset($_SESSION['user_id'])) {
            echo "<p class='info'>ℹ️ Usuário logado: ID {$_SESSION['user_id']}, Tipo: {$_SESSION['user_type']}</p>";
        } else {
            echo "<p class='info'>ℹ️ Nenhum usuário logado</p>";
        }
        ?>
    </div>

    <!-- Formulário de Teste de Login -->
    <div class="test-section">
        <h2>🧪 6. Teste de Login Manual</h2>
        
        <?php
        // Processar login de teste
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_login'])) {
            echo "<h3>🔍 Resultado do Teste:</h3>";
            
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            
            echo "<p><strong>Email testado:</strong> {$email}</p>";
            echo "<p><strong>Senha testada:</strong> " . str_repeat('*', strlen($password)) . "</p>";
            
            try {
                // Validar email
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    echo "<p class='error'>❌ Email inválido</p>";
                } else {
                    echo "<p class='success'>✅ Email válido</p>";
                }
                
                // Buscar usuário
                $sql = "SELECT u.*, cb.id as cliente_id 
                        FROM usuarios u
                        LEFT JOIN clientes_b2b cb ON u.id = cb.usuario_id
                        WHERE u.email = ?";
                
                $stmt = $db->query($sql, [$email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    echo "<p class='success'>✅ Usuário encontrado no banco</p>";
                    echo "<p><strong>Nome:</strong> {$user['nome']}</p>";
                    echo "<p><strong>Tipo:</strong> {$user['tipo']}</p>";
                    echo "<p><strong>Ativo:</strong> " . ($user['ativo'] ? 'Sim' : 'Não') . "</p>";
                    
                    if (!$user['ativo']) {
                        echo "<p class='error'>❌ Usuário INATIVO</p>";
                    } else {
                        echo "<p class='success'>✅ Usuário ativo</p>";
                        
                        // Verificar senha
                        if (password_verify($password, $user['senha'])) {
                            echo "<p class='success'>✅ Senha CORRETA</p>";
                            
                            // Simular login
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['user_name'] = $user['nome'];
                            $_SESSION['user_email'] = $user['email'];
                            $_SESSION['user_type'] = $user['tipo'];
                            
                            if ($user['tipo'] === 'cliente_b2b' && $user['cliente_id']) {
                                $_SESSION['client_id'] = $user['cliente_id'];
                            }
                            
                            echo "<p class='success'>🎉 LOGIN REALIZADO COM SUCESSO!</p>";
                            echo "<p><a href='admin/dashboard.php' class='btn btn-success'>Ir para Dashboard</a></p>";
                            echo "<p><a href='catalog.php' class='btn btn-success'>Ir para Catálogo</a></p>";
                            
                        } else {
                            echo "<p class='error'>❌ Senha INCORRETA</p>";
                            
                            // Debug da senha
                            echo "<p class='warning'>Debug da senha:</p>";
                            echo "<p>Hash no banco: " . substr($user['senha'], 0, 30) . "...</p>";
                            echo "<p>Tamanho do hash: " . strlen($user['senha']) . "</p>";
                            echo "<p>Senha fornecida: '{$password}'</p>";
                            
                            // Testar hash manual
                            $testHash = password_hash($password, PASSWORD_DEFAULT);
                            echo "<p>Hash de teste: " . substr($testHash, 0, 30) . "...</p>";
                        }
                    }
                } else {
                    echo "<p class='error'>❌ Usuário NÃO encontrado</p>";
                    
                    // Sugerir emails existentes
                    $stmt = $db->query("SELECT email FROM usuarios LIMIT 5");
                    $emails = $stmt->fetchAll();
                    if (!empty($emails)) {
                        echo "<p class='info'>Emails existentes no banco:</p>";
                        foreach ($emails as $e) {
                            echo "<p>• {$e['email']}</p>";
                        }
                    }
                }
                
            } catch (Exception $e) {
                echo "<p class='error'>❌ Erro no teste: " . $e->getMessage() . "</p>";
            }
        }
        ?>

        <div class="login-form">
            <h3>🔐 Teste Manual de Login</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Email:</label>
                    <input type="email" name="email" value="admin@empresa.com" required>
                </div>
                <div class="form-group">
                    <label>Senha:</label>
                    <input type="password" name="password" value="admin123" required>
                </div>
                <button type="submit" name="test_login" class="btn">🧪 Testar Login</button>
            </form>
        </div>
    </div>

    <!-- Criar Usuário Admin -->
    <div class="test-section">
        <h2>👑 7. Criar Usuário Admin</h2>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
            try {
                $nome = trim($_POST['admin_name']);
                $email = trim($_POST['admin_email']);
                $senha = $_POST['admin_password'];
                
                // Verificar se já existe
                $stmt = $db->query("SELECT id FROM usuarios WHERE email = ?", [$email]);
                if ($stmt->fetch()) {
                    echo "<p class='error'>❌ Usuário com este email já existe</p>";
                } else {
                    // Criar usuário
                    $hashedPassword = password_hash($senha, PASSWORD_DEFAULT);
                    $sql = "INSERT INTO usuarios (nome, email, senha, tipo, ativo) VALUES (?, ?, ?, 'admin', 1)";
                    $db->query($sql, [$nome, $email, $hashedPassword]);
                    
                    echo "<p class='success'>✅ Usuário admin criado com sucesso!</p>";
                    echo "<p><strong>Nome:</strong> {$nome}</p>";
                    echo "<p><strong>Email:</strong> {$email}</p>";
                    echo "<p><strong>Senha:</strong> {$senha}</p>";
                }
            } catch (Exception $e) {
                echo "<p class='error'>❌ Erro ao criar usuário: " . $e->getMessage() . "</p>";
            }
        }
        ?>

        <div class="login-form">
            <h3>👤 Criar Usuário Administrador</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Nome:</label>
                    <input type="text" name="admin_name" value="Administrador" required>
                </div>
                <div class="form-group">
                    <label>Email:</label>
                    <input type="email" name="admin_email" value="admin@empresa.com" required>
                </div>
                <div class="form-group">
                    <label>Senha:</label>
                    <input type="password" name="admin_password" value="admin123" required>
                </div>
                <button type="submit" name="create_admin" class="btn btn-success">👑 Criar Admin</button>
            </form>
        </div>
    </div>

    <!-- SQL para criar estrutura -->
    <div class="test-section">
        <h2>📜 8. Script SQL para Criar Estrutura</h2>
        <p>Se a tabela usuarios não existir, execute este SQL no seu banco:</p>
        
        <div class="code-block">
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('admin', 'cliente_b2b') NOT NULL,
    ativo BOOLEAN DEFAULT TRUE,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS clientes_b2b (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    razao_social VARCHAR(200) NOT NULL,
    cnpj VARCHAR(18) UNIQUE NOT NULL,
    telefone VARCHAR(20),
    endereco TEXT,
    cidade VARCHAR(100),
    estado VARCHAR(2),
    cep VARCHAR(9),
    desconto_padrao DECIMAL(5,2) DEFAULT 0.00,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Inserir usuário admin padrão
INSERT IGNORE INTO usuarios (nome, email, senha, tipo) VALUES 
('Administrador', 'admin@empresa.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
        </div>
        
        <?php if (isset($db)): ?>
            <form method="POST">
                <button type="submit" name="create_structure" class="btn btn-success">📜 Executar SQL Automaticamente</button>
            </form>
            
            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_structure'])) {
                try {
                    // Criar tabela usuarios
                    $sql = "CREATE TABLE IF NOT EXISTS usuarios (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        nome VARCHAR(100) NOT NULL,
                        email VARCHAR(100) UNIQUE NOT NULL,
                        senha VARCHAR(255) NOT NULL,
                        tipo ENUM('admin', 'cliente_b2b') NOT NULL,
                        ativo BOOLEAN DEFAULT TRUE,
                        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )";
                    $db->query($sql);
                    echo "<p class='success'>✅ Tabela usuarios criada</p>";
                    
                    // Criar tabela clientes_b2b
                    $sql = "CREATE TABLE IF NOT EXISTS clientes_b2b (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        usuario_id INT NOT NULL,
                        razao_social VARCHAR(200) NOT NULL,
                        cnpj VARCHAR(18) UNIQUE NOT NULL,
                        telefone VARCHAR(20),
                        endereco TEXT,
                        cidade VARCHAR(100),
                        estado VARCHAR(2),
                        cep VARCHAR(9),
                        desconto_padrao DECIMAL(5,2) DEFAULT 0.00,
                        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
                    )";
                    $db->query($sql);
                    echo "<p class='success'>✅ Tabela clientes_b2b criada</p>";
                    
                    // Inserir admin padrão
                    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
                    $sql = "INSERT IGNORE INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, 'admin')";
                    $db->query($sql, ['Administrador', 'admin@empresa.com', $hashedPassword]);
                    echo "<p class='success'>✅ Usuário admin criado</p>";
                    
                } catch (Exception $e) {
                    echo "<p class='error'>❌ Erro ao criar estrutura: " . $e->getMessage() . "</p>";
                }
            }
            ?>
        <?php endif; ?>
    </div>

    <div style="text-align: center; margin-top: 2rem; padding: 2rem; background: #f0f8ff; border-radius: 10px;">
        <h3>🎯 Próximos Passos</h3>
        <p><a href="login.php" class="btn">🔐 Testar Login</a></p>
        <p><a href="index.php" class="btn">🏠 Página Inicial</a></p>
        <p><a href="debug.php" class="btn">🔧 Debug Geral</a></p>
    </div>
</body>
</html>