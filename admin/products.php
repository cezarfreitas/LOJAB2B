<?php
// admin/products.php - Versão Profissional Completa
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar se está na pasta admin
if (!file_exists('../config/database.php')) {
    die('Erro: Execute este arquivo da pasta admin/');
}

// Incluir arquivos necessários
require_once '../config/database.php';
require_once '../config/functions.php';

// Verificar autenticação
requireAdmin();

$db = new Database();
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$success = '';
$error = '';

// Paginação
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'create':
        case 'edit':
            try {
                $nome = sanitizeInput($_POST['nome']);
                $preco = floatval($_POST['preco_base']);
                $categoria_id = intval($_POST['categoria_id']);
                $genero = $_POST['genero'];
                $descricao = sanitizeInput($_POST['descricao'] ?? '');
                $marca = sanitizeInput($_POST['marca'] ?? '');
                $material = $_POST['material'] ?? '';
                $codigo = $action === 'create' ? generateProductCode($nome) : sanitizeInput($_POST['codigo_produto']);
                
                if (empty($nome) || $preco <= 0 || empty($categoria_id)) {
                    throw new Exception('Nome, preço e categoria são obrigatórios');
                }
                
                // Upload de imagem
                $imagemNome = $_POST['imagem_atual'] ?? null;
                if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === 0) {
                    $imagemNome = uploadImage($_FILES['imagem'], '../uploads/products/');
                }
                
                if ($action === 'create') {
                    $sql = "INSERT INTO produtos (nome, codigo_produto, preco_base, categoria_id, genero, descricao, marca, material, imagem_principal) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $params = [$nome, $codigo, $preco, $categoria_id, $genero, $descricao, $marca, $material, $imagemNome];
                    $db->query($sql, $params);
                    
                    logActivity($_SESSION['user_id'], 'produto_criado', "Produto: {$nome}");
                    $success = 'Produto criado com sucesso!';
                } else {
                    $sql = "UPDATE produtos SET nome = ?, preco_base = ?, categoria_id = ?, genero = ?, descricao = ?, marca = ?, material = ?, imagem_principal = ? WHERE id = ?";
                    $params = [$nome, $preco, $categoria_id, $genero, $descricao, $marca, $material, $imagemNome, $id];
                    $db->query($sql, $params);
                    
                    logActivity($_SESSION['user_id'], 'produto_editado', "Produto ID: {$id}");
                    $success = 'Produto atualizado com sucesso!';
                }
                
                $action = 'list';
            } catch (Exception $e) {
                $error = 'Erro: ' . $e->getMessage();
            }
            break;
            
        case 'toggle_status':
            try {
                $sql = "UPDATE produtos SET ativo = NOT ativo WHERE id = ?";
                $db->query($sql, [$id]);
                logActivity($_SESSION['user_id'], 'produto_status_alterado', "Produto ID: {$id}");
                $success = 'Status do produto alterado com sucesso!';
                $action = 'list';
            } catch (Exception $e) {
                $error = 'Erro: ' . $e->getMessage();
            }
            break;
    }
}

// Ação de deletar (GET)
if ($action === 'delete' && $id) {
    try {
        $sql = "UPDATE produtos SET ativo = 0 WHERE id = ?";
        $db->query($sql, [$id]);
        logActivity($_SESSION['user_id'], 'produto_removido', "Produto ID: {$id}");
        $success = 'Produto removido com sucesso!';
        $action = 'list';
    } catch (Exception $e) {
        $error = 'Erro: ' . $e->getMessage();
    }
}

// Criar tabelas se não existirem
$db->query("CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    ativo BOOLEAN DEFAULT TRUE,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

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
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id)
)");

// Inserir categorias padrão se não existirem
$categorias_padrao = [
    'Chinelos Básicos' => 'Chinelos simples para uso diário',
    'Chinelos Conforto' => 'Chinelos com palmilha anatômica',
    'Chinelos Esportivos' => 'Chinelos para atividades esportivas',
    'Chinelos Luxo' => 'Chinelos premium com acabamento especial'
];

foreach ($categorias_padrao as $nome => $desc) {
    $db->query("INSERT IGNORE INTO categorias (nome, descricao) VALUES (?, ?)", [$nome, $desc]);
}

// Buscar dados
$categorias = $db->query("SELECT * FROM categorias WHERE ativo = 1 ORDER BY nome")->fetchAll();

// Filtros
$filtros = [
    'busca' => $_GET['busca'] ?? '',
    'categoria' => $_GET['categoria'] ?? '',
    'genero' => $_GET['genero'] ?? '',
    'status' => $_GET['status'] ?? 'ativo'
];

// Construir query com filtros
$where = "WHERE 1=1";
$params = [];

if (!empty($filtros['busca'])) {
    $where .= " AND (p.nome LIKE ? OR p.codigo_produto LIKE ?)";
    $busca = '%' . $filtros['busca'] . '%';
    $params[] = $busca;
    $params[] = $busca;
}

if (!empty($filtros['categoria'])) {
    $where .= " AND p.categoria_id = ?";
    $params[] = $filtros['categoria'];
}

if (!empty($filtros['genero'])) {
    $where .= " AND p.genero = ?";
    $params[] = $filtros['genero'];
}

if ($filtros['status'] === 'ativo') {
    $where .= " AND p.ativo = 1";
} elseif ($filtros['status'] === 'inativo') {
    $where .= " AND p.ativo = 0";
}

// Total de produtos (para paginação)
$sql_count = "SELECT COUNT(*) as total FROM produtos p {$where}";
$total_produtos = $db->query($sql_count, $params)->fetch()['total'];
$total_pages = ceil($total_produtos / $limit);

// Buscar produtos
$sql = "SELECT p.*, c.nome as categoria_nome 
        FROM produtos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id 
        {$where}
        ORDER BY p.data_criacao DESC 
        LIMIT {$limit} OFFSET {$offset}";

$produtos = $db->query($sql, $params)->fetchAll();

// Para formulário de edição
$produto_atual = null;
if ($action === 'edit' && $id) {
    $sql = "SELECT p.*, c.nome as categoria_nome FROM produtos p LEFT JOIN categorias c ON p.categoria_id = c.id WHERE p.id = ?";
    $produto_atual = $db->query($sql, [$id])->fetch();
    if (!$produto_atual) {
        $error = 'Produto não encontrado';
        $action = 'list';
    }
}

// Estatísticas
$stats = [
    'total' => $db->query("SELECT COUNT(*) as total FROM produtos WHERE ativo = 1")->fetch()['total'],
    'categorias' => $db->query("SELECT COUNT(DISTINCT categoria_id) as total FROM produtos WHERE ativo = 1")->fetch()['total'],
    'valor_total' => $db->query("SELECT SUM(preco_base) as total FROM produtos WHERE ativo = 1")->fetch()['total'] ?? 0,
    'sem_imagem' => $db->query("SELECT COUNT(*) as total FROM produtos WHERE ativo = 1 AND (imagem_principal IS NULL OR imagem_principal = '')")->fetch()['total']
];
$stats['preco_medio'] = $stats['total'] > 0 ? $stats['valor_total'] / $stats['total'] : 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Produtos - Catálogo B2B</title>
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

        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .filters-section {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
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

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .product-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .product-image {
            height: 200px;
            background: linear-gradient(45deg, #f0f0f0, #e0e0e0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #999;
            position: relative;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-status {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
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

        .product-info {
            padding: 1.5rem;
        }

        .product-name {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .product-code {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }

        .product-category {
            font-size: 0.85rem;
            color: #667eea;
            margin-bottom: 1rem;
        }

        .product-price {
            font-size: 1.3rem;
            font-weight: bold;
            color: #28a745;
            margin-bottom: 1rem;
        }

        .product-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            color: #6c757d;
        }

        .product-actions {
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
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin: 2rem 0;
        }

        .pagination a,
        .pagination span {
            padding: 0.5rem 1rem;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
        }

        .pagination a:hover {
            background: #e9ecef;
        }

        .pagination .current {
            background: #667eea;
            color: white;
            border-color: #667eea;
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
            .actions-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .products-grid {
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
            <div class="breadcrumb">Dashboard / Produtos</div>
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
                <a href="products.php" class="menu-item active">
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
            <div class="page-title">Gestão de Produtos</div>
            <div class="page-subtitle">Gerencie seu catálogo de chinelos</div>

            <!-- Actions Bar -->
            <div class="actions-bar">
                <div>
                    <a href="?action=create" class="btn btn-success">
                        <span>➕</span> Novo Produto
                    </a>
                    <a href="?action=import" class="btn btn-primary">
                        <span>📥</span> Importar
                    </a>
                </div>
                <div>
                    <a href="?action=export" class="btn btn-secondary">
                        <span>📤</span> Exportar
                    </a>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card" style="--card-color: #667eea;">
                    <div class="stat-header">
                        <div class="stat-title">Total de Produtos</div>
                        <div class="stat-icon">👡</div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                    <div style="color: #6c757d; font-size: 0.85rem;">Produtos ativos</div>
                </div>

                <div class="stat-card" style="--card-color: #f39c12;">
                    <div class="stat-header">
                        <div class="stat-title">Categorias</div>
                        <div class="stat-icon">📁</div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['categorias']); ?></div>
                    <div style="color: #6c757d; font-size: 0.85rem;">Diferentes categorias</div>
                </div>

                <div class="stat-card" style="--card-color: #27ae60;">
                    <div class="stat-header">
                        <div class="stat-title">Preço Médio</div>
                        <div class="stat-icon">💰</div>
                    </div>
                    <div class="stat-value"><?php echo formatPrice($stats['preco_medio']); ?></div>
                    <div style="color: #6c757d; font-size: 0.85rem;">Valor médio</div>
                </div>

                <div class="stat-card" style="--card-color: #e74c3c;">
                    <div class="stat-header">
                        <div class="stat-title">Sem Imagem</div>
                        <div class="stat-icon">🖼️</div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['sem_imagem']); ?></div>
                    <div style="color: #6c757d; font-size: 0.85rem;">Precisam de foto</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <h3 style="margin-bottom: 1rem;">🔍 Filtros</h3>
                <form method="GET">
                    <div class="filters-grid">
                        <div class="form-group">
                            <label>Buscar</label>
                            <input type="text" name="busca" placeholder="Nome ou código..." 
                                   value="<?php echo e($filtros['busca']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Categoria</label>
                            <select name="categoria">
                                <option value="">Todas as categorias</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" 
                                            <?php echo $filtros['categoria'] == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($cat['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Gênero</label>
                            <select name="genero">
                                <option value="">Todos os gêneros</option>
                                <option value="masculino" <?php echo $filtros['genero'] === 'masculino' ? 'selected' : ''; ?>>Masculino</option>
                                <option value="feminino" <?php echo $filtros['genero'] === 'feminino' ? 'selected' : ''; ?>>Feminino</option>
                                <option value="unissex" <?php echo $filtros['genero'] === 'unissex' ? 'selected' : ''; ?>>Unissex</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="ativo" <?php echo $filtros['status'] === 'ativo' ? 'selected' : ''; ?>>Ativos</option>
                                <option value="inativo" <?php echo $filtros['status'] === 'inativo' ? 'selected' : ''; ?>>Inativos</option>
                                <option value="todos" <?php echo $filtros['status'] === 'todos' ? 'selected' : ''; ?>>Todos</option>
                            </select>
                        </div>
                    </div>
                    <div style="margin-top: 1rem;">
                        <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                        <a href="?" class="btn btn-secondary">🔄 Limpar</a>
                    </div>
                </form>
            </div>

            <!-- Products Grid -->
            <?php if (empty($produtos)): ?>
                <div class="empty-state">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">📦</div>
                    <h3>Nenhum produto encontrado</h3>
                    <p>Comece criando seu primeiro produto ou ajuste os filtros</p>
                    <a href="?action=create" class="btn btn-success" style="margin-top: 1rem;">
                        ➕ Criar Primeiro Produto
                    </a>
                </div>
            <?php else: ?>
                <div class="products-grid">
                    <?php foreach ($produtos as $produto): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <?php if ($produto['imagem_principal']): ?>
                                    <img src="../uploads/products/<?php echo e($produto['imagem_principal']); ?>" 
                                         alt="<?php echo e($produto['nome']); ?>">
                                <?php else: ?>
                                    👡
                                <?php endif; ?>
                                <div class="product-status status-<?php echo $produto['ativo'] ? 'ativo' : 'inativo'; ?>">
                                    <?php echo $produto['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                </div>
                            </div>
                            <div class="product-info">
                                <div class="product-name"><?php echo e($produto['nome']); ?></div>
                                <div class="product-code"><?php echo e($produto['codigo_produto']); ?></div>
                                <div class="product-category"><?php echo e($produto['categoria_nome']); ?></div>
                                <div class="product-price"><?php echo formatPrice($produto['preco_base']); ?></div>
                                
                                <div class="product-meta">
                                    <div><strong>Marca:</strong> <?php echo e($produto['marca'] ?: 'N/A'); ?></div>
                                    <div><strong>Gênero:</strong> <?php echo ucfirst($produto['genero']); ?></div>
                                    <div><strong>Material:</strong> <?php echo e($produto['material'] ?: 'N/A'); ?></div>
                                    <div><strong>Criado:</strong> <?php echo formatDate($produto['data_criacao']); ?></div>
                                </div>
                                
                                <div class="product-actions">
                                    <a href="?action=edit&id=<?php echo $produto['id']; ?>" class="btn btn-primary btn-sm">
                                        ✏️ Editar
                                    </a>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <button type="submit" class="btn btn-warning btn-sm"
                                                onclick="return confirm('Alterar status do produto?')">
                                            <?php echo $produto['ativo'] ? '⏸️ Inativar' : '▶️ Ativar'; ?>
                                        </button>
                                    </form>
                                    <a href="?action=delete&id=<?php echo $produto['id']; ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Tem certeza que deseja remover este produto?')">
                                        🗑️ Excluir
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($filtros); ?>">« Anterior</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&<?php echo http_build_query($filtros); ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($filtros); ?>">Próximo »</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

        <?php elseif ($action === 'create' || $action === 'edit'): ?>
            <!-- Product Form -->
            <div class="form-card">
                <div class="form-header">
                    <div class="form-title">
                        <?php echo $action === 'create' ? '➕ Novo Produto' : '✏️ Editar Produto'; ?>
                    </div>
                    <div style="color: #6c757d;">
                        <?php echo $action === 'create' ? 'Adicione um novo produto ao catálogo' : 'Modifique as informações do produto'; ?>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nome do Produto *</label>
                            <input type="text" name="nome" required 
                                   value="<?php echo e($produto_atual['nome'] ?? ''); ?>"
                                   placeholder="Ex: Chinelo Conforto Masculino">
                        </div>
                        
                        <?php if ($action === 'edit'): ?>
                            <div class="form-group">
                                <label>Código do Produto</label>
                                <input type="text" name="codigo_produto" readonly
                                       value="<?php echo e($produto_atual['codigo_produto'] ?? ''); ?>">
                            </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label>Categoria *</label>
                            <select name="categoria_id" required>
                                <option value="">Selecione uma categoria</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"
                                            <?php echo ($produto_atual['categoria_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($cat['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Preço Base (R$) *</label>
                            <input type="number" step="0.01" name="preco_base" required 
                                   value="<?php echo $produto_atual['preco_base'] ?? ''; ?>"
                                   placeholder="0.00" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label>Marca</label>
                            <input type="text" name="marca" 
                                   value="<?php echo e($produto_atual['marca'] ?? ''); ?>"
                                   placeholder="Ex: Nike, Adidas">
                        </div>
                        
                        <div class="form-group">
                            <label>Material</label>
                            <select name="material">
                                <option value="">Selecione o material</option>
                                <option value="EVA" <?php echo ($produto_atual['material'] ?? '') === 'EVA' ? 'selected' : ''; ?>>EVA</option>
                                <option value="Borracha" <?php echo ($produto_atual['material'] ?? '') === 'Borracha' ? 'selected' : ''; ?>>Borracha</option>
                                <option value="Couro" <?php echo ($produto_atual['material'] ?? '') === 'Couro' ? 'selected' : ''; ?>>Couro</option>
                                <option value="Sintético" <?php echo ($produto_atual['material'] ?? '') === 'Sintético' ? 'selected' : ''; ?>>Sintético</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Gênero *</label>
                            <select name="genero" required>
                                <option value="">Selecione o gênero</option>
                                <option value="masculino" <?php echo ($produto_atual['genero'] ?? '') === 'masculino' ? 'selected' : ''; ?>>Masculino</option>
                                <option value="feminino" <?php echo ($produto_atual['genero'] ?? '') === 'feminino' ? 'selected' : ''; ?>>Feminino</option>
                                <option value="unissex" <?php echo ($produto_atual['genero'] ?? '') === 'unissex' ? 'selected' : ''; ?>>Unissex</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Imagem Principal</label>
                            <input type="file" name="imagem" accept="image/*">
                            <?php if ($action === 'edit' && $produto_atual['imagem_principal']): ?>
                                <input type="hidden" name="imagem_atual" value="<?php echo $produto_atual['imagem_principal']; ?>">
                                <div style="margin-top: 0.5rem;">
                                    <img src="../uploads/products/<?php echo e($produto_atual['imagem_principal']); ?>" 
                                         style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px;">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Descrição</label>
                        <textarea name="descricao" placeholder="Descreva as características do produto..."><?php echo e($produto_atual['descricao'] ?? ''); ?></textarea>
                    </div>

                    <div style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #f0f0f0;">
                        <button type="submit" class="btn btn-success">
                            <?php echo $action === 'create' ? '💾 Criar Produto' : '💾 Atualizar Produto'; ?>
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

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.style.display = 'none', 300);
            });
        }, 5000);

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form[enctype]');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const nome = form.querySelector('input[name="nome"]').value.trim();
                    const preco = form.querySelector('input[name="preco_base"]').value;
                    const categoria = form.querySelector('select[name="categoria_id"]').value;
                    
                    if (!nome || !preco || !categoria) {
                        e.preventDefault();
                        alert('Por favor, preencha todos os campos obrigatórios (*)');
                        return;
                    }
                    
                    if (parseFloat(preco) <= 0) {
                        e.preventDefault();
                        alert('O preço deve ser maior que zero');
                        return;
                    }
                    
                    // Disable submit button to prevent double submission
                    const submitBtn = form.querySelector('button[type="submit"]');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '⏳ Salvando...';
                });
            }
        });

        // Preview image on file select
        const imageInput = document.querySelector('input[name="imagem"]');
        if (imageInput) {
            imageInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        let preview = document.getElementById('image-preview');
                        if (!preview) {
                            preview = document.createElement('div');
                            preview.id = 'image-preview';
                            preview.style.marginTop = '0.5rem';
                            imageInput.parentNode.appendChild(preview);
                        }
                        preview.innerHTML = `
                            <img src="${e.target.result}" 
                                 style="width: 150px; height: 150px; object-fit: cover; border-radius: 8px; border: 2px solid #e9ecef;">
                        `;
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    </script>
</body>
</html>