<?php
require_once 'conectionpool.php';  // Pool de conexiones y gestor de transacciones

class PersonService {
    private $soapClient;

    public function __construct() {
        try {
            // Obtener el cliente SOAP desde el pool de conexiones
            $pool = ConnectionPool::getInstance();
            $this->soapClient = $pool->getConnection();

            // Verificación de la conexión SOAP
            if (!$this->soapClient) {
                throw new Exception("No se pudo obtener una conexión SOAP desde el pool.");
            }
        } catch (Exception $e) {
            throw new SoapFault("Server", "Error en la conexión SOAP: " . $e->getMessage());
        }
    }

    // Registrar persona (incluyendo login y password)
    public function registerPerson($nombre, $apellido_paterno, $apellido_materno, $numero_carnet, $fecha_nacimiento, $sexo, $lugar_nacimiento, $estado_civil, $profesion, $domicilio, $login, $password, $token) {
        try {
            // Verificar si el token ya está registrado (persona con estos mismos datos)
            $tokenExists = $this->remoteCall('checkIfPersonExists', [$token]);
            if ($tokenExists) {
                $personInfo = $this->remoteCall('getPersonInfo', [$token]);
                throw new SoapFault("Client", "Nivel 2: Error - Este registro ya existe. Nombre: " . $personInfo['nombre'] . ", Apellido Paterno: " . $personInfo['apellido_paterno'] . ", Apellido Materno: " . $personInfo['apellido_materno'] . ", Carnet: " . $personInfo['numero_carnet'] . ".");
            }

            // Verificar si el carnet ya está registrado
            $carnetExists = $this->remoteCall('checkIfCarnetExists', [$numero_carnet]);
            if ($carnetExists) {
                throw new SoapFault("Client", "Nivel 2: Error - El número de carnet ya está registrado.");
            }

            // Verificar si el login ya está registrado
            $loginExists = $this->remoteCall('checkIfLoginExists', [$login]);
            if ($loginExists) {
                throw new SoapFault("Client", "Nivel 2: Error - El login ya está en uso.");
            }

            // Insertar persona en la base de datos
            $result = $this->remoteCall('insertPerson', [
                $nombre, $apellido_paterno, $apellido_materno, $numero_carnet, $fecha_nacimiento, $sexo,
                $lugar_nacimiento, $estado_civil, $profesion, $domicilio, $login, $password, $token
            ]);

            if ($result !== true) {
                throw new Exception("Error al insertar la persona.");
            }

            return "Registro exitoso de: $nombre $apellido_paterno $apellido_materno - $numero_carnet";
        } catch (SoapFault $e) {
            throw $e;  // Propagar errores de nivel 2 o 3
        } catch (Exception $e) {
            throw new SoapFault("Server", "Nivel 2: Error - " . $e->getMessage());
        }
    }

    public function getCuentasInfo() {
        return $this->remoteCall('getCuentasInfo', []);
    }

    // Método para autenticar login y contraseña
    public function loginPerson($login, $password) {
        try {
            // Llamar a la base de datos para verificar el login
            $result = $this->remoteCall('authenticateLogin', [$login, $password]);

            if ($result === true) {
                return "success"; // Autenticación exitosa
            } else {
                return "Nivel 2: Error - Login o contraseña incorrectos.";
            }
        } catch (SoapFault $e) {
            throw $e;
        } catch (Exception $e) {
            throw new SoapFault("Server", "Nivel 2: Error - Ocurrió un problema durante la autenticación.");
        }
    }

    // Método para crear una cuenta
    public function crearCuenta($login, $tipo_cuenta, $token) {
        try {
            // Verificar si ya existe una cuenta del mismo tipo para este cliente
            $cuentaExiste = $this->remoteCall('checkIfAccountExists', [$login, $tipo_cuenta]);
            if ($cuentaExiste) {
                throw new SoapFault("Client", "Nivel 2: Error - Ya tienes una cuenta en $tipo_cuenta.");
            }

            // Crear la cuenta si no existe
            $result = $this->remoteCall('insertAccount', [$login, $tipo_cuenta, $token]);

            if ($result !== true) {
                throw new Exception("Error al crear la cuenta.");
            }

            return "success";
        } catch (SoapFault $e) {
            throw $e;  // Propagar errores de nivel 2 o 3
        } catch (Exception $e) {
            throw new SoapFault("Server", "Nivel 2: Error - " . $e->getMessage());
        }
    }

    // Obtener las cuentas de un cliente
    public function getCuentasCliente($login) {
        try {
            return $this->remoteCall('getCuentasCliente', [$login]);
        } catch (SoapFault $e) {
            throw new SoapFault("Server", "Nivel 2: Error - " . $e->getMessage());  // Manejo de errores de Nivel 2 o 3
        }
    }

// Método para realizar un depósito con la cola de peticiones y el pool
public function depositar($cuenta_id, $monto, $token, $callback_url) {
    try {
        // Crear una nueva petición en estado "Pendiente" en la tabla `peticiones`
        $this->remoteCall('agregarPeticion', [
            'tipo' => 'deposito',
            'cuenta_id' => $cuenta_id,
            'monto' => $monto,
            'token' => $token,
            'callback_url' => $callback_url  // Guardamos la URL de callback del cliente
        ]);

        // Devolver un mensaje de éxito indicando que la transacción está en cola
        return true;
    } catch (SoapFault $e) {
        throw new SoapFault("Server", "Error al encolar el depósito: " . $e->getMessage());
    }
}

// Método para realizar un retiro con la cola de peticiones y el pool
public function retirar($cuenta_id, $monto, $token, $callback_url) {
    try {
        // Crear una nueva petición en estado "Pendiente" en la tabla `peticiones`
        $this->remoteCall('agregarPeticion', [
            'tipo' => 'retiro',
            'cuenta_id' => $cuenta_id,
            'monto' => $monto,
            'token' => $token,
            'callback_url' => $callback_url  // Guardamos la URL de callback del cliente
        ]);

        // Devolver un mensaje de éxito indicando que la transacción está en cola
        return true;
    } catch (SoapFault $e) {
        throw new SoapFault("Server", "Error al encolar el retiro: " . $e->getMessage());
    }
}

    // Método para consultar el estado de la transacción
    public function getEstadoTransaccion($token) {
        try {
            // Hacer una llamada remota al método de la base de datos para obtener el estado de la transacción
            return $this->remoteCall('getEstadoTransaccion', [$token]);
        } catch (SoapFault $e) {
            throw new SoapFault("Server", "Error al obtener el estado de la transacción: " . $e->getMessage());
        }
    }

    // Llamadas remotas para conectar al servidor de base de datos
    private function remoteCall($method, $params) {
        try {
            // Realizar la llamada SOAP al método en bd.php
            return $this->soapClient->__soapCall($method, $params);
        } catch (SoapFault $e) {
            throw new SoapFault("Server", "Nivel 3: Error - Problema al acceder a la base de datos: " . $e->getMessage());
        }
    }

}

// Configuración del servidor SOAP
$server = new SoapServer(null, ['uri' => "urn:PersonService"]);
$server->setClass('PersonService');
$server->handle();


