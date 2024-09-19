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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tipo_cuenta = htmlspecialchars($_POST['tipo_cuenta']); // Puede ser "bolivianos" o "dólares"
    $token = md5($_SESSION['login'] . $tipo_cuenta); // Generar un token único para la cuenta

    try {
        // Obtener el cliente SOAP usando la función
    $client = getSoapClient();

        // Verificar si la cuenta ya existe
        $response = $client->crearCuenta($_SESSION['login'], $tipo_cuenta, $token);

        // Mostrar respuesta del servidor
        if ($response == "success") {
            $message = "Cuenta creada exitosamente.";
            $message_type = 'success';
        } else {
            $message = $response; // Mostrar mensaje de error del servidor (Nivel 2 o 3)
            $message_type = 'error';
        }
    } catch (SoapFault $e) {
         // Capturar el mensaje específico de XML y manejarlo como un error de nivel 2
         if (strpos($e->getMessage(), 'looks like we got no XML document') !== false) {
            $message = "Nivel 2: Error - No se recibió una respuesta válida del servidor. Intente nuevamente.";
            $message_type = 'error';
        } elseif (strpos($e->getMessage(), 'Could not connect to host') !== false) {
            // Nivel 1: Error al intentar conectarse al servidor SOAP
            $message = "Nivel 1: Error - No se pudo conectar al servidor. Intentando nuevamente en 10 segundos...";
            $message_type = 'error';
            echo "<script>
                setTimeout(function() {
                    document.forms[0].submit();
                }, 10000);
            </script>";
        } else {
            // Error de Nivel 2 o 3
            $message = "Detalle: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Cuenta</title>
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
            <p>Aqui puedes crear tus cuentas bancarias.</p>
            <button class="logout-button" onclick="window.location.href='logout.php'">Cerrar Sesión</button>
        </div>

        <div class="right-side hidden" id="right-side">
            <div class="form-container visible" id="form-container">
                <h1>Crear cuenta bancaria</h1>

            <!-- Área de notificaciones para mostrar errores o mensajes de éxito -->
            <?php if ($message): ?>
                <div class="message <?= $message_type ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Formulario para crear una cuenta -->
            <form action="crear_cuenta.php" method="POST">
                <label for="tipo_cuenta">Tipo de Cuenta:</label>
                <select id="tipo_cuenta" name="tipo_cuenta" required>
                    <option value="bolivianos">Bolivianos</option>
                    <option value="dolares">Dólares</option>
                </select>

                <button type="submit">Crear Cuenta</button>
            </form>
        </div>
    </div>
    </div>
</body>
</html>
