<?php
// admin/categories.php - Gestão de Categorias
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

// Processar formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'create':
        case 'edit':
            try {
                $nome = sanitizeInput($_POST['nome']);
                $descricao = sanitizeInput($_POST['descricao'] ?? '');
                
                if (empty($nome)) {
                    throw new Exception('Nome da categoria é obrigatório');
                }
                
                if ($action === 'create') {
                    $sql = "INSERT INTO categorias (nome, descricao) VALUES (?, ?)";
                    $db->query($sql, [$nome, $descricao]);
                    $success = 'Categoria criada com sucesso!';
                } else {
                    $sql = "UPDATE categorias SET nome = ?, descricao = ? WHERE id = ?";
                    $db->query($sql, [$nome, $descricao, $id]);
                    $success = 'Categoria atualizada com sucesso!';
                }
                
                logActivity($_SESSION['user_id'], 'categoria_' . $action, "Categoria: {$nome}");
                $action = 'list';
            } catch (Exception $e) {
                $error = 'Erro: ' . $e->getMessage();
            }
            break;
            
        case 'toggle_status':
            try {
                $sql = "UPDATE categorias SET ativo = NOT ativo WHERE id = ?";
                $db->query($sql, [$id]);
                $success = 'Status da categoria alterado com sucesso!';
                $action = 'list';
            } catch (Exception $e) {
                $error = 'Erro: ' . $e->getMessage();
            }
            break;
    }
}

// Buscar categorias
$sql = "SELECT c.*, COUNT(p.id) as total_produtos 
        FROM categorias c 
        LEFT JOIN produtos p ON c.id = p.categoria_id AND p.ativo = 1
        GROUP BY c.id 
        ORDER BY c.nome";
$categorias = $db->query($sql)->fetchAll();

// Para edição
$categoria_atual = null;
if ($action === 'edit' && $id) {
    $sql = "SELECT * FROM categorias WHERE id = ?";
    $categoria_atual = $db->query($sql, [$id])->fetch();
    if (!$categoria_atual) {
        $error = 'Categoria não encontrada';
        $action = 'list';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Categorias - Catálogo B2B</title>
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

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .category-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }

        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .category-header {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .category-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .category-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-ativo {
            background: #d4edda;
            color: #155724;
        }

        .status-inativo {
            background: #f8d7da;
            color: #721c24;
        }

        .category-description {
            color: #6c757d;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .category-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .category-actions {
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

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
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
            .categories-grid {
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
            <div class="breadcrumb">Dashboard / Configurações / Categorias</div>
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
                <a href="categories.php" class="menu-item active">
                    <i>📁</i> Categorias
                </a>
                <a href="colors.php" class="menu-item">
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
            <div class="page-title">Gestão de Categorias</div>
            <div class="page-subtitle">Organize seus produtos em categorias</div>

            <!-- Actions Bar -->
            <div class="actions-bar">
                <div>
                    <a href="?action=create" class="btn btn-success">
                        <span>➕</span> Nova Categoria
                    </a>
                </div>
                <div>
                    <a href="products.php" class="btn btn-primary">
                        <span>👡</span> Ver Produtos
                    </a>
                </div>
            </div>

            <!-- Categories Grid -->
            <?php if (empty($categorias)): ?>
                <div class="empty-state">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">📁</div>
                    <h3>Nenhuma categoria cadastrada</h3>
                    <p>Comece criando sua primeira categoria de produtos</p>
                    <a href="?action=create" class="btn btn-success" style="margin-top: 1rem;">
                        ➕ Criar Primeira Categoria
                    </a>
                </div>
            <?php else: ?>
                <div class="categories-grid">
                    <?php foreach ($categorias as $categoria): ?>
                        <div class="category-card">
                            <div class="category-header">
                                <div>
                                    <div class="category-name"><?php echo e($categoria['nome']); ?></div>
                                    <div class="category-status status-<?php echo $categoria['ativo'] ? 'ativo' : 'inativo'; ?>">
                                        <?php echo $categoria['ativo'] ? 'Ativa' : 'Inativa'; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($categoria['descricao']): ?>
                                <div class="category-description">
                                    <?php echo e($categoria['descricao']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="category-stats">
                                <div>
                                    <strong><?php echo number_format($categoria['total_produtos']); ?></strong>
                                    <div style="font-size: 0.8rem; color: #6c757d;">Produtos</div>
                                </div>
                                <div>
                                    <strong><?php echo formatDate($categoria['data_criacao']); ?></strong>
                                    <div style="font-size: 0.8rem; color: #6c757d;">Criada em</div>
                                </div>
                            </div>
                            
                            <div class="category-actions">
                                <a href="?action=edit&id=<?php echo $categoria['id']; ?>" class="btn btn-primary btn-sm">
                                    ✏️ Editar
                                </a>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <button type="submit" class="btn btn-warning btn-sm"
                                            onclick="return confirm('Alterar status da categoria?')">
                                        <?php echo $categoria['ativo'] ? '⏸️ Inativar' : '▶️ Ativar'; ?>
                                    </button>
                                </form>
                                <a href="products.php?categoria=<?php echo $categoria['id']; ?>" class="btn btn-secondary btn-sm">
                                    👡 Ver Produtos
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php elseif ($action === 'create' || $action === 'edit'): ?>
            <!-- Category Form -->
            <div class="form-card">
                <div class="form-header">
                    <div class="form-title">
                        <?php echo $action === 'create' ? '➕ Nova Categoria' : '✏️ Editar Categoria'; ?>
                    </div>
                    <div style="color: #6c757d;">
                        <?php echo $action === 'create' ? 'Adicione uma nova categoria de produtos' : 'Modifique as informações da categoria'; ?>
                    </div>
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label>Nome da Categoria *</label>
                        <input type="text" name="nome" required 
                               value="<?php echo e($categoria_atual['nome'] ?? ''); ?>"
                               placeholder="Ex: Chinelos Conforto">
                    </div>
                    
                    <div class="form-group">
                        <label>Descrição</label>
                        <textarea name="descricao" 
                                  placeholder="Descreva esta categoria de produtos..."><?php echo e($categoria_atual['descricao'] ?? ''); ?></textarea>
                    </div>

                    <div style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #f0f0f0;">
                        <button type="submit" class="btn btn-success">
                            <?php echo $action === 'create' ? '💾 Criar Categoria' : '💾 Atualizar Categoria'; ?>
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

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const nome = form.querySelector('input[name="nome"]').value.trim();
                    
                    if (!nome) {
                        e.preventDefault();
                        alert('Nome da categoria é obrigatório');
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