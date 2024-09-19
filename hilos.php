<?php
require_once 'Peticiones.php';  // Maneja las peticiones desde la base de datos
require_once 'conectionpool.php';  // Pool de conexiones SOAP

class HilosManager {
    private $pool;
    private $peticiones;

    public function __construct() {
        // Inicializar el pool de conexiones y el gestor de peticiones
        $this->pool = ConnectionPool::getInstance();
        $this->peticiones = new Peticiones();
    }

    // Función que procesa una petición específica
    private function procesarPeticion($peticion) {
        try {
            $tipo = $peticion['tipo'];  // Tipo de petición: 'depositar' o 'retirar'
            $cuenta_id = $peticion['cuenta_id'];
            $monto = $peticion['monto'];
            $token = $peticion['token'];

            // Obtener una conexión desde el pool
            $soapClient = $this->pool->getConnection();

            // Procesar la petición según su tipo llamando a bd.php vía SOAP
            switch ($tipo) {
                case 'depositar':
                    $resultado = $soapClient->__soapCall('depositar', [$cuenta_id, $monto, $token]);
                    break;
                case 'retirar':
                    $resultado = $soapClient->__soapCall('retirar', [$cuenta_id, $monto, $token]);
                    break;
                default:
                    throw new Exception("Tipo de petición no reconocido: $tipo");
            }

            // Si todo salió bien, marcar la petición como realizada en la base de datos
            $this->peticiones->marcarComoRealizada($peticion['peticion_id']);  // Actualiza el estado a "Realizado"

            // Liberar la conexión de vuelta al pool
            $this->pool->releaseConnection($soapClient);

            return $resultado;
        } catch (Exception $e) {
            // Si ocurre un error, marcar la petición como errónea en la base de datos
            $this->peticiones->marcarError($peticion['peticion_id']);
            throw new Exception("Error al procesar la petición: " . $e->getMessage());
        }
    }

    // Función que procesa la cola de peticiones pendientes
    public function procesarCola() {
        // Leer las peticiones pendientes de la base de datos
        $cola = $this->peticiones->leerCola();

        // Procesar cada petición que esté en estado "Pendiente"
        foreach ($cola as $peticion) {
            if ($peticion['estado'] === 'Pendiente') {
                try {
                    // Procesar la petición individualmente
                    $this->procesarPeticion($peticion);
                } catch (Exception $e) {
                    // Manejo de errores, pero ya se marca en `marcarError()`
                    error_log("Error procesando petición ID: " . $peticion['peticion_id'] . " - " . $e->getMessage());
                }
            }
        }
    }

    // Método para ejecutar el procesamiento en intervalos (simulando un sistema de colas asíncrono)
    public function ejecutarConIntervalo($intervaloSegundos = 5) {
        while (true) {
            $this->procesarCola();  // Procesar las peticiones pendientes
            sleep($intervaloSegundos);  // Esperar antes de procesar la cola nuevamente
        }
    }
}

// Inicializar el manejador de hilos y procesar la cola
$gestorHilos = new HilosManager();
$gestorHilos->ejecutarConIntervalo(10);  // Procesa cada 10 segundos
?>
