<?php
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: login.php"); // Redirigir al login si no ha iniciado sesión
    exit();
}

$message = '';
$message_type = '';

// Incluir la función para obtener el cliente SOAP
include 'soapClient.php';

try {
    // Obtener el cliente SOAP usando la función
    $client = getSoapClient();

    // Obtener las cuentas del cliente
    $cuentas = $client->getCuentasCliente($_SESSION['login']);

} catch (SoapFault $e) {
    // Manejar error de nivel 1 cuando no se puede conectar al servidor SOAP
    if (strpos($e->getMessage(), 'Could not connect to host') !== false) {
        $message = "Nivel 1: Error - No se pudo conectar al servidor.";
        $message_type = 'error';
    } else {
        // Cualquier otro error del SOAP
        $message = "Detalle: " . $e->getMessage();
        $message_type = 'error';
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cuenta_id = htmlspecialchars($_POST['cuenta_id']);
    $monto = htmlspecialchars($_POST['monto']);

    // Buscar la cuenta seleccionada y obtener el saldo
    foreach ($cuentas as $cuenta) {
        if ($cuenta['id'] == $cuenta_id) {
            $saldo = $cuenta['saldo'];
            break;
        }
    }

    // Verificar si el saldo es suficiente antes de intentar el retiro
    if ($saldo < $monto) {
        $message = "Saldo insuficiente. El saldo disponible es: $saldo.";
        $message_type = 'error';
    } else {
        // Generar un token único aleatorio
        $token = bin2hex(random_bytes(16));  // Genera un token de 32 caracteres

        try {
            // Realizar la solicitud de retiro al servidor SOAP
            $response = $client->retirar($cuenta_id, $monto, $token);

            if ($response === true) {
                // Guardar el mensaje en la sesión y redirigir para evitar el reenvío
                $_SESSION['message'] = "Retiro realizado con éxito.";
                $_SESSION['message_type'] = 'success';
                header("Location: retiro.php"); // Redirigir a la misma página (GET)
                exit();
            } else {
                $message = $response;
                $message_type = 'error';
            }
        } catch (SoapFault $e) {
            // Manejar errores de nivel 1 y otros niveles
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

// Mostrar mensajes guardados en la sesión
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

                <!-- Mostrar mensajes de éxito o error -->
                <?php if ($message): ?>
                    <div class="message <?= $message_type ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <!-- Formulario de Retiro -->
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
            </div>
        </div>
    </div>
</body>
</html>
