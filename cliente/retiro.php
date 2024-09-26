<?php
session_start();
error_log("Session ID: " . session_id());

// Detect if this is a SOAP request
if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'text/xml') !== false) {
    class ClienteService {
        public function notificarEstadoTransaccion($token, $status, $mensaje) {
            session_start();
            // Store the notification in the session
            $_SESSION['notificacion'] = [
                'token' => $token,
                'status' => $status,
                'mensaje' => $mensaje
            ];
            error_log("Notification stored in session: " . print_r($_SESSION['notificacion'], true));
        }
    }

    // SOAP server handling
    try {
        $server = new SoapServer(null, ['uri' => "urn:ClienteService"]);
        $server->setClass('ClienteService');
        $server->handle();
    } catch (SoapFault $e) {
        error_log("SOAP Error: " . $e->getMessage());
    }
    exit();
}

// Regular HTTP request handling for withdrawal functionality
if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit();
}

$message = '';
$message_type = '';

// Include the function to get the SOAP client
include 'soapClient.php';

try {
    // Get the SOAP client using the function
    $client = getSoapClient();

    // Get the customer's accounts
    $cuentas = $client->getCuentasCliente($_SESSION['login']);
} catch (SoapFault $e) {
    if (strpos($e->getMessage(), 'Could not connect to host') !== false) {
        $message = "Nivel 1: Error - No se pudo conectar al servidor.";
        $message_type = 'error';
    } else {
        $message = "Detalle: " . $e->getMessage();
        $message_type = 'error';
    }
}

// Logic to handle withdrawal form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['check_notificacion'])) {
    $cuenta_id = htmlspecialchars($_POST['cuenta_id']);
    $monto = htmlspecialchars($_POST['monto']);

    // Find the selected account and check the balance
    foreach ($cuentas as $cuenta) {
        if ($cuenta['id'] == $cuenta_id) {
            $saldo = $cuenta['saldo'];
            break;
        }
    }

    // Check if there is enough balance
    if ($saldo < $monto) {
        $message = "Saldo insuficiente. El saldo disponible es: $saldo.";
        $message_type = 'error';
    } else {
        $token = bin2hex(random_bytes(16));  // Generate a unique 32-character token

        try {
            // Client callback URL
            $callback_url = "http://localhost:8000/colas/cliente/retiro.php";

            // Call the SOAP service to process the withdrawal with the callback URL
            $response = $client->retirar($cuenta_id, $monto, $token, $callback_url);

            if ($response === true) {
                // Store the message in the session and redirect to avoid form resubmission
                $_SESSION['message'] = "Retiro en proceso. Token: $token";
                $_SESSION['message_type'] = 'success';
                header("Location: retiro.php");
                exit();
            } else {
                $message = $response;
                $message_type = 'error';
            }
        } catch (SoapFault $e) {
            if (strpos($e->getMessage(), 'Could not connect to host') !== false) {
                $message = "Nivel 1: Error - No se pudo conectar al servidor.";
                $message_type = 'error';
            } else {
                $message = "Detalle: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// Logic to handle notifications within the same page (AJAX requests)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['check_notificacion'])) {
    error_log("Notification check triggered");

    // Log session data
    error_log("Session data: " . print_r($_SESSION, true));

    // Check if there's a notification stored in the session
    if (isset($_SESSION['notificacion'])) {
        error_log("Notification found in session");

        // Return JSON response with the notification details
        echo json_encode([
            'status' => 'success',
            'message' => $_SESSION['notificacion']['mensaje']
        ]);

        // Clear the notification after sending it to the frontend
        unset($_SESSION['notificacion']);
        exit();
    } else {
        error_log("No notification found in session");

        // No notification found
        echo json_encode(['status' => 'none']);
        exit();
    }
}

// Show messages stored in the session
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
    <title>Retiro</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Periodically call 'retiro.php' to check if the notification has arrived
        setInterval(function() {
            $.ajax({
                url: 'retiro.php',
                method: 'POST',
                data: { check_notificacion: true },
                success: function(response) {
                    try {
                        const data = JSON.parse(response);
                        if (data.status === 'success') {
                            alert('Notificación: ' + data.message);
                            $('#notification-area').html('<p>' + data.message + '</p>');
                        } else {
                            console.log('No new notifications');
                        }
                    } catch (error) {
                        console.error('Error parsing JSON response: ' + error);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error checking notifications: ' + error);
                }
            });
        }, 5000);  // Check every 5 seconds

        // Manual notification check
        function verificarNotificacionManual() {
            $.ajax({
                url: 'retiro.php',
                method: 'POST',
                data: { check_notificacion: true },
                success: function(response) {
                    try {
                        const data = JSON.parse(response);
                        if (data.status === 'success') {
                            alert('Notificación: ' + data.message);
                            $('#notification-area').html('<p>' + data.message + '</p>');
                        } else {
                            alert('No hay nuevas notificaciones.');
                        }
                    } catch (error) {
                        console.error('Error parsing JSON response: ' + error);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error checking notification manually: ' + error);
                }
            });
        }
    </script>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="logout.php">Cerrar Sesión</a></li>
        </ul>
    </nav>

    <div class="container" id="container">
        <div class="left-side expanded" id="left-side">
            <h2>Bienvenido, <?= htmlspecialchars($_SESSION['login']) ?>!</h2>
            <p>Realiza un retiro en una de tus cuentas.</p>
            <button class="logout-button" onclick="window.location.href='logout.php'">Cerrar Sesión</button>
        </div>

        <div class="right-side visible" id="right-side">
            <div class="form-container visible">
                <h1>Retiro</h1>

                <!-- Show success or error messages -->
                <?php if ($message): ?>
                    <div class="message <?= $message_type ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <!-- Withdrawal form -->
                <form action="retiro.php" method="POST">
                    <label for="cuenta_id">Seleccionar Cuenta:</label>
                    <select id="cuenta_id" name="cuenta_id" required>
                        <?php foreach ($cuentas as $cuenta): ?>
                            <option value="<?= $cuenta['id'] ?>">ID: <?= $cuenta['id'] ?> - <?= $cuenta['tipo_cuenta'] ?> - Saldo: <?= $cuenta['saldo'] ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="monto">Monto a Retirar:</label>
                    <input type="number" id="monto" name="monto" step="0.01" min="1" required>

                    <button type="submit">Realizar Retiro</button>
                </form>

                <!-- Manual notification check button -->
                <button id="check-notification-btn">Check Notification</button>
                <script>
                    document.getElementById('check-notification-btn').addEventListener('click', function() {
                        verificarNotificacionManual();
                    });
                </script>

                <!-- Notification area -->
                <div id="notification-area"></div>
            </div>
        </div>
    </div>
</body>
</html>
