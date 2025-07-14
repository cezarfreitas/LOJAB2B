<?php
// admin/config.php - Configuração e inicialização para área admin
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar se está na pasta admin
if (!file_exists('../config/database.php')) {
    die('Erro: Execute este arquivo da pasta admin/');
}

// Incluir arquivos necessários
try {
    require_once '../config/database.php';
    
    // Verificar se functions.php existe
    if (file_exists('../config/functions.php')) {
        require_once '../config/functions.php';
    }
    
    // Incluir classes do sistema
    if (file_exists('../config/classes.php')) {
        require_once '../config/classes.php';
    } else {
        // Se não existir, definir classes básicas aqui
        include_once 'classes_inline.php';
    }
    
} catch (Exception $e) {
    die('Erro ao carregar configurações: ' . $e->getMessage());
}

// Iniciar sessão se não estiver ativa
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Função para verificar autenticação admin
function requireAdminAuth() {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
        header('Location: ../login.php');
        exit;
    }
}

// Função para obter estatísticas básicas (fallback se database falhar)
function getBasicStats() {
    try {
        $db = new Database();
        
        $stats = [
            'produtos' => 0,
            'pedidos_pendentes' => 0,
            'clientes' => 0,
            'vendas_mes' => 0
        ];
        
        // Total de produtos
        try {
            $stmt = $db->query("SELECT COUNT(*) as total FROM produtos WHERE ativo = 1");
            $result = $stmt->fetch();
            $stats['produtos'] = $result['total'] ?? 0;
        } catch (Exception $e) {
            // Tabela pode não existir ainda
        }
        
        // Pedidos pendentes
        try {
            $stmt = $db->query("SELECT COUNT(*) as total FROM pedidos WHERE status = 'pendente'");
            $result = $stmt->fetch();
            $stats['pedidos_pendentes'] = $result['total'] ?? 0;
        } catch (Exception $e) {
            // Tabela pode não existir ainda
        }
        
        // Clientes ativos
        try {
            $stmt = $db->query("SELECT COUNT(*) as total FROM clientes_b2b cb JOIN usuarios u ON cb.usuario_id = u.id WHERE u.ativo = 1");
            $result = $stmt->fetch();
            $stats['clientes'] = $result['total'] ?? 0;
        } catch (Exception $e) {
            // Tabela pode não existir ainda
        }
        
        // Vendas do mês
        try {
            $stmt = $db->query("SELECT COALESCE(SUM(total), 0) as total FROM pedidos WHERE MONTH(data_pedido) = MONTH(CURRENT_DATE) AND YEAR(data_pedido) = YEAR(CURRENT_DATE) AND status != 'cancelado'");
            $result = $stmt->fetch();
            $stats['vendas_mes'] = $result['total'] ?? 0;
        } catch (Exception $e) {
            // Tabela pode não existir ainda
        }
        
        return $stats;
        
    } catch (Exception $e) {
        // Retornar valores zero se banco não conectar
        return [
            'produtos' => 0,
            'pedidos_pendentes' => 0,
            'clientes' => 0,
            'vendas_mes' => 0
        ];
    }
}

// Função para verificar se todas as tabelas existem
function checkDatabaseTables() {
    try {
        $db = new Database();
        
        $requiredTables = [
            'usuarios',
            'clientes_b2b', 
            'produtos',
            'categorias',
            'cores',
            'tamanhos',
            'produto_variantes',
            'estoque',
            'pedidos',
            'pedido_itens',
            'carrinho'
        ];
        
        $existingTables = [];
        $missingTables = [];
        
        foreach ($requiredTables as $table) {
            try {
                $db->query("SELECT 1 FROM {$table} LIMIT 1");
                $existingTables[] = $table;
            } catch (Exception $e) {
                $missingTables[] = $table;
            }
        }
        
        return [
            'existing' => $existingTables,
            'missing' => $missingTables,
            'all_exist' => empty($missingTables)
        ];
        
    } catch (Exception $e) {
        return [
            'existing' => [],
            'missing' => $requiredTables,
            'all_exist' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Função para criar tabelas que faltam
function createMissingTables() {
    try {
        $db = new Database();
        
        $tableCreationSQL = [
            'usuarios' => "CREATE TABLE IF NOT EXISTS usuarios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(100) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                senha VARCHAR(255) NOT NULL,
                tipo ENUM('admin', 'cliente_b2b') NOT NULL,
                ativo BOOLEAN DEFAULT TRUE,
                data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            
            'clientes_b2b' => "CREATE TABLE IF NOT EXISTS clientes_b2b (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT NOT NULL,
                razao_social VARCHAR(200) NOT NULL,
                cnpj VARCHAR(18) UNIQUE NOT NULL,
                inscricao_estadual VARCHAR(20),
                telefone VARCHAR(20),
                endereco TEXT,
                cidade VARCHAR(100),
                estado VARCHAR(2),
                cep VARCHAR(9),
                desconto_padrao DECIMAL(5,2) DEFAULT 0.00,
                limite_credito DECIMAL(10,2) DEFAULT 0.00,
                data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
            )",
            
            'categorias' => "CREATE TABLE IF NOT EXISTS categorias (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(100) NOT NULL,
                descricao TEXT,
                ativo BOOLEAN DEFAULT TRUE,
                data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            
            'cores' => "CREATE TABLE IF NOT EXISTS cores (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(50) NOT NULL,
                codigo_hex VARCHAR(7),
                ativo BOOLEAN DEFAULT TRUE
            )",
            
            'tamanhos' => "CREATE TABLE IF NOT EXISTS tamanhos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                numero VARCHAR(10) NOT NULL,
                ordem INT NOT NULL,
                ativo BOOLEAN DEFAULT TRUE
            )",
            
            'produtos' => "CREATE TABLE IF NOT EXISTS produtos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                categoria_id INT NOT NULL,
                nome VARCHAR(200) NOT NULL,
                descricao TEXT,
                preco_base DECIMAL(10,2) NOT NULL,
                codigo_produto VARCHAR(50) UNIQUE NOT NULL,
                marca VARCHAR(100),
                material VARCHAR(100),
                genero ENUM('masculino', 'feminino', 'unissex') NOT NULL,
                imagem_principal VARCHAR(255),
                ativo BOOLEAN DEFAULT TRUE,
                data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (categoria_id) REFERENCES categorias(id)
            )",
            
            'produto_variantes' => "CREATE TABLE IF NOT EXISTS produto_variantes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                produto_id INT NOT NULL,
                cor_id INT NOT NULL,
                tamanho_id INT NOT NULL,
                codigo_variante VARCHAR(50) UNIQUE NOT NULL,
                preco_adicional DECIMAL(10,2) DEFAULT 0.00,
                peso DECIMAL(8,3),
                ativo BOOLEAN DEFAULT TRUE,
                data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (produto_id) REFERENCES produtos(id),
                FOREIGN KEY (cor_id) REFERENCES cores(id),
                FOREIGN KEY (tamanho_id) REFERENCES tamanhos(id),
                UNIQUE KEY unique_variante (produto_id, cor_id, tamanho_id)
            )",
            
            'estoque' => "CREATE TABLE IF NOT EXISTS estoque (
                id INT AUTO_INCREMENT PRIMARY KEY,
                variante_id INT NOT NULL,
                quantidade INT NOT NULL DEFAULT 0,
                estoque_minimo INT DEFAULT 5,
                estoque_maximo INT DEFAULT 100,
                localizacao VARCHAR(100),
                data_ultima_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (variante_id) REFERENCES produto_variantes(id)
            )",
            
            'pedidos' => "CREATE TABLE IF NOT EXISTS pedidos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cliente_id INT NOT NULL,
                numero_pedido VARCHAR(50) UNIQUE NOT NULL,
                status ENUM('pendente', 'confirmado', 'processando', 'enviado', 'entregue', 'cancelado') DEFAULT 'pendente',
                subtotal DECIMAL(10,2) NOT NULL,
                desconto DECIMAL(10,2) DEFAULT 0.00,
                total DECIMAL(10,2) NOT NULL,
                observacoes TEXT,
                data_pedido TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                data_confirmacao TIMESTAMP NULL,
                data_entrega_prevista DATE NULL,
                FOREIGN KEY (cliente_id) REFERENCES clientes_b2b(id)
            )",
            
            'pedido_itens' => "CREATE TABLE IF NOT EXISTS pedido_itens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pedido_id INT NOT NULL,
                variante_id INT NOT NULL,
                quantidade INT NOT NULL,
                preco_unitario DECIMAL(10,2) NOT NULL,
                subtotal DECIMAL(10,2) NOT NULL,
                FOREIGN KEY (pedido_id) REFERENCES pedidos(id),
                FOREIGN KEY (variante_id) REFERENCES produto_variantes(id)
            )",
            
            'carrinho' => "CREATE TABLE IF NOT EXISTS carrinho (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cliente_id INT NOT NULL,
                variante_id INT NOT NULL,
                quantidade INT NOT NULL,
                preco_unitario DECIMAL(10,2) NOT NULL,
                data_adicao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (cliente_id) REFERENCES clientes_b2b(id),
                FOREIGN KEY (variante_id) REFERENCES produto_variantes(id),
                UNIQUE KEY unique_carrinho_item (cliente_id, variante_id)
            )",
            
            'log_atividades' => "CREATE TABLE IF NOT EXISTS log_atividades (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT,
                acao VARCHAR(100) NOT NULL,
                detalhes TEXT,
                data_acao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
            )"
        ];
        
        $created = [];
        $errors = [];
        
        foreach ($tableCreationSQL as $tableName => $sql) {
            try {
                $db->query($sql);
                $created[] = $tableName;
            } catch (Exception $e) {
                $errors[$tableName] = $e->getMessage();
            }
        }
        
        return [
            'created' => $created,
            'errors' => $errors,
            'success' => empty($errors)
        ];
        
    } catch (Exception $e) {
        return [
            'created' => [],
            'errors' => ['general' => $e->getMessage()],
            'success' => false
        ];
    }
}

// Função para inserir dados iniciais
function insertInitialData() {
    try {
        $db = new Database();
        
        // Inserir categorias iniciais
        $categorias = [
            'Chinelos Básicos' => 'Chinelos simples para uso diário',
            'Chinelos Conforto' => 'Chinelos com palmilha anatômica',
            'Chinelos Esportivos' => 'Chinelos para atividades esportivas',
            'Chinelos Luxo' => 'Chinelos premium com acabamento especial'
        ];
        
        foreach ($categorias as $nome => $desc) {
            try {
                $db->query("INSERT IGNORE INTO categorias (nome, descricao) VALUES (?, ?)", [$nome, $desc]);
            } catch (Exception $e) {
                // Ignorar se já existir
            }
        }
        
        // Inserir cores iniciais
        $cores = [
            'Preto' => '#000000',
            'Branco' => '#FFFFFF',
            'Azul Marinho' => '#1E3A8A',
            'Vermelho' => '#DC2626',
            'Verde' => '#16A34A',
            'Amarelo' => '#EAB308',
            'Rosa' => '#EC4899',
            'Marrom' => '#A3A3A3',
            'Cinza' => '#6B7280',
            'Azul Claro' => '#3B82F6'
        ];
        
        foreach ($cores as $nome => $hex) {
            try {
                $db->query("INSERT IGNORE INTO cores (nome, codigo_hex) VALUES (?, ?)", [$nome, $hex]);
            } catch (Exception $e) {
                // Ignorar se já existir
            }
        }
        
        // Inserir tamanhos iniciais
        $tamanhos = ['33', '34', '35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45', '46'];
        
        foreach ($tamanhos as $index => $numero) {
            try {
                $db->query("INSERT IGNORE INTO tamanhos (numero, ordem) VALUES (?, ?)", [$numero, $index + 1]);
            } catch (Exception $e) {
                // Ignorar se já existir
            }
        }
        
        // Criar usuário admin se não existir
        try {
            $stmt = $db->query("SELECT id FROM usuarios WHERE email = 'admin@empresa.com'");
            if (!$stmt->fetch()) {
                $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
                $db->query("INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, ?)", 
                          ['Administrador', 'admin@empresa.com', $hashedPassword, 'admin']);
            }
        } catch (Exception $e) {
            // Ignorar se já existir
        }
        
        return true;
        
    } catch (Exception $e) {
        return false;
    }
}

// Função para formatar preço (fallback se functions.php não existir)
if (!function_exists('formatPrice')) {
    function formatPrice($price) {
        return 'R$ ' . number_format(floatval($price), 2, ',', '.');
    }
}

// Função para log de atividade (fallback)
if (!function_exists('logActivity')) {
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
}

// Verificar se deve executar instalação automática
if (isset($_GET['auto_install']) && $_GET['auto_install'] === '1') {
    $tablesCheck = checkDatabaseTables();
    
    if (!$tablesCheck['all_exist']) {
        $creation = createMissingTables();
        if ($creation['success']) {
            insertInitialData();
            header('Location: dashboard.php?install=success');
            exit;
        }
    }
}

// Definir constantes úteis para admin
if (!defined('ADMIN_PATH')) {
    define('ADMIN_PATH', __DIR__);
}

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

if (!defined('ADMIN_URL')) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['REQUEST_URI']);
    define('ADMIN_URL', $protocol . '://' . $host . $path);
}
?>