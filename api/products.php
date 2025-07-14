<?php
// api/products.php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/functions.php';

SessionManager::start();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $product = new Product();
    
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'list':
                    $filters = [
                        'categoria_id' => $_GET['categoria_id'] ?? '',
                        'genero' => $_GET['genero'] ?? '',
                        'search' => $_GET['search'] ?? ''
                    ];
                    
                    $products = $product->getAll($filters);
                    
                    // Add variants for each product
                    foreach ($products as &$prod) {
                        $prod['variants'] = $product->getVariants($prod['id']);
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $products
                    ]);
                    break;
                    
                case 'get':
                    $id = $_GET['id'] ?? null;
                    if (!$id) {
                        throw new Exception('ID do produto é obrigatório');
                    }
                    
                    $productData = $product->getById($id);
                    if (!$productData) {
                        throw new Exception('Produto não encontrado');
                    }
                    
                    $productData['variants'] = $product->getVariants($id);
                    $productData['images'] = $product->getProductImages($id);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $productData
                    ]);
                    break;
                    
                case 'search':
                    $query = $_GET['q'] ?? '';
                    $filters = [
                        'search' => $query,
                        'categoria_id' => $_GET['categoria_id'] ?? '',
                        'genero' => $_GET['genero'] ?? ''
                    ];
                    
                    $products = $product->getAll($filters);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $products
                    ]);
                    break;
                    
                default:
                    throw new Exception('Ação não encontrada');
            }
            break;
            
        default:
            throw new Exception('Método não permitido');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// api/cart.php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/functions.php';

SessionManager::requireLogin();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$clientId = SessionManager::get('client_id');

if (!$clientId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Cliente não encontrado']);
    exit;
}

try {
    $cart = new Cart();
    
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'items':
                    $items = $cart->getItems($clientId);
                    $total = $cart->getTotal($clientId);
                    $count = $cart->getItemCount($clientId);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'items' => $items,
                            'total' => $total,
                            'count' => $count
                        ]
                    ]);
                    break;
                    
                case 'validate':
                    $stockErrors = $cart->validateStock($clientId);
                    
                    echo json_encode([
                        'success' => true,
                        'valid' => empty($stockErrors),
                        'errors' => $stockErrors
                    ]);
                    break;
                    
                default:
                    throw new Exception('Ação não encontrada');
            }
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            switch ($action) {
                case 'add':
                    $variantId = $input['variant_id'] ?? null;
                    $quantity = $input['quantity'] ?? 1;
                    
                    if (!$variantId) {
                        throw new Exception('ID da variante é obrigatório');
                    }
                    
                    $cart->addItem($clientId, $variantId, $quantity);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Item adicionado ao carrinho'
                    ]);
                    break;
                    
                case 'update':
                    $variantId = $input['variant_id'] ?? null;
                    $quantity = $input['quantity'] ?? 1;
                    
                    if (!$variantId) {
                        throw new Exception('ID da variante é obrigatório');
                    }
                    
                    $cart->updateQuantity($clientId, $variantId, $quantity);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Carrinho atualizado'
                    ]);
                    break;
                    
                case 'remove':
                    $variantId = $input['variant_id'] ?? null;
                    
                    if (!$variantId) {
                        throw new Exception('ID da variante é obrigatório');
                    }
                    
                    $cart->removeItem($clientId, $variantId);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Item removido do carrinho'
                    ]);
                    break;
                    
                case 'clear':
                    $cart->clearCart($clientId);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Carrinho limpo'
                    ]);
                    break;
                    
                default:
                    throw new Exception('Ação não encontrada');
            }
            break;
            
        default:
            throw new Exception('Método não permitido');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// api/orders.php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/functions.php';

SessionManager::requireLogin();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$clientId = SessionManager::get('client_id');

if (!$clientId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Cliente não encontrado']);
    exit;
}

try {
    $order = new Order();
    
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'list':
                    $orders = $order->getByClient($clientId);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $orders
                    ]);
                    break;
                    
                case 'get':
                    $id = $_GET['id'] ?? null;
                    if (!$id) {
                        throw new Exception('ID do pedido é obrigatório');
                    }
                    
                    $orderData = $order->getById($id);
                    if (!$orderData || $orderData['cliente_id'] != $clientId) {
                        throw new Exception('Pedido não encontrado');
                    }
                    
                    $orderData['items'] = $order->getItems($id);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $orderData
                    ]);
                    break;
                    
                case 'stats':
                    $stats = $order->getOrderStats($clientId);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $stats
                    ]);
                    break;
                    
                default:
                    throw new Exception('Ação não encontrada');
            }
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            switch ($action) {
                case 'create':
                    $observacoes = $input['observacoes'] ?? '';
                    
                    $orderId = $order->create($clientId, $observacoes);
                    
                    echo json_encode([
                        'success' => true,
                        'order_id' => $orderId,
                        'message' => 'Pedido criado com sucesso'
                    ]);
                    break;
                    
                default:
                    throw new Exception('Ação não encontrada');
            }
            break;
            
        default:
            throw new Exception('Método não permitido');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// api/admin/dashboard.php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../config/functions.php';

SessionManager::requireAdmin();

$action = $_GET['action'] ?? '';

try {
    $db = new Database();
    
    switch ($action) {
        case 'stats':
            // Get dashboard statistics
            $stats = [];
            
            // Total products
            $stmt = $db->query("SELECT COUNT(*) as total FROM produtos WHERE ativo = 1");
            $stats['total_products'] = $stmt->fetchColumn();
            
            // Pending orders
            $stmt = $db->query("SELECT COUNT(*) as total FROM pedidos WHERE status = 'pendente'");
            $stats['pending_orders'] = $stmt->fetchColumn();
            
            // Active clients
            $stmt = $db->query("SELECT COUNT(*) as total FROM clientes_b2b cb JOIN usuarios u ON cb.usuario_id = u.id WHERE u.ativo = 1");
            $stats['active_clients'] = $stmt->fetchColumn();
            
            // Monthly sales
            $stmt = $db->query("SELECT SUM(total) as total FROM pedidos WHERE MONTH(data_pedido) = MONTH(CURRENT_DATE) AND YEAR(data_pedido) = YEAR(CURRENT_DATE)");
            $stats['monthly_sales'] = $stmt->fetchColumn() ?: 0;
            
            // Low stock items
            $product = new Product();
            $lowStockItems = $product->getLowStockItems();
            $stats['low_stock_count'] = count($lowStockItems);
            
            // Recent orders
            $stmt = $db->query("SELECT p.*, cb.razao_social 
                               FROM pedidos p 
                               JOIN clientes_b2b cb ON p.cliente_id = cb.id 
                               ORDER BY p.data_pedido DESC 
                               LIMIT 10");
            $stats['recent_orders'] = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);
            break;
            
        case 'sales_chart':
            // Get sales data for chart (last 30 days)
            $stmt = $db->query("SELECT DATE(data_pedido) as date, SUM(total) as total 
                               FROM pedidos 
                               WHERE data_pedido >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
                               GROUP BY DATE(data_pedido)
                               ORDER BY date");
            
            $salesData = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'data' => $salesData
            ]);
            break;
            
        default:
            throw new Exception('Ação não encontrada');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// catalog.php - Main catalog page
require_once 'config/database.php';
require_once 'config/functions.php';

SessionManager::requireLogin();

if (SessionManager::isAdmin()) {
    header('Location: admin/dashboard.php');
    exit;
}

$clientId = SessionManager::get('client_id');
$client = new Client();
$clientData = $client->getById($clientId);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo B2B - Chinelos</title>
    <style>
        /* Include the same CSS from catalog_frontend.html */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }

        .header {
            background: linear-gradient(135deg, #2c5aa0 0%, #1e3d72 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
        }

        .company-name {
            font-size: 1rem;
            opacity: 0.9;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .cart-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255,255,255,0.1);
            padding: 0.5rem 1rem;
            border-radius: 25px;
            cursor: pointer;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
            transition: background-color 0.3s;
        }

        .btn-danger {
            background-color: #dc3545;
            color: white;
        }

        .main-content {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .loading {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">ChinelosB2B</div>
            <div class="company-name"><?php echo htmlspecialchars($clientData['razao_social']); ?></div>
            <div class="user-menu">
                <div class="cart-info" onclick="toggleCart()">
                    🛒 <span id="cart-count">0</span> itens
                    <span id="cart-total">R$ 0,00</span>
                </div>
                <a href="orders.php" class="btn btn-primary">Meus Pedidos</a>
                <a href="logout.php" class="btn btn-danger">Sair</a>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="loading">
            <h2>Carregando catálogo...</h2>
            <p>Aguarde enquanto carregamos os produtos disponíveis.</p>
        </div>
    </div>

    <script>
        // Load catalog dynamically
        document.addEventListener('DOMContentLoaded', function() {
            loadCatalog();
            updateCartDisplay();
        });

        function loadCatalog() {
            fetch('api/products.php?action=list')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayProducts(data.data);
                    } else {
                        showError('Erro ao carregar produtos: ' + data.error);
                    }
                })
                .catch(error => {
                    showError('Erro de conexão: ' + error.message);
                });
        }

        function displayProducts(products) {
            const mainContent = document.querySelector('.main-content');
            
            if (products.length === 0) {
                mainContent.innerHTML = '<div class="loading"><h2>Nenhum produto disponível</h2></div>';
                return;
            }

            // Create the same structure as the static catalog
            let html = `
                <div class="products-grid">
                    ${products.map(product => createProductHTML(product)).join('')}
                </div>
            `;

            mainContent.innerHTML = html;
        }

        function createProductHTML(product) {
            return `
                <div class="product-card">
                    <div class="product-image">
                        ${product.imagem_principal ? 
                            `<img src="uploads/products/${product.imagem_principal}" style="width:100%;height:100%;object-fit:cover;">` : 
                            '👡'
                        }
                    </div>
                    <div class="product-info">
                        <div class="product-name">${product.nome}</div>
                        <div class="product-code">${product.codigo_produto}</div>
                        <div class="product-description">${product.descricao}</div>
                        <div class="product-price">R$ ${parseFloat(product.preco_base).toFixed(2).replace('.', ',')}</div>
                        
                        <div class="variants-section">
                            <div class="variants-title">Cores disponíveis:</div>
                            <div class="color-options">
                                ${product.variants.map(variant => 
                                    `<div class="color-option" 
                                          style="background-color: ${variant.codigo_hex}" 
                                          data-variant-id="${variant.id}"
                                          title="${variant.cor_nome}"
                                          onclick="selectVariant(${product.id}, ${variant.id})"></div>`
                                ).join('')}
                            </div>
                            
                            <div class="quantity-section">
                                <label>Quantidade:</label>
                                <input type="number" class="quantity-input" 
                                       id="qty-${product.id}" value="1" min="1" max="999">
                                <div class="stock-info" id="stock-${product.id}">Selecione uma variante</div>
                            </div>
                            
                            <button class="add-to-cart" 
                                    id="btn-${product.id}" 
                                    onclick="addToCart(${product.id})"
                                    disabled>
                                Adicionar ao Carrinho
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }

        function selectVariant(productId, variantId) {
            // Implementation for variant selection
            const button = document.getElementById(`btn-${productId}`);
            button.dataset.variantId = variantId;
            button.disabled = false;
            
            document.getElementById(`stock-${productId}`).textContent = 'Variante selecionada';
        }

        function addToCart(productId) {
            const button = document.getElementById(`btn-${productId}`);
            const quantity = parseInt(document.getElementById(`qty-${productId}`).value);
            const variantId = button.dataset.variantId;
            
            if (!variantId) {
                alert('Selecione uma variante');
                return;
            }

            fetch('api/cart.php?action=add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    variant_id: variantId,
                    quantity: quantity
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateCartDisplay();
                    button.textContent = 'Adicionado!';
                    setTimeout(() => {
                        button.textContent = 'Adicionar ao Carrinho';
                    }, 1000);
                } else {
                    alert('Erro: ' + data.error);
                }
            })
            .catch(error => {
                alert('Erro de conexão');
            });
        }

        function updateCartDisplay() {
            fetch('api/cart.php?action=items')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('cart-count').textContent = data.data.count;
                        document.getElementById('cart-total').textContent = 
                            `R$ ${parseFloat(data.data.total).toFixed(2).replace('.', ',')}`;
                    }
                });
        }

        function toggleCart() {
            // Implementation for cart sidebar
            alert('Funcionalidade do carrinho em desenvolvimento');
        }

        function showError(message) {
            const mainContent = document.querySelector('.main-content');
            mainContent.innerHTML = `
                <div class="loading">
                    <h2>Erro</h2>
                    <p>${message}</p>
                    <button onclick="loadCatalog()" class="btn btn-primary">Tentar Novamente</button>
                </div>
            `;
        }
    </script>
</body>
</html>
?>