<?php
session_name('FactuFacil');
session_start();

if (!isset($_SESSION['userLogin']) || $_SESSION['userLogin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'No autenticado.']);
    exit();
}

$path_root = trim($_SERVER['DOCUMENT_ROOT']);
include($path_root . "/FactuFacil/includes/mainFunctions_.php"); // Tu archivo de conexión a DB

header('Content-Type: application/json;charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? ''; // Obtener la acción solicitada

try {
    switch ($action) {
        case 'getAllProfiles':
            $stmt = $dblink->query("SELECT id_perfil, codigo, descripcion, is_active FROM public.catalogo_perfil ORDER BY codigo ASC");
            $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['data' => $profiles]);
            break;

        case 'getProfileById':
            $id_perfil = $_POST['id_perfil'] ?? null;
            if (!$id_perfil) {
                echo json_encode(['success' => false, 'message' => 'ID de perfil no proporcionado.']);
                exit();
            }
            $stmt = $dblink->prepare("SELECT id_perfil, codigo, descripcion, is_active FROM public.catalogo_perfil WHERE id_perfil = :id_perfil");
            $stmt->bindParam(':id_perfil', $id_perfil, PDO::PARAM_INT);
            $stmt->execute();
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($profile) {
                echo json_encode(['success' => true, 'data' => $profile]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Perfil no encontrado.']);
            }
            break;

        case 'saveProfile':
            $id_perfil = $_POST['id_perfil'] ?? '';
            $codigo = trim($_POST['codigo'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $is_active = isset($_POST['is_active']) ? true : false;

            if (empty($codigo) || empty($descripcion)) {
                echo json_encode(['success' => false, 'message' => 'Código y descripción son obligatorios.']);
                exit();
            }

            if ($id_perfil) { // Actualizar
                $stmt = $dblink->prepare("UPDATE public.catalogo_perfil SET codigo = :codigo, descripcion = :descripcion, is_active = :is_active WHERE id_perfil = :id_perfil");
                $stmt->bindParam(':codigo', $codigo, PDO::PARAM_STR);
                $stmt->bindParam(':descripcion', $descripcion, PDO::PARAM_STR);
                $stmt->bindParam(':is_active', $is_active, PDO::PARAM_BOOL);
                $stmt->bindParam(':id_perfil', $id_perfil, PDO::PARAM_INT);
                $stmt->execute();
                echo json_encode(['success' => true, 'message' => 'Perfil actualizado exitosamente.']);
            } else { // Crear nuevo
                // Verificar si el código ya existe
                $stmt_check = $dblink->prepare("SELECT COUNT(*) FROM public.catalogo_perfil WHERE codigo = :codigo");
                $stmt_check->bindParam(':codigo', $codigo, PDO::PARAM_STR);
                $stmt_check->execute();
                if ($stmt_check->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'message' => 'El código de perfil ya existe.']);
                    exit();
                }

                $stmt = $dblink->prepare("INSERT INTO public.catalogo_perfil (codigo, descripcion, is_active) VALUES (:codigo, :descripcion, :is_active)");
                $stmt->bindParam(':codigo', $codigo, PDO::PARAM_STR);
                $stmt->bindParam(':descripcion', $descripcion, PDO::PARAM_STR);
                $stmt->bindParam(':is_active', $is_active, PDO::PARAM_BOOL);
                $stmt->execute();
                echo json_encode(['success' => true, 'message' => 'Perfil creado exitosamente.']);
            }
            break;

        case 'deleteProfile':
            $id_perfil = $_POST['id_perfil'] ?? null;
            if (!$id_perfil) {
                echo json_encode(['success' => false, 'message' => 'ID de perfil no proporcionado para eliminar.']);
                exit();
            }
            $stmt = $dblink->prepare("DELETE FROM public.catalogo_perfil WHERE id_perfil = :id_perfil");
            $stmt->bindParam(':id_perfil', $id_perfil, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Perfil eliminado exitosamente.']);
            break;

            case 'getNextProfileCodigo':
                // Obtener el máximo código numérico, excluyendo '99'
                // Convertimos a INT para asegurar un orden numérico correcto.
                $stmt = $dblink->query("SELECT MAX(CAST(codigo AS INT)) AS max_codigo FROM public.catalogo_perfil WHERE codigo != '99'");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
                $next_codigo = 1; // Valor por defecto si no hay códigos numéricos o la tabla está vacía
                if ($result && $result['max_codigo'] !== null) {
                    $next_codigo = (int)$result['max_codigo'] + 1;
                }
                // Formatear el código con ceros iniciales si es necesario (ej. 1 -> "01")
                $next_codigo_formatted = str_pad($next_codigo, 2, '0', STR_PAD_LEFT);
                
                echo json_encode(['success' => true, 'next_codigo' => $next_codigo_formatted]);
                break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
            break;

            
    }
} catch (PDOException $e) {
    error_log("Error en phpAjaxCatalogoPerfiles.inc.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error general en phpAjaxCatalogoPerfiles.inc.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error inesperado: ' . $e->getMessage()]);
}
?>