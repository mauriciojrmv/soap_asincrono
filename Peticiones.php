<?php
class Peticiones {
    private $pdo;

    public function __construct() {
        try {
            $dsn = 'mysql:host=localhost;dbname=banco_db';
            $username = 'root';
            $password = '';
            $options = array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_PERSISTENT => true
            );
            $this->pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            throw new Exception("Error al conectar a la base de datos: " . $e->getMessage());
        }
    }

    // Leer las peticiones pendientes de la base de datos
    public function leerCola() {
        $stmt = $this->pdo->prepare("SELECT * FROM peticiones WHERE estado = 'Pendiente'");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Agregar una nueva petición a la base de datos
    public function agregarPeticion($tipo, $data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO peticiones (peticion_id, tipo, cuenta_id, monto, token, estado) 
            VALUES (:peticion_id, :tipo, :cuenta_id, :monto, :token, 'Pendiente')
        ");
        $stmt->execute([
            'peticion_id' => uniqid(),
            'tipo' => $tipo,
            'cuenta_id' => $data['cuenta_id'],
            'monto' => $data['monto'],
            'token' => $data['token']
        ]);
    }

    // Marcar una petición como realizada en la base de datos
    public function marcarComoRealizada($peticion_id) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE peticiones 
                SET estado = 'Realizado' 
                WHERE peticion_id = :peticion_id
            ");
            $stmt->execute(['peticion_id' => $peticion_id]);
            error_log("Petición ID: $peticion_id marcada como Realizado.");
        } catch (Exception $e) {
            error_log("Error actualizando petición ID: $peticion_id - " . $e->getMessage());
        }
    }

    // Marcar una petición como errónea en la base de datos
    public function marcarError($peticion_id) {
        $stmt = $this->pdo->prepare("
            UPDATE peticiones 
            SET estado = 'Error', intentos = intentos + 1
            WHERE peticion_id = :peticion_id
        ");
        $stmt->execute(['peticion_id' => $peticion_id]);
    }
}
?>
