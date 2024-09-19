<?php
class ConnectionPool {
    private static $instance = null;
    private $connections = [];
    private $maxConnections = 500;

    // Constructor privado para asegurar que solo haya una instancia del pool
    private function __construct() {
        for ($i = 0; $i < $this->maxConnections; $i++) {
            $this->connections[] = new SoapClient(null, [
                'location' => "http://localhost:8000/colas/bd.php",
                'uri' => "urn:DatabaseService"
            ]);
        }
    }

    // Obtener la única instancia del pool
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new ConnectionPool();
        }
        return self::$instance;
    }

    // Obtener una conexión del pool
    public function getConnection() {
        if (count($this->connections) > 0) {
            return array_pop($this->connections);
        } else {
            throw new Exception("No hay conexiones disponibles en el pool.");
        }
    }

    // Devolver una conexión al pool
    public function releaseConnection($connection) {
        $this->connections[] = $connection;
    }
}
?>
