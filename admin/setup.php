<?php
// admin/setup.php - Instalação e configuração automática do dashboard
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir configurações
require_once 'config.php';

// Se já está logado como admin, redirecionar para dashboard
if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'admin') {
    header('Location: dashboard.php');
    exit;
}

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// Verificações automáticas
$dbCheck = false;
$tablesCheck = ['all_exist' => false, 'missing' => []];
$adminExists = false;

try {
    $db = new Database();
    $dbCheck = true;
    
    // Verificar tabelas
    $tablesCheck = checkDatabaseTables();
    
    // Verificar se admin existe
    if (in_array('usuarios', $tablesCheck['existing'])) {
        $stmt = $db->query("SELECT id FROM usuarios WHERE email = 'admin@empresa.com' AND tipo = 'admin'");
        $adminExists = $stmt->fetch() !== false;
    }
    
} catch (Exception $e) {
    $error = 'Erro na conexão com banco: ' . $e->getMessage();
}

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_tables':
            try {
                $result = createMissingTables();
                if ($result['success']) {
                    $success = 'Tabelas criadas com sucesso!';
                    $tablesCheck = checkDatabaseTables();
                } else {
                    $error = 'Erro ao criar tabelas: ' . implode(', ', $result['errors']);
                }
            } catch (Exception $e) {
                $error = 'Erro: ' . $e->getMessage();
            }
            break;
            
        case 'insert_data':
            try {
                if (insertInitialData()) {
                    $success = 'Dados iniciais inseridos com sucesso!';
                    // Verificar novamente se admin existe
                    $stmt = $db->query("SELECT id FROM usuarios WHERE email = 'admin@empresa.com' AND tipo = 'admin'");
                    $adminExists = $stmt->fetch() !== false;
                } else {
                    $error = 'Erro ao inserir dados iniciais';
                }
            } catch (Exception $e) {
                $error = 'Erro: ' . $e->getMessage();
            }
            break;
            
        case 'complete_setup':
            if ($dbCheck && $tablesCheck['all_exist'] && $adminExists) {
                $success = 'Configuração concluída! Redirecionando...';
                echo '<script>setTimeout(function(){ window.location.href = "../login.php"; }, 2000);</script>';
            } else {
                $error = 'Ainda há itens pendentes na configuração.';
            }
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Dashboard Administrativo</title>
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

        .setup-container {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            width: 100%;
            max-width: 800px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo h1 {
            color: #333;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .logo p {
            color: #666;
            font-size: 1.1rem;
        }

        .progress-bar {
            background: #e9ecef;
            height: 8px;
            border-radius: 4px;
            margin: 2rem 0;
            overflow: hidden;
        }

        .progress-fill {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .check-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 1.5rem;
        }

        .check-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 0;
            border-bottom: 1px solid #e9ecef;
        }

        .check-item:last-child {
            border-bottom: none;
        }

        .check-label {
            font-weight: 500;
            color: #333;
        }

        .check-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-icon {
            font-size: 1.2rem;
        }

        .status-ok {
            color: #28a745;
        }

        .status-error {
            color: #dc3545;
        }

        .status-warning {
            color: #ffc107;
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            margin: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%);
            color: #212529;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .missing-tables {
            background: #fff3cd;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }

        .missing-tables h4 {
            color: #856404;
            margin-bottom: 0.5rem;
        }

        .missing-tables ul {
            color: #856404;
            margin-left: 1.5rem;
        }

        .actions {
            text-align: center;
            margin-top: 2rem;
        }

        .completion-message {
            text-align: center;
            padding: 2rem;
            background: #d4edda;
            border-radius: 15px;
            color: #155724;
        }

        .completion-message h2 {
            margin-bottom: 1rem;
        }

        .next-steps {
            background: #e3f2fd;
            padding: 1.5rem;
            border-radius: 15px;
            margin-top: 2rem;
        }

        .next-steps h3 {
            color: #1976d2;
            margin-bottom: 1rem;
        }

        .next-steps ol {
            margin-left: 1.5rem;
            color: #1976d2;
        }

        .debug-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
            font-family: monospace;
            font-size: 0.9rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="logo">
            <h1>⚙️ Setup Dashboard</h1>
            <p>Configuração do Sistema Administrativo</p>
        </div>

        <!-- Progress Bar -->
        <?php
        $progress = 0;
        if ($dbCheck) $progress += 25;
        if ($tablesCheck['all_exist']) $progress += 40;
        if ($adminExists) $progress += 35;
        ?>
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
        </div>
        <div style="text-align: center; color: #666; margin-bottom: 2rem;">
            Progresso: <?php echo $progress; ?>%
        </div>

        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                <span>✅</span>
                <div><?php echo htmlspecialchars($success); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <span>❌</span>
                <div><?php echo htmlspecialchars($error); ?></div>
            </div>
        <?php endif; ?>

        <!-- System Check -->
        <div class="check-section">
            <h3 style="margin-bottom: 1rem; color: #333;">📋 Verificação do Sistema</h3>
            
            <!-- Database Connection -->
            <div class="check-item">
                <div class="check-label">Conexão com Banco de Dados</div>
                <div class="check-status">
                    <?php if ($dbCheck): ?>
                        <span class="status-icon status-ok">✅</span>
                        <span style="color: #28a745;">Conectado</span>
                    <?php else: ?>
                        <span class="status-icon status-error">❌</span>
                        <span style="color: #dc3545;">Erro de Conexão</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tables Check -->
            <div class="check-item">
                <div class="check-label">Estrutura do Banco (Tabelas)</div>
                <div class="check-status">
                    <?php if ($tablesCheck['all_exist']): ?>
                        <span class="status-icon status-ok">✅</span>
                        <span style="color: #28a745;">Completa</span>
                    <?php else: ?>
                        <span class="status-icon status-warning">⚠️</span>
                        <span style="color: #ffc107;">Incompleta</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Admin User -->
            <div class="check-item">
                <div class="check-label">Usuário Administrador</div>
                <div class="check-status">
                    <?php if ($adminExists): ?>
                        <span class="status-icon status-ok">✅</span>
                        <span style="color: #28a745;">Criado</span>
                    <?php else: ?>
                        <span class="status-icon status-error">❌</span>
                        <span style="color: #dc3545;">Não Encontrado</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Missing Tables Info -->
        <?php if (!$tablesCheck['all_exist'] && !empty($tablesCheck['missing'])): ?>
            <div class="missing-tables">
                <h4>📋 Tabelas Faltantes:</h4>
                <ul>
                    <?php foreach ($tablesCheck['missing'] as $table): ?>
                        <li><?php echo htmlspecialchars($table); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="actions">
            <?php if (!$dbCheck): ?>
                <!-- Database Error -->
                <div style="text-align: left; background: #f8d7da; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                    <h4 style="color: #721c24;">❌ Erro de Conexão com Banco</h4>
                    <p style="color: #721c24; margin: 0.5rem 0;">Para resolver:</p>
                    <ol style="color: #721c24; margin-left: 1.5rem;">
                        <li>Verifique se MySQL está rodando</li>
                        <li>Crie o banco: <code>CREATE DATABASE catalogo_chinelos;</code></li>
                        <li>Verifique credenciais em <code>config/database.php</code></li>
                        <li>Teste a conexão</li>
                    </ol>
                </div>
                
                <a href="../create-admin.php" class="btn btn-warning">🔧 Criar Banco e Admin</a>
                <button onclick="window.location.reload()" class="btn btn-primary">🔄 Testar Novamente</button>
                
            <?php elseif (!$tablesCheck['all_exist']): ?>
                <!-- Missing Tables -->
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="create_tables">
                    <button type="submit" class="btn btn-warning">📋 Criar Tabelas</button>
                </form>
                
            <?php elseif (!$adminExists): ?>
                <!-- Missing Admin -->
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="insert_data">
                    <button type="submit" class="btn btn-success">👤 Criar Admin e Dados</button>
                </form>
                
            <?php else: ?>
                <!-- Setup Complete -->
                <div class="completion-message">
                    <h2>🎉 Configuração Concluída!</h2>
                    <p>O dashboard administrativo está pronto para uso.</p>
                </div>
                
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="complete_setup">
                    <button type="submit" class="btn btn-success">🚀 Finalizar e Fazer Login</button>
                </form>
            <?php endif; ?>

            <a href="../login.php" class="btn btn-primary">🔐 Ir para Login</a>
        </div>

        <!-- Next Steps -->
        <?php if ($progress === 100): ?>
            <div class="next-steps">
                <h3>🎯 Próximos Passos</h3>
                <ol>
                    <li><strong>Fazer Login:</strong> Use admin@empresa.com / admin123</li>
                    <li><strong>Configurar Categorias:</strong> Adicione categorias de produtos</li>
                    <li><strong>Cadastrar Cores:</strong> Configure cores disponíveis</li>
                    <li><strong>Definir Tamanhos:</strong> Configure numeração</li>
                    <li><strong>Adicionar Produtos:</strong> Comece a cadastrar produtos</li>
                </ol>
            </div>
        <?php endif; ?>

        <!-- Debug Info -->
        <?php if (isset($_GET['debug'])): ?>
            <div class="debug-info">
                <strong>Debug Info:</strong><br>
                Database Check: <?php echo $dbCheck ? 'OK' : 'FAIL'; ?><br>
                Tables Existing: <?php echo count($tablesCheck['existing']); ?><br>
                Tables Missing: <?php echo count($tablesCheck['missing']); ?><br>
                Admin Exists: <?php echo $adminExists ? 'YES' : 'NO'; ?><br>
                Progress: <?php echo $progress; ?>%<br>
                PHP Version: <?php echo PHP_VERSION; ?><br>
                MySQL Available: <?php echo extension_loaded('pdo_mysql') ? 'YES' : 'NO'; ?>
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <div style="text-align: center; margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #e9ecef; color: #6c757d; font-size: 0.9rem;">
            <p>Dashboard Administrativo v1.0 | <a href="?debug=1" style="color: #667eea;">Debug</a></p>
        </div>
    </div>

    <script>
        // Auto-refresh em caso de erro para ver se foi resolvido
        <?php if (!$dbCheck && !$error): ?>
            setTimeout(function() {
                if (confirm('Tentar conectar novamente com o banco?')) {
                    window.location.reload();
                }
            }, 5000);
        <?php endif; ?>

        // Mostrar progresso em tempo real
        function updateProgress() {
            const progressBar = document.querySelector('.progress-fill');
            const currentWidth = parseInt(progressBar.style.width);
            const targetWidth = <?php echo $progress; ?>;
            
            if (currentWidth < targetWidth) {
                let width = 0;
                const interval = setInterval(function() {
                    if (width >= targetWidth) {
                        clearInterval(interval);
                    } else {
                        width += 2;
                        progressBar.style.width = width + '%';
                    }
                }, 50);
            }
        }

        document.addEventListener('DOMContentLoaded', updateProgress);
    </script>
</body>
</html>