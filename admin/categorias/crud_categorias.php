<?php
// admin/categorias/crud_categorias.php

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

foreach ($_POST as $key => $value) {
    if (is_string($value)) {
        $_POST[$key] = trim($value);
    }
}

$codigo_perfil_sesion = $_SESSION['codigo_perfil'] ?? '';
$codigo_institucion_sesion = $_SESSION['codigo_institucion'] ?? '';

function generarCodigoCategoria($pdo, $codigo_institucion_sesion) {
    try {
        $pdo->beginTransaction();
        $codigo_tipo = 'CATEGORIA' . '_' . $codigo_institucion_sesion;

        $sql = "SELECT ultimo_numero FROM correlativos WHERE codigo_tipo = ? FOR UPDATE";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$codigo_tipo]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $ultimo_numero = $result ? $result['ultimo_numero'] : 0;
        $nuevo_numero = $ultimo_numero + 1;

        if ($result) {
            $sql_update = "UPDATE correlativos SET ultimo_numero = ? WHERE codigo_tipo = ?";
        } else {
            $sql_update = "INSERT INTO correlativos (ultimo_numero, codigo_tipo) VALUES (?, ?)";
        }
        
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([$nuevo_numero, $codigo_tipo]);

        $correlativo_formateado = str_pad($nuevo_numero, 3, '0', STR_PAD_LEFT);
        $nuevo_codigo = 'CAT' . $correlativo_formateado;

        $pdo->commit();
        return $nuevo_codigo;

    } catch (PDOException $e) {
        $pdo->rollBack();
        return null;
    }
}

switch ($accion) {
    case 'listarCategorias':
        try {
            if ($codigo_perfil_sesion === '99') {
                $sql = "SELECT id_categoria, codigo, descripcion FROM catalogo_categoria ORDER BY descripcion";
                $stmt = $pdo->query($sql);
            } else {
                $sql = "SELECT id_categoria, codigo, descripcion FROM catalogo_categoria WHERE codigo_institucion = :codigo_institucion_sesion ORDER BY descripcion";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':codigo_institucion_sesion', $codigo_institucion_sesion, PDO::PARAM_STR);
                $stmt->execute();
            }
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['data' => $data]);
        } catch (PDOException $e) {
            echo json_encode(['data' => []]);
        }
        break;
        
    case 'obtenerCategoria':
        $id = $_POST['id_categoria'];
        try {
            if ($codigo_perfil_sesion === '99') {
                $stmt = $pdo->prepare("SELECT * FROM catalogo_categoria WHERE id_categoria = ?");
                $stmt->execute([$id]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM catalogo_categoria WHERE id_categoria = ? AND codigo_institucion = ?");
                $stmt->execute([$id, $codigo_institucion_sesion]);
            }
            $categoria = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['respuesta' => true, 'categoria' => $categoria]);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al obtener datos.']);
        }
        break;

    case 'crearActualizar':
        $id_categoria = $_POST['id_categoria'] ?? '';
        $descripcion = $_POST['descripcion'] ?? '';
        $comentario = $_POST['comentario'] ?? '';
        
        try {
            if (empty($id_categoria)) {
                $codigo_generado = generarCodigoCategoria($pdo, $codigo_institucion_sesion);
                if (!$codigo_generado) {
                    throw new Exception("No se pudo generar el código de la categoría.");
                }
                $sql = "INSERT INTO catalogo_categoria (codigo, codigo_institucion, descripcion, comentario) VALUES (?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$codigo_generado, $codigo_institucion_sesion, $descripcion, $comentario]);
                //echo json_encode(['respuesta' => true, 'mensaje' => 'Categoría guardada exitosamente.', 'nuevo_codigo' => $codigo_generado]);
            } else {
                $codigo = $_POST['codigo'] ?? '';
                $sql = "UPDATE catalogo_categoria SET codigo = ?, descripcion = ?, comentario = ? WHERE id_categoria = ? AND codigo_institucion = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$codigo, $descripcion, $comentario, $id_categoria, $codigo_institucion_sesion]);
            }
            echo json_encode(['respuesta' => true, 'mensaje' => 'Categoría guardada exitosamente.']);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al guardar: ' . $e->getMessage()]);
        }
        break;

    case 'eliminar':
        $id_categoria = $_POST['id_categoria'];
        try {
            if ($codigo_perfil_sesion === '99') {
                $stmt = $pdo->prepare("DELETE FROM catalogo_categoria WHERE id_categoria = ?");
                $stmt->execute([$id_categoria]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM catalogo_categoria WHERE id_categoria = ? AND codigo_institucion = ?");
                $stmt->execute([$id_categoria, $codigo_institucion_sesion]);
            }
            echo json_encode(['respuesta' => true, 'mensaje' => 'Categoría eliminada.']);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al eliminar.']);
        }
        break;
        
    default:
        echo json_encode(['respuesta' => false, 'mensaje' => 'Acción no válida.']);
        break;
}
?>