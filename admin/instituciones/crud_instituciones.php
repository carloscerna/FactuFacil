<?php
// admin/instituciones/crud_instituciones.php

session_name('FactuFacil');
session_start();

if (empty($_SESSION['userNombre'])) {
    header('Content-Type: application/json');
    echo json_encode(['respuesta' => false, 'mensaje' => 'Sesión no válida.']);
    exit();
}
// ruta de los archivos con su carpeta
$path_root = trim($_SERVER['DOCUMENT_ROOT']);
include($path_root."/FactuFacil/includes/mainFunctions_.php");
$pdo = $dblink;
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// Recortar espacios en blanco para todos los datos del formulario
foreach ($_POST as $key => $value) {
    if (is_string($value)) {
        $_POST[$key] = trim($value);
    }
}

switch ($accion) {
    case 'listarInstituciones':
        try {
            $sql = "SELECT codigo_institucion, nombre_institucion, nit, nrc, estado_actividad FROM instituciones ORDER BY nombre_institucion";
            $stmt = $pdo->query($sql);
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
        $codigo_institucion = $_POST['codigo_institucion'] ?? '';
        $nombre_institucion = $_POST['nombre_institucion'];
        $nombre_legal = $_POST['nombre_legal'];
        $nombre_corto = $_POST['nombre_corto'];
        $nit = $_POST['nit'];
        $nrc = $_POST['nrc'];
        $nrc_vigente = $_POST['nrc_vigente'] ?? false; // Valor por defecto
        $telefono = $_POST['telefono'];
        $correo_electronico = $_POST['correo_electronico'];
        $direccion = $_POST['direccion'];
        $representante_legal = $_POST['representante_legal'];
        $estado_actividad = $_POST['estado_actividad'] ?? true; // Valor por defecto
        $logo_uno = $_POST['logo_uno'] ?? '';
        $logo_dos = $_POST['logo_dos'] ?? '';

        try {
            if (empty($codigo_institucion)) {
                // Generar código para la nueva institución
                $nuevo_codigo = generarCodigoInstitucion($pdo);
                $sql = "INSERT INTO instituciones (codigo_institucion, nombre_institucion, nombre_legal, nombre_corto, nit, nrc, nrc_vigente, telefono, correo_electronico, direccion, representante_legal, estado_actividad, logo_uno, logo_dos, fecha_registro) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nuevo_codigo, $nombre_institucion, $nombre_legal, $nombre_corto, $nit, $nrc, $nrc_vigente, $telefono, $correo_electronico, $direccion, $representante_legal, $estado_actividad, $logo_uno, $logo_dos]);
            } else {
                // Actualizar una institución existente
                $sql = "UPDATE instituciones SET nombre_institucion = ?, nombre_legal = ?, nombre_corto = ?, nit = ?, nrc = ?, nrc_vigente = ?, telefono = ?, correo_electronico = ?, direccion = ?, representante_legal = ?, estado_actividad = ?, logo_uno = ?, logo_dos = ? WHERE codigo_institucion = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombre_institucion, $nombre_legal, $nombre_corto, $nit, $nrc, $nrc_vigente, $telefono, $correo_electronico, $direccion, $representante_legal, $estado_actividad, $logo_uno, $logo_dos, $codigo_institucion]);
            }
            echo json_encode(['respuesta' => true, 'mensaje' => 'Institución guardada exitosamente.']);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al guardar la institución: ' . $e->getMessage()]);
        }
        break;

    case 'eliminar':
        $codigo = $_POST['codigo_institucion'];
        try {
            $stmt = $pdo->prepare("DELETE FROM instituciones WHERE codigo_institucion = ?");
            $stmt->execute([$codigo]);
            echo json_encode(['respuesta' => true, 'mensaje' => 'Institución eliminada exitosamente.']);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al eliminar la institución.']);
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