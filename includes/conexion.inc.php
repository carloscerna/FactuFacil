<?php
// includes/conexion.inc.php

// Asegurarse de que el nombre de la sesión esté configurado
if (session_name() !== 'FactuFacil') {
    session_name('FactuFacil');
}

// Iniciar sesión si aún no está iniciada.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Variables de conexión, seguras para el entorno de desarrollo y producción
$host = 'localhost';
$port = 5432;
// Si la base de datos no está en la sesión, usa una por defecto.
$database = $_SESSION['dbname'] ?? 'sistema_facturacion'; 
$username = getenv('DB_USERNAME') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: 'Orellana';

global $dblink;
$dblink = null; // Inicializar a null

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database";
    $dblink = new PDO($dsn, $username, $password);
    $dblink->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $dblink->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $dblink->exec("SET NAMES 'utf8'");
} catch (PDOException $e) {
    // Manejo de errores de conexión. En producción, solo se registra.
    error_log("Error de conexión a la base de datos: " . $e->getMessage());
    $dblink = null;
}
?>