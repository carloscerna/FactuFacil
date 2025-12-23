<?php
// admin/prestamos/crud_prestamos.php

session_name('FactuFacil');
session_start();

// Validación de sesión básica
if (empty($_SESSION['userNombre']) || empty($_SESSION['codigo_institucion'])) {
    header('Content-Type: application/json');
    echo json_encode(['respuesta' => false, 'mensaje' => 'Sesión no válida o institución no definida.']);
    exit();
}

global $pdo;
$path_root = trim($_SERVER['DOCUMENT_ROOT']);
include($path_root."/FactuFacil/includes/mainFunctions_.php");
include($path_root."/FactuFacil/admin/contabilidad/funciones/contabilidad_api.php");

/** @var PDO $dblink */
$pdo = $dblink;
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// Variables de Sesión
$usuario_activo = $_SESSION['userNombre'];
$cod_institucion = $_SESSION['codigo_institucion']; // <--- LA CLAVE

header('Content-Type: application/json');

switch ($accion) {
    
    case 'listar_prestamistas':
        try {
            // Filtramos por codigo_institucion
            $sql = "SELECT id, nombre_prestamista 
                    FROM fin_prestamistas 
                    WHERE codigo_institucion = :inst AND estado = 'A' 
                    ORDER BY nombre_prestamista ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':inst' => $cod_institucion]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['respuesta' => true, 'data' => $data]);
        } catch (Exception $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => $e->getMessage()]);
        }
        break;

    case 'guardar_prestamo':
        try {
            $pdo->beginTransaction();

            // Datos del formulario
            $id_prestamista = $_POST['id_prestamista'];
            $monto = $_POST['monto'];
            $fecha = $_POST['fecha'];
            $destino = $_POST['destino']; 
            $id_cuenta = ($destino === 'BANCO') ? $_POST['id_cuenta_banco'] : null;
            $concepto = $_POST['concepto'];

            // 1. Insertar el Préstamo con codigo_institucion
            $sql = "INSERT INTO fin_prestamos_ingreso 
                    (codigo_institucion, id_prestamista, fecha_ingreso, monto, destino_fondos, id_cuenta_banco, concepto, usuario_registro)
                    VALUES (:inst, :prov, :fecha, :monto, :dest, :cta, :con, :usr)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':inst'  => $cod_institucion, // <--- Guardamos la institución
                ':prov'  => $id_prestamista,
                ':fecha' => $fecha,
                ':monto' => $monto,
                ':dest'  => $destino,
                ':cta'   => $id_cuenta,
                ':con'   => $concepto,
                ':usr'   => $usuario_activo
            ]);

            // 2. Afectación de Saldos (Lógica Financiera)
            if ($destino === 'BANCO') {
                // Verificar que la cuenta bancaria pertenezca a la institución antes de sumar
                $sqlCheck = "SELECT id FROM bancos_cuentas WHERE id = :id AND codigo_institucion = :inst";
                $stmtCheck = $pdo->prepare($sqlCheck);
                $stmtCheck->execute([':id' => $id_cuenta, ':inst' => $cod_institucion]);
                
                if($stmtCheck->rowCount() > 0){
                    $sqlBanco = "UPDATE bancos_cuentas SET saldo_actual = saldo_actual + :monto WHERE id = :id";
                    $stmtB = $pdo->prepare($sqlBanco);
                    $stmtB->execute([':monto' => $monto, ':id' => $id_cuenta]);
                } else {
                    throw new Exception("La cuenta bancaria no pertenece a su institución.");
                }
            } 
            // Si es CAJA, aquí iría la lógica de inserción en movimientos_caja con su respectivo codigo_institucion

            $pdo->commit();
            echo json_encode(['respuesta' => true, 'mensaje' => 'Préstamo registrado correctamente.']);

        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error: ' . $e->getMessage()]);
        }
        break;

// 1. Listar para la tabla (DataTable o Tabla simple)
    case 'tabla_prestamistas':
        try {
            $sql = "SELECT id, nombre_prestamista, telefono, estado 
                    FROM fin_prestamistas 
                    WHERE codigo_institucion = :inst AND estado = 'A' 
                    ORDER BY id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':inst' => $cod_institucion]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['respuesta' => true, 'data' => $result]);
        } catch (Exception $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => $e->getMessage()]);
        }
        break;

    // 2. Guardar o Editar Prestamista
    case 'guardar_prestamista_catalogo':
        try {
            $id = $_POST['id_prestamista'] ?? ''; // Si viene vacío es NUEVO
            $nombre = $_POST['nombre_prestamista'];
            $telefono = $_POST['telefono'];

            if (empty($id)) {
                // INSERTAR
                $sql = "INSERT INTO fin_prestamistas (codigo_institucion, nombre_prestamista, telefono, estado) 
                        VALUES (:inst, :nom, :tel, 'A')";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':inst' => $cod_institucion,
                    ':nom' => $nombre,
                    ':tel' => $telefono
                ]);
            } else {
                // ACTUALIZAR
                $sql = "UPDATE fin_prestamistas 
                        SET nombre_prestamista = :nom, telefono = :tel 
                        WHERE id = :id AND codigo_institucion = :inst";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':nom' => $nombre,
                    ':tel' => $telefono,
                    ':id' => $id,
                    ':inst' => $cod_institucion
                ]);
            }
            echo json_encode(['respuesta' => true, 'mensaje' => 'Guardado correctamente']);
        } catch (Exception $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => $e->getMessage()]);
        }
        break;

    // 3. Eliminar (Borrado lógico)
    case 'eliminar_prestamista':
        try {
            $id = $_POST['id'];
            $sql = "UPDATE fin_prestamistas SET estado = 'I' WHERE id = :id AND codigo_institucion = :inst";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id, ':inst' => $cod_institucion]);
            echo json_encode(['respuesta' => true, 'mensaje' => 'Eliminado correctamente']);
        } catch (Exception $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => $e->getMessage()]);
        }
        break;

    // 4. Obtener uno solo para editar
    case 'obtener_prestamista':
        try {
            $id = $_POST['id'];
            $sql = "SELECT * FROM fin_prestamistas WHERE id = :id AND codigo_institucion = :inst";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id, ':inst' => $cod_institucion]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['respuesta' => true, 'data' => $data]);
        } catch (Exception $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['respuesta' => false, 'mensaje' => 'Acción no válida']);
        break;
}
?>