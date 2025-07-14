<?php
// admin/clients.php - Gestão Completa de Clientes B2B
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
    
    if (!function_exists('formatPrice')) {
        function formatPrice($price) {
            return 'R$ ' . number_format((float)$price, 2, ',', '.');
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

// Implementação simples de classe Client para evitar dependências
class SimpleClient {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function getAll($filters = []) {
        $where = ['u.ativo = 1'];
        $params = [];
        
        if (!empty($filters['search'])) {
            $where[] = "(cb.razao_social LIKE ? OR cb.cnpj LIKE ? OR u.email LIKE ?)";
            $search = "%{$filters['search']}%";
            $params = array_merge($params, [$search, $search, $search]);
        }
        
        if (!empty($filters['estado'])) {
            $where[] = "cb.estado = ?";
            $params[] = $filters['estado'];
        }
        
        if ($filters['status'] === 'ativo') {
            $where[] = "u.ativo = 1";
        } elseif ($filters['status'] === 'inativo') {
            $where[] = "u.ativo = 0";
        }
        
        $orderBy = $filters['orderBy'] ?? 'cb.razao_social';
        $sql = "SELECT cb.*, u.nome, u.email, u.ativo as usuario_ativo,
                       (SELECT MAX(data_pedido) FROM pedidos p WHERE p.cliente_id = cb.id) as ultimo_pedido,
                       (SELECT COUNT(*) FROM pedidos p WHERE p.cliente_id = cb.id) as total_pedidos
                FROM clientes_b2b cb 
                JOIN usuarios u ON cb.usuario_id = u.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY $orderBy ASC";
        
        try {
            $stmt = $this->db->query($sql, $params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
    
    public function getById($id) {
        $sql = "SELECT cb.*, u.nome, u.email, u.ativo as usuario_ativo
                FROM clientes_b2b cb 
                JOIN usuarios u ON cb.usuario_id = u.id
                WHERE cb.id = ?";
        try {
            $stmt = $this->db->query($sql, [$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function create($data) {
        $sql = "INSERT INTO clientes_b2b (usuario_id, razao_social, cnpj, inscricao_estadual, telefone, endereco, cidade, estado, cep, desconto_padrao, limite_credito) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->query($sql, [
            $data['usuario_id'], $data['razao_social'], $data['cnpj'], 
            $data['inscricao_estadual'], $data['telefone'], $data['endereco'],
            $data['cidade'], $data['estado'], $data['cep'],
            $data['desconto_padrao'], $data['limite_credito']
        ]);
        return $this->db->lastInsertId();
    }
    
    public function update($id, $data) {
        $sql = "UPDATE clientes_b2b SET razao_social = ?, inscricao_estadual = ?, telefone = ?, endereco = ?, cidade = ?, estado = ?, cep = ?, desconto_padrao = ?, limite_credito = ? WHERE id = ?";
        return $this->db->query($sql, [
            $data['razao_social'], $data['inscricao_estadual'], $data['telefone'],
            $data['endereco'], $data['cidade'], $data['estado'], $data['cep'],
            $data['desconto_padrao'], $data['limite_credito'], $id
        ]);
    }
    
    public function existsByCNPJ($cnpj) {
        $sql = "SELECT id FROM clientes_b2b WHERE cnpj = ?";
        $stmt = $this->db->query($sql, [$cnpj]);
        return $stmt->fetch() !== false;
    }
    
    public function getGeneralStats() {
        try {
            $stats = [];
            
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM clientes_b2b cb JOIN usuarios u ON cb.usuario_id = u.id");
            $stats['total_clientes'] = $stmt->fetchColumn();
            
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM clientes_b2b cb JOIN usuarios u ON cb.usuario_id = u.id WHERE u.ativo = 1");
            $stats['clientes_ativos'] = $stmt->fetchColumn();
            
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM pedidos WHERE MONTH(data_pedido) = MONTH(CURRENT_DATE) AND YEAR(data_pedido) = YEAR(CURRENT_DATE)");
            $stats['pedidos_mes'] = $stmt->fetchColumn();
            
            $stmt = $this->db->query("SELECT COALESCE(SUM(total), 0) as total FROM pedidos WHERE MONTH(data_pedido) = MONTH(CURRENT_DATE) AND YEAR(data_pedido) = YEAR(CURRENT_DATE)");
            $stats['faturamento_mes'] = $stmt->fetchColumn();
            
            return $stats;
        } catch (Exception $e) {
            return ['total_clientes' => 0, 'clientes_ativos' => 0, 'pedidos_mes' => 0, 'faturamento_mes' => 0];
        }
    }
    
    public function getStats($clientId) {
        try {
            $stats = [];
            
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM pedidos WHERE cliente_id = ?", [$clientId]);
            $stats['total_pedidos'] = $stmt->fetchColumn();
            
            $stmt = $this->db->query("SELECT COALESCE(SUM(total), 0) as total FROM pedidos WHERE cliente_id = ?", [$clientId]);
            $stats['valor_total'] = $stmt->fetchColumn();
            
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM pedidos WHERE cliente_id = ? AND status = 'pendente'", [$clientId]);
            $stats['pedidos_pendentes'] = $stmt->fetchColumn();
            
            $stmt = $this->db->query("SELECT MAX(data_pedido) as ultimo FROM pedidos WHERE cliente_id = ?", [$clientId]);
            $stats['ultimo_pedido'] = $stmt->fetchColumn();
            
            return $stats;
        } catch (Exception $e) {
            return ['total_pedidos' => 0, 'valor_total' => 0, 'pedidos_pendentes' => 0, 'ultimo_pedido' => null];
        }
    }
    
    public function getOrders($clientId, $limit = 10) {
        $sql = "SELECT numero_pedido, data_pedido, total, status FROM pedidos WHERE cliente_id = ? ORDER BY data_pedido DESC LIMIT ?";
        try {
            $stmt = $this->db->query($sql, [$clientId, $limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
}

class SimpleUser {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function create($data) {
        $sql = "INSERT INTO usuarios (nome, email, senha, tipo, ativo) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->query($sql, [
            $data['nome'], $data['email'], $data['senha'], $data['tipo'], $data['ativo']
        ]);
        return $this->db->lastInsertId();
    }
    
    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $params[] = $value;
        }
        
        $params[] = $id;
        $sql = "UPDATE usuarios SET " . implode(', ', $fields) . " WHERE id = ?";
        return $this->db->query($sql, $params);
    }
    
    public function existsByEmail($email) {
        $sql = "SELECT id FROM usuarios WHERE email = ?";
        $stmt = $this->db->query($sql, [$email]);
        return $stmt->fetch() !== false;
    }
    
    public function updateStatus($id, $status) {
        $sql = "UPDATE usuarios SET ativo = ? WHERE id = ?";
        return $this->db->query($sql, [$status ? 1 : 0, $id]);
    }
}

// Inicializar classes
$client = new SimpleClient();
$user = new SimpleUser();

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
                // Validar CNPJ
                $cnpj = preg_replace('/[^0-9]/', '', $_POST['cnpj']);
                if (strlen($cnpj) !== 14) {
                    throw new Exception('CNPJ deve ter 14 dígitos');
                }
                
                // Verificar se CNPJ já existe
                if ($client->existsByCNPJ($cnpj)) {
                    throw new Exception('CNPJ já cadastrado no sistema');
                }
                
                // Criar usuário primeiro
                $userData = [
                    'nome' => sanitizeInput($_POST['nome_responsavel']),
                    'email' => sanitizeInput($_POST['email']),
                    'senha' => password_hash($_POST['senha'], PASSWORD_DEFAULT),
                    'tipo' => 'cliente_b2b',
                    'ativo' => 1
                ];
                
                // Verificar se email já existe
                if ($user->existsByEmail($userData['email'])) {
                    throw new Exception('Email já cadastrado no sistema');
                }
                
                $userId = $user->create($userData);
                
                // Criar cliente
                $clientData = [
                    'usuario_id' => $userId,
                    'razao_social' => sanitizeInput($_POST['razao_social']),
                    'cnpj' => $cnpj,
                    'inscricao_estadual' => sanitizeInput($_POST['inscricao_estadual']),
                    'telefone' => sanitizeInput($_POST['telefone']),
                    'endereco' => sanitizeInput($_POST['endereco']),
                    'cidade' => sanitizeInput($_POST['cidade']),
                    'estado' => sanitizeInput($_POST['estado']),
                    'cep' => preg_replace('/[^0-9]/', '', $_POST['cep']),
                    'desconto_padrao' => floatval($_POST['desconto_padrao'] ?? 0),
                    'limite_credito' => floatval($_POST['limite_credito'] ?? 0)
                ];
                
                $clientId = $client->create($clientData);
                
                logActivity($_SESSION['user_id'], 'client_created', "Cliente criado: {$clientData['razao_social']} (ID: $clientId)");
                $success = 'Cliente criado com sucesso!';
                $action = 'list';
                
            } catch (Exception $e) {
                $error = 'Erro ao criar cliente: ' . $e->getMessage();
            }
            break;
            
        case 'update':
            try {
                $clientData = [
                    'razao_social' => sanitizeInput($_POST['razao_social']),
                    'inscricao_estadual' => sanitizeInput($_POST['inscricao_estadual']),
                    'telefone' => sanitizeInput($_POST['telefone']),
                    'endereco' => sanitizeInput($_POST['endereco']),
                    'cidade' => sanitizeInput($_POST['cidade']),
                    'estado' => sanitizeInput($_POST['estado']),
                    'cep' => preg_replace('/[^0-9]/', '', $_POST['cep']),
                    'desconto_padrao' => floatval($_POST['desconto_padrao'] ?? 0),
                    'limite_credito' => floatval($_POST['limite_credito'] ?? 0)
                ];
                
                // Atualizar dados do usuário
                $userData = [
                    'nome' => sanitizeInput($_POST['nome_responsavel'])
                ];
                
                // Atualizar senha se fornecida
                if (!empty($_POST['nova_senha'])) {
                    $userData['senha'] = password_hash($_POST['nova_senha'], PASSWORD_DEFAULT);
                }
                
                $currentClient = $client->getById($id);
                $user->update($currentClient['usuario_id'], $userData);
                $client->update($id, $clientData);
                
                logActivity($_SESSION['user_id'], 'client_updated', "Cliente atualizado: {$clientData['razao_social']} (ID: $id)");
                $success = 'Cliente atualizado com sucesso!';
                $action = 'list';
                
            } catch (Exception $e) {
                $error = 'Erro ao atualizar cliente: ' . $e->getMessage();
            }
            break;
            
        case 'toggle_status':
            try {
                $clientId = $_POST['id'] ?? $id;
                if (!$clientId) {
                    throw new Exception('ID do cliente não fornecido');
                }
                
                $currentClient = $client->getById($clientId);
                if (!$currentClient) {
                    throw new Exception('Cliente não encontrado');
                }
                
                $newStatus = !$currentClient['usuario_ativo'];
                $user->updateStatus($currentClient['usuario_id'], $newStatus);
                
                $statusText = $newStatus ? 'ativado' : 'desativado';
                logActivity($_SESSION['user_id'], 'client_status_changed', "Cliente {$statusText}: {$currentClient['razao_social']} (ID: $clientId)");
                $success = "Cliente {$statusText} com sucesso!";
                $action = 'list';
                
            } catch (Exception $e) {
                $error = 'Erro ao alterar status: ' . $e->getMessage();
            }
            break;
    }
}

// Ações GET
if ($action === 'delete' && $id) {
    try {
        $currentClient = $client->getById($id);
        
        // Verificar se tem pedidos
        $stmt = (new Database())->query("SELECT COUNT(*) FROM pedidos WHERE cliente_id = ?", [$id]);
        $orderCount = $stmt->fetchColumn();
        
        if ($orderCount > 0) {
            throw new Exception('Não é possível excluir cliente com pedidos. Desative o cliente ao invés de excluir.');
        }
        
        // Excluir cliente e usuário
        (new Database())->query("DELETE FROM clientes_b2b WHERE id = ?", [$id]);
        (new Database())->query("DELETE FROM usuarios WHERE id = ?", [$currentClient['usuario_id']]);
        
        logActivity($_SESSION['user_id'], 'client_deleted', "Cliente removido: {$currentClient['razao_social']} (ID: $id)");
        $success = 'Cliente removido com sucesso!';
        $action = 'list';
        
    } catch (Exception $e) {
        $error = 'Erro ao remover cliente: ' . $e->getMessage();
    }
}

// Buscar dados para exibição
if ($action === 'edit' && $id) {
    $currentClient = $client->getById($id);
    if (!$currentClient) {
        $error = 'Cliente não encontrado';
        $action = 'list';
    }
}

if ($action === 'view' && $id) {
    $currentClient = $client->getById($id);
    $clientOrders = $client->getOrders($id, 10);
    $clientStats = $client->getStats($id);
    if (!$currentClient) {
        $error = 'Cliente não encontrado';
        $action = 'list';
    }
}

// Filtros para listagem
$filters = [
    'search' => $_GET['search'] ?? '',
    'estado' => $_GET['estado'] ?? '',
    'status' => $_GET['status'] ?? 'ativo',
    'orderBy' => $_GET['orderBy'] ?? 'razao_social'
];

$clients = $client->getAll($filters);
$stats = $client->getGeneralStats();

// Estados brasileiros
$estados = [
    'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
    'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
    'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
    'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
    'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
    'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
    'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins'
];

// Funções auxiliares específicas
if (!function_exists('formatCNPJ')) {
    function formatCNPJ($cnpj) {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpj);
    }
}

if (!function_exists('formatCEP')) {
    function formatCEP($cep) {
        $cep = preg_replace('/[^0-9]/', '', $cep);
        return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $cep);
    }
}

if (!function_exists('formatPhone')) {
    function formatPhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) === 11) {
            return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $phone);
        } elseif (strlen($phone) === 10) {
            return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $phone);
        }
        return $phone;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Clientes B2B - Admin</title>
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
            max-width: 1400px;
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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

        .clients-table {
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

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
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
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .client-detail {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .client-header {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
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

        .orders-preview {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-top: 2rem;
        }

        .order-item {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 0.75rem;
            background: white;
            border-radius: 8px;
            margin-bottom: 0.5rem;
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
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .client-header {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-left">
            <h1>Gestão de Clientes B2B</h1>
            <div class="breadcrumb">Dashboard / Clientes</div>
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
                    <h2 class="page-title">Clientes B2B</h2>
                    <p style="color: #6c757d;">Gerencie seus clientes corporativos</p>
                </div>
                <div class="actions-bar">
                    <a href="?action=create" class="btn btn-success">
                        <span>➕</span> Novo Cliente
                    </a>
                    <a href="?action=export" class="btn btn-primary">
                        <span>📊</span> Exportar
                    </a>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card" style="--card-color: #667eea;">
                    <div class="stat-icon">👥</div>
                    <div class="stat-value"><?php echo number_format($stats['total_clientes'] ?? 0); ?></div>
                    <div class="stat-label">Total de Clientes</div>
                </div>
                <div class="stat-card" style="--card-color: #28a745;">
                    <div class="stat-icon">✅</div>
                    <div class="stat-value"><?php echo number_format($stats['clientes_ativos'] ?? 0); ?></div>
                    <div class="stat-label">Clientes Ativos</div>
                </div>
                <div class="stat-card" style="--card-color: #ffc107;">
                    <div class="stat-icon">🛒</div>
                    <div class="stat-value"><?php echo number_format($stats['pedidos_mes'] ?? 0); ?></div>
                    <div class="stat-label">Pedidos Este Mês</div>
                </div>
                <div class="stat-card" style="--card-color: #17a2b8;">
                    <div class="stat-icon">💰</div>
                    <div class="stat-value"><?php echo formatPrice($stats['faturamento_mes'] ?? 0); ?></div>
                    <div class="stat-label">Faturamento do Mês</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <h3 style="margin-bottom: 1rem;">🔍 Filtros</h3>
                <form method="GET">
                    <div class="filters-grid">
                        <div class="form-group">
                            <label>Buscar</label>
                            <input type="text" name="search" placeholder="Razão social, CNPJ ou email..." 
                                   value="<?php echo htmlspecialchars($filters['search']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Estado</label>
                            <select name="estado">
                                <option value="">Todos os estados</option>
                                <?php foreach ($estados as $uf => $nome): ?>
                                    <option value="<?php echo $uf; ?>" 
                                            <?php echo $filters['estado'] === $uf ? 'selected' : ''; ?>>
                                        <?php echo $uf . ' - ' . $nome; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="ativo" <?php echo $filters['status'] === 'ativo' ? 'selected' : ''; ?>>Ativos</option>
                                <option value="inativo" <?php echo $filters['status'] === 'inativo' ? 'selected' : ''; ?>>Inativos</option>
                                <option value="todos" <?php echo $filters['status'] === 'todos' ? 'selected' : ''; ?>>Todos</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Ordenar por</label>
                            <select name="orderBy">
                                <option value="razao_social" <?php echo $filters['orderBy'] === 'razao_social' ? 'selected' : ''; ?>>Razão Social</option>
                                <option value="data_criacao" <?php echo $filters['orderBy'] === 'data_criacao' ? 'selected' : ''; ?>>Data de Cadastro</option>
                                <option value="ultimo_pedido" <?php echo $filters['orderBy'] === 'ultimo_pedido' ? 'selected' : ''; ?>>Último Pedido</option>
                            </select>
                        </div>
                    </div>
                    <div style="margin-top: 1rem;">
                        <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                        <a href="?" class="btn btn-secondary">🔄 Limpar</a>
                    </div>
                </form>
            </div>

            <!-- Clients Table -->
            <div class="clients-table">
                <div class="table-header">
                    <h3>Lista de Clientes</h3>
                    <div>
                        Total: <?php echo count($clients); ?> cliente(s)
                    </div>
                </div>

                <?php if (empty($clients)): ?>
                    <div class="empty-state">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">👥</div>
                        <h3>Nenhum cliente encontrado</h3>
                        <p>Comece cadastrando seu primeiro cliente B2B</p>
                        <a href="?action=create" class="btn btn-success" style="margin-top: 1rem;">
                            ➕ Cadastrar Primeiro Cliente
                        </a>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Razão Social</th>
                                <th>CNPJ</th>
                                <th>Responsável</th>
                                <th>Cidade/UF</th>
                                <th>Status</th>
                                <th>Último Pedido</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $cliente): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($cliente['razao_social']); ?></div>
                                        <div style="font-size: 0.85rem; color: #6c757d;">
                                            <?php echo htmlspecialchars($cliente['email']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo formatCNPJ($cliente['cnpj']); ?></td>
                                    <td><?php echo htmlspecialchars($cliente['nome']); ?></td>
                                    <td><?php echo htmlspecialchars($cliente['cidade']); ?>/<?php echo htmlspecialchars($cliente['estado']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $cliente['usuario_ativo'] ? 'ativo' : 'inativo'; ?>">
                                            <?php echo $cliente['usuario_ativo'] ? 'Ativo' : 'Inativo'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($cliente['ultimo_pedido']): ?>
                                            <?php echo date('d/m/Y', strtotime($cliente['ultimo_pedido'])); ?>
                                        <?php else: ?>
                                            <span style="color: #6c757d;">Nenhum</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 0.25rem;">
                                            <a href="?action=view&id=<?php echo $cliente['id']; ?>" 
                                               class="btn btn-info btn-sm" title="Ver Detalhes">👁️</a>
                                            <a href="?action=edit&id=<?php echo $cliente['id']; ?>" 
                                               class="btn btn-primary btn-sm" title="Editar">✏️</a>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Alterar status do cliente?')">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="id" value="<?php echo $cliente['id']; ?>">
                                                <button type="submit" 
                                                        class="btn <?php echo $cliente['usuario_ativo'] ? 'btn-warning' : 'btn-success'; ?> btn-sm"
                                                        title="<?php echo $cliente['usuario_ativo'] ? 'Desativar' : 'Ativar'; ?>">
                                                    <?php echo $cliente['usuario_ativo'] ? '⏸️' : '▶️'; ?>
                                                </button>
                                            </form>
                                            <?php if ($cliente['total_pedidos'] == 0): ?>
                                                <a href="?action=delete&id=<?php echo $cliente['id']; ?>" 
                                                   class="btn btn-danger btn-sm" title="Excluir"
                                                   onclick="return confirm('Tem certeza que deseja excluir este cliente?')">🗑️</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        <?php elseif ($action === 'create' || $action === 'edit'): ?>
            <!-- Client Form -->
            <div class="form-card">
                <div class="form-header">
                    <h2 class="form-title">
                        <?php echo $action === 'create' ? '➕ Novo Cliente B2B' : '✏️ Editar Cliente'; ?>
                    </h2>
                    <p style="color: #6c757d;">
                        <?php echo $action === 'create' ? 'Cadastre um novo cliente corporativo' : 'Modifique as informações do cliente'; ?>
                    </p>
                </div>

                <form method="POST">
                    <h3 style="margin-bottom: 1rem; color: #667eea;">👤 Dados da Empresa</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Razão Social *</label>
                            <input type="text" name="razao_social" required 
                                   value="<?php echo htmlspecialchars($currentClient['razao_social'] ?? ''); ?>"
                                   placeholder="Nome completo da empresa">
                        </div>
                        
                        <div class="form-group">
                            <label>CNPJ *</label>
                            <input type="text" name="cnpj" required maxlength="18"
                                   value="<?php echo $action === 'edit' ? formatCNPJ($currentClient['cnpj']) : ''; ?>"
                                   placeholder="00.000.000/0000-00"
                                   <?php echo $action === 'edit' ? 'readonly' : ''; ?>
                                   onkeyup="maskCNPJ(this)">
                        </div>
                        
                        <div class="form-group">
                            <label>Inscrição Estadual</label>
                            <input type="text" name="inscricao_estadual" 
                                   value="<?php echo htmlspecialchars($currentClient['inscricao_estadual'] ?? ''); ?>"
                                   placeholder="000.000.000.000">
                        </div>
                        
                        <div class="form-group">
                            <label>Telefone *</label>
                            <input type="text" name="telefone" required 
                                   value="<?php echo htmlspecialchars($currentClient['telefone'] ?? ''); ?>"
                                   placeholder="(11) 99999-9999"
                                   onkeyup="maskPhone(this)">
                        </div>
                    </div>

                    <h3 style="margin: 2rem 0 1rem; color: #667eea;">📍 Endereço</h3>
                    <div class="form-grid">
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Endereço Completo *</label>
                            <textarea name="endereco" required 
                                      placeholder="Rua, número, complemento, bairro"><?php echo htmlspecialchars($currentClient['endereco'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Cidade *</label>
                            <input type="text" name="cidade" required 
                                   value="<?php echo htmlspecialchars($currentClient['cidade'] ?? ''); ?>"
                                   placeholder="Nome da cidade">
                        </div>
                        
                        <div class="form-group">
                            <label>Estado *</label>
                            <select name="estado" required>
                                <option value="">Selecione o estado</option>
                                <?php foreach ($estados as $uf => $nome): ?>
                                    <option value="<?php echo $uf; ?>"
                                            <?php echo ($currentClient['estado'] ?? '') === $uf ? 'selected' : ''; ?>>
                                        <?php echo $uf . ' - ' . $nome; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>CEP *</label>
                            <input type="text" name="cep" required maxlength="9"
                                   value="<?php echo $action === 'edit' ? formatCEP($currentClient['cep']) : ''; ?>"
                                   placeholder="00000-000"
                                   onkeyup="maskCEP(this)">
                        </div>
                    </div>

                    <h3 style="margin: 2rem 0 1rem; color: #667eea;">👤 Responsável e Acesso</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nome do Responsável *</label>
                            <input type="text" name="nome_responsavel" required 
                                   value="<?php echo htmlspecialchars($currentClient['nome'] ?? ''); ?>"
                                   placeholder="Nome da pessoa responsável">
                        </div>
                        
                        <div class="form-group">
                            <label>Email de Acesso *</label>
                            <input type="email" name="email" required 
                                   value="<?php echo htmlspecialchars($currentClient['email'] ?? ''); ?>"
                                   placeholder="email@empresa.com"
                                   <?php echo $action === 'edit' ? 'readonly' : ''; ?>>
                        </div>
                        
                        <?php if ($action === 'create'): ?>
                            <div class="form-group">
                                <label>Senha *</label>
                                <input type="password" name="senha" required minlength="6"
                                       placeholder="Mínimo 6 caracteres">
                            </div>
                        <?php else: ?>
                            <div class="form-group">
                                <label>Nova Senha (deixe em branco para manter)</label>
                                <input type="password" name="nova_senha" minlength="6"
                                       placeholder="Nova senha (opcional)">
                            </div>
                        <?php endif; ?>
                    </div>

                    <h3 style="margin: 2rem 0 1rem; color: #667eea;">💰 Configurações Comerciais</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Desconto Padrão (%)</label>
                            <input type="number" step="0.01" min="0" max="100" name="desconto_padrao" 
                                   value="<?php echo $currentClient['desconto_padrao'] ?? '0'; ?>"
                                   placeholder="0.00">
                        </div>
                        
                        <div class="form-group">
                            <label>Limite de Crédito (R$)</label>
                            <input type="number" step="0.01" min="0" name="limite_credito" 
                                   value="<?php echo $currentClient['limite_credito'] ?? '0'; ?>"
                                   placeholder="0.00">
                        </div>
                    </div>

                    <div style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #f0f0f0;">
                        <button type="submit" class="btn btn-success">
                            <?php echo $action === 'create' ? '💾 Cadastrar Cliente' : '💾 Atualizar Cliente'; ?>
                        </button>
                        <a href="?" class="btn btn-secondary">❌ Cancelar</a>
                    </div>
                </form>
            </div>

        <?php elseif ($action === 'view' && $currentClient): ?>
            <!-- Client Details -->
            <div class="client-detail">
                <div class="client-header">
                    <div>
                        <h2><?php echo htmlspecialchars($currentClient['razao_social']); ?></h2>
                        <p><strong>CNPJ:</strong> <?php echo formatCNPJ($currentClient['cnpj']); ?></p>
                        <p><strong>Responsável:</strong> <?php echo htmlspecialchars($currentClient['nome']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($currentClient['email']); ?></p>
                        <p><strong>Telefone:</strong> <?php echo formatPhone($currentClient['telefone']); ?></p>
                    </div>
                    <div>
                        <p><strong>Status:</strong> 
                            <span class="status-badge status-<?php echo $currentClient['usuario_ativo'] ? 'ativo' : 'inativo'; ?>">
                                <?php echo $currentClient['usuario_ativo'] ? 'Ativo' : 'Inativo'; ?>
                            </span>
                        </p>
                        <p><strong>Cadastrado em:</strong> <?php echo date('d/m/Y', strtotime($currentClient['data_criacao'])); ?></p>
                        <p><strong>Desconto:</strong> <?php echo number_format($currentClient['desconto_padrao'], 2); ?>%</p>
                        <p><strong>Limite:</strong> <?php echo formatPrice($currentClient['limite_credito']); ?></p>
                    </div>
                </div>

                <!-- Address -->
                <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
                    <h4 style="margin-bottom: 0.5rem;">📍 Endereço</h4>
                    <p><?php echo htmlspecialchars($currentClient['endereco']); ?></p>
                    <p><?php echo htmlspecialchars($currentClient['cidade']); ?> - <?php echo htmlspecialchars($currentClient['estado']); ?></p>
                    <p>CEP: <?php echo formatCEP($currentClient['cep']); ?></p>
                </div>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card" style="--card-color: #28a745;">
                        <div class="stat-value"><?php echo $clientStats['total_pedidos'] ?? 0; ?></div>
                        <div class="stat-label">Total de Pedidos</div>
                    </div>
                    <div class="stat-card" style="--card-color: #17a2b8;">
                        <div class="stat-value"><?php echo formatPrice($clientStats['valor_total'] ?? 0); ?></div>
                        <div class="stat-label">Valor Total Comprado</div>
                    </div>
                    <div class="stat-card" style="--card-color: #ffc107;">
                        <div class="stat-value"><?php echo $clientStats['pedidos_pendentes'] ?? 0; ?></div>
                        <div class="stat-label">Pedidos Pendentes</div>
                    </div>
                    <div class="stat-card" style="--card-color: #dc3545;">
                        <div class="stat-value">
                            <?php echo $clientStats['ultimo_pedido'] ? date('d/m/Y', strtotime($clientStats['ultimo_pedido'])) : 'Nunca'; ?>
                        </div>
                        <div class="stat-label">Último Pedido</div>
                    </div>
                </div>

                <!-- Recent Orders -->
                <?php if (!empty($clientOrders)): ?>
                    <div class="orders-preview">
                        <h4 style="margin-bottom: 1rem;">🛒 Pedidos Recentes</h4>
                        <?php foreach ($clientOrders as $order): ?>
                            <div class="order-item">
                                <div>
                                    <strong><?php echo htmlspecialchars($order['numero_pedido']); ?></strong><br>
                                    <small><?php echo date('d/m/Y H:i', strtotime($order['data_pedido'])); ?></small>
                                </div>
                                <div>
                                    <?php echo formatPrice($order['total']); ?>
                                </div>
                                <div>
                                    <span class="status-badge status-<?php echo $order['status']; ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div style="text-align: center; margin-top: 1rem;">
                            <a href="orders.php?cliente_id=<?php echo $currentClient['id']; ?>" class="btn btn-primary">
                                Ver Todos os Pedidos
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <div style="margin-top: 2rem;">
                    <a href="?" class="btn btn-primary">← Voltar</a>
                    <a href="?action=edit&id=<?php echo $currentClient['id']; ?>" class="btn btn-warning">✏️ Editar</a>
                    <a href="orders.php?action=create&cliente_id=<?php echo $currentClient['id']; ?>" class="btn btn-success">🛒 Novo Pedido</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Máscaras para inputs
        function maskCNPJ(input) {
            let value = input.value.replace(/\D/g, '');
            value = value.replace(/(\d{2})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1/$2');
            value = value.replace(/(\d{4})(\d)/, '$1-$2');
            input.value = value;
        }

        function maskCEP(input) {
            let value = input.value.replace(/\D/g, '');
            value = value.replace(/(\d{5})(\d)/, '$1-$2');
            input.value = value;
        }

        function maskPhone(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length <= 10) {
                value = value.replace(/(\d{2})(\d)/, '($1) $2');
                value = value.replace(/(\d{4})(\d)/, '$1-$2');
            } else {
                value = value.replace(/(\d{2})(\d)/, '($1) $2');
                value = value.replace(/(\d{5})(\d)/, '$1-$2');
            }
            input.value = value;
        }

        // Busca CEP automaticamente
        function searchCEP(cep) {
            cep = cep.replace(/\D/g, '');
            if (cep.length === 8) {
                fetch(`https://viacep.com.br/ws/${cep}/json/`)
                    .then(response => response.json())
                    .then(data => {
                        if (!data.erro) {
                            document.querySelector('input[name="endereco"]').value = 
                                `${data.logradouro}, ${data.bairro}`;
                            document.querySelector('input[name="cidade"]').value = data.localidade;
                            document.querySelector('select[name="estado"]').value = data.uf;
                        }
                    })
                    .catch(error => console.log('Erro ao buscar CEP:', error));
            }
        }

        // Adicionar evento no campo CEP
        document.addEventListener('DOMContentLoaded', function() {
            const cepInput = document.querySelector('input[name="cep"]');
            if (cepInput) {
                cepInput.addEventListener('blur', function() {
                    searchCEP(this.value);
                });
            }

            // Validação de CNPJ
            const cnpjInput = document.querySelector('input[name="cnpj"]');
            if (cnpjInput) {
                cnpjInput.addEventListener('blur', function() {
                    const cnpj = this.value.replace(/\D/g, '');
                    if (cnpj.length === 14 && !validateCNPJ(cnpj)) {
                        alert('CNPJ inválido!');
                        this.focus();
                    }
                });
            }
        });

        // Validação de CNPJ
        function validateCNPJ(cnpj) {
            if (cnpj.length !== 14) return false;
            
            // Verifica se todos os dígitos são iguais
            if (/^(\d)\1+$/.test(cnpj)) return false;
            
            // Validação dos dígitos verificadores
            let size = cnpj.length - 2;
            let numbers = cnpj.substring(0, size);
            let digits = cnpj.substring(size);
            let sum = 0;
            let pos = size - 7;
            
            for (let i = size; i >= 1; i--) {
                sum += numbers.charAt(size - i) * pos--;
                if (pos < 2) pos = 9;
            }
            
            let result = sum % 11 < 2 ? 0 : 11 - sum % 11;
            if (result != digits.charAt(0)) return false;
            
            size = size + 1;
            numbers = cnpj.substring(0, size);
            sum = 0;
            pos = size - 7;
            
            for (let i = size; i >= 1; i--) {
                sum += numbers.charAt(size - i) * pos--;
                if (pos < 2) pos = 9;
            }
            
            result = sum % 11 < 2 ? 0 : 11 - sum % 11;
            return result == digits.charAt(1);
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
        });
    </script>
</body>
</html>