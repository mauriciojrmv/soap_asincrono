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
    
    public function verificarUnicidadToken($token) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM transacciones WHERE token = :token");
        $stmt->execute(['token' => $token]);
        return $stmt->fetchColumn() == 0; // Retorna true si el token no existe
    }
    
    public function insertTransaction($cuenta_id, $monto, $token, $tipo) {
        if ($this->verificarUnicidadToken($token)) {
            $stmt = $this->pdo->prepare("INSERT INTO transacciones (cuenta_id, tipo_transaccion, monto, token) VALUES (:cuenta_id, :tipo, :monto, :token)");
            return $stmt->execute(['cuenta_id' => $cuenta_id, 'tipo' => $tipo, 'monto' => $monto, 'token' => $token]);
        } else {
            throw new Exception("Token duplicado. Intenta con otro token.");
        }
    }

    // Método para actualizar el estado de una petición en la tabla peticiones
private function actualizarEstadoPeticion($token, $estado) {
    try {
        $stmt = $this->pdo->prepare("
            UPDATE peticiones 
            SET estado = :estado 
            WHERE token = :token
        ");
        $stmt->execute([
            'estado' => $estado,
            'token' => $token
        ]);
    } catch (PDOException $e) {
        error_log("Error al actualizar el estado de la petición: " . $e->getMessage());
    }
}
    

    // Insertar una transacción de depósito
    public function depositar($cuenta_id, $monto, $token) {
        try {
            // Iniciar la transacción
            $this->pdo->beginTransaction();

            // Verificar si el token es único antes de proceder
        if (!$this->verificarUnicidadToken($token)) {
            throw new Exception("Token duplicado.");
        }

            // Verificar si la cuenta existe y obtener el saldo actual (bloqueando la fila con FOR UPDATE)
            $stmt = $this->pdo->prepare("SELECT saldo FROM cuentas WHERE id = :cuenta_id FOR UPDATE");
            $stmt->execute(['cuenta_id' => $cuenta_id]);
            $saldo = $stmt->fetchColumn();

            if ($saldo === false) {
                throw new SoapFault("Client", "Nivel 2: Error - Cuenta no encontrada.");
            }

            // Insertar la transacción de depósito
            $stmt = $this->pdo->prepare("
                INSERT INTO transacciones (cuenta_id, tipo_transaccion, monto, token) 
                VALUES (:cuenta_id, 'deposito', :monto, :token)
            ");
            $stmt->execute(['cuenta_id' => $cuenta_id, 'monto' => $monto, 'token' => $token]);

            // Actualizar el saldo de la cuenta
            $stmt = $this->pdo->prepare("UPDATE cuentas SET saldo = saldo + :monto, actualizado_en = NOW() WHERE id = :cuenta_id");
            $stmt->execute(['monto' => $monto, 'cuenta_id' => $cuenta_id]);

            // Confirmar la transacción
            $this->pdo->commit();

            // Guardar en el historial de transacciones exitosas
            $stmt = $this->pdo->prepare("
                INSERT INTO historial_transacciones (cuenta_id, monto, token, estado) 
                VALUES (:cuenta_id, :monto, :token, 'Realizado')
            ");
            $stmt->execute([
                'cuenta_id' => $cuenta_id,
                'monto' => $monto,
                'token' => $token
            ]);

            // Actualizar el estado de la petición en la tabla peticiones
        $this->actualizarEstadoPeticion($token, 'Realizado');

            return true;
        } catch (PDOException $e) {
            // En caso de error, revertir la transacción
            $this->pdo->rollBack();

            // Insertar el error en el log de errores
            $stmt = $this->pdo->prepare("
                INSERT INTO log_errores (peticion_id, tipo, cuenta_id, mensaje_error) 
                VALUES (:peticion_id, 'depositar', :cuenta_id, :mensaje_error)
            ");
            $stmt->execute([
                'peticion_id' => $token, // Usamos el token como identificador de la transacción
                'cuenta_id' => $cuenta_id,
                'mensaje_error' => $e->getMessage()
            ]);

            throw new SoapFault("Server", "Error al realizar el depósito: " . $e->getMessage());
        }
    }

    // Insertar una transacción de retiro
    public function retirar($cuenta_id, $monto, $token) {
        try {
            // Iniciar la transacción
            $this->pdo->beginTransaction();

            // Verificar si la cuenta tiene saldo suficiente
            $stmt = $this->pdo->prepare("SELECT saldo FROM cuentas WHERE id = :cuenta_id FOR UPDATE");
            $stmt->execute(['cuenta_id' => $cuenta_id]);
            $saldo = $stmt->fetchColumn();

            if ($saldo === false) {
                throw new SoapFault("Client", "Nivel 2: Error - Cuenta no encontrada.");
            }

            // Validar si hay suficiente saldo
            if ($saldo < $monto) {
                throw new SoapFault("Client", "Nivel 2: Error - Fondos insuficientes. Saldo disponible: $saldo.");
            }

            // Proceder con el retiro
            $stmt = $this->pdo->prepare("
                INSERT INTO transacciones (cuenta_id, tipo_transaccion, monto, token) 
                VALUES (:cuenta_id, 'retiro', :monto, :token)
            ");
            $stmt->execute(['cuenta_id' => $cuenta_id, 'monto' => $monto, 'token' => $token]);

            // Actualizar el saldo de la cuenta
            $stmt = $this->pdo->prepare("UPDATE cuentas SET saldo = saldo - :monto, actualizado_en = NOW() WHERE id = :cuenta_id");
            $stmt->execute(['monto' => $monto, 'cuenta_id' => $cuenta_id]);

            // Confirmar la transacción
            $this->pdo->commit();

            // Guardar en el historial de transacciones exitosas
            $stmt = $this->pdo->prepare("
                INSERT INTO historial_transacciones (cuenta_id, monto, token, estado) 
                VALUES (:cuenta_id, :monto, :token, 'Realizado')
            ");
            $stmt->execute([
                'cuenta_id' => $cuenta_id,
                'monto' => $monto,
                'token' => $token
            ]);

            // Actualizar el estado de la petición en la tabla peticiones
        $this->actualizarEstadoPeticion($token, 'Realizado');

            return true;
        } catch (PDOException $e) {
            // En caso de error, revertir la transacción
            $this->pdo->rollBack();

            // Insertar el error en el log de errores
            $stmt = $this->pdo->prepare("
                INSERT INTO log_errores (peticion_id, tipo, cuenta_id, mensaje_error) 
                VALUES (:peticion_id, 'retirar', :cuenta_id, :mensaje_error)
            ");
            $stmt->execute([
                'peticion_id' => $token,
                'cuenta_id' => $cuenta_id,
                'mensaje_error' => $e->getMessage()
            ]);

            throw new SoapFault("Server", "Error al realizar el retiro: " . $e->getMessage());
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
        $stmt = $this->pdo->prepare("
            UPDATE peticiones 
            SET estado = 'Realizado' 
            WHERE peticion_id = :peticion_id
        ");
        $stmt->execute(['peticion_id' => $peticion_id]);
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

// Configuración del servidor SOAP
$server = new SoapServer(null, ['uri' => "urn:DatabaseService"]);
$server->setClass('DatabaseService');
$server->handle();
?>
