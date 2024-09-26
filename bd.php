<?php
class DatabaseService {
    private $pdo;

    public function __construct() {
        try {
            $dsn = 'mysql:host=localhost;dbname=banco_db';
            $username = 'root';
            $password = '';
            $options = array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_PERSISTENT => true // Conexiones persistentes
            );
            
            $this->pdo = new PDO($dsn, $username, $password, $options);
            $this->pdo->exec("SET TRANSACTION ISOLATION LEVEL SERIALIZABLE");
        } catch (PDOException $e) {
            throw new SoapFault("Server", "Nivel 3: Error - No se pudo conectar a la base de datos: " . $e->getMessage());
        }
    }

    // Verificar si una persona con un token ya está registrada
    public function checkIfPersonExists($token) {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM clientes WHERE token = :token");
            $stmt->execute(['token' => $token]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            throw new SoapFault("Server", "Nivel 3: Error - Esta persona ya existe: " . $e->getMessage());
        }
    }

    // Verificar si el carnet ya está registrado
    public function checkIfCarnetExists($numero_carnet) {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM clientes WHERE numero_carnet = :numero_carnet");
            $stmt->execute(['numero_carnet' => $numero_carnet]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            throw new SoapFault("Server", "Nivel 3: Error - Este carnet ya fue utilizado: " . $e->getMessage());
        }
    }

    // Autenticar login y contraseña
    public function authenticateLogin($login, $password) {
        try {
            $stmt = $this->pdo->prepare("SELECT password FROM clientes WHERE login = :login");
            $stmt->execute(['login' => $login]);
            $storedPassword = $stmt->fetchColumn();

            if ($storedPassword && password_verify($password, $storedPassword)) {
                return true; // Autenticación exitosa
            } else {
                return false; // Login o contraseña incorrectos
            }
        } catch (PDOException $e) {
            throw new SoapFault("Server", "Nivel 3: Error - Problema al autenticar el login: " . $e->getMessage());
        }
    }

    // Verificar si el login ya está registrado
    public function checkIfLoginExists($login) {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM clientes WHERE login = :login");
            $stmt->execute(['login' => $login]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            throw new SoapFault("Server", "Nivel 3: Error - Este login ya fue utilizado: " . $e->getMessage());
        }
    }
    
    // Obtener información de la persona por token
    public function getPersonInfo($token) {
        try {
            $stmt = $this->pdo->prepare("SELECT nombre, apellido_paterno, apellido_materno, numero_carnet FROM clientes WHERE token = :token");
            $stmt->execute(['token' => $token]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new SoapFault("Server", "Nivel 3: Error - Problema al obtener la información de la persona: " . $e->getMessage());
        }
    }

    // Insertar una nueva persona en la base de datos
    public function insertPerson($nombre, $apellido_paterno, $apellido_materno, $numero_carnet, $fecha_nacimiento, $sexo, $lugar_nacimiento, $estado_civil, $profesion, $domicilio, $login, $password, $token) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO clientes 
                (nombre, apellido_paterno, apellido_materno, numero_carnet, fecha_nacimiento, sexo, lugar_nacimiento, estado_civil, profesion, domicilio, login, password, token) 
                VALUES 
                (:nombre, :apellido_paterno, :apellido_materno, :numero_carnet, :fecha_nacimiento, :sexo, :lugar_nacimiento, :estado_civil, :profesion, :domicilio, :login, :password, :token)
            ");
            $result = $stmt->execute([
                'nombre' => $nombre,
                'apellido_paterno' => $apellido_paterno,
                'apellido_materno' => $apellido_materno,
                'numero_carnet' => $numero_carnet,
                'fecha_nacimiento' => $fecha_nacimiento,
                'sexo' => $sexo,
                'lugar_nacimiento' => $lugar_nacimiento,
                'estado_civil' => $estado_civil,
                'profesion' => $profesion,
                'domicilio' => $domicilio,
                'login' => $login,
                'password' => $password,
                'token' => $token
            ]);
            return $result;
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') { // Código para violaciones de integridad
                throw new SoapFault("Client", "Nivel 2: Error - Este registro ya existe en el sistema.");
            } else {
                throw new SoapFault("Server", "Nivel 3: Error - Problema al insertar la persona en la base de datos: " . $e->getMessage());
            }
        }
    }

// Obtener las cuentas del cliente
public function getCuentasCliente($login) {
    try {
        $stmt = $this->pdo->prepare("SELECT id, tipo_cuenta, saldo FROM cuentas WHERE login = :login");
        $stmt->execute(['login' => $login]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        throw new SoapFault("Server", "Nivel 3: Error - Problema al obtener las cuentas del cliente: " . $e->getMessage());
    }
}

// Insertar nueva cuenta en la base de datos
public function insertAccount($login, $tipo_cuenta, $token) {
    try {
        $stmt = $this->pdo->prepare("
            INSERT INTO cuentas (login, tipo_cuenta, token, saldo, creado_en)
            VALUES (:login, :tipo_cuenta, :token, 0, NOW())
        ");
        $result = $stmt->execute([
            'login' => $login,
            'tipo_cuenta' => $tipo_cuenta,
            'token' => $token
        ]);
        return $result;
    } catch (PDOException $e) {
        throw new SoapFault("Server", "Nivel 3: Error - Problema al insertar la cuenta: " . $e->getMessage());
    }
}

// Verificar si ya existe una cuenta del mismo tipo para este cliente
public function checkIfAccountExists($login, $tipo_cuenta) {
    try {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM cuentas WHERE login = :login AND tipo_cuenta = :tipo_cuenta");
        $stmt->execute(['login' => $login, 'tipo_cuenta' => $tipo_cuenta]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        throw new SoapFault("Server", "Nivel 3: Error - Problema al verificar la existencia de la cuenta: " . $e->getMessage());
    }
}

// Obtener todas las cuentas, la cuenta con más y menos saldo
public function getCuentasInfo() {
    try {
        // Obtener todas las cuentas
        $stmt = $this->pdo->prepare("SELECT id, saldo FROM cuentas");
        $stmt->execute();
        $cuentas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Obtener la cuenta con más saldo
        $stmt = $this->pdo->prepare("SELECT id, saldo FROM cuentas ORDER BY saldo DESC LIMIT 1");
        $stmt->execute();
        $maxCuenta = $stmt->fetch(PDO::FETCH_ASSOC);

        // Obtener la cuenta con menos saldo
        $stmt = $this->pdo->prepare("SELECT id, saldo FROM cuentas ORDER BY saldo ASC LIMIT 1");
        $stmt->execute();
        $minCuenta = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'cuentas' => $cuentas,
            'maxCuenta' => $maxCuenta, // Contiene el id y el saldo de la cuenta con más saldo
            'minCuenta' => $minCuenta  // Contiene el id y el saldo de la cuenta con menos saldo
        ];
    } catch (PDOException $e) {
        throw new SoapFault("Server", "Nivel 3: Error - No se pudo obtener la información de las cuentas.");
    }
}

// Insertar una petición de transacción (depósito o retiro) en la tabla de peticiones
public function agregarPeticion($tipo, $cuenta_id, $monto, $token, $callback_url) {
    try {
        // Solo permitir valores 'deposito' o 'retiro' en la columna tipo
        if (!in_array($tipo, ['deposito', 'retiro'])) {
            throw new Exception("Tipo de transacción inválido.");
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO peticiones (tipo, cuenta_id, monto, token, callback_url, estado, intentos) 
            VALUES (:tipo, :cuenta_id, :monto, :token, :callback_url, 'Pendiente', 0)
        ");
        $stmt->execute([
            'tipo' => $tipo,
            'cuenta_id' => $cuenta_id,
            'monto' => $monto,
            'token' => $token,
            'callback_url' => $callback_url // Aquí incluimos el callback_url
        ]);
        return true;
    } catch (PDOException $e) {
        throw new SoapFault("Server", "Nivel 3: Error - No se pudo agregar la petición: " . $e->getMessage());
    }
}


// Método para obtener el estado de la transacción
public function getEstadoTransaccion($token) {
    try {
        $stmt = $this->pdo->prepare("SELECT estado FROM peticiones WHERE token = :token");
        $stmt->execute(['token' => $token]);
        $estado = $stmt->fetchColumn();

        if ($estado) {
            return $estado; // Retorna 'Pendiente', 'Realizado' o 'Error'
        } else {
            throw new SoapFault("Server", "No se encontró una transacción con el token proporcionado.");
        }
    } catch (PDOException $e) {
        throw new SoapFault("Server", "Nivel 3: Error en la base de datos al obtener el estado de la transacción: " . $e->getMessage());
    }
}
    
public function verificarUnicidadTokenEnPeticiones($token) {
    $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM peticiones WHERE token = :token");
    $stmt->execute(['token' => $token]);
    return $stmt->fetchColumn() == 0; // Retorna true si el token no existe
}


// Obtener las peticiones pendientes
public function obtenerPeticionesPendientes() {
    $stmt = $this->pdo->prepare("SELECT * FROM peticiones WHERE estado = 'Pendiente' AND intentos < 5");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Eliminar petición realizada
public function eliminarPeticion($token) {
    $stmt = $this->pdo->prepare("DELETE FROM peticiones WHERE token = :token");
    $stmt->execute(['token' => $token]);
}

// Manejar errores de petición
public function manejarErrorPeticion($peticion_id, $mensaje_error) {
    // Aumentar el número de intentos en la tabla peticiones
    $stmt = $this->pdo->prepare("UPDATE peticiones SET intentos = intentos + 1 WHERE peticion_id = :peticion_id");
    $stmt->execute(['peticion_id' => $peticion_id]);

    // Si se alcanzan los 5 intentos, registrar el error en log_errores
    $stmt = $this->pdo->prepare("SELECT intentos FROM peticiones WHERE peticion_id = :peticion_id");
    $stmt->execute(['peticion_id' => $peticion_id]);
    $intentos = $stmt->fetchColumn();

    if ($intentos >= 5) {
        // Mover el error a log_errores
        $stmt = $this->pdo->prepare("INSERT INTO log_errores (peticion_id, mensaje_error) VALUES (:peticion_id, :mensaje_error)");
        $stmt->execute(['peticion_id' => $peticion_id, 'mensaje_error' => $mensaje_error]);

        // Eliminar la petición de la tabla peticiones
        $this->eliminarPeticion($peticion_id);
    }
}



// Método para procesar una transacción de depósito
public function procesarDeposito($cuenta_id, $monto, $token, $callback_url) {
    try {
        // Iniciar la transacción en la base de datos
        $this->pdo->beginTransaction();

        // Verificar si la cuenta existe y obtener el saldo actual
        $stmt = $this->pdo->prepare("SELECT saldo FROM cuentas WHERE id = :cuenta_id FOR UPDATE");
        $stmt->execute(['cuenta_id' => $cuenta_id]);
        $saldo = $stmt->fetchColumn();

        if ($saldo === false) {
            throw new Exception("Cuenta no encontrada.");
        }

        // Actualizar el saldo de la cuenta
        $stmt = $this->pdo->prepare("UPDATE cuentas SET saldo = saldo + :monto WHERE id = :cuenta_id");
        $stmt->execute(['monto' => $monto, 'cuenta_id' => $cuenta_id]);

        // Guardar la transacción en la tabla de transacciones con el estado de notificación en 'pendiente'
        $stmt = $this->pdo->prepare("
            INSERT INTO transacciones (cuenta_id, tipo_transaccion, monto, token, estado_notificacion, callback_url) 
            VALUES (:cuenta_id, 'deposito', :monto, :token, 'pendiente', :callback_url)
        ");
        $stmt->execute([
            'cuenta_id' => $cuenta_id,
            'monto' => $monto,
            'token' => $token,
            'callback_url' => $callback_url
        ]);

        // Confirmar la transacción en la base de datos
        $this->pdo->commit();

        return true;
    } catch (Exception $e) {
        // En caso de error, revertir la transacción
        $this->pdo->rollBack();

        // Re-lanzar la excepción para manejarla a nivel superior
        throw $e;
    }
}


// Método para procesar una transacción de retiro
public function procesarRetiro($cuenta_id, $monto, $token, $callback_url) {
    try {
        // Iniciar la transacción en la base de datos
        $this->pdo->beginTransaction();

        // Verificar si la cuenta existe y obtener el saldo actual
        $stmt = $this->pdo->prepare("SELECT saldo FROM cuentas WHERE id = :cuenta_id FOR UPDATE");
        $stmt->execute(['cuenta_id' => $cuenta_id]);
        $saldo = $stmt->fetchColumn();

        if ($saldo === false || $saldo < $monto) {
            throw new Exception("Saldo insuficiente o cuenta no encontrada.");
        }

        // Actualizar el saldo de la cuenta
        $stmt = $this->pdo->prepare("UPDATE cuentas SET saldo = saldo - :monto WHERE id = :cuenta_id");
        $stmt->execute(['monto' => $monto, 'cuenta_id' => $cuenta_id]);

        // Guardar la transacción en la tabla de transacciones con el estado de notificación en 'pendiente'
        $stmt = $this->pdo->prepare("
            INSERT INTO transacciones (cuenta_id, tipo_transaccion, monto, token, estado_notificacion, callback_url) 
            VALUES (:cuenta_id, 'retiro', :monto, :token, 'pendiente', :callback_url)
        ");
        $stmt->execute([
            'cuenta_id' => $cuenta_id,
            'monto' => $monto,
            'token' => $token,
            'callback_url' => $callback_url
        ]);

        // Confirmar la transacción en la base de datos
        $this->pdo->commit();

        return true;
    } catch (Exception $e) {
        // En caso de error, revertir la transacción
        $this->pdo->rollBack();

        // Re-lanzar la excepción para manejarla a nivel superior
        throw $e;
    }
}


// Obtener las transacciones pendientes de notificación
public function obtenerTransaccionesPendientesNotificacion() {
    // Aquí seleccionamos las transacciones pendientes por el estado de notificación
    $stmt = $this->pdo->prepare("SELECT * FROM transacciones WHERE estado_notificacion = 'pendiente'");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}





// Marcar la transacción como en proceso de notificación
public function marcarNotificacionEnProceso($token) {
    // Usamos el token como identificador para actualizar el estado de la notificación
    $stmt = $this->pdo->prepare("UPDATE transacciones SET estado_notificacion = 'enviando' WHERE token = :token");
    $stmt->execute(['token' => $token]);
}

// Marcar la notificación como enviada
public function marcarNotificacionEnviada($token) {
    // Usamos el token para marcar que la notificación fue enviada
    $stmt = $this->pdo->prepare("UPDATE transacciones SET estado_notificacion = 'enviada' WHERE token = :token");
    $stmt->execute(['token' => $token]);
}

// Manejar errores de notificación
public function manejarErrorNotificacion($token, $mensaje_error) {
    try {
        // Insertamos el error en el log usando el token de la transacción
        $stmt = $this->pdo->prepare("
            INSERT INTO log_errores (peticion_id, tipo, mensaje_error) 
            VALUES (:peticion_id, 'notificacion', :mensaje_error)
        ");
        $stmt->execute([
            'peticion_id' => $token,  // Usamos el token como peticion_id
            'mensaje_error' => $mensaje_error
        ]);
    } catch (PDOException $e) {
        error_log("Error al registrar el error en la notificación: " . $e->getMessage());
    }
}


// Método para enviar notificación al cliente
public function enviarNotificacionCliente($callback_url, $token, $status, $mensaje) {
    try {
        // Crear un cliente SOAP apuntando al callback URL del cliente
        $client = new SoapClient(null, [
            'location' => $callback_url,  // URL del servicio SOAP del cliente
            'uri' => "urn:ClienteService", // El namespace definido en el cliente
            'trace' => 1,  // Para habilitar el seguimiento y depurar si es necesario
            'exceptions' => true  // Lanzar excepciones en caso de error
        ]);

        // Llamar al método SOAP 'notificarEstadoTransaccion' en el cliente
        $client->__soapCall('notificarEstadoTransaccion', [
            'token' => $token,
            'status' => $status,
            'mensaje' => $mensaje
        ]);

        // Notificación enviada exitosamente
        return true;
    } catch (Exception $e) {
        // Registrar el error de notificación si falla
        error_log("Error al notificar al cliente: " . $e->getMessage());
        throw $e;  // Volver a lanzar la excepción para manejarla externamente
    }
}


}

// Configuración del servidor SOAP
$server = new SoapServer(null, ['uri' => "urn:DatabaseService"]);
$server->setClass('DatabaseService');
$server->handle();
?>
