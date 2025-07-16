<?php
// Incluir configurações e classes
require_once 'config/database.php';
require_once 'models/Product.php';
require_once 'models/Category.php';
require_once 'models/Color.php';
require_once 'models/Size.php';

// Inicializar classes
$product = new Product();
$category = new Category();
$color = new Color();
$size = new Size();

// Verificar se está logado (para funcionalidades do carrinho)


// Carrinho só funciona se logado
if ($isLoggedIn) {
    require_once 'models/Cart.php';
    $cart = new Cart();
    
    // Processar ações do carrinho via AJAX
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'add_to_cart') {
            $variantId = $_POST['variant_id'] ?? 0;
            $quantidade = $_POST['quantidade'] ?? 1;
            
            if ($variantId > 0) {
                try {
                    $cart->addItem($clienteId, $variantId, $quantidade);
                    echo json_encode(['success' => true, 'message' => 'Produto adicionado ao carrinho']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                exit;
            }
        }
        
        if ($action === 'update_cart') {
            $variantId = $_POST['variant_id'] ?? 0;
            $quantidade = $_POST['quantidade'] ?? 0;
            
            if ($variantId > 0) {
                try {
                    if ($quantidade > 0) {
                        $cart->updateQuantity($clienteId, $variantId, $quantidade);
                    } else {
                        $cart->removeItem($clienteId, $variantId);
                    }
                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                exit;
            }
        }
        
        if ($action === 'get_cart_info') {
            try {
                $cartItems = $cart->getItems($clienteId);
                $cartCount = $cart->getItemCount($clienteId);
                $cartTotal = $cart->getTotal($clienteId);
                
                echo json_encode([
                    'items' => $cartItems,
                    'count' => $cartCount,
                    'total' => $cartTotal
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
        }
    }
}

// Filtros de busca
$filters = [
    'search' => $_GET['busca'] ?? '',
    'categoria_id' => $_GET['categoria'] ?? '',
    'marca' => $_GET['marca'] ?? '',
    'preco_min' => $_GET['preco_min'] ?? '',
    'preco_max' => $_GET['preco_max'] ?? '',
    'orderBy' => $_GET['ordem'] ?? 'nome'
];

// Buscar dados
$produtos = $product->getAll($filters);
$categorias = $category->getWithProductCount();
$cores = $color->getAll();
$tamanhos = $size->getAll();

// Dados do carrinho (se logado)
$cartCount = 0;
$cartItems = [];
$nomeCliente = '';

if ($isLoggedIn) {
    $cartCount = $cart->getItemCount($clienteId);
    $cartItems = $cart->getItems($clienteId);
    
    // Buscar informações do cliente
    try {
        $db = new Database();
        $sql = "SELECT cb.razao_social FROM clientes_b2b cb WHERE cb.id = ?";
        $stmt = $db->query($sql, [$clienteId]);
        $clienteInfo = $stmt->fetch();
        $nomeCliente = $clienteInfo['razao_social'] ?? 'Cliente';
    } catch (Exception $e) {
        $nomeCliente = 'Cliente';
    }
}

// Função auxiliar para formatar preço
function formatPrice($price) {
    return 'R$ ' . number_format($price, 2, ',', '.');
}

// Função para escapar HTML
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal do Lojista - Catálogo B2B</title>
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

        /* Header */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .auth-links {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .auth-link {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            transition: all 0.3s;
            border: 1px solid rgba(255,255,255,0.3);
        }

        .auth-link:hover {
            background: rgba(255,255,255,0.1);
            transform: translateY(-2px);
        }

        .auth-link.primary {
            background: rgba(255,255,255,0.2);
            font-weight: 600;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(255,255,255,0.1);
            border-radius: 25px;
            font-size: 0.9rem;
        }

        .cart-icon {
            position: relative;
            background: rgba(255,255,255,0.1);
            padding: 0.75rem;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            color: white;
            font-size: 1.2rem;
        }

        .cart-icon:hover {
            background: rgba(255,255,255,0.2);
            transform: scale(1.05);
        }

        .cart-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: bold;
        }

        .cart-disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            padding: 3rem 0;
            text-align: center;
        }

        .hero-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .hero h1 {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 1rem;
        }

        .hero p {
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 2rem;
        }

        .hero-cta {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        /* Main Content */
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 2rem;
        }

        /* Sidebar Categories */
        .sidebar {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .sidebar h3 {
            margin-bottom: 1rem;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .category-list {
            list-style: none;
        }

        .category-item {
            margin-bottom: 0.5rem;
        }

        .category-link {
            display: block;
            padding: 0.75rem 1rem;
            text-decoration: none;
            color: #666;
            border-radius: 8px;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .category-link:hover, .category-link.active {
            background: #f8f9fa;
            color: #667eea;
            border-left-color: #667eea;
            transform: translateX(5px);
        }

        .category-count {
            float: right;
            background: #e9ecef;
            color: #666;
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
        }

        /* Product Area */
        .product-area {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        /* Search and Filters */
        .search-filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 2px solid #e9ecef;
            border-radius: 25px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        .filter-select {
            padding: 0.75rem 1rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-select:focus {
            outline: none;
            border-color: #667eea;
        }

        /* Product Grid */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .product-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
            cursor: pointer;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .product-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(45deg, #f1f2f6, #ddd);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #666;
            position: relative;
            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #667eea;
            color: white;
            padding: 0.3rem 0.6rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .product-info {
            padding: 1.25rem;
        }

        .product-name {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .product-code {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 0.75rem;
        }

        .product-variants {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .variant-color {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #fff;
            box-shadow: 0 0 0 1px #ddd;
            cursor: pointer;
            transition: all 0.3s;
        }

        .variant-color:hover {
            transform: scale(1.2);
            box-shadow: 0 0 0 2px #667eea;
        }

        .product-price {
            font-size: 1.3rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 1rem;
        }

        .price-range {
            font-size: 0.9rem;
            color: #666;
        }

        .product-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn {
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s;
            flex: 1;
            text-align: center;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-outline:hover {
            background: #667eea;
            color: white;
        }

        .btn-disabled {
            background: #e9ecef;
            color: #6c757d;
            cursor: not-allowed;
        }

        .btn-disabled:hover {
            transform: none;
        }

        /* Login Prompt */
        .login-prompt {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            text-align: center;
        }

        .login-prompt-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        /* Empty States */
        .empty-products {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        /* Stats Bar */
        .stats-bar {
            background: white;
            padding: 1rem;
            margin-bottom: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #667eea;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #666;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-container {
                grid-template-columns: 1fr;
                padding: 1rem;
            }

            .sidebar {
                order: 2;
                position: static;
            }

            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 1rem;
            }

            .search-filters {
                flex-direction: column;
                align-items: stretch;
            }

            .header-container {
                padding: 0 0.5rem;
            }

            .logo {
                font-size: 1.4rem;
            }

            .hero h1 {
                font-size: 2rem;
            }

            .hero p {
                font-size: 1rem;
            }

            .stats-bar {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-container">
            <div class="logo">
                👟 Portal do Lojista
            </div>
            <div class="header-right">
                <?php if ($isLoggedIn): ?>
                    <div class="user-info">
                        👤 <?php echo e($nomeCliente); ?>
                    </div>
                    <button class="cart-icon" onclick="toggleCart()">
                        🛒
                        <span class="cart-count"><?php echo $cartCount; ?></span>
                    </button>
                    <a href="login.php?logout=1" class="auth-link">Sair</a>
                <?php else: ?>
                    <div class="auth-links">
                        <a href="login.php" class="auth-link">Entrar</a>
                        <a href="register.php" class="auth-link primary">Cadastrar Empresa</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <?php if (!$isLoggedIn): ?>
    <section class="hero">
        <div class="hero-container">
            <h1>🛒 Catálogo B2B de Chinelos</h1>
            <p>Produtos de qualidade para revendedores e lojistas. Explore nosso catálogo completo.</p>
            <div class="hero-cta">
                <a href="login.php" class="btn btn-primary">Fazer Login</a>
                <a href="register.php" class="btn btn-outline">Cadastrar Empresa</a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Stats Bar -->
    <div class="main-container" style="padding-top: 1rem;">
        <div class="stats-bar" style="grid-column: 1 / -1;">
            <div class="stat-item">
                <div class="stat-number"><?php echo count($produtos); ?></div>
                <div class="stat-label">Produtos</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo count($categorias); ?></div>
                <div class="stat-label">Categorias</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo count($cores); ?></div>
                <div class="stat-label">Cores</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo count($tamanhos); ?></div>
                <div class="stat-label">Tamanhos</div>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Sidebar Categories -->
        <aside class="sidebar">
            <h3>📁 Categorias</h3>
            <ul class="category-list">
                <li class="category-item">
                    <a href="?" class="category-link <?php echo empty($filters['categoria_id']) ? 'active' : ''; ?>">
                        Todos os Produtos
                        <span class="category-count"><?php echo count($produtos); ?></span>
                    </a>
                </li>
                <?php foreach ($categorias as $cat): ?>
                <li class="category-item">
                    <a href="?categoria=<?php echo $cat['id']; ?>" 
                       class="category-link <?php echo $filters['categoria_id'] == $cat['id'] ? 'active' : ''; ?>">
                        <?php echo e($cat['nome']); ?>
                        <span class="category-count"><?php echo $cat['total_produtos']; ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>

            <h3 style="margin-top: 2rem;">🏷️ Filtros</h3>
            <form method="GET" style="margin-top: 1rem;">
                <?php if (!empty($filters['categoria_id'])): ?>
                <input type="hidden" name="categoria" value="<?php echo e($filters['categoria_id']); ?>">
                <?php endif; ?>
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Marca:</label>
                    <select name="marca" class="filter-select" style="width: 100%;">
                        <option value="">Todas as marcas</option>
                        <?php
                        // Buscar marcas disponíveis
                        $marcas = array_unique(array_filter(array_column($produtos, 'marca')));
                        foreach ($marcas as $marca): 
                        ?>
                        <option value="<?php echo e($marca); ?>" <?php echo $filters['marca'] === $marca ? 'selected' : ''; ?>>
                            <?php echo e($marca); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Preço Mín:</label>
                    <input type="number" name="preco_min" value="<?php echo e($filters['preco_min']); ?>" 
                           placeholder="0.00" step="0.01" class="filter-select" style="width: 100%;">
                </div>

                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Preço Máx:</label>
                    <input type="number" name="preco_max" value="<?php echo e($filters['preco_max']); ?>" 
                           placeholder="999.99" step="0.01" class="filter-select" style="width: 100%;">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">Aplicar Filtros</button>
            </form>
        </aside>

        <!-- Product Area -->
        <main class="product-area">
            <!-- Login Prompt for Purchase -->
            <?php if (!$isLoggedIn): ?>
            <div class="login-prompt">
                <div class="login-prompt-icon">🔐</div>
                <strong>Para comprar produtos:</strong> 
                <a href="login.php" style="color: #667eea; text-decoration: none;">Faça login</a> ou 
                <a href="register.php" style="color: #667eea; text-decoration: none;">cadastre sua empresa</a>
            </div>
            <?php endif; ?>

            <!-- Search and Filters -->
            <div class="search-filters">
                <form method="GET" style="display: contents;">
                    <div class="search-box">
                        <span class="search-icon">🔍</span>
                        <input type="text" name="busca" class="search-input" 
                               value="<?php echo e($filters['search']); ?>"
                               placeholder="Buscar produtos por nome ou código...">
                    </div>
                    <select name="ordem" class="filter-select">
                        <option value="nome" <?php echo $filters['orderBy'] === 'nome' ? 'selected' : ''; ?>>Nome A-Z</option>
                        <option value="preco_asc" <?php echo $filters['orderBy'] === 'preco_asc' ? 'selected' : ''; ?>>Menor preço</option>
                        <option value="preco_desc" <?php echo $filters['orderBy'] === 'preco_desc' ? 'selected' : ''; ?>>Maior preço</option>
                        <option value="data_desc" <?php echo $filters['orderBy'] === 'data_desc' ? 'selected' : ''; ?>>Mais recente</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Buscar</button>
                </form>
            </div>

            <!-- Product Grid -->
            <?php if (empty($produtos)): ?>
            <div class="empty-products">
                <div style="font-size: 3rem; margin-bottom: 1rem;">📦</div>
                <h3>Nenhum produto encontrado</h3>
                <p>Tente ajustar os filtros ou buscar por outros termos.</p>
            </div>
            <?php else: ?>
            <div class="product-grid">
                <?php foreach ($produtos as $produto): 
                    $produtoVariants = $product->getVariants($produto['id']);
                    $precoMin = !empty($produtoVariants) ? min(array_column($produtoVariants, 'preco_final')) : $produto['preco_base'];
                    $precoMax = !empty($produtoVariants) ? max(array_column($produtoVariants, 'preco_final')) : $produto['preco_base'];
                    $coresDisponiveis = array_unique(array_column($produtoVariants, 'cor_id'));
                ?>
                <div class="product-card" data-product-id="<?php echo $produto['id']; ?>">
                    <div class="product-image">
                        <?php if (!empty($produto['imagem_principal'])): ?>
                        <img src="<?php echo e($produto['imagem_principal']); ?>" alt="<?php echo e($produto['nome']); ?>">
                        <?php else: ?>
                        👡
                        <?php endif; ?>
                        <?php if (strtotime($produto['data_criacao']) > strtotime('-30 days')): ?>
                        <span class="product-badge">Novo</span>
                        <?php endif; ?>
                    </div>
                    <div class="product-info">
                        <h3 class="product-name"><?php echo e($produto['nome']); ?></h3>
                        <div class="product-code">Código: <?php echo e($produto['codigo_produto']); ?></div>
                        
                        <?php if (!empty($produtoVariants)): ?>
                        <div class="product-variants">
                            <?php 
                            $coresExibidas = [];
                            foreach ($produtoVariants as $variant): 
                                if (!in_array($variant['cor_id'], $coresExibidas)):
                                    $coresExibidas[] = $variant['cor_id'];
                            ?>
                            <div class="variant-color" 
                                 style="background: <?php echo e($variant['codigo_hex'] ?: '#ccc'); ?>;" 
                                 title="<?php echo e($variant['cor_nome']); ?>"></div>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="product-price">
                            <?php if ($precoMin === $precoMax): ?>
                                <?php echo formatPrice($precoMin); ?>
                            <?php else: ?>
                                <?php echo formatPrice($precoMin); ?> - <?php echo formatPrice($precoMax); ?>
                                <div class="price-range">Variação de preço</div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="product-actions">
                            <a href="produto.php?id=<?php echo $produto['id']; ?>" class="btn btn-outline">Ver Detalhes</a>
                            <?php if ($isLoggedIn && !empty($produtoVariants)): ?>
                            <button class="btn btn-primary" onclick="quickAdd(<?php echo $produto['id']; ?>)">Adicionar</button>
                            <?php elseif (!empty($produtoVariants)): ?>
                            <a href="login.php" class="btn btn-disabled">Login para Comprar</a>
                            <?php else: ?>
                            <button class="btn btn-disabled" disabled>Indisponível</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <?php if ($isLoggedIn): ?>
    <!-- Cart Sidebar -->
    <div class="cart-sidebar" id="cartSidebar">
        <div class="cart-header">
            <h3>🛒 Carrinho</h3>
            <button class="cart-close" onclick="toggleCart()">×</button>
        </div>
        <div class="cart-items" id="cartItems">
            <?php if (empty($cartItems)): ?>
            <div class="empty-cart" style="text-align: center; padding: 3rem; color: #666;">
                <div style="font-size: 2rem; margin-bottom: 1rem;">🛒</div>
                <p>Seu carrinho está vazio</p>
            </div>
            <?php else: ?>
                <?php foreach ($cartItems as $item): ?>
                <div class="cart-item" data-variant-id="<?php echo $item['variante_id']; ?>" style="display: flex; gap: 1rem; padding: 1rem; border-bottom: 1px solid #eee;">
                    <div style="width: 60px; height: 60px; background: #f1f2f6; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        👡
                    </div>
                    <div style="flex: 1;">
                        <div style="font-weight: 600; margin-bottom: 0.25rem;"><?php echo e($item['produto_nome']); ?></div>
                        <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.5rem;">
                            <?php echo e($item['cor_nome']); ?> - Tam <?php echo e($item['tamanho_numero']); ?>
                        </div>
                        <div style="font-weight: 600; color: #667eea;"><?php echo formatPrice($item['preco_unitario']); ?></div>
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem;">
                            <button onclick="updateQuantity(<?php echo $item['variante_id']; ?>, <?php echo $item['quantidade'] - 1; ?>)" style="background: #f1f2f6; border: none; width: 30px; height: 30px; border-radius: 6px; cursor: pointer;">-</button>
                            <span><?php echo $item['quantidade']; ?></span>
                            <button onclick="updateQuantity(<?php echo $item['variante_id']; ?>, <?php echo $item['quantidade'] + 1; ?>)" style="background: #f1f2f6; border: none; width: 30px; height: 30px; border-radius: 6px; cursor: pointer;">+</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php if (!empty($cartItems)): ?>
        <div style="padding: 1.5rem; border-top: 2px solid #eee; background: #f8f9fa;">
            <div style="font-size: 1.2rem; font-weight: bold; margin-bottom: 1rem; display: flex; justify-content: space-between;">
                <span>Total:</span>
                <span><?php echo formatPrice($cart->getTotal($clienteId)); ?></span>
            </div>
            <a href="checkout.php" class="btn btn-primary" style="width: 100%; text-align: center;">Finalizar Pedido</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Overlay -->
    <div class="overlay" id="overlay" onclick="toggleCart()" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1500; opacity: 0; visibility: hidden; transition: all 0.3s;"></div>
    <?php endif; ?>

    <script>
        <?php if ($isLoggedIn): ?>
        // Funções do carrinho (apenas se logado)
        function toggleCart() {
            const sidebar = document.getElementById('cartSidebar');
            const overlay = document.getElementById('overlay');
            
            if (sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
                overlay.classList.remove('show');
            } else {
                sidebar.classList.add('open');
                overlay.classList.add('show');
            }
        }

        function addToCart(variantId, quantidade = 1) {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=add_to_cart&variant_id=${variantId}&quantidade=${quantidade}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateCartInfo();
                    alert('Produto adicionado ao carrinho!');
                } else {
                    alert(data.message || 'Erro ao adicionar produto');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao adicionar produto ao carrinho');
            });
        }

        function updateQuantity(variantId, quantidade) {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_cart&variant_id=${variantId}&quantidade=${quantidade}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateCartInfo();
                    if (quantidade === 0) {
                        location.reload();
                    }
                } else {
                    alert(data.message || 'Erro ao atualizar carrinho');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
            });
        }

        function updateCartInfo() {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_cart_info'
            })
            .then(response => response.json())
            .then(data => {
                if (data.count !== undefined) {
                    document.getElementById('cartCount').textContent = data.count;
                }
            })
            .catch(error => {
                console.error('Erro:', error);
            });
        }

        function quickAdd(productId) {
            window.location.href = `produto.php?id=${productId}`;
        }

        // Adicionar estilos CSS para carrinho
        const cartStyles = `
            .cart-sidebar {
                position: fixed;
                right: -400px;
                top: 0;
                width: 400px;
                height: 100vh;
                background: white;
                box-shadow: -5px 0 15px rgba(0,0,0,0.1);
                transition: all 0.3s;
                z-index: 2000;
                overflow-y: auto;
            }
            .cart-sidebar.open {
                right: 0;
            }
            .cart-header {
                padding: 1.5rem;
                border-bottom: 1px solid #eee;
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: #f8f9fa;
            }
            .cart-close {
                background: none;
                border: none;
                font-size: 1.5rem;
                cursor: pointer;
                color: #666;
            }
            .overlay.show {
                opacity: 1;
                visibility: visible;
            }
        `;
        
        const styleSheet = document.createElement('style');
        styleSheet.textContent = cartStyles;
        document.head.appendChild(styleSheet);
        <?php else: ?>
        // Funções para usuários não logados
        function quickAdd(productId) {
            alert('Faça login para adicionar produtos ao carrinho');
            window.location.href = 'login.php';
        }
        <?php endif; ?>

        function viewProduct(productId) {
            window.location.href = `produto.php?id=${productId}`;
        }

        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            // Código de inicialização se necessário
        });
    </script>
</body>
</html>