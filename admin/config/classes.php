<?php
// config/classes.php - Classes principais do sistema
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Classe base para gerenciamento de sessões
 */
class SessionManager {
    public static function start() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public static function requireLogin() {
        self::start();
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
            header('Location: login.php');
            exit;
        }
    }
    
    public static function requireAdmin() {
        self::start();
        if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
            header('Location: ../login.php');
            exit;
        }
    }
    
    public static function isAdmin() {
        self::start();
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
    }
    
    public static function get($key) {
        self::start();
        return $_SESSION[$key] ?? null;
    }
    
    public static function set($key, $value) {
        self::start();
        $_SESSION[$key] = $value;
    }
    
    public static function destroy() {
        self::start();
        session_destroy();
    }
}

/**
 * Classe Category para gerenciar categorias
 */
class Category {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function getAll($activeOnly = true) {
        $sql = "SELECT * FROM categorias";
        if ($activeOnly) {
            $sql .= " WHERE ativo = 1";
        }
        $sql .= " ORDER BY nome";
        
        return $this->db->query($sql)->fetchAll();
    }
    
    public function getById($id) {
        $sql = "SELECT * FROM categorias WHERE id = ?";
        return $this->db->query($sql, [$id])->fetch();
    }
    
    public function create($data) {
        $sql = "INSERT INTO categorias (nome, descricao, ativo) VALUES (?, ?, 1)";
        $this->db->query($sql, [$data['nome'], $data['descricao']]);
        return $this->db->getLastInsertId();
    }
    
    public function update($id, $data) {
        $sql = "UPDATE categorias SET nome = ?, descricao = ? WHERE id = ?";
        return $this->db->query($sql, [$data['nome'], $data['descricao'], $id]);
    }
    
    public function delete($id) {
        $sql = "UPDATE categorias SET ativo = 0 WHERE id = ?";
        return $this->db->query($sql, [$id]);
    }
}

/**
 * Classe Color para gerenciar cores
 */
class Color {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function getAll($activeOnly = true) {
        $sql = "SELECT * FROM cores";
        if ($activeOnly) {
            $sql .= " WHERE ativo = 1";
        }
        $sql .= " ORDER BY nome";
        
        return $this->db->query($sql)->fetchAll();
    }
    
    public function getById($id) {
        $sql = "SELECT * FROM cores WHERE id = ?";
        return $this->db->query($sql, [$id])->fetch();
    }
    
    public function create($data) {
        $sql = "INSERT INTO cores (nome, codigo_hex, ativo) VALUES (?, ?, 1)";
        $this->db->query($sql, [$data['nome'], $data['codigo_hex']]);
        return $this->db->getLastInsertId();
    }
    
    public function update($id, $data) {
        $sql = "UPDATE cores SET nome = ?, codigo_hex = ? WHERE id = ?";
        return $this->db->query($sql, [$data['nome'], $data['codigo_hex'], $id]);
    }
    
    public function delete($id) {
        $sql = "UPDATE cores SET ativo = 0 WHERE id = ?";
        return $this->db->query($sql, [$id]);
    }
}

/**
 * Classe Size para gerenciar tamanhos
 */
class Size {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function getAll($activeOnly = true) {
        $sql = "SELECT * FROM tamanhos";
        if ($activeOnly) {
            $sql .= " WHERE ativo = 1";
        }
        $sql .= " ORDER BY ordem";
        
        return $this->db->query($sql)->fetchAll();
    }
    
    public function getById($id) {
        $sql = "SELECT * FROM tamanhos WHERE id = ?";
        return $this->db->query($sql, [$id])->fetch();
    }
    
    public function create($data) {
        $sql = "INSERT INTO tamanhos (numero, ordem, ativo) VALUES (?, ?, 1)";
        $this->db->query($sql, [$data['numero'], $data['ordem']]);
        return $this->db->getLastInsertId();
    }
    
    public function update($id, $data) {
        $sql = "UPDATE tamanhos SET numero = ?, ordem = ? WHERE id = ?";
        return $this->db->query($sql, [$data['numero'], $data['ordem'], $id]);
    }
    
    public function delete($id) {
        $sql = "UPDATE tamanhos SET ativo = 0 WHERE id = ?";
        return $this->db->query($sql, [$id]);
    }
}

/**
 * Classe Product para gerenciar produtos
 */
class Product {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function getAll($filters = []) {
        $sql = "SELECT p.*, c.nome as categoria_nome 
                FROM produtos p 
                LEFT JOIN categorias c ON p.categoria_id = c.id 
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['search'])) {
            $sql .= " AND (p.nome LIKE ? OR p.codigo_produto LIKE ? OR p.descricao LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['categoria_id'])) {
            $sql .= " AND p.categoria_id = ?";
            $params[] = $filters['categoria_id'];
        }
        
        if (!empty($filters['genero'])) {
            $sql .= " AND p.genero = ?";
            $params[] = $filters['genero'];
        }
        
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'ativo') {
                $sql .= " AND p.ativo = 1";
            } elseif ($filters['status'] === 'inativo') {
                $sql .= " AND p.ativo = 0";
            }
            // 'todos' não adiciona filtro
        }
        
        $sql .= " ORDER BY p.data_criacao DESC";
        
        return $this->db->query($sql, $params)->fetchAll();
    }
    
    public function getById($id) {
        $sql = "SELECT p.*, c.nome as categoria_nome 
                FROM produtos p 
                LEFT JOIN categorias c ON p.categoria_id = c.id 
                WHERE p.id = ?";
        return $this->db->query($sql, [$id])->fetch();
    }
    
    public function create($data) {
        $sql = "INSERT INTO produtos (categoria_id, nome, descricao, preco_base, codigo_produto, marca, material, genero, imagem_principal, ativo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
        
        $this->db->query($sql, [
            $data['categoria_id'],
            $data['nome'],
            $data['descricao'],
            $data['preco_base'],
            $data['codigo_produto'],
            $data['marca'],
            $data['material'],
            $data['genero'],
            $data['imagem_principal'] ?? null
        ]);
        
        return $this->db->getLastInsertId();
    }
    
    public function update($id, $data) {
        $sql = "UPDATE produtos SET categoria_id = ?, nome = ?, descricao = ?, preco_base = ?, marca = ?, material = ?, genero = ?, imagem_principal = ? WHERE id = ?";
        
        return $this->db->query($sql, [
            $data['categoria_id'],
            $data['nome'],
            $data['descricao'],
            $data['preco_base'],
            $data['marca'],
            $data['material'],
            $data['genero'],
            $data['imagem_principal'],
            $id
        ]);
    }
    
    public function delete($id) {
        $sql = "UPDATE produtos SET ativo = 0 WHERE id = ?";
        return $this->db->query($sql, [$id]);
    }
    
    public function getVariants($productId) {
        $sql = "SELECT pv.*, c.nome as cor_nome, c.codigo_hex, t.numero as tamanho_numero,
                       (p.preco_base + pv.preco_adicional) as preco_final,
                       e.quantidade as estoque_quantidade
                FROM produto_variantes pv
                LEFT JOIN cores c ON pv.cor_id = c.id
                LEFT JOIN tamanhos t ON pv.tamanho_id = t.id
                LEFT JOIN produtos p ON pv.produto_id = p.id
                LEFT JOIN estoque e ON pv.id = e.variante_id
                WHERE pv.produto_id = ? AND pv.ativo = 1
                ORDER BY t.ordem, c.nome";
        
        return $this->db->query($sql, [$productId])->fetchAll();
    }
    
    public function createVariant($data) {
        $sql = "INSERT INTO produto_variantes (produto_id, cor_id, tamanho_id, codigo_variante, preco_adicional, peso, ativo) 
                VALUES (?, ?, ?, ?, ?, ?, 1)";
        
        $this->db->query($sql, [
            $data['produto_id'],
            $data['cor_id'],
            $data['tamanho_id'],
            $data['codigo_variante'],
            $data['preco_adicional'],
            $data['peso']
        ]);
        
        $variantId = $this->db->getLastInsertId();
        
        // Criar registro de estoque inicial se quantidade fornecida
        if (isset($data['quantidade_inicial']) && $data['quantidade_inicial'] > 0) {
            $stockSql = "INSERT INTO estoque (variante_id, quantidade) VALUES (?, ?)";
            $this->db->query($stockSql, [$variantId, $data['quantidade_inicial']]);
        }
        
        return $variantId;
    }
    
    public function generateVariantCode($productId, $colorId, $sizeId) {
        $product = $this->getById($productId);
        $color = (new Color())->getById($colorId);
        $size = (new Size())->getById($sizeId);
        
        $colorCode = strtoupper(substr($color['nome'], 0, 2));
        $sizeCode = str_pad($size['numero'], 2, '0', STR_PAD_LEFT);
        
        return $product['codigo_produto'] . '-' . $colorCode . $sizeCode;
    }
}

/**
 * Classe Order para gerenciar pedidos
 */
class Order {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function getAll($filters = []) {
        $sql = "SELECT p.*, cb.razao_social 
                FROM pedidos p 
                LEFT JOIN clientes_b2b cb ON p.cliente_id = cb.id 
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND p.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['cliente_id'])) {
            $sql .= " AND p.cliente_id = ?";
            $params[] = $filters['cliente_id'];
        }
        
        if (!empty($filters['data_inicio'])) {
            $sql .= " AND DATE(p.data_pedido) >= ?";
            $params[] = $filters['data_inicio'];
        }
        
        if (!empty($filters['data_fim'])) {
            $sql .= " AND DATE(p.data_pedido) <= ?";
            $params[] = $filters['data_fim'];
        }
        
        $sql .= " ORDER BY p.data_pedido DESC";
        
        return $this->db->query($sql, $params)->fetchAll();
    }
    
    public function getById($id) {
        $sql = "SELECT p.*, cb.razao_social, cb.cnpj, cb.endereco, cb.cidade, cb.estado 
                FROM pedidos p 
                LEFT JOIN clientes_b2b cb ON p.cliente_id = cb.id 
                WHERE p.id = ?";
        return $this->db->query($sql, [$id])->fetch();
    }
    
    public function getByClient($clientId, $limit = 50) {
        $sql = "SELECT * FROM pedidos WHERE cliente_id = ? ORDER BY data_pedido DESC LIMIT ?";
        return $this->db->query($sql, [$clientId, $limit])->fetchAll();
    }
    
    public function getItems($orderId) {
        $sql = "SELECT pi.*, p.nome as produto_nome, c.nome as cor_nome, t.numero as tamanho_numero, pv.codigo_variante
                FROM pedido_itens pi
                LEFT JOIN produto_variantes pv ON pi.variante_id = pv.id
                LEFT JOIN produtos p ON pv.produto_id = p.id
                LEFT JOIN cores c ON pv.cor_id = c.id
                LEFT JOIN tamanhos t ON pv.tamanho_id = t.id
                WHERE pi.pedido_id = ?
                ORDER BY p.nome";
        
        return $this->db->query($sql, [$orderId])->fetchAll();
    }
    
    public function updateStatus($orderId, $status) {
        $sql = "UPDATE pedidos SET status = ? WHERE id = ?";
        if ($status === 'confirmado') {
            $sql = "UPDATE pedidos SET status = ?, data_confirmacao = NOW() WHERE id = ?";
        }
        return $this->db->query($sql, [$status, $orderId]);
    }
    
    public function updateDeliveryDate($orderId, $date) {
        $sql = "UPDATE pedidos SET data_entrega_prevista = ? WHERE id = ?";
        return $this->db->query($sql, [$date, $orderId]);
    }
    
    public function processOrder($orderId) {
        // Lógica para processar pedido (reduzir estoque, etc.)
        $items = $this->getItems($orderId);
        
        foreach ($items as $item) {
            $sql = "UPDATE estoque SET quantidade = quantidade - ? WHERE variante_id = ?";
            $this->db->query($sql, [$item['quantidade'], $item['variante_id']]);
        }
        
        return true;
    }
    
    public function getOrderStats($clientId) {
        $sql = "SELECT 
                    COUNT(*) as total_pedidos,
                    SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                    SUM(CASE WHEN status = 'confirmado' THEN 1 ELSE 0 END) as confirmados,
                    SUM(total) as valor_total
                FROM pedidos 
                WHERE cliente_id = ?";
        
        return $this->db->query($sql, [$clientId])->fetch();
    }
}

/**
 * Classe Client para gerenciar clientes B2B
 */
class Client {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function getAll($activeOnly = true) {
        $sql = "SELECT cb.*, u.nome, u.email, u.ativo 
                FROM clientes_b2b cb 
                LEFT JOIN usuarios u ON cb.usuario_id = u.id";
        
        if ($activeOnly) {
            $sql .= " WHERE u.ativo = 1";
        }
        
        $sql .= " ORDER BY cb.razao_social";
        
        return $this->db->query($sql)->fetchAll();
    }
    
    public function getById($id) {
        $sql = "SELECT cb.*, u.nome, u.email 
                FROM clientes_b2b cb 
                LEFT JOIN usuarios u ON cb.usuario_id = u.id 
                WHERE cb.id = ?";
        return $this->db->query($sql, [$id])->fetch();
    }
    
    public function create($userData, $clientData) {
        // Criar usuário primeiro
        $hashedPassword = password_hash($userData['senha'], PASSWORD_DEFAULT);
        $userSql = "INSERT INTO usuarios (nome, email, senha, tipo, ativo) VALUES (?, ?, ?, 'cliente_b2b', 1)";
        $this->db->query($userSql, [$userData['nome'], $userData['email'], $hashedPassword]);
        $userId = $this->db->getLastInsertId();
        
        // Criar cliente B2B
        $clientSql = "INSERT INTO clientes_b2b (usuario_id, razao_social, cnpj, inscricao_estadual, telefone, endereco, cidade, estado, cep, desconto_padrao, limite_credito) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $this->db->query($clientSql, [
            $userId,
            $clientData['razao_social'],
            $clientData['cnpj'],
            $clientData['inscricao_estadual'] ?? null,
            $clientData['telefone'] ?? null,
            $clientData['endereco'] ?? null,
            $clientData['cidade'] ?? null,
            $clientData['estado'] ?? null,
            $clientData['cep'] ?? null,
            $clientData['desconto_padrao'] ?? 0,
            $clientData['limite_credito'] ?? 0
        ]);
        
        return $this->db->getLastInsertId();
    }
}

/**
 * Funções auxiliares
 */
function sanitizeInput($input) {
    return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
}

function formatPrice($price) {
    return 'R$ ' . number_format(floatval($price), 2, ',', '.');
}

function formatCNPJ($cnpj) {
    $cnpj = preg_replace('/\D/', '', $cnpj);
    return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpj);
}

function uploadImage($file, $uploadDir) {
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Tipo de arquivo não permitido');
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $destination = $uploadDir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('Erro ao fazer upload do arquivo');
    }
    
    return $filename;
}

function generateProductCode($name) {
    $code = strtoupper(preg_replace('/[^A-Za-z]/', '', $name));
    $code = substr($code, 0, 3);
    $code .= '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    return $code;
}

function logActivity($userId, $action, $details = '') {
    try {
        $db = new Database();
        $sql = "INSERT INTO log_atividades (usuario_id, acao, detalhes, data_acao) VALUES (?, ?, ?, NOW())";
        $db->query($sql, [$userId, $action, $details]);
    } catch (Exception $e) {
        // Log silencioso se tabela não existir
        error_log("Log activity error: " . $e->getMessage());
    }
}

// Incluir as classes automaticamente nos arquivos que precisam
if (!class_exists('SessionManager')) {
    // Classes já definidas acima
}
?>