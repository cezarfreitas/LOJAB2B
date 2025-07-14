<?php
// admin/orders.php - Gestão de Pedidos Administrativo
require_once '../config/database.php';
require_once '../config/functions.php';

SessionManager::requireAdmin();

$order = new Order();
$client = new Client();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$success = '';
$error = '';

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_status') {
    try {
        $orderId = $_POST['order_id'];
        $newStatus = $_POST['status'];
        $deliveryDate = $_POST['delivery_date'] ?? null;
        
        $order->updateStatus($orderId, $newStatus);
        
        if ($deliveryDate) {
            $order->updateDeliveryDate($orderId, $deliveryDate);
        }
        
        if ($newStatus === 'confirmado') {
            $order->processOrder($orderId);
        }
        
        $success = 'Status do pedido atualizado com sucesso!';
    } catch (Exception $e) {
        $error = 'Erro ao atualizar pedido: ' . $e->getMessage();
    }
}

// Get orders based on filters
$filters = [
    'status' => $_GET['status'] ?? '',
    'cliente_id' => $_GET['cliente_id'] ?? '',
    'data_inicio' => $_GET['data_inicio'] ?? '',
    'data_fim' => $_GET['data_fim'] ?? ''
];

$orders = $order->getAll($filters);
$clients = $client->getAll();

if ($action === 'view' && $id) {
    $currentOrder = $order->getById($id);
    $orderItems = $order->getItems($id);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Pedidos - Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .main-content {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .filter-row {
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
        }

        .form-group input,
        .form-group select {
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-primary { background: #667eea; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-info { background: #17a2b8; color: white; }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .orders-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-pendente { background: #fff3cd; color: #856404; }
        .status-confirmado { background: #d1ecf1; color: #0c5460; }
        .status-processando { background: #d4edda; color: #155724; }
        .status-enviado { background: #cce5ff; color: #004085; }
        .status-entregue { background: #d1ecf1; color: #0c5460; }
        .status-cancelado { background: #f8d7da; color: #721c24; }

        .order-detail {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .order-header {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .order-items {
            margin: 2rem 0;
        }

        .item-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
            gap: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            margin-bottom: 0.5rem;
            border-radius: 8px;
        }

        .status-form {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-top: 2rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
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

        @media (max-width: 768px) {
            .filter-row {
                grid-template-columns: 1fr;
            }
            
            .order-header {
                grid-template-columns: 1fr;
            }
            
            .item-row {
                grid-template-columns: 1fr;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Gestão de Pedidos</h1>
        <div>
            <a href="dashboard.php" class="btn btn-primary">Dashboard</a>
            <a href="../logout.php" class="btn btn-danger">Sair</a>
        </div>
    </div>

    <div class="main-content">
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>
            <!-- Filtros -->
            <div class="filters">
                <h3>Filtrar Pedidos</h3>
                <form method="GET">
                    <div class="filter-row">
                        <div class="form-group">
                            <label>Status:</label>
                            <select name="status">
                                <option value="">Todos os Status</option>
                                <option value="pendente" <?php echo $filters['status'] === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                                <option value="confirmado" <?php echo $filters['status'] === 'confirmado' ? 'selected' : ''; ?>>Confirmado</option>
                                <option value="processando" <?php echo $filters['status'] === 'processando' ? 'selected' : ''; ?>>Processando</option>
                                <option value="enviado" <?php echo $filters['status'] === 'enviado' ? 'selected' : ''; ?>>Enviado</option>
                                <option value="entregue" <?php echo $filters['status'] === 'entregue' ? 'selected' : ''; ?>>Entregue</option>
                                <option value="cancelado" <?php echo $filters['status'] === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Cliente:</label>
                            <select name="cliente_id">
                                <option value="">Todos os Clientes</option>
                                <?php foreach ($clients as $cliente): ?>
                                    <option value="<?php echo $cliente['id']; ?>" 
                                            <?php echo $filters['cliente_id'] == $cliente['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cliente['razao_social']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Data Início:</label>
                            <input type="date" name="data_inicio" value="<?php echo $filters['data_inicio']; ?>">
                        </div>
                        <div class="form-group">
                            <label>Data Fim:</label>
                            <input type="date" name="data_fim" value="<?php echo $filters['data_fim']; ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="orders.php" class="btn btn-secondary">Limpar</a>
                </form>
            </div>

            <!-- Tabela de Pedidos -->
            <div class="orders-table">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Cliente</th>
                            <th>Data</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $pedido): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($pedido['numero_pedido']); ?></td>
                                <td><?php echo htmlspecialchars($pedido['razao_social']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($pedido['data_pedido'])); ?></td>
                                <td><?php echo formatPrice($pedido['total']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $pedido['status']; ?>">
                                        <?php echo ucfirst($pedido['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?action=view&id=<?php echo $pedido['id']; ?>" class="btn btn-info btn-sm">Ver</a>
                                    <?php if ($pedido['status'] === 'pendente'): ?>
                                        <a href="#" onclick="updateOrderStatus(<?php echo $pedido['id']; ?>, 'confirmado')" 
                                           class="btn btn-success btn-sm">Confirmar</a>
                                    <?php endif; ?>
                                    <?php if (in_array($pedido['status'], ['pendente', 'confirmado'])): ?>
                                        <a href="#" onclick="updateOrderStatus(<?php echo $pedido['id']; ?>, 'cancelado')" 
                                           class="btn btn-danger btn-sm">Cancelar</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($action === 'view' && $currentOrder): ?>
            <!-- Detalhes do Pedido -->
            <div class="order-detail">
                <div class="order-header">
                    <div>
                        <h2>Pedido #<?php echo htmlspecialchars($currentOrder['numero_pedido']); ?></h2>
                        <p><strong>Cliente:</strong> <?php echo htmlspecialchars($currentOrder['razao_social']); ?></p>
                        <p><strong>CNPJ:</strong> <?php echo formatCNPJ($currentOrder['cnpj']); ?></p>
                        <p><strong>Data:</strong> <?php echo date('d/m/Y H:i', strtotime($currentOrder['data_pedido'])); ?></p>
                    </div>
                    <div>
                        <p><strong>Status:</strong> 
                            <span class="status-badge status-<?php echo $currentOrder['status']; ?>">
                                <?php echo ucfirst($currentOrder['status']); ?>
                            </span>
                        </p>
                        <p><strong>Subtotal:</strong> <?php echo formatPrice($currentOrder['subtotal']); ?></p>
                        <p><strong>Desconto:</strong> <?php echo formatPrice($currentOrder['desconto']); ?></p>
                        <p><strong>Total:</strong> <?php echo formatPrice($currentOrder['total']); ?></p>
                    </div>
                </div>

                <!-- Endereço de Entrega -->
                <div style="margin-bottom: 2rem; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                    <h4>Endereço de Entrega</h4>
                    <p><?php echo htmlspecialchars($currentOrder['endereco']); ?></p>
                    <p><?php echo htmlspecialchars($currentOrder['cidade']); ?> - <?php echo htmlspecialchars($currentOrder['estado']); ?></p>
                </div>

                <!-- Itens do Pedido -->
                <div class="order-items">
                    <h4>Itens do Pedido</h4>
                    <div class="item-row" style="background: #e9ecef; font-weight: bold;">
                        <div>Produto</div>
                        <div>Quantidade</div>
                        <div>Preço Unit.</div>
                        <div>Subtotal</div>
                        <div>Código</div>
                    </div>
                    <?php foreach ($orderItems as $item): ?>
                        <div class="item-row">
                            <div>
                                <?php echo htmlspecialchars($item['produto_nome']); ?><br>
                                <small><?php echo htmlspecialchars($item['cor_nome']); ?> - Tam. <?php echo htmlspecialchars($item['tamanho_numero']); ?></small>
                            </div>
                            <div><?php echo $item['quantidade']; ?></div>
                            <div><?php echo formatPrice($item['preco_unitario']); ?></div>
                            <div><?php echo formatPrice($item['subtotal']); ?></div>
                            <div><?php echo htmlspecialchars($item['codigo_variante']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Observações -->
                <?php if ($currentOrder['observacoes']): ?>
                    <div style="margin: 2rem 0; padding: 1rem; background: #fff3cd; border-radius: 8px;">
                        <h4>Observações</h4>
                        <p><?php echo htmlspecialchars($currentOrder['observacoes']); ?></p>
                    </div>
                <?php endif; ?>

                <!-- Atualizar Status -->
                <div class="status-form">
                    <h4>Atualizar Status do Pedido</h4>
                    <form method="POST">
                        <input type="hidden" name="order_id" value="<?php echo $currentOrder['id']; ?>">
                        <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 1rem; align-items: end;">
                            <div class="form-group">
                                <label>Novo Status:</label>
                                <select name="status" required>
                                    <option value="pendente" <?php echo $currentOrder['status'] === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                                    <option value="confirmado" <?php echo $currentOrder['status'] === 'confirmado' ? 'selected' : ''; ?>>Confirmado</option>
                                    <option value="processando" <?php echo $currentOrder['status'] === 'processando' ? 'selected' : ''; ?>>Processando</option>
                                    <option value="enviado" <?php echo $currentOrder['status'] === 'enviado' ? 'selected' : ''; ?>>Enviado</option>
                                    <option value="entregue" <?php echo $currentOrder['status'] === 'entregue' ? 'selected' : ''; ?>>Entregue</option>
                                    <option value="cancelado" <?php echo $currentOrder['status'] === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Previsão de Entrega:</label>
                                <input type="date" name="delivery_date" 
                                       value="<?php echo $currentOrder['data_entrega_prevista']; ?>">
                            </div>
                            <button type="submit" name="action" value="update_status" class="btn btn-success">
                                Atualizar
                            </button>
                        </div>
                    </form>
                </div>

                <div style="margin-top: 2rem;">
                    <a href="orders.php" class="btn btn-primary">Voltar</a>
                    <button onclick="window.print()" class="btn btn-info">Imprimir</button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function updateOrderStatus(orderId, status) {
            if (confirm(`Tem certeza que deseja alterar o status para "${status}"?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="order_id" value="${orderId}">
                    <input type="hidden" name="status" value="${status}">
                    <input type="hidden" name="action" value="update_status">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Auto-refresh para pedidos pendentes
        if (window.location.search.includes('status=pendente')) {
            setTimeout(() => {
                window.location.reload();
            }, 60000); // Refresh a cada minuto
        }
    </script>
</body>
</html>

<?php
// orders.php - Página de Pedidos para Clientes
require_once 'config/database.php';
require_once 'config/functions.php';

SessionManager::requireLogin();

if (SessionManager::isAdmin()) {
    header('Location: admin/orders.php');
    exit;
}

$clientId = SessionManager::get('client_id');
$order = new Order();
$client = new Client();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

$clientData = $client->getById($clientId);
$orders = $order->getByClient($clientId, 50);
$stats = $order->getOrderStats($clientId);

if ($action === 'view' && $id) {
    $currentOrder = $order->getById($id);
    if (!$currentOrder || $currentOrder['cliente_id'] != $clientId) {
        header('Location: orders.php');
        exit;
    }
    $orderItems = $order->getItems($id);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Pedidos - <?php echo htmlspecialchars($clientData['razao_social']); ?></title>
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

        .main-content {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #2c5aa0;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .orders-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .orders-header {
            background: #f8f9fa;
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-pendente { background: #fff3cd; color: #856404; }
        .status-confirmado { background: #d1ecf1; color: #0c5460; }
        .status-processando { background: #d4edda; color: #155724; }
        .status-enviado { background: #cce5ff; color: #004085; }
        .status-entregue { background: #d1ecf1; color: #0c5460; }
        .status-cancelado { background: #f8d7da; color: #721c24; }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-primary { background: #2c5aa0; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        .btn-danger { background: #dc3545; color: white; }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .order-detail {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .order-header {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .order-items {
            margin: 2rem 0;
        }

        .item-card {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 1rem;
            align-items: center;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .order-header {
                grid-template-columns: 1fr;
            }
            
            .item-card {
                grid-template-columns: 1fr;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <h1>Meus Pedidos</h1>
                <p><?php echo htmlspecialchars($clientData['razao_social']); ?></p>
            </div>
            <div>
                <a href="catalog.php" class="btn btn-primary">Catálogo</a>
                <a href="logout.php" class="btn btn-danger">Sair</a>
            </div>
        </div>
    </div>

    <div class="main-content">
        <?php if ($action === 'list'): ?>
            <!-- Estatísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_pedidos'] ?: 0; ?></div>
                    <div class="stat-label">Total de Pedidos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['pendentes'] ?: 0; ?></div>
                    <div class="stat-label">Pendentes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['confirmados'] ?: 0; ?></div>
                    <div class="stat-label">Confirmados</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo formatPrice($stats['valor_total'] ?: 0); ?></div>
                    <div class="stat-label">Valor Total</div>
                </div>
            </div>

            <!-- Lista de Pedidos -->
            <div class="orders-container">
                <div class="orders-header">
                    <h3>Histórico de Pedidos</h3>
                    <a href="catalog.php" class="btn btn-success">+ Novo Pedido</a>
                </div>

                <?php if (empty($orders)): ?>
                    <div class="empty-state">
                        <h3>Nenhum pedido encontrado</h3>
                        <p>Você ainda não fez nenhum pedido.</p>
                        <a href="catalog.php" class="btn btn-primary">Começar a Comprar</a>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Data</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $pedido): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($pedido['numero_pedido']); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($pedido['data_pedido'])); ?></td>
                                    <td><?php echo formatPrice($pedido['total']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $pedido['status']; ?>">
                                            <?php echo ucfirst($pedido['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="?action=view&id=<?php echo $pedido['id']; ?>" 
                                           class="btn btn-info btn-sm">Ver Detalhes</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        <?php elseif ($action === 'view' && $currentOrder): ?>
            <!-- Detalhes do Pedido -->
            <div class="order-detail">
                <div class="order-header">
                    <div>
                        <h2>Pedido #<?php echo htmlspecialchars($currentOrder['numero_pedido']); ?></h2>
                        <p><strong>Data do Pedido:</strong> <?php echo date('d/m/Y H:i', strtotime($currentOrder['data_pedido'])); ?></p>
                        <?php if ($currentOrder['data_confirmacao']): ?>
                            <p><strong>Confirmado em:</strong> <?php echo date('d/m/Y H:i', strtotime($currentOrder['data_confirmacao'])); ?></p>
                        <?php endif; ?>
                        <?php if ($currentOrder['data_entrega_prevista']): ?>
                            <p><strong>Previsão de Entrega:</strong> <?php echo date('d/m/Y', strtotime($currentOrder['data_entrega_prevista'])); ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p><strong>Status:</strong> 
                            <span class="status-badge status-<?php echo $currentOrder['status']; ?>">
                                <?php echo ucfirst($currentOrder['status']); ?>
                            </span>
                        </p>
                        <p><strong>Subtotal:</strong> <?php echo formatPrice($currentOrder['subtotal']); ?></p>
                        <p><strong>Desconto:</strong> <?php echo formatPrice($currentOrder['desconto']); ?></p>
                        <p><strong>Total:</strong> <?php echo formatPrice($currentOrder['total']); ?></p>
                    </div>
                </div>

                <!-- Itens do Pedido -->
                <div class="order-items">
                    <h4>Itens do Pedido</h4>
                    <?php foreach ($orderItems as $item): ?>
                        <div class="item-card">
                            <div>
                                <strong><?php echo htmlspecialchars($item['produto_nome']); ?></strong><br>
                                <small><?php echo htmlspecialchars($item['cor_nome']); ?> - Tamanho <?php echo htmlspecialchars($item['tamanho_numero']); ?></small><br>
                                <small>Código: <?php echo htmlspecialchars($item['codigo_variante']); ?></small>
                            </div>
                            <div><strong>Qtd:</strong> <?php echo $item['quantidade']; ?></div>
                            <div><strong>Preço:</strong> <?php echo formatPrice($item['preco_unitario']); ?></div>
                            <div><strong>Total:</strong> <?php echo formatPrice($item['subtotal']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Observações -->
                <?php if ($currentOrder['observacoes']): ?>
                    <div style="margin: 2rem 0; padding: 1rem; background: #fff3cd; border-radius: 8px;">
                        <h4>Observações</h4>
                        <p><?php echo htmlspecialchars($currentOrder['observacoes']); ?></p>
                    </div>
                <?php endif; ?>

                <div style="margin-top: 2rem;">
                    <a href="orders.php" class="btn btn-primary">Voltar</a>
                    <button onclick="window.print()" class="btn btn-info">Imprimir</button>
                    <?php if ($currentOrder['status'] === 'pendente'): ?>
                        <button onclick="reorderItems()" class="btn btn-success">Fazer Pedido Similar</button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function reorderItems() {
            if (confirm('Deseja adicionar todos os itens deste pedido ao carrinho?')) {
                // Aqui você implementaria a lógica para adicionar itens ao carrinho
                alert('Funcionalidade em desenvolvimento');
            }
        }

        // Auto-refresh para pedidos com status ativo
        if (document.querySelector('.status-pendente, .status-confirmado, .status-processando, .status-enviado')) {
            setTimeout(() => {
                window.location.reload();
            }, 120000); // Refresh a cada 2 minutos
        }
    </script>
</body>
</html>
?>