<?php
session_name('FactuFacil');
session_start();

header('Content-Type: application/json;charset=utf-8');

// Asegurarse de que el usuario está logueado y tiene permisos de administrador (ej. '01' o '99')
$allowed_profiles = ['01', '99']; // Perfiles con permiso para administrar permisos

if (!isset($_SESSION['userLogin']) || $_SESSION['userLogin'] !== true || !in_array($_SESSION['codigo_perfil'], $allowed_profiles)) {
    error_log("Acceso denegado a phpAjaxMenuPermissions.inc.php. Usuario no logueado o sin permisos.");
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Se requieren permisos de administración de menú.']);
    exit();
}

// Incluir la conexión a la base de datos
$path_root = trim($_SERVER['DOCUMENT_ROOT']);
include($path_root . "/FactuFacil/includes/mainFunctions_.php"); // Aquí se establece $dblink y $errorDbConexion

// Verificar si hubo un error de conexión
if ($errorDbConexion || !$dblink) {
    $errorMessage = $_SESSION['db_connection_error_message'] ?? 'No se pudo conectar a la base de datos.';
    error_log("Error de conexión a la DB en phpAjaxMenuPermissions.inc.php: " . $errorMessage);
    echo json_encode(['success' => false, 'message' => $errorMessage]);
    unset($_SESSION['db_connection_error_message']);
    exit();
}

// Inicializar respuesta
$response = ['success' => false, 'message' => 'Acción no válida.'];

// Determinar la acción a realizar
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// === DEBUGGING: Registrar datos de entrada ===
error_log("phpAjaxMenuPermissions.inc.php: Acción recibida: " . $action);
if ($action === 'saveMenuItemPermissions') {
    error_log("phpAjaxMenuPermissions.inc.php: Datos POST para saveMenuItemPermissions: " . print_r($_POST, true));
}
// ===================================

try {
    switch ($action) {
        case 'getAllProfiles':
            $query = "SELECT codigo AS codigo_perfil, descripcion AS nombre_perfil, id_perfil FROM catalogo_perfil ORDER BY descripcion ASC";
            $stmt = $dblink->prepare($query);
            $stmt->execute();
            $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response = ['success' => true, 'data' => $profiles];
            break;

        case 'getPermissionsByMenuItem':
            $menu_item_id = $_GET['menu_item_id'] ?? null;
            if ($menu_item_id === null) {
                $response = ['success' => false, 'message' => 'ID de elemento de menú no proporcionado.'];
                break;
            }
            $query = "SELECT profile_id FROM profile_menu_permissions WHERE menu_item_id = :menu_item_id";
            $stmt = $dblink->prepare($query);
            $stmt->bindParam(':menu_item_id', $menu_item_id, PDO::PARAM_INT);
            $stmt->execute();
            $assigned_profiles = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            $response = ['success' => true, 'data' => $assigned_profiles];
            break;

        case 'saveMenuItemPermissions':
            $menu_item_id = $_POST['menu_item_id'] ?? null;
            // $selected_profiles ahora se espera como un array directo de $_POST
            $selected_profiles = isset($_POST['selected_profiles']) ? (array)$_POST['selected_profiles'] : [];

            // === DEBUGGING: Registrar datos procesados ===
            error_log("phpAjaxMenuPermissions.inc.php: Guardando permisos para menu_item_id: " . $menu_item_id);
            error_log("phpAjaxMenuPermissions.inc.php: Perfiles seleccionados para guardar: " . print_r($selected_profiles, true));
            // ===================================

            if ($menu_item_id === null) {
                $response = ['success' => false, 'message' => 'ID de elemento de menú no proporcionado.'];
                break;
            }

            $dblink->beginTransaction();

            try {
                // 1. Eliminar todos los permisos existentes para este menu_item_id
                $query_delete = "DELETE FROM profile_menu_permissions WHERE menu_item_id = :menu_item_id";
                $stmt_delete = $dblink->prepare($query_delete);
                $stmt_delete->bindParam(':menu_item_id', $menu_item_id, PDO::PARAM_INT);
                $stmt_delete->execute();
                error_log("phpAjaxMenuPermissions.inc.php: Se eliminaron " . $stmt_delete->rowCount() . " permisos existentes para menu_item_id: " . $menu_item_id); // DEBUG

                // 2. Insertar los nuevos permisos seleccionados
                if (!empty($selected_profiles)) {
                    $placeholders = [];
                    $values = [];
                    foreach ($selected_profiles as $profile_id) {
                        // Importante: Asegúrate que el tipo de $profile_id coincide con el de la columna profile_id en DB.
                        // Si catalogo_perfil.codigo es VARCHAR, profile_menu_permissions.profile_id debe ser VARCHAR.
                        // Si es numérico en catalogo_perfil.codigo y profile_menu_permissions.profile_id es INT, puedes usar (int)$profile_id.
                        $placeholders[] = '(?, ?)';
                        $values[] = $profile_id;
                        $values[] = $menu_item_id;
                    }
                    $query_insert = "INSERT INTO profile_menu_permissions (profile_id, menu_item_id) VALUES " . implode(', ', $placeholders);
                    error_log("phpAjaxMenuPermissions.inc.php: Consulta INSERT generada: " . $query_insert); // DEBUG
                    error_log("phpAjaxMenuPermissions.inc.php: Valores para INSERT: " . print_r($values, true)); // DEBUG

                    $stmt_insert = $dblink->prepare($query_insert);
                    $stmt_insert->execute($values);
                    error_log("phpAjaxMenuPermissions.inc.php: Se insertaron " . count($selected_profiles) . " nuevos permisos para menu_item_id: " . $menu_item_id); // DEBUG
                } else {
                    error_log("phpAjaxMenuPermissions.inc.php: No se seleccionaron nuevos perfiles para menu_item_id: " . $menu_item_id); // DEBUG
                }

                $dblink->commit(); // Confirmar la transacción
                $response = ['success' => true, 'message' => 'Permisos actualizados exitosamente.'];

            } catch (PDOException $e) {
                $dblink->rollBack(); // Revertir la transacción si algo falla
                error_log("Error en la transacción de permisos (PDOException): " . $e->getMessage());
                // En producción, evita mostrar el mensaje de error de la base de datos directamente al usuario.
                $response = ['success' => false, 'message' => 'Error de base de datos al guardar permisos. Por favor, revise los logs del servidor para más detalles.'];
            }
            break;

        default:
            $response = ['success' => false, 'message' => 'Acción no reconocida para permisos.'];
            break;
    }
} catch (Exception $e) { // Capturar cualquier otra excepción no PDO
    error_log("phpAjaxMenuPermissions.inc.php: Excepción no capturada: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'Ocurrió un error inesperado en el servidor al procesar la solicitud.'];
}

echo json_encode($response);
exit();
?>