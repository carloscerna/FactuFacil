<?php
// admin/contabilidad/configuracion_contable/crud_config.php

session_name('FactuFacil');
session_start();
header('Content-Type: application/json');

// Validación de sesión
if (empty($_SESSION['codigo_institucion'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida. Recargue la página.']);
    exit();
}

$path_root = trim($_SERVER['DOCUMENT_ROOT']);
require_once($path_root . "/FactuFacil/includes/mainFunctions_.php");

global $dblink; 
$db = $dblink;
$codigo_institucion_activa = $_SESSION['codigo_institucion']; // Esto vale 'E0001'

$accion = isset($_POST['accion']) ? $_POST['accion'] : 'listar';

try {
    switch ($accion) {
        case 'listar':
            $query = "
                SELECT 
                    conf.id,
                    conf.clave_mapeo,
                    conf.cuenta_id,
                    cc.codigo AS cuenta_codigo,
                    cc.nombre AS cuenta_nombre
                FROM configuracion_contable conf
                LEFT JOIN cuentas_contables cc ON conf.cuenta_id = cc.id
                WHERE conf.codigo_institucion = :codigo_inst
                ORDER BY conf.clave_mapeo ASC
            ";
            $stmt = $db->prepare($query);
            // CORRECCIÓN AQUÍ: Usamos PARAM_STR porque 'E0001' es texto
            $stmt->bindParam(':codigo_inst', $codigo_institucion_activa, PDO::PARAM_STR);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($data);
            break;

        case 'guardar':
            $id = isset($_POST['config_id']) ? intval($_POST['config_id']) : 0;
            $clave_mapeo = isset($_POST['clave_mapeo']) ? strtoupper(trim($_POST['clave_mapeo'])) : '';
            $cuenta_id = isset($_POST['cuenta_id']) ? intval($_POST['cuenta_id']) : 0;

            if (empty($clave_mapeo) || $cuenta_id <= 0) {
                throw new Exception("Faltan datos obligatorios.");
            }

            if ($id > 0) {
                // EDICIÓN
                $query = "UPDATE configuracion_contable SET cuenta_id = :cuenta_id WHERE id = :id AND codigo_institucion = :codigo_inst";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':cuenta_id', $cuenta_id, PDO::PARAM_INT);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                // CORRECCIÓN AQUÍ: PARAM_STR
                $stmt->bindParam(':codigo_inst', $codigo_institucion_activa, PDO::PARAM_STR);
                $stmt->execute();
                $mensaje = "Mapeo actualizado correctamente.";
            } else {
                // NUEVO
                $checkQuery = "SELECT COUNT(*) FROM configuracion_contable WHERE clave_mapeo = :clave AND codigo_institucion = :codigo_inst";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->bindParam(':clave', $clave_mapeo, PDO::PARAM_STR);
                // CORRECCIÓN AQUÍ: PARAM_STR
                $checkStmt->bindParam(':codigo_inst', $codigo_institucion_activa, PDO::PARAM_STR);
                $checkStmt->execute();
                
                if ($checkStmt->fetchColumn() > 0) {
                    throw new Exception("La clave '$clave_mapeo' ya existe.");
                }

                $query = "INSERT INTO configuracion_contable (clave_mapeo, cuenta_id, codigo_institucion) VALUES (:clave, :cuenta_id, :codigo_inst)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':clave', $clave_mapeo, PDO::PARAM_STR);
                $stmt->bindParam(':cuenta_id', $cuenta_id, PDO::PARAM_INT);
                // CORRECCIÓN AQUÍ: PARAM_STR
                $stmt->bindParam(':codigo_inst', $codigo_institucion_activa, PDO::PARAM_STR);
                $stmt->execute();
                $mensaje = "Nuevo mapeo agregado exitosamente.";
            }

            echo json_encode(['success' => true, 'message' => $mensaje]);
            break;

        case 'eliminar':
            $id_eliminar = isset($_POST['id']) ? intval($_POST['id']) : 0;
            if ($id_eliminar <= 0) { throw new Exception("ID no válido."); }

            $query = "DELETE FROM configuracion_contable WHERE id = :id AND codigo_institucion = :codigo_inst";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id_eliminar, PDO::PARAM_INT);
            // CORRECCIÓN AQUÍ: PARAM_STR
            $stmt->bindParam(':codigo_inst', $codigo_institucion_activa, PDO::PARAM_STR);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                 echo json_encode(['success' => true, 'message' => 'Configuración eliminada.']);
            } else {
                 throw new Exception("No se pudo eliminar el registro.");
            }
            break;

        default:
            throw new Exception("Acción no reconocida.");
    }

} catch (Exception $e) {
    error_log("Error ConfigContable: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>