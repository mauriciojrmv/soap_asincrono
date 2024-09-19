<?php
class Peticiones {
    private $soapClient;

    public function __construct() {
        try {
            // Crear un cliente SOAP que apunte a la PC2 (servidor de la base de datos)
            $this->soapClient = new SoapClient(null, [
                'location' => "http://localhost:8000/colas/bd.php", // URL de PC2
                'uri' => "urn:DatabaseService"
            ]);
        } catch (Exception $e) {
            throw new Exception("Error al conectar al servidor SOAP: " . $e->getMessage());
        }
    }

    // Leer las peticiones pendientes llamando al servidor de la base de datos en PC2
    public function leerCola() {
        return $this->soapClient->__soapCall('leerCola', []);
    }

    // Agregar una nueva petición llamando al servidor de la base de datos en PC2
    public function agregarPeticion($tipo, $data) {
        return $this->soapClient->__soapCall('agregarPeticion', [$tipo, $data]);
    }

    // Marcar una petición como realizada llamando al servidor de la base de datos en PC2
    public function marcarComoRealizada($peticion_id) {
        return $this->soapClient->__soapCall('marcarComoRealizada', [$peticion_id]);
    }

    // Marcar una petición como errónea llamando al servidor de la base de datos en PC2
    public function marcarError($peticion_id) {
        return $this->soapClient->__soapCall('marcarError', [$peticion_id]);
    }
}
?>
