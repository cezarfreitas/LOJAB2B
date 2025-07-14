<?php
// login.php - Versão Corrigida
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar se os arquivos existem antes de incluir
if (!file_exists('config/database.php')) {
    die('Erro: Arquivo config/database.php não encontrado');
}

if (!file_exists('config/functions.php')) {
    die('Erro: Arquivo config/functions.php não encontrado');
}

try {
    require_once 'config/database.php';
    require_once 'config/functions.php';
} catch (Exception $e) {
    die('Erro ao carregar arquivos: ' . $e->getMessage());
}

// Iniciar sessão
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar se já está logado (APENAS uma vez para evitar loop)
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$userType = $_SESSION['user_type'] ?? '';

// Debug: Mostrar status da sessão (remover em produção)
if (isset($_GET['debug'])) {
    echo "Debug Info:<br>";
    echo "Session ID: " . session_id() . "<br>";
    echo "Logged In: " . ($isLoggedIn ? 'Yes' : 'No') . "<br>";
    echo "User Type: " . $userType . "<br>";
    echo "User ID: " . ($_SESSION['user_id'] ?? 'null') . "<br>";
    echo "<hr>";
}

// Redirecionamento APENAS se não for uma requisição POST e estiver logado
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($userType === 'admin') {
        // Verificar se o arquivo admin/dashboard.php existe
        if (file_exists('admin/dashboard.php')) {
            header('Location: admin/dashboard.php');
            exit;
        } else {
            // Se não existir, mostrar mensagem
            $error = 'Área administrativa não encontrada. Criando página simples...';
        }
    } else {
        // Verificar se o arquivo catalog.php existe  
        if (file_exists('catalog.php')) {
            header('Location: catalog.php');
            exit;
        } else {
            // Se não existir, mostrar mensagem
            $error = 'Catálogo não encontrado. Criando página simples...';
        }
    }
}

$error = '';
$success = '';

// Processar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error = 'Todos os campos são obrigatórios';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email inválido';
        } else {
            // Tentar conectar com banco
            $db = new Database();
            
            // Verificar se usuário existe
            $sql = "SELECT u.*, cb.id as cliente_id 
                    FROM usuarios u
                    LEFT JOIN clientes_b2b cb ON u.id = cb.usuario_id
                    WHERE u.email = ? AND u.ativo = 1";
            
            $stmt = $db->query($sql, [$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['senha'])) {
                // Login válido - configurar sessão
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['nome'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_type'] = $user['tipo'];
                
                if ($user['tipo'] === 'cliente_b2b' && $user['cliente_id']) {
                    $_SESSION['client_id'] = $user['cliente_id'];
                }
                
                // Log da atividade
                if (function_exists('logActivity')) {
                    logActivity($user['id'], 'login', 'Login realizado com sucesso');
                }
                
                $success = 'Login realizado com sucesso! Redirecionando...';
                
                // Redirecionamento baseado no tipo de usuário
                if ($user['tipo'] === 'admin') {
                    echo '<script>setTimeout(function(){ window.location.href = "admin/dashboard.php"; }, 2000);</script>';
                } else {
                    echo '<script>setTimeout(function(){ window.location.href = "catalog.php"; }, 2000);</script>';
                }
            } else {
                $error = 'Email ou senha incorretos';
                // Log da tentativa
                if (function_exists('logActivity')) {
                    logActivity(null, 'login_failed', 'Tentativa de login falhada para: ' . $email);
                }
            }
        }
    } catch (Exception $e) {
        $error = 'Erro no sistema: ' . $e->getMessage();
        error_log('Erro no login: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Catálogo de Chinelos B2B</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .login-container {
            background: white;
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
            position: relative;
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo h1 {
            color: #333;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .logo p {
            color: #666;
            font-size: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn-login {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
        }

        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .success {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .links {
            text-align: center;
            margin-top: 2rem;
        }

        .links a {
            color: #667eea;
            text-decoration: none;
            margin: 0 0.5rem;
        }

        .links a:hover {
            text-decoration: underline;
        }

        .debug-info {
            position: absolute;
            bottom: -100px;
            left: 0;
            right: 0;
            background: rgba(255,255,255,0.9);
            padding: 1rem;
            border-radius: 10px;
            font-size: 0.8rem;
            color: #666;
        }

        .version-info {
            position: fixed;
            bottom: 10px;
            right: 10px;
            background: rgba(255,255,255,0.9);
            padding: 0.5rem;
            border-radius: 5px;
            font-size: 0.8rem;
            color: #666;
        }

        .loading {
            display: none;
            text-align: center;
            margin-top: 1rem;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>ChinelosB2B</h1>
            <p>Catálogo Virtual</p>
        </div>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success">
                <?php echo htmlspecialchars($success); ?>
                <div class="loading">
                    <div class="spinner"></div>
                    <p>Redirecionando...</p>
                </div>
            </div>
            <script>
                document.querySelector('.loading').style.display = 'block';
                document.querySelector('form').style.display = 'none';
            </script>
        <?php else: ?>
            <form method="POST" id="loginForm">
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           placeholder="seu@email.com">
                </div>

                <div class="form-group">
                    <label for="password">Senha:</label>
                    <input type="password" id="password" name="password" required
                           placeholder="Sua senha">
                </div>

                <button type="submit" class="btn-login" id="loginBtn">Entrar</button>
            </form>
        <?php endif; ?>

        <div class="links">
            <a href="forgot-password.php">Esqueci minha senha</a>
            <br><br>
            <a href="register.php">Cadastrar nova empresa</a>
            <br><br>
            <a href="index.php">← Voltar ao início</a>
        </div>

        <!-- Debug info (remover em produção) -->
        <?php if (isset($_GET['debug'])): ?>
            <div class="debug-info">
                <strong>Debug Mode:</strong><br>
                PHP Version: <?php echo PHP_VERSION; ?><br>
                Session Status: <?php echo session_status(); ?><br>
                Database File: <?php echo file_exists('config/database.php') ? 'OK' : 'MISSING'; ?><br>
                Functions File: <?php echo file_exists('config/functions.php') ? 'OK' : 'MISSING'; ?><br>
                Admin Dashboard: <?php echo file_exists('admin/dashboard.php') ? 'OK' : 'MISSING'; ?><br>
                Catalog: <?php echo file_exists('catalog.php') ? 'OK' : 'MISSING'; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="version-info">
        Sistema v1.0 | <?php echo date('d/m/Y H:i'); ?>
    </div>

    <script>
        // Validação do formulário
        document.getElementById('loginForm')?.addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const btn = document.getElementById('loginBtn');
            
            if (!email || !password) {
                e.preventDefault();
                alert('Preencha todos os campos');
                return;
            }
            
            if (!email.includes('@')) {
                e.preventDefault();
                alert('Email inválido');
                return;
            }
            
            // Mostrar carregamento
            btn.textContent = 'Entrando...';
            btn.disabled = true;
        });

        // Credenciais de teste (remover em produção)
        <?php if (isset($_GET['demo'])): ?>
            document.getElementById('email').value = 'admin@empresa.com';
            document.getElementById('password').value = 'admin123';
        <?php endif; ?>
    </script>
</body>
</html>
<?php
// Criar páginas simples se não existirem
if (!file_exists('admin/dashboard.php') && $isLoggedIn && $userType === 'admin') {
    if (!is_dir('admin')) mkdir('admin', 0755, true);
    
    $adminDashboard = '<?php
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
        body { font-family: Arial, sans-serif; margin: 2rem; }
        .header { background: #667eea; color: white; padding: 1rem; border-radius: 10px; margin-bottom: 2rem; }
        .card { background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Dashboard Administrativo</h1>
        <p>Bem-vindo, <?php echo $_SESSION["user_name"]; ?>!</p>
    </div>
    
    <div class="card">
        <h2>Sistema em Desenvolvimento</h2>
        <p>O dashboard administrativo está sendo desenvolvido.</p>
        <p><a href="../login.php?logout=1">Fazer Logout</a></p>
    </div>
</body>
</html>';
    
    file_put_contents('admin/dashboard.php', $adminDashboard);
}

if (!file_exists('catalog.php') && $isLoggedIn && $userType === 'cliente_b2b') {
    $catalog = '<?php
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
        body { font-family: Arial, sans-serif; margin: 2rem; }
        .header { background: #2c5aa0; color: white; padding: 1rem; border-radius: 10px; margin-bottom: 2rem; }
        .card { background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Catálogo B2B</h1>
        <p>Bem-vindo, <?php echo $_SESSION["user_name"]; ?>!</p>
    </div>
    
    <div class="card">
        <h2>Sistema em Desenvolvimento</h2>
        <p>O catálogo de produtos está sendo desenvolvido.</p>
        <p><a href="login.php?logout=1">Fazer Logout</a></p>
    </div>
</body>
</html>';
    
    file_put_contents('catalog.php', $catalog);
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>