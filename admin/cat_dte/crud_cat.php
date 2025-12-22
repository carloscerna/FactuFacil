<?php
// admin/cat_dte/crud_cat.php

session_name('FactuFacil');
session_start();

if (empty($_SESSION['userNombre'])) {
    header('Content-Type: application/json');
    echo json_encode(['respuesta' => false, 'mensaje' => 'Sesión no válida.']);
    exit();
}

$path_root = trim($_SERVER['DOCUMENT_ROOT']);
include($path_root."/FactuFacil/includes/mainFunctions_.php");
$pdo = $dblink;
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
$tabla = $_POST['tabla'] ?? $_GET['tabla'] ?? '';

// Array de tablas permitidas para el mantenimiento
$tablas_permitidas = [
    'cat_001', 'cat_002', 'cat_003', 'cat_004', 'cat_005', 'cat_006', 'cat_007', 'cat_008', 'cat_009', 'cat_010', 'cat_011', 'cat_012', 'cat_013', 'cat_014', 'cat_015', 'cat_016', 'cat_017_forma_pago', 'cat_018', 'cat_019', 'cat_020', 'cat_021', 'cat_022', 'cat_023', 'cat_024', 'cat_025', 'cat_026', 'cat_027', 'cat_028', 'cat_029', 'cat_030', 'cat_031', 'cat_032',
    'catalogo_categoria', 'catalogo_ganancia'
];

if ($accion !== 'listarCatalogoTablas' && !in_array($tabla, $tablas_permitidas)) {
    echo json_encode(['respuesta' => false, 'mensaje' => 'Tabla no válida.']);
    exit();
}

switch ($accion) {
    case 'listarCatalogoTablas':
        try {
            $sql = "SELECT codigo, descripcion FROM catalogo_dte_tablas ORDER BY codigo";
            $stmt = $pdo->query($sql);
            $tablas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['respuesta' => true, 'tablas' => $tablas]);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al obtener catálogos de tablas: ' . $e->getMessage()]);
        }
        break;

    case 'listar':
        try {
            $sql = "SELECT id, codigo, descripcion FROM " . $tabla . " ORDER BY descripcion";
            $stmt = $pdo->query($sql);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['data' => $data]);
        } catch (PDOException $e) {
            echo json_encode(['data' => []]);
        }
        break;
        
    case 'obtener':
        $id = $_POST['id'] ?? '';
        try {
            $sql = "SELECT id, codigo, descripcion FROM " . $tabla . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['respuesta' => true, 'item' => $item]);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al obtener datos.']);
        }
        break;

    case 'crearActualizar':
        $id = $_POST['id'] ?? '';
        $codigo = $_POST['codigo'] ?? '';
        $descripcion = $_POST['descripcion'] ?? '';
        
        try {
            if (empty($id)) {
                $sql = "INSERT INTO " . $tabla . " (codigo, descripcion) VALUES (?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$codigo, $descripcion]);
            } else {
                $sql = "UPDATE " . $tabla . " SET codigo = ?, descripcion = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$codigo, $descripcion, $id]);
            }
            echo json_encode(['respuesta' => true, 'mensaje' => 'Registro guardado exitosamente.']);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al guardar: ' . $e->getMessage()]);
        }
        break;

    case 'eliminar':
        $id = $_POST['id'] ?? '';
        try {
            $sql = "DELETE FROM " . $tabla . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            echo json_encode(['respuesta' => true, 'mensaje' => 'Registro eliminado.']);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al eliminar: ' . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['respuesta' => false, 'mensaje' => 'Acción no válida.']);
        break;
}
?>