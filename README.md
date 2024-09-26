# Sistemas-distribuidos

# Implementacion de un sistema de registro con metodo SOAP y manejo de tokens

Este proyecto implementa un sistema de registro distribuido utilizando el metodo SOAP en php, generando tokens unicos para cada creacion de cliente, creacion de cuentas, manejo de transacciones y manejando errores en tres niveles (conexion, server, base de datos). A continuacion se describe la instalacion, la arquitectura del proyecto y funcionamiento de cada componente.

## Instalacion 

### Requisitos previos
1. **PHP**: Asegurate de tener instalado php. Asegurate que el servicio Apache este corriendo
2. **XAMPP**: Necesario para ejecutar MySQL. Asegurate que el servicio MySQL este corriendo
3. **Python**: Necesario para ejecutar las interfaces de los hilos
4. **Composer**: Necesario para usar las dependencias necesarias

## Arquitectura del proyecto
El proyecto esta dividido en tres componentes principales:
# 1. index.php, styles.css, login.php, dashboard.php, logout.php, crear_cuenta.php, deposito.php, retiro.php, soapClient.php
Estos archivos actuan como intermediario e interfaz html (el formulario que llena el usuario) que conecta con el servidor SOAP.

Generacion de tokens:

Un token unico es generado utilizando los datos del formulario mediante un hash MD5. Esto asegura que cada transaccion sea unica y evita duplicados.

para las transacciones estamos usando un token bin2hex que Genera un token de 32 caracteres

Envio de Datos:

Los datos del formulario y el token generados se envian al servidor mediante una solicitud POST.

Maneja los errores de conexion y un timeout para volver a hacer intento de conexion en creacion de cuentas.

No manejamos timeout para transacciones por motivos de seguridad.

# 2. server.php, conectionpool.php, hilos.php, Peticiones.php
Este archivo es el servidor principal que maneja las solicitudes provenientes del cliente.

Valida que todos los campos requeridos esten presentes en la solicitud.
Se conecta al server soap BD donde se manejaran la introduccion y recuperacion de datos.

Maneja todas las funciones requeridas por los servicios

Si la conexion con la base de datos no existe devuelve un mensaje de error de conexion a la base de datos.

Se implementa el manejo de errores en nivel 2

# 3. bd.php
Este archivo es el servidor de base de datos que maneja la insercion y obtencion de datos de la bd.

Maneja errores de nivel 3

Conexion a la base de datos:

Utiliza MySQL para conectarse a la base de datos. los credenciales deben editarse en bd.php

## Creacion de la Base de datos 
Ejecuta las siguientes sentencias SQL para crear la base de datos y la tabla:

-- Crear base de datos si no existe
CREATE DATABASE IF NOT EXISTS banco_db;
USE banco_db;

-- Crear tabla clientes
CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido_paterno VARCHAR(100) NOT NULL,
    apellido_materno VARCHAR(100) NOT NULL,
    numero_carnet VARCHAR(20) NOT NULL UNIQUE,
    fecha_nacimiento DATE NOT NULL,
    sexo ENUM('M', 'F') NOT NULL,
    lugar_nacimiento VARCHAR(100) NOT NULL,
    estado_civil ENUM('S', 'C', 'D', 'V') NOT NULL,
    profesion VARCHAR(100) NOT NULL,
    domicilio VARCHAR(255) NOT NULL,
    login VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Crear tabla cuentas
CREATE TABLE IF NOT EXISTS cuentas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    login VARCHAR(100) NOT NULL,
    tipo_cuenta ENUM('bolivianos', 'dolares') NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    saldo DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (login) REFERENCES clientes(login) ON DELETE CASCADE
);

-- Crear tabla transacciones
CREATE TABLE IF NOT EXISTS transacciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cuenta_id INT NOT NULL,
    tipo_transaccion ENUM('deposito', 'retiro') NOT NULL,
    monto DECIMAL(10, 2) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    estado_notificacion VARCHAR(20) DEFAULT 'pendiente',
    callback_url VARCHAR(255) NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cuenta_id) REFERENCES cuentas(id) ON DELETE CASCADE
);

-- Crear tabla de log de errores
CREATE TABLE IF NOT EXISTS log_errores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    peticion_id VARCHAR(255) NOT NULL,
    tipo VARCHAR(50),
    cuenta_id INT,
    mensaje_error TEXT,
    fecha_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cuenta_id) REFERENCES cuentas(id) ON DELETE CASCADE
);

-- Crear tabla de peticiones (cola de transacciones)
CREATE TABLE IF NOT EXISTS peticiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('deposito', 'retiro') NOT NULL,
    cuenta_id INT NOT NULL,
    monto DECIMAL(10, 2) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    callback_url VARCHAR(255) NOT NULL,
    estado ENUM('Pendiente', 'Realizado', 'Error') DEFAULT 'Pendiente',
    intentos INT DEFAULT 0,
    fecha_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);




### En caso de equivocarse y querer eliminar alguna tabla
USE banco_db;

-- Desactivar las restricciones de claves foráneas temporalmente para eliminar las tablas
SET FOREIGN_KEY_CHECKS = 0;

-- Eliminar tablas
DROP TABLE IF EXISTS transacciones;
DROP TABLE IF EXISTS cuentas;
DROP TABLE IF EXISTS clientes;
DROP TABLE IF EXISTS historial_transacciones;
DROP TABLE IF EXISTS log_errores;
DROP TABLE IF EXISTS peticiones;

-- Activar nuevamente las restricciones de claves foráneas
SET FOREIGN_KEY_CHECKS = 1;


# Modo de ejecucion:
PC1, PC2, PC3: Si se implementa en diferentes PCs, se debe cambiar la IP del servidor y puerto al cual se conectara cada cliente.

Para la PC1 se cambia la ruta y puerto de direccionamiento hacia el server en **soapClient.php**

Para la PC2 se cambia el direccionamiento hacia la bd en **conectionpool.php**

PC3 hacemos las configuraciones pertinentes para la conexion a la base de datos en **bd.php**

# Ejecucion del Proyecto

Se debe ejecutar el XAMPP y asegurarse que tengamos activa la extension Soap en nuestro apache. esto se activa en **config>php.ini>** aqui se debe buscar la linea **;extension=soap**  y quitar el **";"**

despues debemos asignar la ip asignada a nuestra pc por la red a nuestro APACHE que sera donde se dirigira el trafico

**config>http.conf**  aqui se debe buscar la linea listen e introducimos nuestra ip y puerto que ocupamos en la red.

**composer install**    para instalar las dependencias

## para ejecutar los hilos 

**python cola_interface.py**

**python notification_interface.py**




