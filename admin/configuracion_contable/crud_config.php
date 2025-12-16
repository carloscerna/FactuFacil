<?php
session_name('FactuFacil');
//session_start();
// Establecer cabecera para respuesta JSON
header('Content-Type: application/json');

// Validar sesión y permisos (crítico para seguridad)
if (empty($_SESSION['codigo_institucion'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida. Recargue la página.']);
    exit();
}

$path_root = trim($_SERVER['DOCUMENT_ROOT']);
require_once($path_root . "/FactuFacil/includes/mainFunctions_.php");

// Usar la conexión global $dblink (asegúrate de que así se llame en tu mainFunctions_.php)
$db = $dblink;
$codigo_institucion_activa = $_SESSION['codigo_institucion'];

// Determinar la acción a realizar (por defecto 'listar' si no viene nada)
$accion = isset($_POST['accion']) ? $_POST['accion'] : 'listar';

try {
    switch ($accion) {
        // --- CASO 1: LISTAR (Para llenar la DataTable) ---
        case 'listar':
            // Consulta JOIN para obtener los datos del mapeo Y los detalles de la cuenta asociada
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
            $stmt->bindParam(':codigo_inst', $codigo_institucion_activa, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Devolver los datos directamente como un array JSON
            echo json_encode($data);
            break;

        // --- CASO 2: GUARDAR (Agregar o Editar) ---
        case 'guardar':
            $id = isset($_POST['config_id']) ? intval($_POST['config_id']) : 0;
            // Convertir la clave a mayúsculas y quitar espacios
            $clave_mapeo = isset($_POST['clave_mapeo']) ? strtoupper(trim($_POST['clave_mapeo'])) : '';
            $cuenta_id = isset($_POST['cuenta_id']) ? intval($_POST['cuenta_id']) : 0;

            // Validaciones del servidor
            if (empty($clave_mapeo) || $cuenta_id <= 0) {
                throw new Exception("Faltan datos obligatorios (Clave o Cuenta ID).");
            }

            if ($id > 0) {
                // --- MODO EDICIÓN ---
                // Solo actualizamos la cuenta_id, la clave no se debería cambiar
                $query = "UPDATE configuracion_contable SET cuenta_id = :cuenta_id WHERE id = :id AND codigo_institucion = :codigo_inst";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':cuenta_id', $cuenta_id, PDO::PARAM_INT);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->bindParam(':codigo_inst', $codigo_institucion_activa, PDO::PARAM_INT);
                $stmt->execute();
                $mensaje = "Mapeo actualizado correctamente.";
            } else {
                // --- MODO AGREGAR ---
                // Primero, verificar si la clave ya existe para esta institución
                $checkQuery = "SELECT COUNT(*) FROM configuracion_contable WHERE clave_mapeo = :clave AND codigo_institucion = :codigo_inst";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->bindParam(':clave', $clave_mapeo, PDO::PARAM_STR);
                $checkStmt->bindParam(':codigo_inst', $codigo_institucion_activa, PDO::PARAM_INT);
                $checkStmt->execute();
                
                if ($checkStmt->fetchColumn() > 0) {
                    throw new Exception("La clave '$clave_mapeo' ya está configurada para esta institución.");
                }

                // Insertar el nuevo registro
                $query = "INSERT INTO configuracion_contable (clave_mapeo, cuenta_id, codigo_institucion) VALUES (:clave, :cuenta_id, :codigo_inst)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':clave', $clave_mapeo, PDO::PARAM_STR);
                $stmt->bindParam(':cuenta_id', $cuenta_id, PDO::PARAM_INT);
                $stmt->bindParam(':codigo_inst', $codigo_institucion_activa, PDO::PARAM_INT);
                $stmt->execute();
                $mensaje = "Nuevo mapeo agregado exitosamente.";
            }

            echo json_encode(['success' => true, 'message' => $mensaje]);
            break;

        // --- CASO 3: ELIMINAR ---
        case 'eliminar':
            $id_eliminar = isset($_POST['id']) ? intval($_POST['id']) : 0;
            if ($id_eliminar <= 0) { throw new Exception("ID no válido para eliminar."); }

            $query = "DELETE FROM configuracion_contable WHERE id = :id AND codigo_institucion = :codigo_inst";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id_eliminar, PDO::PARAM_INT);
            $stmt->bindParam(':codigo_inst', $codigo_institucion_activa, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                 echo json_encode(['success' => true, 'message' => 'Configuración eliminada.']);
            } else {
                 throw new Exception("No se pudo eliminar el registro (puede que no exista o no tengas permisos).");
            }
            break;

        default:
            throw new Exception("Acción no reconocida: " . $accion);
    }

} catch (Exception $e) {
    // Capturar cualquier error y devolverlo como JSON
    // Registrar el error en el log del servidor para depuración
    error_log("Error en crud_config.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>