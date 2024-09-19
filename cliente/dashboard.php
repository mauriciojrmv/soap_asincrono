<?php
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: login.php"); // Redirigir al login si no ha iniciado sesión
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
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
            <p>Accede a los servicios disponibles para gestionar tus cuentas.</p>
            <button class="logout-button" onclick="window.location.href='logout.php'">Cerrar Sesión</button>
        </div>

        <div class="right-side visible" id="right-side">
            <div class="form-container visible">
                <h1>Servicios Disponibles</h1>
                <ul class="service-list">
                    <li><button class="service-button" onclick="window.location.href='/colas/cliente/crear_cuenta.php'">Crear Cuenta</button></li>
                    <li><button class="service-button" onclick="window.location.href='/colas/cliente/deposito.php'">Depósito</button></li>
                    <li><button class="service-button" onclick="window.location.href='/colas/cliente/retiro.php'">Retiro</button></li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>