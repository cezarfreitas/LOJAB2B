<?php
// models/Cart.php
class Cart {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function addItem($clienteId, $variantId, $quantity) {
        // Verificar se o item já existe no carrinho
        $sql = "SELECT id, quantidade FROM carrinho WHERE cliente_id = ? AND variante_id = ?";
        $stmt = $this->db->query($sql, [$clienteId, $variantId]);
        $existingItem = $stmt->fetch();
        
        // Buscar preço atual da variante
        $sql = "SELECT (pv.preco_adicional + p.preco_base) as preco_final
                FROM produto_variantes pv
                JOIN produtos p ON pv.produto_id = p.id
                WHERE pv.id = ?";
        $stmt = $this->db->query($sql, [$variantId]);
        $variant = $stmt->fetch();
        
        if ($existingItem) {
            // Atualizar quantidade
            $newQuantity = $existingItem['quantidade'] + $quantity;
            $sql = "UPDATE carrinho SET quantidade = ?, preco_unitario = ? WHERE id = ?";
            $this->db->query($sql, [$newQuantity, $variant['preco_final'], $existingItem['id']]);
        } else {
            // Adicionar novo item
            $sql = "INSERT INTO carrinho (cliente_id, variante_id, quantidade, preco_unitario) 
                    VALUES (?, ?, ?, ?)";
            $this->db->query($sql, [$clienteId, $variantId, $quantity, $variant['preco_final']]);
        }
        
        return true;
    }
    
    public function updateQuantity($clienteId, $variantId, $quantity) {
        if ($quantity <= 0) {
            return $this->removeItem($clienteId, $variantId);
        }
        
        $sql = "UPDATE carrinho SET quantidade = ? WHERE cliente_id = ? AND variante_id = ?";
        return $this->db->query($sql, [$quantity, $clienteId, $variantId]);
    }
    
    public function removeItem($clienteId, $variantId) {
        $sql = "DELETE FROM carrinho WHERE cliente_id = ? AND variante_id = ?";
        return $this->db->query($sql, [$clienteId, $variantId]);
    }
    
    public function getItems($clienteId) {
        $sql = "SELECT c.*, pv.codigo_variante, p.nome as produto_nome, p.imagem_principal,
                       cor.nome as cor_nome, cor.codigo_hex, t.numero as tamanho_numero,
                       e.quantidade as estoque_disponivel,
                       (c.quantidade * c.preco_unitario) as subtotal
                FROM carrinho c
                JOIN produto_variantes pv ON c.variante_id = pv.id
                JOIN produtos p ON pv.produto_id = p.id
                JOIN cores cor ON pv.cor_id = cor.id
                JOIN tamanhos t ON pv.tamanho_id = t.id
                LEFT JOIN estoque e ON pv.id = e.variante_id
                WHERE c.cliente_id = ?
                ORDER BY c.data_adicao DESC";
        
        $stmt = $this->db->query($sql, [$clienteId]);
        return $stmt->fetchAll();
    }
    
    public function getTotal($clienteId) {
        $sql = "SELECT SUM(quantidade * preco_unitario) as total FROM carrinho WHERE cliente_id = ?";
        $stmt = $this->db->query($sql, [$clienteId]);
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    }
    
    public function getItemCount($clienteId) {
        $sql = "SELECT SUM(quantidade) as total_items FROM carrinho WHERE cliente_id = ?";
        $stmt = $this->db->query($sql, [$clienteId]);
        $result = $stmt->fetch();
        return $result['total_items'] ?? 0;
    }
    
    public function clearCart($clienteId) {
        $sql = "DELETE FROM carrinho WHERE cliente_id = ?";
        return $this->db->query($sql, [$clienteId]);
    }
    
    public function validateStock($clienteId) {
        $sql = "SELECT c.variante_id, c.quantidade, e.quantidade as estoque_disponivel,
                       p.nome as produto_nome, cor.nome as cor_nome, t.numero as tamanho_numero
                FROM carrinho c
                JOIN produto_variantes pv ON c.variante_id = pv.id
                JOIN produtos p ON pv.produto_id = p.id
                JOIN cores cor ON pv.cor_id = cor.id
                JOIN tamanhos t ON pv.tamanho_id = t.id
                JOIN estoque e ON pv.id = e.variante_id
                WHERE c.cliente_id = ? AND c.quantidade > e.quantidade";
        
        $stmt = $this->db->query($sql, [$clienteId]);
        return $stmt->fetchAll();
    }
}

// models/Order.php
class Order {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function create($clienteId, $observacoes = '') {
        $this->db->beginTransaction();
        
        try {
            // Buscar itens do carrinho
            $cart = new Cart();
            $items = $cart->getItems($clienteId);
            
            if (empty($items)) {
                throw new Exception('Carrinho vazio');
            }
            
            // Validar estoque
            $stockErrors = $cart->validateStock($clienteId);
            if (!empty($stockErrors)) {
                throw new Exception('Estoque insuficiente para alguns itens');
            }
            
            // Buscar dados do cliente
            $sql = "SELECT * FROM clientes_b2b WHERE id = ?";
            $stmt = $this->db->query($sql, [$clienteId]);
            $cliente = $stmt->fetch();
            
            // Calcular totais
            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += $item['subtotal'];
            }
            
            $desconto = calculateDiscount($subtotal, $cliente['desconto_padrao']);
            $total = $subtotal - $desconto;
            
            // Criar pedido
            $numeroPedido = generateOrderNumber();
            
            $sql = "INSERT INTO pedidos (cliente_id, numero_pedido, subtotal, desconto, total, observacoes) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            $this->db->query($sql, [$clienteId, $numeroPedido, $subtotal, $desconto, $total, $observacoes]);
            $pedidoId = $this->db->lastInsertId();
            
            // Criar itens do pedido
            foreach ($items as $item) {
                $sql = "INSERT INTO pedido_itens (pedido_id, variante_id, quantidade, preco_unitario, subtotal) 
                        VALUES (?, ?, ?, ?, ?)";
                
                $this->db->query($sql, [
                    $pedidoId,
                    $item['variante_id'],
                    $item['quantidade'],
                    $item['preco_unitario'],
                    $item['subtotal']
                ]);
            }
            
            // Limpar carrinho
            $cart->clearCart($clienteId);
            
            $this->db->commit();
            return $pedidoId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function getById($id) {
        $sql = "SELECT p.*, cb.razao_social, cb.cnpj, cb.endereco, cb.cidade, cb.estado
                FROM pedidos p
                JOIN clientes_b2b cb ON p.cliente_id = cb.id
                WHERE p.id = ?";
        
        $stmt = $this->db->query($sql, [$id]);
        return $stmt->fetch();
    }
    
    public function getItems($pedidoId) {
        $sql = "SELECT pi.*, pv.codigo_variante, pr.nome as produto_nome, 
                       cor.nome as cor_nome, t.numero as tamanho_numero
                FROM pedido_itens pi
                JOIN produto_variantes pv ON pi.variante_id = pv.id
                JOIN produtos pr ON pv.produto_id = pr.id
                JOIN cores cor ON pv.cor_id = cor.id
                JOIN tamanhos t ON pv.tamanho_id = t.id
                WHERE pi.pedido_id = ?";
        
        $stmt = $this->db->query($sql, [$pedidoId]);
        return $stmt->fetchAll();
    }
    
    public function getByClient($clienteId, $limit = 10) {
        $sql = "SELECT * FROM pedidos WHERE cliente_id = ? ORDER BY data_pedido DESC LIMIT ?";
        $stmt = $this->db->query($sql, [$clienteId, $limit]);
        return $stmt->fetchAll();
    }
    
    public function getAll($filters = []) {
        $sql = "SELECT p.*, cb.razao_social 
                FROM pedidos p
                JOIN clientes_b2b cb ON p.cliente_id = cb.id
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
            $sql .= " AND p.data_pedido >= ?";
            $params[] = $filters['data_inicio'];
        }
        
        if (!empty($filters['data_fim'])) {
            $sql .= " AND p.data_pedido <= ?";
            $params[] = $filters['data_fim'];
        }
        
        $sql .= " ORDER BY p.data_pedido DESC";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function updateStatus($id, $status) {
        $sql = "UPDATE pedidos SET status = ?";
        $params = [$status];
        
        if ($status === 'confirmado') {
            $sql .= ", data_confirmacao = NOW()";
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $id;
        
        return $this->db->query($sql, $params);
    }
    
    public function updateDeliveryDate($id, $deliveryDate) {
        $sql = "UPDATE pedidos SET data_entrega_prevista = ? WHERE id = ?";
        return $this->db->query($sql, [$deliveryDate, $id]);
    }
    
    public function processOrder($id) {
        $this->db->beginTransaction();
        
        try {
            // Buscar itens do pedido
            $items = $this->getItems($id);
            
            // Reduzir estoque
            foreach ($items as $item) {
                $sql = "UPDATE estoque SET quantidade = quantidade - ? WHERE variante_id = ?";
                $this->db->query($sql, [$item['quantidade'], $item['variante_id']]);
                
                // Registrar movimentação
                $sql = "INSERT INTO movimentacoes_estoque (variante_id, tipo, quantidade, quantidade_anterior, motivo, usuario_id) 
                        VALUES (?, 'saida', ?, (SELECT quantidade + ? FROM estoque WHERE variante_id = ?), 'Venda - Pedido #' || ?, ?)";
                
                $this->db->query($sql, [
                    $item['variante_id'],
                    -$item['quantidade'],
                    $item['quantidade'],
                    $item['variante_id'],
                    $id,
                    SessionManager::get('user_id')
                ]);
            }
            
            // Atualizar status
            $this->updateStatus($id, 'processando');
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function getOrderStats($clienteId = null) {
        $sql = "SELECT 
                    COUNT(*) as total_pedidos,
                    SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                    SUM(CASE WHEN status = 'confirmado' THEN 1 ELSE 0 END) as confirmados,
                    SUM(CASE WHEN status = 'processando' THEN 1 ELSE 0 END) as processando,
                    SUM(CASE WHEN status = 'enviado' THEN 1 ELSE 0 END) as enviados,
                    SUM(CASE WHEN status = 'entregue' THEN 1 ELSE 0 END) as entregues,
                    SUM(total) as valor_total
                FROM pedidos";
        
        $params = [];
        
        if ($clienteId) {
            $sql .= " WHERE cliente_id = ?";
            $params[] = $clienteId;
        }
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetch();
    }
}
?>