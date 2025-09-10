<?php
// admin/compras/crud_compras.php

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

$codigo_perfil_sesion = $_SESSION['codigo_perfil'] ?? '';
$codigo_institucion_sesion = $_SESSION['codigo_institucion'] ?? '';

switch ($accion) {
    case 'guardarCompra':
        $pdo->beginTransaction();
        try {
            $numero_documento = $_POST['numero_documento'] ?? '';
            $tipo_documento = $_POST['tipo_documento'] ?? '';
            $fecha_emision = $_POST['fecha_emision'] ?? date('Y-m-d');
            $proveedor_id = $_POST['proveedor_id'] ?? '';
            $condicion_pago = $_POST['condicion_pago'] ?? '';
            $plazo_pago = $_POST['plazo_pago'] ?? null;
            $fecha_vencimiento = $_POST['fecha_vencimiento'] ?? null;
            $total_compra = $_POST['total_compra'] ?? 0;
            $observaciones = $_POST['observaciones'] ?? '';
            $productos = json_decode($_POST['productos'], true);
            
            if (empty($productos)) {
                throw new Exception("No se ha agregado ningún producto a la compra.");
            }

            // Insertar la cabecera de la compra
            $sql_cabecera = "INSERT INTO compras_cabecera (codigo_institucion, numero_documento, tipo_documento, fecha_emision, proveedor_id, condicion_pago, plazo_pago, fecha_vencimiento, total_compra, observaciones) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_cabecera = $pdo->prepare($sql_cabecera);
            $stmt_cabecera->execute([$codigo_institucion_sesion, $numero_documento, $tipo_documento, $fecha_emision, $proveedor_id, $condicion_pago, $plazo_pago, $fecha_vencimiento, $total_compra, $observaciones]);
            $id_compra = $pdo->lastInsertId('compras_cabecera_id_compra_seq');

            foreach ($productos as $producto) {
                // Insertar el detalle de la compra. El TRIGGER se encargará de actualizar el inventario.
                $sql_detalle = "INSERT INTO compras_detalle (compra_id, producto_id, cantidad, unidad_medida, precio_unitario, impuesto_aplicable, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt_detalle = $pdo->prepare($sql_detalle);
                $stmt_detalle->execute([
                    $id_compra,
                    $producto['producto_id'],
                    $producto['cantidad'],
                    $producto['unidad_medida'],
                    $producto['precio_unitario'],
                    $producto['impuesto_aplicable'],
                    $producto['subtotal']
                ]);
            }
            
            $pdo->commit();
            echo json_encode(['respuesta' => true, 'mensaje' => 'Compra guardada y inventario actualizado exitosamente.', 'id_compra' => $id_compra]);

        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al guardar la compra: ' . $e->getMessage()]);
        }
        break;

    case 'obtenerProveedores':
        try {
            $sql = "SELECT id_proveedores, nombre_empresa FROM proveedores WHERE codigo_institucion = :codigo_institucion_sesion ORDER BY nombre_empresa";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':codigo_institucion_sesion', $codigo_institucion_sesion, PDO::PARAM_STR);
            $stmt->execute();
            $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['respuesta' => true, 'proveedores' => $proveedores]);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al obtener proveedores.']);
        }
        break;

    case 'buscarProducto':
        $producto_id = $_POST['producto_id'] ?? '';
        try {
            $sql = "SELECT id_producto, codigo_interno, descripcion, unidad_medida FROM productos WHERE id_producto = ? AND codigo_institucion = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$producto_id, $codigo_institucion_sesion]);
            $producto = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($producto) {
                echo json_encode(['respuesta' => true, 'producto' => $producto]);
            } else {
                echo json_encode(['respuesta' => false, 'mensaje' => 'Producto no encontrado.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al buscar producto.']);
        }
        break;

    default:
        echo json_encode(['respuesta' => false, 'mensaje' => 'Acción no válida.']);
        break;
}
?>