<?php
// admin/instituciones/crud_instituciones.php

session_name('FactuFacil');
session_start();

if (empty($_SESSION['userNombre'])) {
    header('Content-Type: application/json');
    echo json_encode(['respuesta' => false, 'mensaje' => 'Sesión no válida.']);
    exit();
}

$codigo_perfil_sesion = $_SESSION['codigo_perfil'] ?? '';
$codigo_institucion_sesion = $_SESSION['codigo_institucion'] ?? '';

$path_root = trim($_SERVER['DOCUMENT_ROOT']);
include($path_root."/FactuFacil/includes/mainFunctions_.php");
$pdo = $dblink;
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

foreach ($_POST as $key => $value) {
    if (is_string($value)) {
        $_POST[$key] = trim($value);
    }
}

$base_upload_dir = $path_root . "/FactuFacil/img/";

switch ($accion) {
    case 'listarInstituciones':
        try {
            if ($codigo_perfil_sesion === '99') { // Si es root, muestra todas las empresas
                $sql = "SELECT codigo_institucion, nombre_institucion, nit, nrc, estado_actividad FROM instituciones ORDER BY nombre_institucion";
                $stmt = $pdo->query($sql);
            } else { // Si no es root, muestra solo la institución de la sesión
                $sql = "SELECT codigo_institucion, nombre_institucion, nit, nrc, estado_actividad FROM instituciones WHERE codigo_institucion = ? ORDER BY nombre_institucion";
                $stmt->execute([$codigo_institucion_sesion]);
            }
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['data' => $data]);
        } catch (PDOException $e) {
            echo json_encode(['data' => []]);
        }
        break;

    case 'obtenerInstitucion':
        $codigo = $_POST['codigo_institucion'];
        try {
            $stmt = $pdo->prepare("SELECT * FROM instituciones WHERE codigo_institucion = ?");
            $stmt->execute([$codigo]);
            $institucion = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['respuesta' => true, 'institucion' => $institucion]);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al obtener datos.']);
        }
        break;

    case 'crearActualizar':
        if ($codigo_perfil_sesion !== '99') {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Solo el superusuario puede crear instituciones.']);
            exit();
        }
        $codigo_institucion = $_POST['codigo_institucion'] ?? '';
        $nombre_institucion = $_POST['nombre_institucion']?? '';
        $nombre_legal = $_POST['nombre_legal'];
        $nombre_corto = $_POST['nombre_corto']?? '';
        $nit = $_POST['nit'] ?? '';
        $nrc = $_POST['nrc'] ?? '';
         // Convertir los valores de '1' y '0' a booleanos
        $nrc_vigente = ($_POST['nrc_vigente']);
        $estado_actividad = ($_POST['estado_actividad']);

        $telefono = $_POST['telefono'] ?? '';
        $correo_electronico = $_POST['correo_electronico'] ?? '';
        $direccion = $_POST['direccion'] ?? '';
        $representante_legal = $_POST['representante_legal'] ?? '';
        
        $logo_uno_nombre_db = $_POST['logo_uno_actual'] ?? null;
        $logo_dos_nombre_db = $_POST['logo_dos_actual'] ?? null;

        try {
            $pdo->beginTransaction();

            if (empty($codigo_institucion)) {
                $nuevo_codigo = generarCodigoInstitucion($pdo);
                if (!$nuevo_codigo) {
                    throw new Exception("No se pudo generar el código de institución.");
                }
                $codigo_institucion = $nuevo_codigo;

                $institucion_upload_dir = $base_upload_dir . $codigo_institucion . "/";
                if (!is_dir($institucion_upload_dir)) {
                    mkdir($institucion_upload_dir, 0777, true);
                }
            } else {
                $institucion_upload_dir = $base_upload_dir . $codigo_institucion . "/";
                if (!is_dir($institucion_upload_dir)) {
                    mkdir($institucion_upload_dir, 0777, true);
                }
            }

            if (isset($_FILES['logo_uno_file']) && $_FILES['logo_uno_file']['error'] === UPLOAD_ERR_OK) {
                $file_tmp_name = $_FILES['logo_uno_file']['tmp_name'];
                $file_name = uniqid('logo1_') . "." . pathinfo($_FILES['logo_uno_file']['name'], PATHINFO_EXTENSION);
                $destination = $institucion_upload_dir . $file_name;

                if (move_uploaded_file($file_tmp_name, $destination)) {
                    if ($logo_uno_nombre_db && $logo_uno_nombre_db !== $file_name && file_exists($institucion_upload_dir . $logo_uno_nombre_db)) {
                        unlink($institucion_upload_dir . $logo_uno_nombre_db);
                    }
                    $logo_uno_nombre_db = $file_name;
                } else {
                    throw new Exception("Error al subir el Logo Principal.");
                }
            }

            if (isset($_FILES['logo_dos_file']) && $_FILES['logo_dos_file']['error'] === UPLOAD_ERR_OK) {
                $file_tmp_name = $_FILES['logo_dos_file']['tmp_name'];
                $file_name = uniqid('logo2_') . "." . pathinfo($_FILES['logo_dos_file']['name'], PATHINFO_EXTENSION);
                $destination = $institucion_upload_dir . $file_name;

                if (move_uploaded_file($file_tmp_name, $destination)) {
                    if ($logo_dos_nombre_db && $logo_dos_nombre_db !== $file_name && file_exists($institucion_upload_dir . $logo_dos_nombre_db)) {
                        unlink($institucion_upload_dir . $logo_dos_nombre_db);
                    }
                    $logo_dos_nombre_db = $file_name;
                } else {
                    throw new Exception("Error al subir el Logo Secundario.");
                }
            }

            if (empty($_POST['codigo_institucion'])) {
                $sql = "INSERT INTO instituciones (codigo_institucion, nombre_institucion, nombre_legal, nombre_corto, nit, nrc, nrc_vigente, telefono, correo_electronico, direccion, representante_legal, estado_actividad, logo_uno, logo_dos, fecha_registro) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$codigo_institucion, $nombre_institucion, $nombre_legal, $nombre_corto, $nit, $nrc, $nrc_vigente, $telefono, $correo_electronico, $direccion, $representante_legal, $estado_actividad, $logo_uno_nombre_db, $logo_dos_nombre_db]);
                
                $pdo->commit();
                echo json_encode(['respuesta' => true, 'mensaje' => 'Institución creada exitosamente.', 'nuevo_codigo' => $codigo_institucion]);

            } else {
                $sql = "UPDATE instituciones SET nombre_institucion = ?, nombre_legal = ?, nombre_corto = ?, nit = ?, nrc = ?, nrc_vigente = ?, telefono = ?, correo_electronico = ?, direccion = ?, representante_legal = ?, estado_actividad = ?, logo_uno = ?, logo_dos = ? WHERE codigo_institucion = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombre_institucion, $nombre_legal, $nombre_corto, $nit, $nrc, $nrc_vigente, $telefono, $correo_electronico, $direccion, $representante_legal, $estado_actividad, $logo_uno_nombre_db, $logo_dos_nombre_db, $codigo_institucion]);
                
                $pdo->commit();
                echo json_encode(['respuesta' => true, 'mensaje' => 'Institución guardada exitosamente.']);
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error al guardar institución: " . $e->getMessage());
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al guardar la institución: ' . $e->getMessage()]);
        }
        break;

    case 'eliminar':
        $codigo = $_POST['codigo_institucion'];
        try {
            $pdo->beginTransaction();

            $stmt_get_logos = $pdo->prepare("SELECT logo_uno, logo_dos FROM instituciones WHERE codigo_institucion = ?");
            $stmt_get_logos->execute([$codigo]);
            $logos = $stmt_get_logos->fetch(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("DELETE FROM instituciones WHERE codigo_institucion = ?");
            $stmt->execute([$codigo]);

            $institucion_dir = $base_upload_dir . $codigo . "/";
            if (is_dir($institucion_dir)) {
                if ($logos['logo_uno'] && file_exists($institucion_dir . $logos['logo_uno'])) {
                    unlink($institucion_dir . $logos['logo_uno']);
                }
                if ($logos['logo_dos'] && file_exists($institucion_dir . $logos['logo_dos'])) {
                    unlink($institucion_dir . $logos['logo_dos']);
                }
                rmdir($institucion_dir);
            }

            $pdo->commit();
            echo json_encode(['respuesta' => true, 'mensaje' => 'Institución eliminada exitosamente y archivos asociados.']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error al eliminar institución: " . $e->getMessage());
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al eliminar la institución: ' . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['respuesta' => false, 'mensaje' => 'Acción no válida.']);
        break;
}

function generarCodigoInstitucion($pdo) {
    try {
        $prefijo = 'E';
        $tipo_correlativo = 'INST';

        $pdo->beginTransaction();
        $sql = "SELECT ultimo_numero FROM correlativos WHERE codigo_tipo = ? FOR UPDATE";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tipo_correlativo]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $ultimo_numero = $result ? $result['ultimo_numero'] : 0;
        $nuevo_numero = $ultimo_numero + 1;

        if ($result) {
            $sql_update = "UPDATE correlativos SET ultimo_numero = ? WHERE codigo_tipo = ?";
        } else {
            $sql_update = "INSERT INTO correlativos (ultimo_numero, codigo_tipo) VALUES (?, ?)";
        }
        
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([$nuevo_numero, $tipo_correlativo]);

        $correlativo_formateado = str_pad($nuevo_numero, 4, '0', STR_PAD_LEFT);
        $nuevo_codigo = $prefijo . $correlativo_formateado;

        $pdo->commit();
        return $nuevo_codigo;

    } catch (PDOException $e) {
        $pdo->rollBack();
        return null;
    }
}
?>