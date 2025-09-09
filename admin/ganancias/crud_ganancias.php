    <?php
// admin/ganancias/crud_ganancias.php

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

function generarCodigoGanancia($pdo, $codigo_institucion_sesion) {
    try {
        $pdo->beginTransaction();
        $codigo_tipo = 'GANANCIA' . '_' . $codigo_institucion_sesion;

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
        $nuevo_codigo = 'GAN' . $correlativo_formateado;

        $pdo->commit();
        return $nuevo_codigo;

    } catch (PDOException $e) {
        $pdo->rollBack();
        return null;
    }
}

switch ($accion) {
    case 'listarGanancias':
        try {
            $sql = "SELECT id_ganancia, codigo, descripcion, porcentaje FROM catalogo_ganancia WHERE codigo_institucion = :codigo_institucion_sesion ORDER BY descripcion";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':codigo_institucion_sesion', $codigo_institucion_sesion, PDO::PARAM_STR);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['data' => $data]);
        } catch (PDOException $e) {
            echo json_encode(['data' => []]);
        }
        break;
        
    case 'obtenerGanancia':
        $id = $_POST['id_ganancia'];
        try {
            $sql = "SELECT * FROM catalogo_ganancia WHERE id_ganancia = ? AND codigo_institucion = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id, $codigo_institucion_sesion]);
            $ganancia = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['respuesta' => true, 'ganancia' => $ganancia]);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al obtener datos.']);
        }
        break;

    case 'crearActualizar':
        $id_ganancia = $_POST['id_ganancia'] ?? '';
        $descripcion = $_POST['descripcion'] ?? '';
        $porcentaje = $_POST['porcentaje'] ?? 0;
        
        try {
            if (empty($id_ganancia)) {
                $codigo_generado = generarCodigoGanancia($pdo, $codigo_institucion_sesion);
                if (!$codigo_generado) {
                    throw new Exception("No se pudo generar el código de ganancia.");
                }
                $sql = "INSERT INTO catalogo_ganancia (codigo, codigo_institucion, descripcion, porcentaje) VALUES (?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$codigo_generado, $codigo_institucion_sesion, $descripcion, $porcentaje]);
               // echo json_encode(['respuesta' => true, 'mensaje' => 'Porcentaje de ganancia guardado exitosamente.']);
            } else {
                $codigo = $_POST['codigo'] ?? '';
                $sql = "UPDATE catalogo_ganancia SET codigo = ?, descripcion = ?, porcentaje = ? WHERE id_ganancia = ? AND codigo_institucion = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$codigo, $descripcion, $porcentaje, $id_ganancia, $codigo_institucion_sesion]);
            }
            echo json_encode(['respuesta' => true, 'mensaje' => 'Porcentaje de ganancia guardado exitosamente.']);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al guardar: ' . $e->getMessage()]);
        }
        break;

    case 'eliminar':
        $id_ganancia = $_POST['id_ganancia'];
        try {
            $sql = "DELETE FROM catalogo_ganancia WHERE id_ganancia = ? AND codigo_institucion = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_ganancia, $codigo_institucion_sesion]);
            echo json_encode(['respuesta' => true, 'mensaje' => 'Porcentaje de ganancia eliminado.']);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al eliminar: ' . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['respuesta' => false, 'mensaje' => 'Acción no válida.']);
        break;
}
?>