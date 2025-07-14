<?php
// admin/dashboard.php - Dashboard Atualizado e Integrado
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../config/functions.php';
requireAdmin();

$db = new Database();

// Buscar estatísticas do sistema
try {
    // Criar tabelas se não existirem
    $db->query("CREATE TABLE IF NOT EXISTS produtos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        categoria_id INT,
        nome VARCHAR(200) NOT NULL,
        codigo_produto VARCHAR(50) UNIQUE NOT NULL,
        preco_base DECIMAL(10,2) NOT NULL,
        genero ENUM('masculino', 'feminino', 'unissex') DEFAULT 'unissex',
        descricao TEXT,
        marca VARCHAR(100),
        material VARCHAR(100),
        imagem_principal VARCHAR(255),
        ativo BOOLEAN DEFAULT TRUE,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $db->query("CREATE TABLE IF NOT EXISTS categorias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        descricao TEXT,
        ativo BOOLEAN DEFAULT TRUE,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Estatísticas
    $stats = [
        'produtos' => $db->query("SELECT COUNT(*) as total FROM produtos WHERE ativo = 1")->fetch()['total'] ?? 0,
        'categorias' => $db->query("SELECT COUNT(*) as total FROM categorias WHERE ativo = 1")->fetch()['total'] ?? 0,
        'produtos_mes' => $db->query("SELECT COUNT(*) as total FROM produtos WHERE ativo = 1 AND MONTH(data_criacao) = MONTH(CURRENT_DATE) AND YEAR(data_criacao) = YEAR(CURRENT_DATE)")->fetch()['total'] ?? 0,
        'valor_total' => $db->query("SELECT SUM(preco_base) as total FROM produtos WHERE ativo = 1")->fetch()['total'] ?? 0
    ];
    
    // Produtos recentes
    $produtos_recentes = $db->query("SELECT * FROM produtos WHERE ativo = 1 ORDER BY data_criacao DESC LIMIT 5")->fetchAll();
    
    // Produtos sem imagem
    $sem_imagem = $db->query("SELECT COUNT(*) as total FROM produtos WHERE ativo = 1 AND (imagem_principal IS NULL OR imagem_principal = '')")->fetch()['total'] ?? 0;
    
    // Categorias mais usadas
    $top_categorias = $db->query("SELECT c.nome, COUNT(p.id) as total 
                                  FROM categorias c 
                                  LEFT JOIN produtos p ON c.id = p.categoria_id AND p.ativo = 1
                                  WHERE c.ativo = 1
                                  GROUP BY c.id 
                                  ORDER BY total DESC 
                                  LIMIT 3")->fetchAll();
    
} catch (Exception $e) {
    $stats = ['produtos' => 0, 'categorias' => 0, 'produtos_mes' => 0, 'valor_total' => 0];
    $produtos_recentes = [];
    $sem_imagem = 0;
    $top_categorias = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrativo - Catálogo B2B</title>
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--card-color, #667eea);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-title {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 500;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--card-color, #667eea);
            color: white;
            font-size: 1.2rem;
        }

        .stat-value {
            font-size: 2.2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .stat-change {
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .stat-change.positive {
            color: #28a745;
        }

        .stat-change.neutral {
            color: #6c757d;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
        }

        .btn {
            padding: 0.5rem 1rem;
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

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
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

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .quick-action {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: white;
            border-radius: 10px;
            text-decoration: none;
            color: #333;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }

        .quick-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            color: #667eea;
        }

        .action-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .product-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .product-item:last-child {
            border-bottom: none;
        }

        .product-image {
            width: 50px;
            height: 50px;
            background: #f0f0f0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.5rem;
        }

        .product-info {
            flex: 1;
        }

        .product-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .product-meta {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .product-price {
            font-weight: 600;
            color: #28a745;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
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
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            
            .quick-actions {
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
            <div class="breadcrumb">Dashboard / Administração</div>
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
                <a href="dashboard.php" class="menu-item active">
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
        <!-- Page Header -->
        <div class="page-title">Dashboard</div>
        <div class="page-subtitle">Bem-vindo ao painel administrativo do Catálogo B2B</div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="products.php?action=create" class="quick-action">
                <div class="action-icon" style="background: #28a745;">➕</div>
                <div>
                    <div style="font-weight: 600;">Novo Produto</div>
                    <div style="font-size: 0.8rem; color: #6c757d;">Adicionar ao catálogo</div>
                </div>
            </a>
            
            <a href="categories.php?action=create" class="quick-action">
                <div class="action-icon" style="background: #f39c12;">📁</div>
                <div>
                    <div style="font-weight: 600;">Nova Categoria</div>
                    <div style="font-size: 0.8rem; color: #6c757d;">Organizar produtos</div>
                </div>
            </a>
            
            <a href="orders.php?status=pendente" class="quick-action">
                <div class="action-icon" style="background: #dc3545;">🛒</div>
                <div>
                    <div style="font-weight: 600;">Pedidos Pendentes</div>
                    <div style="font-size: 0.8rem; color: #6c757d;">Requer atenção</div>
                </div>
            </a>
            
            <a href="reports.php" class="quick-action">
                <div class="action-icon" style="background: #6f42c1;">📊</div>
                <div>
                    <div style="font-weight: 600;">Relatórios</div>
                    <div style="font-size: 0.8rem; color: #6c757d;">Análises e dados</div>
                </div>
            </a>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card" style="--card-color: #667eea;">
                <div class="stat-header">
                    <div class="stat-title">Total de Produtos</div>
                    <div class="stat-icon">👡</div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['produtos']); ?></div>
                <div class="stat-change <?php echo $stats['produtos_mes'] > 0 ? 'positive' : 'neutral'; ?>">
                    <span><?php echo $stats['produtos_mes'] > 0 ? '↗' : '→'; ?></span>
                    +<?php echo $stats['produtos_mes']; ?> este mês
                </div>
            </div>

            <div class="stat-card" style="--card-color: #f39c12;">
                <div class="stat-header">
                    <div class="stat-title">Categorias Ativas</div>
                    <div class="stat-icon">📁</div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['categorias']); ?></div>
                <div class="stat-change neutral">
                    <span>📂</span>
                    Organizando produtos
                </div>
            </div>

            <div class="stat-card" style="--card-color: #27ae60;">
                <div class="stat-header">
                    <div class="stat-title">Valor do Estoque</div>
                    <div class="stat-icon">💰</div>
                </div>
                <div class="stat-value"><?php echo formatPrice($stats['valor_total']); ?></div>
                <div class="stat-change positive">
                    <span>💎</span>
                    Patrimônio em produtos
                </div>
            </div>

            <div class="stat-card" style="--card-color: #e74c3c;">
                <div class="stat-header">
                    <div class="stat-title">Sem Imagem</div>
                    <div class="stat-icon">🖼️</div>
                </div>
                <div class="stat-value"><?php echo number_format($sem_imagem); ?></div>
                <div class="stat-change <?php echo $sem_imagem > 0 ? 'neutral' : 'positive'; ?>">
                    <span><?php echo $sem_imagem > 0 ? '⚠️' : '✅'; ?></span>
                    <?php echo $sem_imagem > 0 ? 'Precisam de foto' : 'Tudo em ordem'; ?>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($sem_imagem > 0): ?>
            <div class="alert alert-warning">
                <span>📸</span>
                <div>
                    <strong>Atenção!</strong> 
                    Você tem <?php echo $sem_imagem; ?> produto(s) sem imagem. 
                    <a href="products.php" style="color: #856404; text-decoration: underline;">Ver produtos</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Recent Products -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Produtos Recentes</div>
                    <a href="products.php" class="btn btn-primary btn-sm">Ver Todos</a>
                </div>
                
                <?php if (empty($produtos_recentes)): ?>
                    <div style="text-align: center; padding: 2rem; color: #6c757d;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📦</div>
                        <p>Nenhum produto cadastrado ainda.</p>
                        <a href="products.php?action=create" class="btn btn-success" style="margin-top: 1rem;">
                            ➕ Criar Primeiro Produto
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($produtos_recentes as $produto): ?>
                        <div class="product-item">
                            <div class="product-image">
                                <?php if ($produto['imagem_principal']): ?>
                                    <img src="../uploads/products/<?php echo e($produto['imagem_principal']); ?>" 
                                         style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                                <?php else: ?>
                                    👡
                                <?php endif; ?>
                            </div>
                            <div class="product-info">
                                <div class="product-name"><?php echo e($produto['nome']); ?></div>
                                <div class="product-meta">
                                    <?php echo e($produto['codigo_produto']); ?> • 
                                    <?php echo ucfirst($produto['genero']); ?> • 
                                    <?php echo formatDate($produto['data_criacao']); ?>
                                </div>
                            </div>
                            <div class="product-price">
                                <?php echo formatPrice($produto['preco_base']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Top Categories -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Top Categorias</div>
                    <a href="categories.php" class="btn btn-primary btn-sm">Gerenciar</a>
                </div>
                
                <?php if (empty($top_categorias)): ?>
                    <div style="text-align: center; padding: 2rem; color: #6c757d;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📁</div>
                        <p>Nenhuma categoria cadastrada ainda.</p>
                        <a href="categories.php?action=create" class="btn btn-success" style="margin-top: 1rem;">
                            ➕ Criar Categoria
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($top_categorias as $categoria): ?>
                        <div class="product-item">
                            <div class="product-image" style="background: #667eea; color: white;">
                                📁
                            </div>
                            <div class="product-info">
                                <div class="product-name"><?php echo e($categoria['nome']); ?></div>
                                <div class="product-meta">
                                    <?php echo $categoria['total']; ?> produto(s)
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- System Status -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Status do Sistema</div>
                <div style="color: #28a745; font-size: 0.9rem;">🟢 Online</div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div style="text-align: center; padding: 1rem;">
                    <div style="font-size: 1.5rem; color: #667eea; margin-bottom: 0.5rem;">⚡</div>
                    <div style="font-weight: 600;">Sistema</div>
                    <div style="font-size: 0.8rem; color: #28a745;">Funcionando</div>
                </div>
                
                <div style="text-align: center; padding: 1rem;">
                    <div style="font-size: 1.5rem; color: #667eea; margin-bottom: 0.5rem;">🗄️</div>
                    <div style="font-weight: 600;">Banco de Dados</div>
                    <div style="font-size: 0.8rem; color: #28a745;">Conectado</div>
                </div>
                
                <div style="text-align: center; padding: 1rem;">
                    <div style="font-size: 1.5rem; color: #667eea; margin-bottom: 0.5rem;">📁</div>
                    <div style="font-weight: 600;">Uploads</div>
                    <div style="font-size: 0.8rem; color: #28a745;">Disponível</div>
                </div>
                
                <div style="text-align: center; padding: 1rem;">
                    <div style="font-size: 1.5rem; color: #667eea; margin-bottom: 0.5rem;">🔐</div>
                    <div style="font-weight: 600;">Segurança</div>
                    <div style="font-size: 0.8rem; color: #28a745;">Ativa</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle sidebar para mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('open');
        }

        // Responsivo - mostrar botão de menu em telas pequenas
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

        // Fechar sidebar ao clicar fora (mobile)
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.querySelector('.menu-toggle');
            
            if (window.innerWidth <= 1200 && 
                sidebar.classList.contains('open') && 
                !sidebar.contains(e.target) && 
                !menuToggle.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });

        // Auto-atualizar dados a cada 5 minutos
        function updateDashboard() {
            // Em produção, aqui faria uma chamada AJAX para atualizar os dados
            console.log('Dashboard atualizado:', new Date().toLocaleTimeString());
        }

        setInterval(updateDashboard, 300000); // 5 minutos

        // Animação de carregamento das estatísticas
        document.addEventListener('DOMContentLoaded', function() {
            const statValues = document.querySelectorAll('.stat-value');
            statValues.forEach(stat => {
                const finalValue = parseInt(stat.textContent.replace(/[^\d]/g, ''));
                if (!isNaN(finalValue) && finalValue > 0) {
                    stat.textContent = '0';
                    let current = 0;
                    const increment = finalValue / 30;
                    const timer = setInterval(() => {
                        current += increment;
                        if (current >= finalValue) {
                            stat.textContent = finalValue.toLocaleString();
                            clearInterval(timer);
                        } else {
                            stat.textContent = Math.floor(current).toLocaleString();
                        }
                    }, 50);
                }
            });
        });
    </script>
</body>
</html>