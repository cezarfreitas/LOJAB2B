<?php
// index.php - Página Inicial Simples
session_start();

// Verificar se está logado e redirecionar
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
if ($isLoggedIn) {
    $userType = $_SESSION['user_type'] ?? '';
    if ($userType === 'admin') {
        header('Location: admin/dashboard.php');
        exit;
    } elseif ($userType === 'cliente_b2b') {
        header('Location: catalog.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo B2B - Chinelos</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
        }

        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="2" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1.5" fill="white" opacity="0.05"/><circle cx="50" cy="10" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
        }

        .hero-content {
            max-width: 800px;
            padding: 2rem;
            position: relative;
            z-index: 1;
        }

        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            animation: fadeInUp 1s ease-out;
        }

        .hero p {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            animation: fadeInUp 1s ease-out 0.2s both;
        }

        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeInUp 1s ease-out 0.4s both;
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            min-width: 200px;
        }

        .btn-primary {
            background: white;
            color: #667eea;
        }

        .btn-secondary {
            background: transparent;
            color: white;
            border: 2px solid white;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .features {
            padding: 5rem 2rem;
            background: #f8f9fa;
        }

        .features-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .features h2 {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 3rem;
            color: #333;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-10px);
        }

        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .feature-card h3 {
            font-size: 1.3rem;
            margin-bottom: 1rem;
            color: #333;
        }

        .feature-card p {
            color: #666;
            line-height: 1.6;
        }

        .footer {
            background: #333;
            color: white;
            text-align: center;
            padding: 2rem;
        }

        .status-bar {
            background: rgba(255,255,255,0.9);
            padding: 0.5rem;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            font-size: 0.9rem;
            text-align: center;
            color: #333;
        }

        .debug-info {
            position: fixed;
            bottom: 10px;
            right: 10px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 0.5rem;
            border-radius: 5px;
            font-size: 0.8rem;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Status do Sistema -->
    <div class="status-bar">
        🟢 Sistema Online | 
        📅 <?php echo date('d/m/Y H:i'); ?> | 
        🔧 <a href="debug.php" style="color: #667eea;">Debug</a> | 
        📊 <a href="login.php?debug=1" style="color: #667eea;">Login Debug</a>
    </div>

    <section class="hero">
        <div class="hero-content">
            <h1>Catálogo B2B</h1>
            <p>Plataforma completa para vendas corporativas de chinelos com gestão inteligente de estoque e pedidos</p>
            <div class="cta-buttons">
                <a href="login.php" class="btn btn-primary">Fazer Login</a>
                <a href="register.php" class="btn btn-secondary">Cadastrar Empresa</a>
            </div>
        </div>
    </section>

    <section class="features">
        <div class="features-container">
            <h2>Funcionalidades</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">🛒</div>
                    <h3>Catálogo Inteligente</h3>
                    <p>Navegue por produtos com filtros avançados, visualize variantes de cor e tamanho em tempo real</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📦</div>
                    <h3>Gestão de Estoque</h3>
                    <p>Controle completo de estoque com alertas automáticos e histórico de movimentações</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">👥</div>
                    <h3>Clientes B2B</h3>
                    <p>Sistema especializado para vendas corporativas com preços e descontos personalizados</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3>Relatórios Completos</h3>
                    <p>Dashboards e relatórios detalhados para acompanhar vendas e performance</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🔒</div>
                    <h3>Segurança Avançada</h3>
                    <p>Sistema seguro com controle de acesso, sessões protegidas e backup automático</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📱</div>
                    <h3>Responsivo</h3>
                    <p>Interface adaptada para todos os dispositivos, desktop, tablet e mobile</p>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <p>&copy; 2025 Catálogo B2B - Chinelos. Sistema em desenvolvimento.</p>
        <p style="margin-top: 0.5rem; opacity: 0.7;">
            <a href="debug.php" style="color: #ccc;">Debug</a> | 
            <a href="login.php" style="color: #ccc;">Login</a> | 
            <a href="register.php" style="color: #ccc;">Cadastro</a>
        </p>
    </footer>

    <!-- Debug Info -->
    <div class="debug-info">
        <?php
        echo "PHP " . PHP_VERSION . " | ";
        echo "Sessão: " . (session_status() === PHP_SESSION_ACTIVE ? 'OK' : 'OFF') . " | ";
        echo "DB: " . (file_exists('config/database.php') ? 'OK' : 'MISS');
        ?>
    </div>

    <script>
        // Verificação básica do sistema
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Sistema B2B Carregado');
            console.log('📅 Data:', new Date().toLocaleString('pt-BR'));
            
            // Teste de conectividade
            fetch('debug.php')
                .then(response => {
                    if (response.ok) {
                        console.log('✅ Debug.php acessível');
                    }
                })
                .catch(error => {
                    console.warn('⚠️ Debug.php não acessível');
                });
        });

        // Easter egg - pressione Ctrl+D para debug rápido
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'd') {
                e.preventDefault();
                window.open('debug.php', '_blank');
            }
        });
    </script>
</body>
</html>

<?php
// Auto-criação de arquivos básicos se não existirem
$autoCreate = [
    'admin' => 'directory',
    'uploads' => 'directory', 
    'uploads/products' => 'directory',
    'cache' => 'directory'
];

foreach ($autoCreate as $path => $type) {
    if ($type === 'directory' && !is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

// Criar admin/dashboard.php básico se não existir
if (!file_exists('admin/dashboard.php')) {
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
    </style>
</head>
<body>
    <div class="header">
        <h1>Dashboard Administrativo</h1>
        <p>Bem-vindo, <?php echo htmlspecialchars($_SESSION["user_name"]); ?>!</p>
    </div>
    
    <div class="card">
        <h2>🚀 Sistema em Desenvolvimento</h2>
        <p>O dashboard administrativo está sendo desenvolvido.</p>
        <p><strong>Usuário:</strong> <?php echo htmlspecialchars($_SESSION["user_email"]); ?></p>
        <p><strong>Tipo:</strong> <?php echo htmlspecialchars($_SESSION["user_type"]); ?></p>
        
        <hr style="margin: 1rem 0;">
        
        <a href="../debug.php" class="btn">🔧 Debug Sistema</a>
        <a href="../index.php" class="btn">🏠 Página Inicial</a>
        <a href="../login.php?logout=1" class="btn" style="background: #dc3545;">🚪 Logout</a>
    </div>
    
    <div class="card">
        <h3>📊 Funcionalidades Futuras</h3>
        <ul>
            <li>Gestão de produtos e variantes</li>
            <li>Controle de estoque</li>
            <li>Gerenciamento de pedidos</li>
            <li>Cadastro de clientes B2B</li>
            <li>Relatórios de vendas</li>
            <li>Dashboard com estatísticas</li>
        </ul>
    </div>
</body>
</html>';
    
    file_put_contents('admin/dashboard.php', $dashboardContent);
}

// Criar catalog.php básico se não existir
if (!file_exists('catalog.php')) {
    $catalogContent = '<?php
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
</html>';
    
    file_put_contents('catalog.php', $catalogContent);
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>