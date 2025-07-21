<?php
// config/database.php

class Database {
    private $host = 'localhost';
    private $db_name = 'avaliacao_saas';
    private $username = 'root';
    private $password = '';
    private $conn;
    private $tenant_id;

    public function __construct($tenant_id = null) {
        $this->tenant_id = $tenant_id;
    }

    public function connect() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name}", 
                $this->username, 
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            echo 'Connection Error: ' . $e->getMessage();
        }

        return $this->conn;
    }

    // Método para adicionar filtro de tenant automaticamente
    public function prepareWithTenant($sql) {
        $conn = $this->connect();
        $stmt = $conn->prepare($sql);
        
        // Adiciona tenant_id a todas as queries se existir
        if ($this->tenant_id) {
            if (strpos($sql, 'WHERE') === false) {
                $sql .= " WHERE empresa_id = :tenant_id";
            } else {
                $sql .= " AND empresa_id = :tenant_id";
            }
            $stmt->bindParam(':tenant_id', $this->tenant_id);
        }
        
        return $stmt;
    }
}
?>