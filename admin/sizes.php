<?php
// admin/sizes.php - Gestão de Tamanhos
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Carregar dependências na ordem correta
require_once '../config/database.php';

// Verificar se functions.php existe, se não, definir funções necessárias
if (file_exists('../config/functions.php')) {
    require_once '../config/functions.php';
} else {
    // Definir funções essenciais se não existirem
    if (!function_exists('sanitizeInput')) {
        function sanitizeInput($input) {
            if (is_array($input)) {
                return array_map('sanitizeInput', $input);
            }
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
    }
    
    if (!function_exists('logActivity')) {
        function logActivity($userId, $action, $details = '') {
            return true; // Placeholder
        }
    }
}

// Iniciar sessão se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticação admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Implementação da classe Size
class SizeManager {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function getAll($filters = []) {
        $where = ['1=1'];
        $params = [];
        
        if (!empty($filters['search'])) {
            $where[] = "numero LIKE ?";
            $params[] = "%{$filters['search']}%";
        }
        
        if ($filters['status'] === 'ativo') {
            $where[] = "ativo = 1";
        } elseif ($filters['status'] === 'inativo') {
            $where[] = "ativo = 0";
        }
        
        $orderBy = $filters['orderBy'] ?? 'ordem';
        $direction = $filters['direction'] ?? 'ASC';
        
        $sql = "SELECT *, 
                       (SELECT COUNT(*) FROM produto_variantes pv WHERE pv.tamanho_id = tamanhos.id) as produtos_usando
                FROM tamanhos 
                WHERE " . implode(' AND ', $where) . "
                ORDER BY $orderBy $direction";
        
        try {
            $stmt = $this->db->query($sql, $params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
    
    public function getById($id) {
        $sql = "SELECT * FROM tamanhos WHERE id = ?";
        try {
            $stmt = $this->db->query($sql, [$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function create($data) {
        // Verificar se número já existe
        if ($this->existsByNumber($data['numero'])) {
            throw new Exception('Tamanho já existe');
        }
        
        $sql = "INSERT INTO tamanhos (numero, ordem, ativo) VALUES (?, ?, ?)";
        $stmt = $this->db->query($sql, [
            $data['numero'], 
            $data['ordem'], 
            $data['ativo'] ?? 1
        ]);
        return $this->db->lastInsertId();
    }
    
    public function update($id, $data) {
        // Verificar se número já existe em outro registro
        $sql = "SELECT id FROM tamanhos WHERE numero = ? AND id != ?";
        $stmt = $this->db->query($sql, [$data['numero'], $id]);
        if ($stmt->fetch()) {
            throw new Exception('Tamanho já existe');
        }
        
        $sql = "UPDATE tamanhos SET numero = ?, ordem = ?, ativo = ? WHERE id = ?";
        return $this->db->query($sql, [
            $data['numero'], 
            $data['ordem'], 
            $data['ativo'], 
            $id
        ]);
    }
    
    public function delete($id) {
        // Verificar se está sendo usado
        $sql = "SELECT COUNT(*) FROM produto_variantes WHERE tamanho_id = ?";
        $stmt = $this->db->query($sql, [$id]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Não é possível excluir tamanho que está sendo usado em produtos');
        }
        
        $sql = "DELETE FROM tamanhos WHERE id = ?";
        return $this->db->query($sql, [$id]);
    }
    
    public function toggleStatus($id) {
        $sql = "UPDATE tamanhos SET ativo = NOT ativo WHERE id = ?";
        return $this->db->query($sql, [$id]);
    }
    
    public function existsByNumber($numero) {
        $sql = "SELECT id FROM tamanhos WHERE numero = ?";
        $stmt = $this->db->query($sql, [$numero]);
        return $stmt->fetch() !== false;
    }
    
    public function getStats() {
        try {
            $stats = [];
            
            $stmt = $this->db->query("SELECT COUNT(*) FROM tamanhos");
            $stats['total'] = $stmt->fetchColumn();
            
            $stmt = $this->db->query("SELECT COUNT(*) FROM tamanhos WHERE ativo = 1");
            $stats['ativos'] = $stmt->fetchColumn();
            
            $stmt = $this->db->query("SELECT COUNT(*) FROM tamanhos WHERE ativo = 0");
            $stats['inativos'] = $stmt->fetchColumn();
            
            $stmt = $this->db->query("SELECT COUNT(DISTINCT tamanho_id) FROM produto_variantes");
            $stats['em_uso'] = $stmt->fetchColumn();
            
            return $stats;
        } catch (Exception $e) {
            return ['total' => 0, 'ativos' => 0, 'inativos' => 0, 'em_uso' => 0];
        }
    }
    
    public function reorder($sizes) {
        try {
            $this->db->beginTransaction();
            
            foreach ($sizes as $index => $sizeId) {
                $ordem = $index + 1;
                $sql = "UPDATE tamanhos SET ordem = ? WHERE id = ?";
                $this->db->query($sql, [$ordem, $sizeId]);
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}

// Inicializar classe
$sizeManager = new SizeManager();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$success = '';
$error = '';

// Processar formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? $action;
    
    switch ($postAction) {
        case 'create':
            try {
                $data = [
                    'numero' => sanitizeInput($_POST['numero']),
                    'ordem' => intval($_POST['ordem']),
                    'ativo' => isset($_POST['ativo']) ? 1 : 0
                ];
                
                if (empty($data['numero'])) {
                    throw new Exception('Número do tamanho é obrigatório');
                }
                
                $sizeId = $sizeManager->create($data);
                logActivity($_SESSION['user_id'], 'size_created', "Tamanho criado: {$data['numero']} (ID: $sizeId)");
                $success = 'Tamanho criado com sucesso!';
                $action = 'list';
                
            } catch (Exception $e) {
                $error = 'Erro ao criar tamanho: ' . $e->getMessage();
            }
            break;
            
        case 'update':
            try {
                $data = [
                    'numero' => sanitizeInput($_POST['numero']),
                    'ordem' => intval($_POST['ordem']),
                    'ativo' => isset($_POST['ativo']) ? 1 : 0
                ];
                
                if (empty($data['numero'])) {
                    throw new Exception('Número do tamanho é obrigatório');
                }
                
                $sizeManager->update($id, $data);
                logActivity($_SESSION['user_id'], 'size_updated', "Tamanho atualizado: {$data['numero']} (ID: $id)");
                $success = 'Tamanho atualizado com sucesso!';
                $action = 'list';
                
            } catch (Exception $e) {
                $error = 'Erro ao atualizar tamanho: ' . $e->getMessage();
            }
            break;
            
        case 'toggle_status':
            try {
                $sizeId = $_POST['id'] ?? $id;
                if (!$sizeId) {
                    throw new Exception('ID do tamanho não fornecido');
                }
                
                $currentSize = $sizeManager->getById($sizeId);
                if (!$currentSize) {
                    throw new Exception('Tamanho não encontrado');
                }
                
                $sizeManager->toggleStatus($sizeId);
                $newStatus = $currentSize['ativo'] ? 'desativado' : 'ativado';
                logActivity($_SESSION['user_id'], 'size_status_changed', "Tamanho {$newStatus}: {$currentSize['numero']} (ID: $sizeId)");
                $success = "Tamanho {$newStatus} com sucesso!";
                $action = 'list';
                
            } catch (Exception $e) {
                $error = 'Erro ao alterar status: ' . $e->getMessage();
            }
            break;
            
        case 'reorder':
            try {
                $sizes = $_POST['sizes'] ?? [];
                if (!empty($sizes)) {
                    $sizeManager->reorder($sizes);
                    logActivity($_SESSION['user_id'], 'sizes_reordered', 'Tamanhos reordenados');
                    $success = 'Ordem dos tamanhos atualizada com sucesso!';
                }
                $action = 'list';
                
            } catch (Exception $e) {
                $error = 'Erro ao reordenar: ' . $e->getMessage();
            }
            break;
            
        case 'bulk_create':
            try {
                $start = intval($_POST['start_size']);
                $end = intval($_POST['end_size']);
                $created = 0;
                
                if ($start <= 0 || $end <= 0 || $start > $end) {
                    throw new Exception('Tamanhos inicial e final devem ser válidos');
                }
                
                for ($i = $start; $i <= $end; $i++) {
                    if (!$sizeManager->existsByNumber($i)) {
                        $sizeManager->create([
                            'numero' => $i,
                            'ordem' => $i,
                            'ativo' => 1
                        ]);
                        $created++;
                    }
                }
                
                logActivity($_SESSION['user_id'], 'sizes_bulk_created', "Criados $created tamanhos em lote");
                $success = "$created tamanho(s) criado(s) com sucesso!";
                $action = 'list';
                
            } catch (Exception $e) {
                $error = 'Erro na criação em lote: ' . $e->getMessage();
            }
            break;
    }
}

// Ações GET
if ($action === 'delete' && $id) {
    try {
        $currentSize = $sizeManager->getById($id);
        if (!$currentSize) {
            throw new Exception('Tamanho não encontrado');
        }
        
        $sizeManager->delete($id);
        logActivity($_SESSION['user_id'], 'size_deleted', "Tamanho removido: {$currentSize['numero']} (ID: $id)");
        $success = 'Tamanho removido com sucesso!';
        $action = 'list';
        
    } catch (Exception $e) {
        $error = 'Erro ao remover tamanho: ' . $e->getMessage();
    }
}

// Buscar dados para exibição
if ($action === 'edit' && $id) {
    $currentSize = $sizeManager->getById($id);
    if (!$currentSize) {
        $error = 'Tamanho não encontrado';
        $action = 'list';
    }
}

// Filtros para listagem
$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? 'ativo',
    'orderBy' => $_GET['orderBy'] ?? 'ordem',
    'direction' => $_GET['direction'] ?? 'ASC'
];

$sizes = $sizeManager->getAll($filters);
$stats = $sizeManager->getStats();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Tamanhos - Admin</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .breadcrumb {
            opacity: 0.8;
            font-size: 0.9rem;
        }

        .main-content {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 600;
            color: #333;
        }

        .actions-bar {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
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

        .btn-info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            text-align: center;
            position: relative;
            overflow: hidden;
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

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
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
        .form-group select {
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .sizes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .size-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s;
            position: relative;
        }

        .size-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .size-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
            text-align: center;
            margin-bottom: 1rem;
        }

        .size-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: #6c757d;
        }

        .size-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }

        .status-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
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

        .table-view {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .table-header {
            background: #f8f9fa;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e9ecef;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 1rem 1.5rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        .sortable {
            cursor: grab;
        }

        .sortable:active {
            cursor: grabbing;
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
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
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

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .bulk-section {
            background: #e3f2fd;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }

        .drag-handle {
            cursor: grab;
            color: #6c757d;
            font-size: 1.2rem;
        }

        .drag-handle:active {
            cursor: grabbing;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 0 1rem;
            }
            
            .page-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .actions-bar {
                width: 100%;
                justify-content: flex-start;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .sizes-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        .view-toggle {
            display: flex;
            background: white;
            border-radius: 8px;
            padding: 0.25rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .view-toggle button {
            padding: 0.5rem 1rem;
            border: none;
            background: transparent;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.3s;
        }

        .view-toggle button.active {
            background: #667eea;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-left">
            <h1>Gestão de Tamanhos</h1>
            <div class="breadcrumb">Dashboard / Configurações / Tamanhos</div>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-primary btn-sm">← Dashboard</a>
            <a href="../logout.php" class="btn btn-danger btn-sm">Sair</a>
        </div>
    </div>

    <div class="main-content">
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

        <?php if ($action === 'list'): ?>
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h2 class="page-title">Tamanhos</h2>
                    <p style="color: #6c757d;">Gerencie os tamanhos disponíveis</p>
                </div>
                <div class="actions-bar">
                    <div class="view-toggle">
                        <button class="active" onclick="toggleView('grid')">🔲 Grade</button>
                        <button onclick="toggleView('table')">📋 Tabela</button>
                    </div>
                    <a href="?action=create" class="btn btn-success">
                        <span>➕</span> Novo Tamanho
                    </a>
                    <button onclick="toggleBulkCreate()" class="btn btn-primary">
                        <span>📦</span> Criação em Lote
                    </button>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card" style="--card-color: #667eea;">
                    <div class="stat-icon">📏</div>
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total de Tamanhos</div>
                </div>
                <div class="stat-card" style="--card-color: #28a745;">
                    <div class="stat-icon">✅</div>
                    <div class="stat-value"><?php echo $stats['ativos']; ?></div>
                    <div class="stat-label">Tamanhos Ativos</div>
                </div>
                <div class="stat-card" style="--card-color: #dc3545;">
                    <div class="stat-icon">❌</div>
                    <div class="stat-value"><?php echo $stats['inativos']; ?></div>
                    <div class="stat-label">Tamanhos Inativos</div>
                </div>
                <div class="stat-card" style="--card-color: #ffc107;">
                    <div class="stat-icon">🎯</div>
                    <div class="stat-value"><?php echo $stats['em_uso']; ?></div>
                    <div class="stat-label">Em Uso</div>
                </div>
            </div>

            <!-- Bulk Create Section -->
            <div id="bulk-create-section" class="bulk-section" style="display: none;">
                <h3 style="margin-bottom: 1rem; color: #1976d2;">📦 Criação em Lote</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="bulk_create">
                    <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 1rem; align-items: end;">
                        <div class="form-group">
                            <label>Tamanho Inicial</label>
                            <input type="number" name="start_size" required min="1" max="100" placeholder="Ex: 33">
                        </div>
                        <div class="form-group">
                            <label>Tamanho Final</label>
                            <input type="number" name="end_size" required min="1" max="100" placeholder="Ex: 46">
                        </div>
                        <button type="submit" class="btn btn-success">📦 Criar Lote</button>
                    </div>
                </form>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <h3 style="margin-bottom: 1rem;">🔍 Filtros</h3>
                <form method="GET">
                    <div class="filters-grid">
                        <div class="form-group">
                            <label>Buscar Tamanho</label>
                            <input type="text" name="search" placeholder="Digite o número do tamanho..." 
                                   value="<?php echo htmlspecialchars($filters['search']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="todos" <?php echo $filters['status'] === 'todos' ? 'selected' : ''; ?>>Todos</option>
                                <option value="ativo" <?php echo $filters['status'] === 'ativo' ? 'selected' : ''; ?>>Ativos</option>
                                <option value="inativo" <?php echo $filters['status'] === 'inativo' ? 'selected' : ''; ?>>Inativos</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Ordenar por</label>
                            <select name="orderBy">
                                <option value="ordem" <?php echo $filters['orderBy'] === 'ordem' ? 'selected' : ''; ?>>Ordem</option>
                                <option value="numero" <?php echo $filters['orderBy'] === 'numero' ? 'selected' : ''; ?>>Número</option>
                                <option value="id" <?php echo $filters['orderBy'] === 'id' ? 'selected' : ''; ?>>Data de Criação</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Direção</label>
                            <select name="direction">
                                <option value="ASC" <?php echo $filters['direction'] === 'ASC' ? 'selected' : ''; ?>>Crescente</option>
                                <option value="DESC" <?php echo $filters['direction'] === 'DESC' ? 'selected' : ''; ?>>Decrescente</option>
                            </select>
                        </div>
                    </div>
                    <div style="margin-top: 1rem;">
                        <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                        <a href="?" class="btn btn-secondary">🔄 Limpar</a>
                    </div>
                </form>
            </div>

            <!-- Sizes Display -->
            <div id="sizes-display">
                <!-- Grid View -->
                <div id="grid-view" class="sizes-grid">
                    <?php if (empty($sizes)): ?>
                        <div style="grid-column: 1 / -1;" class="empty-state">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">📏</div>
                            <h3>Nenhum tamanho encontrado</h3>
                            <p>Comece criando os tamanhos disponíveis</p>
                            <a href="?action=create" class="btn btn-success" style="margin-top: 1rem;">
                                ➕ Criar Primeiro Tamanho
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($sizes as $size): ?>
                            <div class="size-card">
                                <div class="status-badge status-<?php echo $size['ativo'] ? 'ativo' : 'inativo'; ?>">
                                    <?php echo $size['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                </div>
                                
                                <div class="size-number"><?php echo htmlspecialchars($size['numero']); ?></div>
                                
                                <div class="size-info">
                                    <div><strong>Ordem:</strong> <?php echo $size['ordem']; ?></div>
                                    <div><strong>Em uso:</strong> <?php echo $size['produtos_usando']; ?> produto(s)</div>
                                </div>
                                
                                <div class="size-actions">
                                    <a href="?action=edit&id=<?php echo $size['id']; ?>" 
                                       class="btn btn-primary btn-sm" title="Editar">✏️</a>
                                    
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('Alterar status do tamanho?')">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?php echo $size['id']; ?>">
                                        <button type="submit" 
                                                class="btn <?php echo $size['ativo'] ? 'btn-warning' : 'btn-success'; ?> btn-sm"
                                                title="<?php echo $size['ativo'] ? 'Desativar' : 'Ativar'; ?>">
                                            <?php echo $size['ativo'] ? '⏸️' : '▶️'; ?>
                                        </button>
                                    </form>
                                    
                                    <?php if ($size['produtos_usando'] == 0): ?>
                                        <a href="?action=delete&id=<?php echo $size['id']; ?>" 
                                           class="btn btn-danger btn-sm" title="Excluir"
                                           onclick="return confirm('Tem certeza que deseja excluir este tamanho?')">🗑️</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Table View -->
                <div id="table-view" class="table-view" style="display: none;">
                    <div class="table-header">
                        <h3>Lista de Tamanhos</h3>
                        <button onclick="enableSorting()" class="btn btn-info btn-sm">
                            🔄 Reordenar
                        </button>
                    </div>
                    
                    <form id="reorder-form" method="POST" style="display: none;">
                        <input type="hidden" name="action" value="reorder">
                    </form>
                    
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">Ordem</th>
                                <th>Tamanho</th>
                                <th>Status</th>
                                <th>Produtos Usando</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="sortable-sizes">
                            <?php foreach ($sizes as $size): ?>
                                <tr data-id="<?php echo $size['id']; ?>">
                                    <td>
                                        <span class="drag-handle">⋮⋮</span>
                                        <?php echo $size['ordem']; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; font-size: 1.2rem;">
                                            <?php echo htmlspecialchars($size['numero']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $size['ativo'] ? 'ativo' : 'inativo'; ?>">
                                            <?php echo $size['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $size['produtos_usando']; ?> produto(s)</td>
                                    <td>
                                        <div style="display: flex; gap: 0.25rem;">
                                            <a href="?action=edit&id=<?php echo $size['id']; ?>" 
                                               class="btn btn-primary btn-sm">✏️</a>
                                            
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Alterar status?')">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="id" value="<?php echo $size['id']; ?>">
                                                <button type="submit" 
                                                        class="btn <?php echo $size['ativo'] ? 'btn-warning' : 'btn-success'; ?> btn-sm">
                                                    <?php echo $size['ativo'] ? '⏸️' : '▶️'; ?>
                                                </button>
                                            </form>
                                            
                                            <?php if ($size['produtos_usando'] == 0): ?>
                                                <a href="?action=delete&id=<?php echo $size['id']; ?>" 
                                                   class="btn btn-danger btn-sm"
                                                   onclick="return confirm('Excluir tamanho?')">🗑️</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif ($action === 'create' || $action === 'edit'): ?>
            <!-- Size Form -->
            <div class="form-card">
                <div class="form-header">
                    <h2 class="form-title">
                        <?php echo $action === 'create' ? '➕ Novo Tamanho' : '✏️ Editar Tamanho'; ?>
                    </h2>
                    <p style="color: #6c757d;">
                        <?php echo $action === 'create' ? 'Adicione um novo tamanho ao sistema' : 'Modifique as informações do tamanho'; ?>
                    </p>
                </div>

                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Número do Tamanho *</label>
                            <input type="text" name="numero" required 
                                   value="<?php echo htmlspecialchars($currentSize['numero'] ?? ''); ?>"
                                   placeholder="Ex: 33, 34, 35..."
                                   <?php echo $action === 'edit' ? '' : 'autofocus'; ?>>
                        </div>
                        
                        <div class="form-group">
                            <label>Ordem de Exibição *</label>
                            <input type="number" name="ordem" required min="1"
                                   value="<?php echo $currentSize['ordem'] ?? ''; ?>"
                                   placeholder="Ex: 1, 2, 3...">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="ativo" id="ativo" 
                                   <?php echo ($currentSize['ativo'] ?? 1) ? 'checked' : ''; ?>>
                            <label for="ativo">Tamanho ativo</label>
                        </div>
                        <small style="color: #6c757d;">Tamanhos inativos não aparecem para seleção</small>
                    </div>

                    <div style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #f0f0f0;">
                        <button type="submit" class="btn btn-success">
                            <?php echo $action === 'create' ? '💾 Criar Tamanho' : '💾 Atualizar Tamanho'; ?>
                        </button>
                        <a href="?" class="btn btn-secondary">❌ Cancelar</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        let sortingEnabled = false;

        // Toggle between grid and table view
        function toggleView(view) {
            const gridView = document.getElementById('grid-view');
            const tableView = document.getElementById('table-view');
            const buttons = document.querySelectorAll('.view-toggle button');
            
            buttons.forEach(btn => btn.classList.remove('active'));
            
            if (view === 'grid') {
                gridView.style.display = 'grid';
                tableView.style.display = 'none';
                buttons[0].classList.add('active');
            } else {
                gridView.style.display = 'none';
                tableView.style.display = 'block';
                buttons[1].classList.add('active');
            }
        }

        // Toggle bulk create section
        function toggleBulkCreate() {
            const section = document.getElementById('bulk-create-section');
            section.style.display = section.style.display === 'none' ? 'block' : 'none';
        }

        // Enable sorting functionality
        function enableSorting() {
            if (sortingEnabled) {
                saveSortOrder();
                return;
            }
            
            sortingEnabled = true;
            const tbody = document.getElementById('sortable-sizes');
            const button = event.target;
            
            button.textContent = '💾 Salvar Ordem';
            button.classList.remove('btn-info');
            button.classList.add('btn-success');
            
            // Make rows draggable
            Array.from(tbody.children).forEach(row => {
                row.draggable = true;
                row.classList.add('sortable');
                
                row.addEventListener('dragstart', function(e) {
                    e.dataTransfer.setData('text/plain', e.target.dataset.id);
                    e.target.style.opacity = '0.5';
                });
                
                row.addEventListener('dragend', function(e) {
                    e.target.style.opacity = '1';
                });
                
                row.addEventListener('dragover', function(e) {
                    e.preventDefault();
                });
                
                row.addEventListener('drop', function(e) {
                    e.preventDefault();
                    const draggedId = e.dataTransfer.getData('text/plain');
                    const draggedElement = document.querySelector(`[data-id="${draggedId}"]`);
                    const dropTarget = e.target.closest('tr');
                    
                    if (draggedElement && dropTarget && draggedElement !== dropTarget) {
                        const parent = dropTarget.parentNode;
                        const nextSibling = dropTarget.nextSibling;
                        parent.insertBefore(draggedElement, nextSibling);
                    }
                });
            });
        }

        // Save sort order
        function saveSortOrder() {
            const tbody = document.getElementById('sortable-sizes');
            const rows = Array.from(tbody.children);
            const form = document.getElementById('reorder-form');
            
            // Clear existing inputs
            form.innerHTML = '<input type="hidden" name="action" value="reorder">';
            
            // Add size IDs in new order
            rows.forEach((row, index) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'sizes[]';
                input.value = row.dataset.id;
                form.appendChild(input);
            });
            
            form.submit();
        }

        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            });
            
            // Auto-suggest next order number
            const ordemInput = document.querySelector('input[name="ordem"]');
            if (ordemInput && !ordemInput.value) {
                // Calculate next order number
                const maxOrder = <?php echo empty($sizes) ? 0 : max(array_column($sizes, 'ordem')); ?>;
                ordemInput.value = maxOrder + 1;
            }
        });

        // Validate form
        function validateSizeForm() {
            const numero = document.querySelector('input[name="numero"]').value.trim();
            const ordem = document.querySelector('input[name="ordem"]').value;
            
            if (!numero) {
                alert('Número do tamanho é obrigatório');
                return false;
            }
            
            if (!ordem || ordem < 1) {
                alert('Ordem deve ser um número positivo');
                return false;
            }
            
            return true;
        }

        // Add form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form[method="POST"]');
            if (form && !form.querySelector('input[name="action"][value="reorder"]')) {
                form.addEventListener('submit', function(e) {
                    if (!validateSizeForm()) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html>