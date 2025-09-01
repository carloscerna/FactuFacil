<?php
// admin/personal/crud_personal.php

session_name('FactuFacil');
session_start();

if (empty($_SESSION['userNombre'])) {
    header('Content-Type: application/json');
    echo json_encode(['respuesta' => false, 'mensaje' => 'Sesión no válida.']);
    exit();
}

include('includes/conexion.inc.php');
$pdo = $dblink;
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

switch ($accion) {
    case 'listarPersonal':
        try {
            $sql = "SELECT p.id_personal, p.nombres, p.apellidos, p.dui, p.telefono_celular,
                       cp.descripcion AS cargo, p.correo_electronico, p.fecha_ingreso,
                       CASE WHEN p.codigo_estatus = '01' THEN 'Activo' ELSE 'Inactivo' END AS estatus,
                       'acciones' AS acciones
                    FROM personal p
                    INNER JOIN catalogo_cargo cp ON p.codigo_cargo = cp.codigo_cargo
                    ORDER BY p.apellidos, p.nombres";

            $stmt = $pdo->query($sql);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Devolver los datos en el formato que DataTables espera
            echo json_encode(['data' => $data]);
        } catch (PDOException $e) {
            echo json_encode(['data' => []]);
        }
        break;
        
    case 'obtenerPersonal':
        // Lógica para obtener los datos de un solo empleado (para el modal de edición)
        $id = $_POST['id_personal'];
        try {
            $stmt = $pdo->prepare("SELECT * FROM personal WHERE id_personal = ?");
            $stmt->execute([$id]);
            $personal = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['respuesta' => true, 'personal' => $personal]);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al obtener datos.']);
        }
        break;
        
    case 'crearActualizar':
        // Lógica para crear o actualizar un registro de personal
        $id = $_POST['id_personal'];
        $nombres = $_POST['nombres'];
        $apellidos = $_POST['apellidos'];
        // ... (resto de los campos del formulario)
        
        if (empty($id)) {
            // Lógica para crear un nuevo registro
            $sql = "INSERT INTO personal (nombres, apellidos, ...) VALUES (?, ?, ...)";
        } else {
            // Lógica para actualizar un registro existente
            $sql = "UPDATE personal SET nombres = ?, apellidos = ?, ... WHERE id_personal = ?";
        }
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombres, $apellidos, ...]);
            echo json_encode(['respuesta' => true, 'mensaje' => 'Guardado exitosamente.']);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al guardar: ' . $e->getMessage()]);
        }
        break;

    case 'eliminar':
        // Lógica para eliminar un registro
        $id = $_POST['id_personal'];
        try {
            $stmt = $pdo->prepare("DELETE FROM personal WHERE id_personal = ?");
            $stmt->execute([$id]);
            echo json_encode(['respuesta' => true, 'mensaje' => 'Eliminado exitosamente.']);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al eliminar.']);
        }
        break;
        
    case 'obtenerCatalogos':
        // Esta acción es clave para poblar los dropdowns del formulario
        $catalogos = [];
        $catalogos['genero'] = $pdo->query("SELECT codigo_genero AS codigo, descripcion FROM catalogo_genero")->fetchAll(PDO::FETCH_ASSOC);
        $catalogos['estado_civil'] = $pdo->query("SELECT codigo_estado_civil AS codigo, descripcion FROM catalogo_estado_civil")->fetchAll(PDO::FETCH_ASSOC);
        // ... (obtener el resto de los catálogos)
        
        echo json_encode(['respuesta' => true, 'catalogos' => $catalogos]);
        break;

    default:
        echo json_encode(['respuesta' => false, 'mensaje' => 'Acción no válida.']);
        break;
}
?>