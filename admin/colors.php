<?php
// admin/colors.php - Gestão de Cores
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../config/functions.php';
requireAdmin();

$db = new Database();
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$success = '';
$error = '';

// Criar tabela se não existir
$db->query("CREATE TABLE IF NOT EXISTS cores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    codigo_hex VARCHAR(7),
    ativo BOOLEAN DEFAULT TRUE,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Inserir cores padrão se não existirem
$cores_padrao = [
    'Preto' => '#000000',
    'Branco' => '#FFFFFF',
    'Azul Marinho' => '#1E3A8A',
    'Vermelho' => '#DC2626',
    'Verde' => '#16A34A',
    'Amarelo' => '#EAB308',
    'Rosa' => '#EC4899',
    'Marrom' => '#8B4513',
    'Cinza' => '#6B7280',
    'Azul Claro' => '#3B82F6'
];

foreach ($cores_padrao as $nome => $hex) {
    $db->query("INSERT IGNORE INTO cores (nome, codigo_hex) VALUES (?, ?)", [$nome, $hex]);
}

// Processar formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'create':
        case 'edit':
            try {
                $nome = sanitizeInput($_POST['nome']);
                $codigo_hex = sanitizeInput($_POST['codigo_hex']);
                
                if (empty($nome)) {
                    throw new Exception('Nome da cor é obrigatório');
                }
                
                // Validar código hexadecimal
                if (!empty($codigo_hex) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $codigo_hex)) {
                    throw new Exception('Código de cor inválido. Use formato #RRGGBB');
                }
                
                if ($action === 'create') {
                    $sql = "INSERT INTO cores (nome, codigo_hex) VALUES (?, ?)";
                    $db->query($sql, [$nome, $codigo_hex]);
                    $success = 'Cor criada com sucesso!';
                } else {
                    $sql = "UPDATE cores SET nome = ?, codigo_hex = ? WHERE id = ?";
                    $db->query($sql, [$nome, $codigo_hex, $id]);
                    $success = 'Cor atualizada com sucesso!';
                }
                
                logActivity($_SESSION['user_id'], 'cor_' . $action, "Cor: {$nome}");
                $action = 'list';
            } catch (Exception $e) {
                $error = 'Erro: ' . $e->getMessage();
            }
            break;
            
        case 'toggle_status':
            try {
                $sql = "UPDATE cores SET ativo = NOT ativo WHERE id = ?";
                $db->query($sql, [$id]);
                $success = 'Status da cor alterado com sucesso!';
                $action = 'list';
            } catch (Exception $e) {
                $error = 'Erro: ' . $e->getMessage();
            }
            break;
    }
}

// Buscar cores
$sql = "SELECT c.*, COUNT(pv.id) as total_produtos 
        FROM cores c 
        LEFT JOIN produto_variantes pv ON c.id = pv.cor_id
        GROUP BY c.id 
        ORDER BY c.nome";
$cores = $db->query($sql)->fetchAll();

// Para edição
$cor_atual = null;
if ($action === 'edit' && $id) {
    $sql = "SELECT * FROM cores WHERE id = ?";
    $cor_atual = $db->query($sql, [$id])->fetch();
    if (!$cor_atual) {
        $error = 'Cor não encontrada';
        $action = 'list';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Cores - Catálogo B2B</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
        }

        .breadcrumb {
            opacity: 0.8;
            font-size: 0.9rem;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 70px;
            width: 260px;
            height: calc(100vh - 70px);
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            overflow-y: auto;
            z-index: 50;
        }

        .sidebar-menu {
            padding: 1rem 0;
        }

        .menu-section {
            margin-bottom: 1.5rem;
        }

        .menu-title {
            padding: 0.5rem 1.5rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .menu-item {
            display: block;
            padding: 0.75rem 1.5rem;
            color: #333;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .menu-item:hover,
        .menu-item.active {
            background-color: #f8f9fa;
            color: #667eea;
            border-left-color: #667eea;
        }

        .menu-item i {
            width: 20px;
            margin-right: 0.75rem;
        }

        .main-content {
            margin-left: 260px;
            padding: 2rem;
            min-height: calc(100vh - 70px);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: #6c757d;
            margin-bottom: 2rem;
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

        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            transition: all 0.3s;
            font-size: 0.9rem;
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

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        .colors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .color-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }

        .color-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .color-preview {
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
            position: relative;
        }

        .color-info {
            padding: 1.5rem;
        }

        .color-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .color-hex {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }

        .color-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 1rem;
        }

        .status-ativo {
            background: #d4edda;
            color: #155724;
        }

        .status-inativo {
            background: #f8d7da;
            color: #721c24;
        }

        .color-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .color-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .form-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .form-header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .form-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .color-picker-wrapper {
            display: flex;
            gap: 1rem;
            align-items: end;
        }

        .color-picker {
            width: 60px;
            height: 45px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
        }

        .color-preview-large {
            width: 100px;
            height: 60px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
        }

        .empty-state h3 {
            margin-bottom: 1rem;
            color: #333;
        }

        @media (max-width: 1200px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .menu-toggle {
                display: block;
                background: none;
                border: none;
                color: white;
                font-size: 1.5rem;
                cursor: pointer;
            }
        }

        @media (max-width: 768px) {
            .colors-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-left">
            <button class="menu-toggle" onclick="toggleSidebar()" style="display: none;">☰</button>
            <div class="logo">Catálogo B2B</div>
            <div class="breadcrumb">Dashboard / Configurações / Cores</div>
        </div>
        <div class="header-right">
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
                </div>
                <div>
                    <div style="font-weight: 600;"><?php echo e($_SESSION['user_name']); ?></div>
                    <div style="font-size: 0.8rem; opacity: 0.8;">Administrador</div>
                </div>
            </div>
            <a href="../login.php?logout=1" class="btn btn-danger btn-sm">Sair</a>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-menu">
            <div class="menu-section">
                <div class="menu-title">Principal</div>
                <a href="dashboard.php" class="menu-item">
                    <i>📊</i> Dashboard
                </a>
                <a href="products.php" class="menu-item">
                    <i>👡</i> Produtos
                </a>
                <a href="orders.php" class="menu-item">
                    <i>🛒</i> Pedidos
                </a>
                <a href="clients.php" class="menu-item">
                    <i>👥</i> Clientes B2B
                </a>
            </div>
            
            <div class="menu-section">
                <div class="menu-title">Estoque</div>
                <a href="stock.php" class="menu-item">
                    <i>📦</i> Controle de Estoque
                </a>
                <a href="movements.php" class="menu-item">
                    <i>📋</i> Movimentações
                </a>
            </div>
            
            <div class="menu-section">
                <div class="menu-title">Configurações</div>
                <a href="categories.php" class="menu-item">
                    <i>📁</i> Categorias
                </a>
                <a href="colors.php" class="menu-item active">
                    <i>🎨</i> Cores
                </a>
                <a href="sizes.php" class="menu-item">
                    <i>📏</i> Tamanhos
                </a>
            </div>
            
            <div class="menu-section">
                <div class="menu-title">Relatórios</div>
                <a href="reports.php" class="menu-item">
                    <i>📈</i> Relatórios
                </a>
                <a href="analytics.php" class="menu-item">
                    <i>📊</i> Analytics
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                <span>✅</span>
                <div><?php echo e($success); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <span>❌</span>
                <div><?php echo e($error); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>
            <!-- Page Header -->
            <div class="page-title">Gestão de Cores</div>
            <div class="page-subtitle">Defina as cores disponíveis para seus produtos</div>

            <!-- Actions Bar -->
            <div class="actions-bar">
                <div>
                    <a href="?action=create" class="btn btn-success">
                        <span>➕</span> Nova Cor
                    </a>
                </div>
                <div>
                    <a href="products.php" class="btn btn-primary">
                        <span>👡</span> Ver Produtos
                    </a>
                </div>
            </div>

            <!-- Colors Grid -->
            <div class="colors-grid">
                <?php foreach ($cores as $cor): ?>
                    <div class="color-card">
                        <div class="color-preview" style="background-color: <?php echo e($cor['codigo_hex'] ?: '#cccccc'); ?>">
                            <?php echo e($cor['nome']); ?>
                        </div>
                        <div class="color-info">
                            <div class="color-name"><?php echo e($cor['nome']); ?></div>
                            <div class="color-hex"><?php echo e($cor['codigo_hex'] ?: 'Sem código'); ?></div>
                            <div class="color-status status-<?php echo $cor['ativo'] ? 'ativo' : 'inativo'; ?>">
                                <?php echo $cor['ativo'] ? 'Ativa' : 'Inativa'; ?>
                            </div>
                            
                            <div class="color-stats">
                                <div>
                                    <strong><?php echo number_format($cor['total_produtos']); ?></strong> produto(s)
                                </div>
                                <div>
                                    Criada em <?php echo formatDate($cor['data_criacao']); ?>
                                </div>
                            </div>
                            
                            <div class="color-actions">
                                <a href="?action=edit&id=<?php echo $cor['id']; ?>" class="btn btn-primary btn-sm">
                                    ✏️ Editar
                                </a>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <button type="submit" class="btn btn-warning btn-sm"
                                            onclick="return confirm('Alterar status da cor?')">
                                        <?php echo $cor['ativo'] ? '⏸️ Inativar' : '▶️ Ativar'; ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($action === 'create' || $action === 'edit'): ?>
            <!-- Color Form -->
            <div class="form-card">
                <div class="form-header">
                    <div class="form-title">
                        <?php echo $action === 'create' ? '➕ Nova Cor' : '✏️ Editar Cor'; ?>
                    </div>
                    <div style="color: #6c757d;">
                        <?php echo $action === 'create' ? 'Adicione uma nova cor ao catálogo' : 'Modifique as informações da cor'; ?>
                    </div>
                </div>

                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nome da Cor *</label>
                            <input type="text" name="nome" required 
                                   value="<?php echo e($cor_atual['nome'] ?? ''); ?>"
                                   placeholder="Ex: Azul Marinho">
                        </div>
                        
                        <div class="form-group">
                            <label>Código da Cor</label>
                            <div class="color-picker-wrapper">
                                <input type="text" name="codigo_hex" 
                                       value="<?php echo e($cor_atual['codigo_hex'] ?? '#000000'); ?>"
                                       placeholder="#000000" maxlength="7" id="hex-input">
                                <input type="color" class="color-picker" id="color-picker"
                                       value="<?php echo e($cor_atual['codigo_hex'] ?? '#000000'); ?>">
                            </div>
                            <small style="color: #6c757d;">Formato: #RRGGBB (ex: #FF0000 para vermelho)</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Pré-visualização</label>
                        <div class="color-preview-large" id="color-preview" 
                             style="background-color: <?php echo e($cor_atual['codigo_hex'] ?? '#000000'); ?>">
                            Cor Selecionada
                        </div>
                    </div>

                    <div style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #f0f0f0;">
                        <button type="submit" class="btn btn-success">
                            <?php echo $action === 'create' ? '💾 Criar Cor' : '💾 Atualizar Cor'; ?>
                        </button>
                        <a href="?" class="btn btn-secondary">❌ Cancelar</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Toggle sidebar para mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('open');
        }

        // Responsivo
        function checkScreenSize() {
            const menuToggle = document.querySelector('.menu-toggle');
            if (window.innerWidth <= 1200) {
                menuToggle.style.display = 'block';
            } else {
                menuToggle.style.display = 'none';
                document.getElementById('sidebar').classList.remove('open');
            }
        }

        window.addEventListener('resize', checkScreenSize);
        checkScreenSize();

        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.style.display = 'none', 300);
            });
        }, 5000);

        // Color picker functionality
        document.addEventListener('DOMContentLoaded', function() {
            const hexInput = document.getElementById('hex-input');
            const colorPicker = document.getElementById('color-picker');
            const colorPreview = document.getElementById('color-preview');
            
            if (hexInput && colorPicker && colorPreview) {
                function updatePreview(color) {
                    colorPreview.style.backgroundColor = color;
                    // Determine text color based on background brightness
                    const rgb = parseInt(color.slice(1), 16);
                    const r = (rgb >> 16) & 0xff;
                    const g = (rgb >>  8) & 0xff;
                    const b = (rgb >>  0) & 0xff;
                    const brightness = (r * 299 + g * 587 + b * 114) / 1000;
                    colorPreview.style.color = brightness > 128 ? '#000' : '#fff';
                }
                
                hexInput.addEventListener('input', function() {
                    const value = this.value;
                    if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
                        colorPicker.value = value;
                        updatePreview(value);
                    }
                });
                
                colorPicker.addEventListener('input', function() {
                    hexInput.value = this.value;
                    updatePreview(this.value);
                });
                
                // Initial preview update
                updatePreview(hexInput.value || '#000000');
            }

            // Form validation
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const nome = form.querySelector('input[name="nome"]').value.trim();
                    const hex = form.querySelector('input[name="codigo_hex"]').value.trim();
                    
                    if (!nome) {
                        e.preventDefault();
                        alert('Nome da cor é obrigatório');
                        return;
                    }
                    
                    if (hex && !/^#[0-9A-Fa-f]{6}$/.test(hex)) {
                        e.preventDefault();
                        alert('Código de cor inválido. Use formato #RRGGBB');
                        return;
                    }
                    
                    const submitBtn = form.querySelector('button[type="submit"]');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '⏳ Salvando...';
                });
            }
        });
    </script>
</body>
</html>