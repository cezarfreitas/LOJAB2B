<?php
// models/Product.php
class Product {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function create($data) {
        $sql = "INSERT INTO produtos (categoria_id, nome, descricao, preco_base, codigo_produto, marca, material, genero, imagem_principal) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->query($sql, [
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
        
        return $this->db->lastInsertId();
    }
    
    public function update($id, $data) {
        $sql = "UPDATE produtos SET categoria_id = ?, nome = ?, descricao = ?, preco_base = ?, 
                marca = ?, material = ?, genero = ?, imagem_principal = ? WHERE id = ?";
        
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
    
    public function getById($id) {
        $sql = "SELECT p.*, c.nome as categoria_nome 
                FROM produtos p 
                JOIN categorias c ON p.categoria_id = c.id 
                WHERE p.id = ? AND p.ativo = 1";
        
        $stmt = $this->db->query($sql, [$id]);
        return $stmt->fetch();
    }
    
    public function getAll($filters = []) {
        $sql = "SELECT p.*, c.nome as categoria_nome 
                FROM produtos p 
                JOIN categorias c ON p.categoria_id = c.id 
                WHERE p.ativo = 1";
        
        $params = [];
        
        if (!empty($filters['categoria_id'])) {
            $sql .= " AND p.categoria_id = ?";
            $params[] = $filters['categoria_id'];
        }
        
        if (!empty($filters['genero'])) {
            $sql .= " AND p.genero = ?";
            $params[] = $filters['genero'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (p.nome LIKE ? OR p.descricao LIKE ? OR p.codigo_produto LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " ORDER BY p.nome";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function createVariant($data) {
        $sql = "INSERT INTO produto_variantes (produto_id, cor_id, tamanho_id, codigo_variante, preco_adicional, peso) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->query($sql, [
            $data['produto_id'],
            $data['cor_id'],
            $data['tamanho_id'],
            $data['codigo_variante'],
            $data['preco_adicional'] ?? 0,
            $data['peso'] ?? 0
        ]);
        
        $variantId = $this->db->lastInsertId();
        
        // Criar registro de estoque inicial
        $this->createStock($variantId, $data['quantidade_inicial'] ?? 0);
        
        return $variantId;
    }
    
    public function getVariants($productId) {
        $sql = "SELECT pv.*, c.nome as cor_nome, c.codigo_hex, t.numero as tamanho_numero,
                       e.quantidade as estoque_quantidade, e.estoque_minimo,
                       (pv.preco_adicional + p.preco_base) as preco_final
                FROM produto_variantes pv
                JOIN cores c ON pv.cor_id = c.id
                JOIN tamanhos t ON pv.tamanho_id = t.id
                JOIN produtos p ON pv.produto_id = p.id
                LEFT JOIN estoque e ON pv.id = e.variante_id
                WHERE pv.produto_id = ? AND pv.ativo = 1
                ORDER BY t.ordem, c.nome";
        
        $stmt = $this->db->query($sql, [$productId]);
        return $stmt->fetchAll();
    }
    
    public function getVariantById($variantId) {
        $sql = "SELECT pv.*, c.nome as cor_nome, c.codigo_hex, t.numero as tamanho_numero,
                       e.quantidade as estoque_quantidade, e.estoque_minimo,
                       p.nome as produto_nome, p.preco_base,
                       (pv.preco_adicional + p.preco_base) as preco_final
                FROM produto_variantes pv
                JOIN cores c ON pv.cor_id = c.id
                JOIN tamanhos t ON pv.tamanho_id = t.id
                JOIN produtos p ON pv.produto_id = p.id
                LEFT JOIN estoque e ON pv.id = e.variante_id
                WHERE pv.id = ? AND pv.ativo = 1";
        
        $stmt = $this->db->query($sql, [$variantId]);
        return $stmt->fetch();
    }
    
    public function updateVariant($variantId, $data) {
        $sql = "UPDATE produto_variantes SET cor_id = ?, tamanho_id = ?, preco_adicional = ?, peso = ? WHERE id = ?";
        
        return $this->db->query($sql, [
            $data['cor_id'],
            $data['tamanho_id'],
            $data['preco_adicional'],
            $data['peso'],
            $variantId
        ]);
    }
    
    public function deleteVariant($variantId) {
        $sql = "UPDATE produto_variantes SET ativo = 0 WHERE id = ?";
        return $this->db->query($sql, [$variantId]);
    }
    
    private function createStock($variantId, $quantity) {
        $sql = "INSERT INTO estoque (variante_id, quantidade) VALUES (?, ?)";
        return $this->db->query($sql, [$variantId, $quantity]);
    }
    
    public function updateStock($variantId, $quantity, $type = 'ajuste', $reason = '', $userId = null) {
        // Buscar quantidade atual
        $sql = "SELECT quantidade FROM estoque WHERE variante_id = ?";
        $stmt = $this->db->query($sql, [$variantId]);
        $currentStock = $stmt->fetchColumn() ?: 0;
        
        // Atualizar estoque
        $sql = "UPDATE estoque SET quantidade = ?, data_ultima_atualizacao = NOW() WHERE variante_id = ?";
        $this->db->query($sql, [$quantity, $variantId]);
        
        // Registrar movimentação
        if ($userId) {
            $sql = "INSERT INTO movimentacoes_estoque (variante_id, tipo, quantidade, quantidade_anterior, motivo, usuario_id) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            $this->db->query($sql, [
                $variantId,
                $type,
                $quantity - $currentStock,
                $currentStock,
                $reason,
                $userId
            ]);
        }
        
        return true;
    }
    
    public function getStockMovements($variantId) {
        $sql = "SELECT me.*, u.nome as usuario_nome 
                FROM movimentacoes_estoque me
                JOIN usuarios u ON me.usuario_id = u.id
                WHERE me.variante_id = ?
                ORDER BY me.data_movimentacao DESC";
        
        $stmt = $this->db->query($sql, [$variantId]);
        return $stmt->fetchAll();
    }
    
    public function getLowStockItems() {
        $sql = "SELECT pv.*, p.nome as produto_nome, c.nome as cor_nome, t.numero as tamanho_numero,
                       e.quantidade, e.estoque_minimo
                FROM produto_variantes pv
                JOIN produtos p ON pv.produto_id = p.id
                JOIN cores c ON pv.cor_id = c.id
                JOIN tamanhos t ON pv.tamanho_id = t.id
                JOIN estoque e ON pv.id = e.variante_id
                WHERE pv.ativo = 1 AND p.ativo = 1 AND e.quantidade <= e.estoque_minimo
                ORDER BY e.quantidade ASC";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    public function addProductImage($productId, $imagePath, $colorId = null, $order = 0, $isPrimary = false) {
        $sql = "INSERT INTO produto_imagens (produto_id, cor_id, caminho_imagem, ordem, principal) 
                VALUES (?, ?, ?, ?, ?)";
        
        return $this->db->query($sql, [$productId, $colorId, $imagePath, $order, $isPrimary]);
    }
    
    public function getProductImages($productId, $colorId = null) {
        $sql = "SELECT * FROM produto_imagens WHERE produto_id = ?";
        $params = [$productId];
        
        if ($colorId) {
            $sql .= " AND (cor_id = ? OR cor_id IS NULL)";
            $params[] = $colorId;
        }
        
        $sql .= " ORDER BY ordem, id";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function generateVariantCode($productId, $colorId, $sizeId) {
        // Buscar dados do produto
        $product = $this->getById($productId);
        
        // Buscar cor
        $sql = "SELECT nome FROM cores WHERE id = ?";
        $stmt = $this->db->query($sql, [$colorId]);
        $color = $stmt->fetch();
        
        // Buscar tamanho
        $sql = "SELECT numero FROM tamanhos WHERE id = ?";
        $stmt = $this->db->query($sql, [$sizeId]);
        $size = $stmt->fetch();
        
        // Gerar código: PRODUTO-COR-TAMANHO
        $productCode = substr(strtoupper(preg_replace('/[^A-Za-z]/', '', $product['nome'])), 0, 3);
        $colorCode = substr(strtoupper(preg_replace('/[^A-Za-z]/', '', $color['nome'])), 0, 2);
        $sizeCode = $size['numero'];
        
        return $productCode . '-' . $colorCode . '-' . $sizeCode;
    }
}
?>