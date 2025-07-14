<?php
// models/Client.php
class Client {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function create($userData, $clientData) {
        $this->db->beginTransaction();
        
        try {
            // Criar usuário
            $sql = "INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, 'cliente_b2b')";
            $hashedPassword = password_hash($userData['senha'], PASSWORD_DEFAULT);
            
            $this->db->query($sql, [$userData['nome'], $userData['email'], $hashedPassword]);
            $userId = $this->db->lastInsertId();
            
            // Criar cliente B2B
            $sql = "INSERT INTO clientes_b2b (usuario_id, razao_social, cnpj, inscricao_estadual, 
                                               telefone, endereco, cidade, estado, cep, desconto_padrao, limite_credito) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $this->db->query($sql, [
                $userId,
                $clientData['razao_social'],
                $clientData['cnpj'],
                $clientData['inscricao_estadual'],
                $clientData['telefone'],
                $clientData['endereco'],
                $clientData['cidade'],
                $clientData['estado'],
                $clientData['cep'],
                $clientData['desconto_padrao'] ?? 0,
                $clientData['limite_credito'] ?? 0
            ]);
            
            $clientId = $this->db->lastInsertId();
            
            $this->db->commit();
            return $clientId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function update($id, $userData, $clientData) {
        $this->db->beginTransaction();
        
        try {
            // Buscar usuario_id
            $sql = "SELECT usuario_id FROM clientes_b2b WHERE id = ?";
            $stmt = $this->db->query($sql, [$id]);
            $client = $stmt->fetch();
            
            // Atualizar usuário
            $sql = "UPDATE usuarios SET nome = ?, email = ? WHERE id = ?";
            $this->db->query($sql, [$userData['nome'], $userData['email'], $client['usuario_id']]);
            
            // Atualizar senha se fornecida
            if (!empty($userData['senha'])) {
                $hashedPassword = password_hash($userData['senha'], PASSWORD_DEFAULT);
                $sql = "UPDATE usuarios SET senha = ? WHERE id = ?";
                $this->db->query($sql, [$hashedPassword, $client['usuario_id']]);
            }
            
            // Atualizar cliente B2B
            $sql = "UPDATE clientes_b2b SET razao_social = ?, cnpj = ?, inscricao_estadual = ?, 
                                            telefone = ?, endereco = ?, cidade = ?, estado = ?, cep = ?, 
                                            desconto_padrao = ?, limite_credito = ? 
                    WHERE id = ?";
            
            $this->db->query($sql, [
                $clientData['razao_social'],
                $clientData['cnpj'],
                $clientData['inscricao_estadual'],
                $clientData['telefone'],
                $clientData['endereco'],
                $clientData['cidade'],
                $clientData['estado'],
                $clientData['cep'],
                $clientData['desconto_padrao'],
                $clientData['limite_credito'],
                $id
            ]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function getById($id) {
        $sql = "SELECT cb.*, u.nome, u.email, u.ativo 
                FROM clientes_b2b cb
                JOIN usuarios u ON cb.usuario_id = u.id
                WHERE cb.id = ?";
        
        $stmt = $this->db->query($sql, [$id]);
        return $stmt->fetch();
    }
    
    public function getByUserId($userId) {
        $sql = "SELECT cb.*, u.nome, u.email, u.ativo 
                FROM clientes_b2b cb
                JOIN usuarios u ON cb.usuario_id = u.id
                WHERE cb.usuario_id = ?";
        
        $stmt = $this->db->query($sql, [$userId]);
        return $stmt->fetch();
    }
    
    public function getAll($filters = []) {
        $sql = "SELECT cb.*, u.nome, u.email, u.ativo 
                FROM clientes_b2b cb
                JOIN usuarios u ON cb.usuario_id = u.id
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['ativo'])) {
            $sql .= " AND u.ativo = ?";
            $params[] = $filters['ativo'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (cb.razao_social LIKE ? OR cb.cnpj LIKE ? OR u.nome LIKE ? OR u.email LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " ORDER BY cb.razao_social";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function activate($id) {
        $sql = "UPDATE usuarios u 
                JOIN clientes_b2b cb ON u.id = cb.usuario_id 
                SET u.ativo = 1 
                WHERE cb.id = ?";
        
        return $this->db->query($sql, [$id]);
    }
    
    public function deactivate($id) {
        $sql = "UPDATE usuarios u 
                JOIN clientes_b2b cb ON u.id = cb.usuario_id 
                SET u.ativo = 0 
                WHERE cb.id = ?";
        
        return $this->db->query($sql, [$id]);
    }
    
    public function updateDiscount($id, $discount) {
        $sql = "UPDATE clientes_b2b SET desconto_padrao = ? WHERE id = ?";
        return $this->db->query($sql, [$discount, $id]);
    }
    
    public function updateCreditLimit($id, $limit) {
        $sql = "UPDATE clientes_b2b SET limite_credito = ? WHERE id = ?";
        return $this->db->query($sql, [$limit, $id]);
    }
    
    public function getClientStats($id) {
        $sql = "SELECT 
                    COUNT(p.id) as total_pedidos,
                    SUM(p.total) as valor_total_pedidos,
                    AVG(p.total) as ticket_medio,
                    SUM(CASE WHEN p.status = 'pendente' THEN 1 ELSE 0 END) as pedidos_pendentes,
                    MAX(p.data_pedido) as ultimo_pedido
                FROM pedidos p
                WHERE p.cliente_id = ?";
        
        $stmt = $this->db->query($sql, [$id]);
        return $stmt->fetch();
    }
    
    public function getTopClients($limit = 10) {
        $sql = "SELECT cb.*, u.nome, u.email,
                       COUNT(p.id) as total_pedidos,
                       SUM(p.total) as valor_total
                FROM clientes_b2b cb
                JOIN usuarios u ON cb.usuario_id = u.id
                LEFT JOIN pedidos p ON cb.id = p.cliente_id
                WHERE u.ativo = 1
                GROUP BY cb.id
                ORDER BY valor_total DESC
                LIMIT ?";
        
        $stmt = $this->db->query($sql, [$limit]);
        return $stmt->fetchAll();
    }
    
    public function authenticate($email, $password) {
        $sql = "SELECT u.*, cb.id as cliente_id 
                FROM usuarios u
                LEFT JOIN clientes_b2b cb ON u.id = cb.usuario_id
                WHERE u.email = ? AND u.ativo = 1";
        
        $stmt = $this->db->query($sql, [$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['senha'])) {
            return $user;
        }
        
        return false;
    }
    
    public function existsEmail($email, $excludeId = null) {
        $sql = "SELECT id FROM usuarios WHERE email = ?";
        $params = [$email];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetch() !== false;
    }
    
    public function existsCNPJ($cnpj, $excludeId = null) {
        $sql = "SELECT id FROM clientes_b2b WHERE cnpj = ?";
        $params = [$cnpj];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetch() !== false;
    }
    
    public function resetPassword($email) {
        $newPassword = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 8);
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $sql = "UPDATE usuarios SET senha = ? WHERE email = ?";
        $this->db->query($sql, [$hashedPassword, $email]);
        
        // Enviar e-mail com nova senha
        $subject = "Nova senha de acesso";
        $body = "Sua nova senha de acesso é: " . $newPassword;
        sendEmail($email, $subject, $body);
        
        return $newPassword;
    }
}

// models/Category.php
class Category {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function create($data) {
        $sql = "INSERT INTO categorias (nome, descricao) VALUES (?, ?)";
        $stmt = $this->db->query($sql, [$data['nome'], $data['descricao']]);
        return $this->db->lastInsertId();
    }
    
    public function update($id, $data) {
        $sql = "UPDATE categorias SET nome = ?, descricao = ? WHERE id = ?";
        return $this->db->query($sql, [$data['nome'], $data['descricao'], $id]);
    }
    
    public function delete($id) {
        $sql = "UPDATE categorias SET ativo = 0 WHERE id = ?";
        return $this->db->query($sql, [$id]);
    }
    
    public function getById($id) {
        $sql = "SELECT * FROM categorias WHERE id = ? AND ativo = 1";
        $stmt = $this->db->query($sql, [$id]);
        return $stmt->fetch();
    }
    
    public function getAll($activeOnly = true) {
        $sql = "SELECT * FROM categorias";
        
        if ($activeOnly) {
            $sql .= " WHERE ativo = 1";
        }
        
        $sql .= " ORDER BY nome";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    public function getWithProductCount() {
        $sql = "SELECT c.*, COUNT(p.id) as total_produtos
                FROM categorias c
                LEFT JOIN produtos p ON c.id = p.categoria_id AND p.ativo = 1
                WHERE c.ativo = 1
                GROUP BY c.id
                ORDER BY c.nome";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
}

// models/Color.php
class Color {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function create($data) {
        $sql = "INSERT INTO cores (nome, codigo_hex) VALUES (?, ?)";
        $stmt = $this->db->query($sql, [$data['nome'], $data['codigo_hex']]);
        return $this->db->lastInsertId();
    }
    
    public function update($id, $data) {
        $sql = "UPDATE cores SET nome = ?, codigo_hex = ? WHERE id = ?";
        return $this->db->query($sql, [$data['nome'], $data['codigo_hex'], $id]);
    }
    
    public function delete($id) {
        $sql = "UPDATE cores SET ativo = 0 WHERE id = ?";
        return $this->db->query($sql, [$id]);
    }
    
    public function getById($id) {
        $sql = "SELECT * FROM cores WHERE id = ? AND ativo = 1";
        $stmt = $this->db->query($sql, [$id]);
        return $stmt->fetch();
    }
    
    public function getAll($activeOnly = true) {
        $sql = "SELECT * FROM cores";
        
        if ($activeOnly) {
            $sql .= " WHERE ativo = 1";
        }
        
        $sql .= " ORDER BY nome";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
}

// models/Size.php
class Size {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function create($data) {
        $sql = "INSERT INTO tamanhos (numero, ordem) VALUES (?, ?)";
        $stmt = $this->db->query($sql, [$data['numero'], $data['ordem']]);
        return $this->db->lastInsertId();
    }
    
    public function update($id, $data) {
        $sql = "UPDATE tamanhos SET numero = ?, ordem = ? WHERE id = ?";
        return $this->db->query($sql, [$data['numero'], $data['ordem'], $id]);
    }
    
    public function delete($id) {
        $sql = "UPDATE tamanhos SET ativo = 0 WHERE id = ?";
        return $this->db->query($sql, [$id]);
    }
    
    public function getById($id) {
        $sql = "SELECT * FROM tamanhos WHERE id = ? AND ativo = 1";
        $stmt = $this->db->query($sql, [$id]);
        return $stmt->fetch();
    }
    
    public function getAll($activeOnly = true) {
        $sql = "SELECT * FROM tamanhos";
        
        if ($activeOnly) {
            $sql .= " WHERE ativo = 1";
        }
        
        $sql .= " ORDER BY ordem";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
}
?>