<?php
//session_name('FactuFacil');
//session_start();
header('Content-Type: application/json');

// Validar sesión
if (empty($_SESSION['codigo_institucion'])) {
    echo json_encode([]); // Devolver array vacío si no hay sesión
    exit();
}

$path_root = trim($_SERVER['DOCUMENT_ROOT']);
// Asegúrate de que esta ruta sea correcta para tu proyecto
include($path_root."/FactuFacil/includes/mainFunctions_.php"); 

$db = $dblink;
$codigo_institucion_activa = $_SESSION['codigo_institucion'];

try {
    // Consulta para obtener solo cuentas de último nivel (movimiento)
    // Asumo que tienes un campo 'nivel' o 'tipo' para diferenciar cuentas de mayor y de movimiento.
    // SI NO LO TIENES, usa la consulta comentada abajo.
    /*
    $query = "SELECT id, codigo, nombre 
              FROM cuentas_contables 
              WHERE codigo_institucion = :codigo_inst AND tipo_cuenta = 'movimiento'
              ORDER BY codigo ASC";
    */

    // Consulta genérica (trae todas las cuentas, ajusta según tu tabla)
    $query = "SELECT id, codigo, nombre 
              FROM cuentas_contables 
              WHERE codigo_institucion = :codigo_inst 
              ORDER BY codigo ASC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':codigo_inst', $codigo_institucion_activa, PDO::PARAM_INT);
    $stmt->execute();
    $cuentas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($cuentas);

} catch (Exception $e) {
    error_log("Error en get_cuentas.php: " . $e->getMessage());
    echo json_encode([]); // En caso de error, devolver array vacío
}
?>