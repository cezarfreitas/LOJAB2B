<?php
// config/database.php - Versão limpa sem conflitos
class Database {
    private $host = "localhost";
    private $dbname = "loja";
    private $username = "root";
    private $password = "";
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8", 
                $this->username, 
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            // Tentar criar o banco se não existir
            try {
                $tempPdo = new PDO(
                    "mysql:host={$this->host};charset=utf8", 
                    $this->username, 
                    $this->password
                );
                $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$this->dbname}`");
                
                // Conectar novamente com o banco criado
                $this->pdo = new PDO(
                    "mysql:host={$this->host};dbname={$this->dbname};charset=utf8", 
                    $this->username, 
                    $this->password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ]
                );
            } catch (PDOException $e2) {
                die("Erro de conexão com o banco de dados: " . $e2->getMessage());
            }
        }
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception("Erro na consulta SQL: " . $e->getMessage());
        }
    }
    
    public function getLastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    public function commit() {
        return $this->pdo->commit();
    }
    
    public function rollback() {
        return $this->pdo->rollback();
    }
}
?>