<?php
// config/functions.php - Funções centralizadas para evitar redeclaração

// Função para formatar preço
if (!function_exists('formatPrice')) {
    function formatPrice($price) {
        return 'R$ ' . number_format(floatval($price), 2, ',', '.');
    }
}

// Função para sanitizar entrada
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($input) {
        return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
    }
}

// Função para formatar CNPJ
if (!function_exists('formatCNPJ')) {
    function formatCNPJ($cnpj) {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        if (strlen($cnpj) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpj);
        }
        return $cnpj;
    }
}

// Função para log de atividades
if (!function_exists('logActivity')) {
    function logActivity($userId, $action, $details = '') {
        try {
            $db = new Database();
            
            // Criar tabela de log se não existir
            $db->query("CREATE TABLE IF NOT EXISTS log_atividades (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT,
                acao VARCHAR(100) NOT NULL,
                detalhes TEXT,
                data_acao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            
            $sql = "INSERT INTO log_atividades (usuario_id, acao, detalhes) VALUES (?, ?, ?)";
            $db->query($sql, [$userId, $action, $details]);
        } catch (Exception $e) {
            // Log silencioso - não quebrar o sistema se log falhar
            error_log("Erro no log de atividade: " . $e->getMessage());
        }
    }
}

// Função para upload de imagem
if (!function_exists('uploadImage')) {
    function uploadImage($file, $uploadDir) {
        // Criar diretório se não existir
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Verificar se é imagem válida
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = mime_content_type($file['tmp_name']);
        
        if (!in_array($fileType, $allowedTypes)) {
            throw new Exception('Tipo de arquivo não permitido. Use JPG, PNG, GIF ou WebP.');
        }
        
        // Verificar tamanho (máximo 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception('Arquivo muito grande. Máximo 5MB.');
        }
        
        // Gerar nome único
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . strtolower($extension);
        $destination = $uploadDir . $filename;
        
        // Mover arquivo
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new Exception('Erro ao fazer upload do arquivo.');
        }
        
        return $filename;
    }
}

// Função para gerar código de produto
if (!function_exists('generateProductCode')) {
    function generateProductCode($name) {
        $code = strtoupper(preg_replace('/[^A-Za-z]/', '', $name));
        $code = substr($code, 0, 3);
        if (strlen($code) < 3) {
            $code = str_pad($code, 3, 'X');
        }
        $code .= '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        return $code;
    }
}

// Função para verificar se usuário está logado
if (!function_exists('requireLogin')) {
    function requireLogin() {
        session_start();
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
            header('Location: login.php');
            exit;
        }
    }
}

// Função para verificar se usuário é admin
if (!function_exists('requireAdmin')) {
    function requireAdmin() {
        session_start();
        if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
            header('Location: ../login.php');
            exit;
        }
    }
}

// Função para escapar dados para HTML
if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

// Função para formatar data
if (!function_exists('formatDate')) {
    function formatDate($date, $format = 'd/m/Y') {
        if (empty($date)) return '';
        return date($format, strtotime($date));
    }
}

// Função para formatar data e hora
if (!function_exists('formatDateTime')) {
    function formatDateTime($datetime, $format = 'd/m/Y H:i') {
        if (empty($datetime)) return '';
        return date($format, strtotime($datetime));
    }
}

// Função para validar email
if (!function_exists('isValidEmail')) {
    function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

// Função para validar CNPJ
if (!function_exists('isValidCNPJ')) {
    function isValidCNPJ($cnpj) {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        
        if (strlen($cnpj) !== 14) {
            return false;
        }
        
        // Verificar se todos os dígitos são iguais
        if (preg_match('/(\d)\1{13}/', $cnpj)) {
            return false;
        }
        
        // Validar dígitos verificadores
        $length = strlen($cnpj) - 2;
        $numbers = substr($cnpj, 0, $length);
        $digits = substr($cnpj, $length);
        $sum = 0;
        $pos = $length - 7;
        
        for ($i = $length; $i >= 1; $i--) {
            $sum += $numbers[$length - $i] * $pos--;
            if ($pos < 2) $pos = 9;
        }
        
        $result = $sum % 11 < 2 ? 0 : 11 - $sum % 11;
        if ($result != $digits[0]) return false;
        
        $length = $length + 1;
        $numbers = substr($cnpj, 0, $length);
        $sum = 0;
        $pos = $length - 7;
        
        for ($i = $length; $i >= 1; $i--) {
            $sum += $numbers[$length - $i] * $pos--;
            if ($pos < 2) $pos = 9;
        }
        
        $result = $sum % 11 < 2 ? 0 : 11 - $sum % 11;
        return $result == $digits[1];
    }
}

// Função para debug (apenas em desenvolvimento)
if (!function_exists('dd')) {
    function dd($data) {
        echo '<pre style="background: #f8f9fa; padding: 1rem; border: 1px solid #ddd; border-radius: 5px; margin: 1rem 0;">';
        var_dump($data);
        echo '</pre>';
        die();
    }
}
?>