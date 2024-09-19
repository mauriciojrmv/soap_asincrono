<?php
function getSoapClient() {
    try {
        return new SoapClient(null, [
            'location' => "http://localhost:8000/colas/server.php",  // Cambia por tu configuración
            'uri' => "urn:PersonService",
            'trace' => 1
        ]);
    } catch (SoapFault $e) {
        throw new Exception("Nivel 1: Error - No se pudo crear el cliente SOAP.");
    }
}
?>
