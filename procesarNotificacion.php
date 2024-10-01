<?php

require 'vendor/autoload.php';  // Incluimos las dependencias de Composer
require 'bd.php';  // Incluye tu archivo de base de datos que contiene las funciones
use Spatie\Async\Pool;

$bd = new DatabaseService();

$limiteTransacciones = 300;
// Obtener las transacciones pendientes de notificación
$transaccionesPendientes = $bd->obtenerTransaccionesPendientesNotificacion($limiteTransacciones);

if (empty($transaccionesPendientes)) {
    echo "No hay transacciones pendientes de notificacion.\n";
    exit;
}

// Crear un pool de hilos para procesar notificaciones con concurrencia de 3 (puedes ajustar el número)
$pool = Pool::create()->concurrency(3);

// Procesar notificaciones en paralelo
foreach ($transaccionesPendientes as $transaccion) {
    $pool->add(function () use ($transaccion, $bd) {
        try {
            // Usar token
            $token = $transaccion['token'];
            if (!$token) {
                throw new Exception("Token no encontrado.");
            }
    
            $callback_url = $transaccion['callback_url'];
            $status = 'success';  // Asumimos éxito
            $mensaje = "Su transaccion con token $token fue exitosa.";
    
            // Enviar la notificación
            $bd->enviarNotificacionCliente($callback_url, $token, $status, $mensaje);
            error_log("Notificacion enviada para token: {$token} al cliente {$callback_url}");
    
            // Marcar la transacción como notificada
            $bd->marcarNotificacionEnviada($token);
            
            echo "Notificacion enviada para la transaccion con token: {$token}.\n";
        } catch (Exception $e) {
            // Manejar el error en la notificación
            $bd->manejarErrorNotificacion($token, $e->getMessage());
            echo "Error al enviar notificación para la transacción con token: {$token} - {$e->getMessage()}\n";
        }
    });
}

// Esperar a que todos los hilos terminen
$pool->wait();

echo "Todas las notificaciones han sido procesadas.\n";
