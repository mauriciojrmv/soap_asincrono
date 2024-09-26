<?php

require 'vendor/autoload.php';  // Incluimos las dependencias de Composer
require 'bd.php';  // Incluye tu archivo de base de datos que contiene las funciones
use Spatie\Async\Pool;

$bd = new DatabaseService();

// 1. Obtener las peticiones pendientes
$peticionesPendientes = $bd->obtenerPeticionesPendientes();

if (empty($peticionesPendientes)) {
    echo "No hay peticiones pendientes por procesar.\n";
    exit;
}

// Crear un pool de hilos con una concurrencia específica (por ejemplo, 5 hilos simultáneos)
$pool = Pool::create()->concurrency(5); // Establece el número de hilos simultáneos

// 2. Procesar las peticiones en paralelo
foreach ($peticionesPendientes as $peticion) {
    $pool->add(function () use ($peticion, $bd) {
        try {
            // Procesar la transacción según el tipo
            if ($peticion['tipo'] === 'deposito') {
                $bd->procesarDeposito($peticion['cuenta_id'], $peticion['monto'], $peticion['token'], $peticion['callback_url']);
            } elseif ($peticion['tipo'] === 'retiro') {
                $bd->procesarRetiro($peticion['cuenta_id'], $peticion['monto'], $peticion['token'], $peticion['callback_url']);
            }

            // Eliminar la petición procesada de la tabla `peticiones`
            $bd->eliminarPeticion($peticion['token']);

            echo "Token: {$peticion['token']} realizada correctamente y eliminada de la cola.\n";
        } catch (Exception $e) {
            // Si falla, aumentar intentos y registrar en log si supera los 5 intentos
            $bd->manejarErrorPeticion($peticion['token'], $e->getMessage());
            echo "Error al procesar el token: {$peticion['token']} - {$e->getMessage()}\n";
        }
    })->catch(function (Throwable $exception) {
        // Capturar cualquier error crítico en la ejecución del hilo
        echo "Error crítico en hilo: {$exception->getMessage()}\n";
    });
}

// 3. Esperar a que todos los hilos terminen
$pool->wait();

echo "Todas las peticiones han sido procesadas.\n";

