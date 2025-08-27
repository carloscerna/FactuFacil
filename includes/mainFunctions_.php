<?php
// Si la sesión no ha sido iniciada en el script principal que incluye este archivo, iníciala aquí.
// Sin embargo, es mejor que session_start() se llame al principio de cada script PHP que necesite sesiones (index.php, login.php).
// if (session_status() == PHP_SESSION_NONE) {
//     session_start();
// }

// Asegurarse de que el nombre de la sesión esté configurado si es necesario para demoUI
if (session_name() !== 'FactuFacil') {
    session_name('FactuFacil');
}

// Habilitar la visualización de errores solo en entornos de desarrollo.
// En producción, es mejor registrar los errores y no mostrarlos al usuario.
ini_set("display_errors", 1);
ini_set("display_startup_errors", 1);
error_reporting(E_ALL);

// Variables para la conexión
$host = 'localhost';
$port = 5432;
// Asegúrate de que $_SESSION['dbname'] esté siempre disponible antes de incluir este archivo
// o maneja el error de forma segura para el usuario final.
//print $_SESSION['dbname'] ?? 'No database name set in session.';

$database = isset($_SESSION['dbname']) ? $_SESSION['dbname'] : 'sistema_facturacion'; // Considera un nombre de BD por defecto para casos de error o desarrollo

//print 'Nombre base de dastos: ' . $database;
// Obtener credenciales de la base de datos.
// Es crucial que estas variables de entorno (DB_USERNAME, DB_PASSWORD) estén configuradas en tu servidor.
// Si no están configuradas, los fallbacks 'postgres' y 'Orellana' se usarán.
// Para un entorno de producción, evita hardcodear contraseñas incluso como fallback.
$username = getenv('DB_USERNAME') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: 'Orellana';

// Inicializar la variable de conexión global, si no existe o se necesita reestablecer.
// La variable $dblink ya es global por defecto cuando se define en el ámbito global.
global $dblink;
global $errorDbConexion; // Declarar como global si va a ser accedida globalmente

$errorDbConexion = false; // Reiniciar el flag de error al intentar conectar

// Reestablecer la conexión si no está establecida o ha fallado previamente.
// Esto es útil si mainFunctions_.php se incluye múltiples veces.
if (!isset($dblink) || $dblink === null) {
    try {
        // Construir DSN y crear objeto PDO con manejo de excepciones habilitado
        $dsn = "pgsql:host=$host;port=$port;dbname=$database";
        $dblink = new PDO($dsn, $username, $password);
        $dblink->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $dblink->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // Desactivar emulación para prepared statements reales
        // Establecer el juego de caracteres a UTF-8 para evitar problemas con tildes y eñes
        $dblink->exec("SET NAMES 'utf8'");
    } catch (PDOException $e) {
        $errorDbConexion = true; // Establecer el flag de error
        // En un entorno de producción, registra este error en un log y no lo muestres directamente al usuario.
        // echo "Connection failed: " . $e->getMessage(); // Descomentar solo para depuración
        // Para AJAX, podrías devolver un JSON de error aquí
        // Por ejemplo: echo json_encode(['respuesta' => false, 'mensaje' => 'Error de conexión a la base de datos.']); exit();
        // O simplemente dejar que la variable $errorDbConexion sea verificada por el script que lo incluye.
        // Nota: Si este script es incluido por otros y detiene la ejecución, asegúrate de que el script llamador
        // pueda manejar la salida o la detención.
    }
}
?>