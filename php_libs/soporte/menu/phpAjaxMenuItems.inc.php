<?php
session_name('FactuFacil');
session_start();

header('Content-Type: application/json;charset=utf-8');

// Asegurarse de que el usuario está logueado y tiene permisos de administrador (ej. '01' o '99')
$allowed_profiles = ['01', '99']; // Perfiles con permiso para administrar

if (!isset($_SESSION['userLogin']) || $_SESSION['userLogin'] !== true || !in_array($_SESSION['codigo_perfil'], $allowed_profiles)) {
    error_log("Acceso denegado a phpAjaxMenuItems.inc.php. Usuario no logueado o sin permisos.");
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Se requieren permisos de administración.']);
    exit();
}

// Incluir la conexión a la base de datos
$path_root = trim($_SERVER['DOCUMENT_ROOT']);
include($path_root . "/FactuFacil/includes/mainFunctions_.php"); // Aquí se establece $dblink y $errorDbConexion

// Verificar si hubo un error de conexión
if ($errorDbConexion || !$dblink) {
    $errorMessage = $_SESSION['db_connection_error_message'] ?? 'No se pudo conectar a la base de datos.';
    error_log("Error de conexión a la DB en phpAjaxMenuItems.inc.php: " . $errorMessage);
    echo json_encode(['success' => false, 'message' => $errorMessage]);
    unset($_SESSION['db_connection_error_message']);
    exit();
}

// Inicializar respuesta
$response = ['success' => false, 'message' => 'Acción no válida.'];

// Determinar la acción a realizar
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// === DEBUGGING: Registrar datos de entrada ===
error_log("phpAjaxMenuItems.inc.php: Acción recibida: " . $action);
if (!empty($_POST)) {
    error_log("phpAjaxMenuItems.inc.php: Datos POST recibidos: " . print_r($_POST, true));
}
// ===================================

// ===============================================
// FUNCIÓN AUXILIAR PARA GESTIONAR PERMISOS
// Se llama desde 'createMenuItem' y 'updateMenuItem'
// ===============================================
function updateMenuItemPermissions($dblink, $menu_item_id, $selected_profiles) {
    if ($menu_item_id === null) {
        return ['success' => false, 'message' => 'ID de elemento de menú no proporcionado para actualizar permisos.'];
    }
    
    // Asumimos que la transacción se maneja externamente por la función que llama.
    
    try {
        // 1. Eliminar todos los permisos existentes para este menu_item_id
        $query_delete = "DELETE FROM profile_menu_permissions WHERE menu_item_id = :menu_item_id";
        $stmt_delete = $dblink->prepare($query_delete);
        $stmt_delete->bindParam(':menu_item_id', $menu_item_id, PDO::PARAM_INT);
        $stmt_delete->execute();
        error_log("phpAjaxMenuItems.inc.php (Permisos): Se eliminaron " . $stmt_delete->rowCount() . " permisos existentes para menu_item_id: " . $menu_item_id); // DEBUG

        // 2. Insertar los nuevos permisos seleccionados
        if (!empty($selected_profiles)) {
            $placeholders = [];
            $values = [];
            foreach ($selected_profiles as $profile_id) {
                // IMPORTANTE: Asegúrate de que el tipo de $profile_id coincide con el de la columna profile_id en DB.
                // Si 'codigo' en 'catalogo_perfil' es VARCHAR, 'profile_menu_permissions.profile_id' debe ser VARCHAR.
                // Si es INT, puedes usar (int)$profile_id. Asumo VARCHAR por los ejemplos anteriores ('01', '99').
                $placeholders[] = '(?, ?)';
                $values[] = $profile_id;
                $values[] = $menu_item_id;
            }
            $query_insert = "INSERT INTO profile_menu_permissions (profile_id, menu_item_id) VALUES " . implode(', ', $placeholders);
            error_log("phpAjaxMenuItems.inc.php (Permisos): Consulta INSERT generada: " . $query_insert); // DEBUG
            error_log("phpAjaxMenuItems.inc.php (Permisos): Valores para INSERT: " . print_r($values, true)); // DEBUG

            $stmt_insert = $dblink->prepare($query_insert);
            $stmt_insert->execute($values);
            error_log("phpAjaxMenuItems.inc.php (Permisos): Se insertaron " . count($selected_profiles) . " nuevos permisos para menu_item_id: " . $menu_item_id); // DEBUG
        } else {
            error_log("phpAjaxMenuItems.inc.php (Permisos): No se seleccionaron nuevos perfiles para menu_item_id: " . $menu_item_id); // DEBUG
        }
        return ['success' => true, 'message' => 'Permisos del menú actualizados.'];

    } catch (PDOException $e) {
        error_log("Error PDO en updateMenuItemPermissions: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error de DB al actualizar permisos: ' . $e->getMessage()];
    } catch (Exception $e) {
        error_log("Error inesperado en updateMenuItemPermissions: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error inesperado al actualizar permisos: ' . $e->getMessage()];
    }
}
// ===============================================


try {
    switch ($action) {
        case 'getAllMenuItems':
            try {
                // Usar los nombres exactos de las columnas de la tabla menu_items
                $query = "SELECT id, parent_id, text, icon, url, order_index, is_active, created_at, updated_at
                          FROM public.menu_items ORDER BY order_index ASC;";
                $stmt = $dblink->prepare($query);
                $stmt->execute();
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC); // Importante: FETCH_ASSOC para arrays asociativos
        
                $data = [];
                foreach ($result as $row) {
                    $data[] = [
                        'id' => $row['id'],
                        'text' => $row['text'],
                        'icon' => $row['icon'],
                        'url' => $row['url'],
                        'parent_id' => $row['parent_id'],
                        'order_index' => $row['order_index'],
                        'is_active' => $row['is_active'],
                        // 'created_at' y 'updated_at' pueden no ser necesarios para la tabla visible,
                        // pero los incluimos aquí si los necesitas para futuras funcionalidades.
                        // Si no los necesitas en la tabla, no es necesario incluirlos en las columnas de DataTables.
                        'created_at' => $row['created_at'],
                        'updated_at' => $row['updated_at'],
                        // Agrega un campo 'actions' que será renderizado en JS
                        'actions' => ''
                    ];
                }
        
                $response = ['data' => $data];
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => 'Error al obtener elementos del menú: ' . $e->getMessage()];
            }
            break;

        case 'createMenuItem':
            $text = $_POST['text'] ?? '';
            $icon = $_POST['icon'] ?? '';
            $url = $_POST['url'] ?? '';
            $parent_id = empty($_POST['parent_id']) ? null : (int)$_POST['parent_id'];
            $order_index = (int)($_POST['order_index'] ?? 0);
            $is_active = (int)($_POST['is_active'] ?? 0);
            
            // Perfiles seleccionados NO se guardan en la creación para simplificar.
            // Si quieres guardar en creación, necesitarías obtener el ID del nuevo item.
            // $selected_profiles = isset($_POST['selected_profiles']) ? (array)$_POST['selected_profiles'] : [];

            $dblink->beginTransaction();
            try {
                $query = "INSERT INTO menu_items (text, icon, url, parent_id, order_index, is_active) VALUES (:text, :icon, :url, :parent_id, :order_index, :is_active)";
                $stmt = $dblink->prepare($query);
                $stmt->bindParam(':text', $text, PDO::PARAM_STR);
                $stmt->bindParam(':icon', $icon, PDO::PARAM_STR);
                $stmt->bindParam(':url', $url, PDO::PARAM_STR);
                $stmt->bindParam(':parent_id', $parent_id, PDO::PARAM_INT);
                $stmt->bindParam(':order_index', $order_index, PDO::PARAM_INT);
                $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    // Si insertas, obtén el ID del nuevo elemento
                    $new_item_id = $dblink->lastInsertId('menu_items_id_seq'); // Asegúrate que 'menu_items_id_seq' es el nombre correcto de tu secuencia.

                    // Si quieres guardar permisos en la creación, hazlo aquí:
                    // $permission_create_result = updateMenuItemPermissions($dblink, $new_item_id, $selected_profiles);
                    // if ($permission_create_result['success']) {
                        $dblink->commit();
                        $response = ['success' => true, 'message' => 'Elemento de menú creado exitosamente.', 'newId' => $new_item_id];
                    // } else {
                    //     $dblink->rollBack();
                    //     $response = ['success' => false, 'message' => 'Elemento de menú creado, pero error al guardar permisos: ' . $permission_create_result['message']];
                    // }
                } else {
                    $dblink->rollBack();
                    $response = ['success' => false, 'message' => 'Error al crear el elemento de menú.'];
                }
            } catch (PDOException $e) {
                $dblink->rollBack();
                error_log("Error PDO en createMenuItem: " . $e->getMessage());
                $response = ['success' => false, 'message' => 'Error de base de datos al crear elemento de menú: ' . $e->getMessage()];
            }
            break;

        case 'updateMenuItem':
            $id = $_POST['id'] ?? null;
            $text = $_POST['text'] ?? '';
            $icon = $_POST['icon'] ?? '';
            $url = $_POST['url'] ?? '';
            $parent_id = empty($_POST['parent_id']) ? null : (int)$_POST['parent_id'];
            $order_index = (int)($_POST['order_index'] ?? 0);
            $is_active = (int)($_POST['is_active'] ?? 0);
            
            // Perfiles seleccionados vienen del JS
            $selected_profiles = isset($_POST['selected_profiles']) ? (array)$_POST['selected_profiles'] : [];

            if ($id === null) {
                $response = ['success' => false, 'message' => 'ID de elemento de menú no proporcionado para actualizar.'];
                break;
            }

            $dblink->beginTransaction();
            try {
                $query = "UPDATE menu_items SET text = :text, icon = :icon, url = :url, parent_id = :parent_id, order_index = :order_index, is_active = :is_active WHERE id = :id";
                $stmt = $dblink->prepare($query);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->bindParam(':text', $text, PDO::PARAM_STR);
                $stmt->bindParam(':icon', $icon, PDO::PARAM_STR);
                $stmt->bindParam(':url', $url, PDO::PARAM_STR);
                $stmt->bindParam(':parent_id', $parent_id, PDO::PARAM_INT);
                $stmt->bindParam(':order_index', $order_index, PDO::PARAM_INT);
                $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    // LLAMAR A LA FUNCIÓN PARA ACTUALIZAR PERMISOS AQUÍ
                    $permission_update_result = updateMenuItemPermissions($dblink, $id, $selected_profiles);
                    
                    if ($permission_update_result['success']) {
                        $dblink->commit();
                        $response = ['success' => true, 'message' => 'Elemento de menú y permisos actualizados exitosamente.'];
                    } else {
                        $dblink->rollBack();
                        $response = ['success' => false, 'message' => 'Elemento de menú actualizado, pero error al guardar permisos: ' . $permission_update_result['message']];
                    }
                } else {
                    $dblink->rollBack();
                    $response = ['success' => false, 'message' => 'Error al actualizar el elemento de menú.'];
                }
            } catch (PDOException $e) {
                $dblink->rollBack();
                error_log("Error PDO en updateMenuItem (principal): " . $e->getMessage());
                $response = ['success' => false, 'message' => 'Error de base de datos al actualizar elemento de menú: ' . $e->getMessage()];
            }
            break;

        case 'deleteMenuItem':
            $id = $_POST['id'] ?? null;
            if ($id === null) {
                $response = ['success' => false, 'message' => 'ID de elemento de menú no proporcionado para eliminar.'];
                break;
            }

            $dblink->beginTransaction();
            try {
                // Primero, eliminar permisos asociados al elemento de menú
                $query_delete_permissions = "DELETE FROM profile_menu_permissions WHERE menu_item_id = :menu_item_id";
                $stmt_delete_permissions = $dblink->prepare($query_delete_permissions);
                $stmt_delete_permissions->bindParam(':menu_item_id', $id, PDO::PARAM_INT);
                $stmt_delete_permissions->execute();
                error_log("phpAjaxMenuItems.inc.php: Se eliminaron " . $stmt_delete_permissions->rowCount() . " permisos asociados al item " . $id);

                // Luego, eliminar el elemento de menú
                $query_delete_item = "DELETE FROM menu_items WHERE id = :id";
                $stmt_delete_item = $dblink->prepare($query_delete_item);
                $stmt_delete_item->bindParam(':id', $id, PDO::PARAM_INT);

                if ($stmt_delete_item->execute()) {
                    $dblink->commit();
                    $response = ['success' => true, 'message' => 'Elemento de menú y sus permisos eliminados exitosamente.'];
                } else {
                    $dblink->rollBack();
                    $response = ['success' => false, 'message' => 'Error al eliminar el elemento de menú.'];
                }
            } catch (PDOException $e) {
                $dblink->rollBack();
                error_log("Error PDO en deleteMenuItem: " . $e->getMessage());
                $response = ['success' => false, 'message' => 'Error de base de datos al eliminar elemento de menú: ' . $e->getMessage()];
            }
            break;

            case 'getMenuItemsAndParents':
                try {
                    // Selecciona id y text de todos los elementos para el select de padres
                    $query = "SELECT id, text FROM public.menu_items WHERE parent_id IS NULL ORDER BY text ASC;";
                    $stmt = $dblink->prepare($query);
                    $stmt->execute();
                    $items = $stmt->fetchAll(PDO::FETCH_ASSOC); // Obtener como arrays asociativos
    
                    $response = ['success' => true, 'data' => $items];
                } catch (PDOException $e) {
                    $response = ['success' => false, 'message' => 'Error de BD al cargar elementos para select: ' . $e->getMessage()];
                }
                break;

        default:
            $response = ['success' => false, 'message' => 'Acción no reconocida para elementos del menú.'];
            break;
    }
} catch (Exception $e) { // Capturar cualquier otra excepción no PDO
    error_log("phpAjaxMenuItems.inc.php: Excepción no capturada: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'Ocurrió un error inesperado en el servidor al procesar la solicitud.'];
}

echo json_encode($response);
exit();