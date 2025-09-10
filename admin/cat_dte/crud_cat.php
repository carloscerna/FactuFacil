<?php
// admin/cat_dte/crud_cat.php

// ... (El código existente para la sesión y la conexión a la DB) ...

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
$tabla = $_POST['tabla'] ?? $_GET['tabla'] ?? '';

// ... (El código existente para la validación de la tabla) ...

switch ($accion) {
    // NUEVA ACCIÓN: Listar los catálogos disponibles para el Select
    case 'listarCatalogoTablas':
        try {
            $sql = "SELECT codigo, descripcion FROM catalogo_dte_tablas ORDER BY descripcion";
            $stmt = $pdo->query($sql);
            $tablas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['respuesta' => true, 'tablas' => $tablas]);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al obtener catálogos de tablas.']);
        }
        break;

    // ... (El resto del código con las acciones 'listar', 'obtener', etc.) ...
}
?>