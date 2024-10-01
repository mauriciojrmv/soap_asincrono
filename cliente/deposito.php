<?php
session_start();
error_log("Session ID: " . session_id());

// Detectar si es una solicitud SOAP
if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'text/xml') !== false) {
    class ClienteService {
        public function notificarEstadoTransaccion($token, $status, $mensaje) {
            session_start();
            // Guardar la notificación en la sesión
            $_SESSION['notificacion'] = [
                'token' => $token,
                'status' => $status,
                'mensaje' => $mensaje
            ];
            error_log("Notificación almacenada en la sesión: " . print_r($_SESSION['notificacion'], true));
        }
    }

    // Manejo del servidor SOAP
    try {
        $server = new SoapServer(null, ['uri' => "urn:ClienteService"]);
        $server->setClass('ClienteService');
        $server->handle();
    } catch (SoapFault $e) {
        error_log("Error SOAP: " . $e->getMessage());
    }
    exit();
}

// Manejo de la solicitud HTTP regular para la funcionalidad de depósito
if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit();
}

$message = '';
$message_type = '';

// Incluimos el cliente SOAP
include 'soapClient.php';

try {
    // Obtenemos el cliente SOAP
    $client = getSoapClient();

    // Obtenemos las cuentas del cliente
    $cuentas = $client->getCuentasCliente($_SESSION['login']);
} catch (SoapFault $e) {
    if (strpos($e->getMessage(), 'Could not connect to host') !== false) {
        $message = "Nivel 1: Error - No se pudo conectar al servidor.";
        $message_type = 'error';
    } else {
        $message = "Nivel 2 o 3: Error - " . $e->getMessage();
        $message_type = 'error';
    }
}

// Manejo del formulario de depósito
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Datos del depósito
    $cuenta_id = htmlspecialchars($_POST['cuenta_id']);
    $monto = htmlspecialchars($_POST['monto']);
    $token = bin2hex(random_bytes(16));  // Generamos un token único

    try {
        // URL de callback
        $callback_url = "http://localhost:8000/colas/cliente/deposito.php";

        // Llamamos al servicio SOAP para encolar la transacción
        $response = $client->depositar($cuenta_id, $monto, $token, $callback_url);

        if ($response === true) {
            // Guardamos el token y otros datos en la sesión
            $_SESSION['message'] = "Su transacción ha sido encolada. El token de su transacción es: " . $token;
            $_SESSION['message_type'] = 'success';
            $_SESSION['token'] = $token; // Guardamos el token para verificar el estado luego
            
            header("Location: deposito.php");
            exit();
        } else {
            $message = $response;
            $message_type = 'error';
        }
    } catch (SoapFault $e) {
        $message = "Error al encolar la transacción: " . $e->getMessage();
        $message_type = 'error';
    }
}

// Mostrar mensajes almacenados en la sesión
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Depósito</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Función para verificar el estado de la transacción
        function verificarEstadoTransaccion(token) {
            $.ajax({
                url: 'http://localhost:8000/colas/server.php',
                method: 'POST',
                data: { token: token, action: 'verificarEstadoNotificacion' },  // Acción y token
                success: function(response) {
                    try {
                        const data = JSON.parse(response);
                        if (data.estado_notificacion === 'enviada') {
                            // Mostramos el mensaje de éxito con los datos del depósito
                            $('#notification-area').html('<p>Transacción exitosa: Se ha depositado $' + data.monto + ' a la cuenta ' + data.cuenta_id + ' el ' + data.fecha + '.</p>');
                            clearInterval(checkInterval);  // Detenemos el intervalo
                        } else {
                            console.log('Estado actual: ' + data.estado_notificacion);
                        }
                    } catch (error) {
                        console.error('Error al procesar la respuesta JSON: ' + error);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error al verificar el estado de la transacción: ' + error);
                }
            });
        }

        // Si existe un token en la sesión, iniciamos la verificación periódica
        <?php if (isset($_SESSION['token'])): ?>
            var checkInterval = setInterval(function() {
                verificarEstadoTransaccion('<?= $_SESSION['token'] ?>');
            }, 5000);  // Verificamos cada 5 segundos
        <?php endif; ?>
    </script>
</head>
<body>
    <nav class="navbar">
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="logout.php">Cerrar Sesión</a></li>
        </ul>
    </nav>

    <div class="container" id="container">
        <div class="left-side expanded" id="left-side">
            <h2>Bienvenido, <?= htmlspecialchars($_SESSION['login']) ?>!</h2>
            <p>Realiza un depósito en una de tus cuentas.</p>
            <button class="logout-button" onclick="window.location.href='logout.php'">Cerrar Sesión</button>
        </div>

        <div class="right-side visible" id="right-side">
            <div class="form-container visible">
                <h1>Depósito</h1>

                <!-- Mostrar mensajes de éxito o error -->
                <?php if ($message): ?>
                    <div class="message <?= $message_type ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <!-- Formulario de depósito -->
                <form action="deposito.php" method="POST">
                    <label for="cuenta_id">Seleccionar Cuenta:</label>
                    <select id="cuenta_id" name="cuenta_id" required>
                        <?php foreach ($cuentas as $cuenta): ?>
                            <option value="<?= $cuenta['id'] ?>">ID: <?= $cuenta['id'] ?> - <?= $cuenta['tipo_cuenta'] ?> - Saldo: <?= $cuenta['saldo'] ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="monto">Monto a Depositar:</label>
                    <input type="number" id="monto" name="monto" step="0.01" min="1" required>

                    <button type="submit">Realizar Depósito</button>
                </form>

                <!-- Área de notificaciones -->
                <div id="notification-area"></div>
            </div>
        </div>
    </div>
</body>
</html>
