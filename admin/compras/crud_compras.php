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
            $id_proveedores = $_POST['id_proveedores'] ?? '';
            $condicion_pago = $_POST['condicion_pago'] ?? '';
            $plazo_pago = $_POST['plazo_pago'] ?? null;
            $fecha_vencimiento = $_POST['fecha_vencimiento'] ?? null;
            $total_compra = $_POST['total_compra'] ?? 0;
            $observaciones = $_POST['observaciones'] ?? '';
            $productos = json_decode($_POST['productos'], true);
            
            if (empty($productos)) {
                throw new Exception("No se ha agregado ningún producto a la compra.");
            }

            $sql_cabecera = "INSERT INTO compras_cabecera (codigo_institucion, numero_documento, tipo_documento, fecha_emision, id_proveedores, condicion_pago, plazo_pago, fecha_vencimiento, total_compra, observaciones) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_cabecera = $pdo->prepare($sql_cabecera);
            $stmt_cabecera->execute([$codigo_institucion_sesion, $numero_documento, $tipo_documento, $fecha_emision, $id_proveedores, $condicion_pago, $plazo_pago, $fecha_vencimiento, $total_compra, $observaciones]);
            $id_compra = $pdo->lastInsertId('compras_cabecera_id_compra_seq');

            foreach ($productos as $producto) {
                $sql_get_codigo = "SELECT codigo_interno, unidad_medida FROM catalogo_productos WHERE id_productos = ? AND codigo_institucion = ?";
                $stmt_get_codigo = $pdo->prepare($sql_get_codigo);
                $stmt_get_codigo->execute([$producto['id_productos'], $codigo_institucion_sesion]);
                $producto_info = $stmt_get_codigo->fetch(PDO::FETCH_ASSOC);

                if (!$producto_info) {
                    throw new Exception("El producto con ID " . $producto['id_productos'] . " no existe en el catálogo.");
                }

                $codigo_producto_final = $producto_info['codigo_interno'];
                $precio_costo = $producto['precio_costo'] ?? 0;

                $sql_detalle = "INSERT INTO compras_detalle (compra_id, producto_id, cantidad, precio_costo, precio_unitario, subtotal, iva, descuento, unidad_medida) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_detalle = $pdo->prepare($sql_detalle);
                $stmt_detalle->execute([
                    $id_compra,
                    $codigo_producto_final,
                    $producto['cantidad'],
                    $precio_costo,
                    $producto['precio_unitario'],
                    $producto['subtotal'],
                    $producto['iva'] ?? 0,
                    $producto['descuento'] ?? 0,
                    $producto_info['unidad_medida'] // Se añade la unidad de medida
                ]);
            }
            
            $pdo->commit();
            echo json_encode(['respuesta' => true, 'mensaje' => 'Compra guardada y inventario actualizado exitosamente.', 'id_compra' => $id_compra]);

        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al guardar la compra: ' . $e->getMessage()]);
        }
        break;

    case 'obtenerCompra':
        $id_compra = $_POST['id_compra'] ?? '';
        try {
            $sql_cabecera = "SELECT * FROM compras_cabecera WHERE id_compra = ? AND codigo_institucion = ?";
            $stmt_cabecera = $pdo->prepare($sql_cabecera);
            $stmt_cabecera->execute([$id_compra, $codigo_institucion_sesion]);
            $compra = $stmt_cabecera->fetch(PDO::FETCH_ASSOC);
            
            if (!$compra) {
                throw new Exception("Compra no encontrada.");
            }

            $sql_detalle = "SELECT d.*, p.descripcion FROM compras_detalle d INNER JOIN catalogo_productos p ON d.producto_id = p.codigo_interno WHERE d.id_compra = ?";
            $stmt_detalle = $pdo->prepare($sql_detalle);
            $stmt_detalle->execute([$id_compra]);
            $detalle = $stmt_detalle->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['respuesta' => true, 'compra' => $compra, 'detalle' => $detalle]);

        } catch (Exception $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al obtener la compra: ' . $e->getMessage()]);
        }
        break;

    case 'actualizarCompra':
        $pdo->beginTransaction();
        try {
            $id_compra = $_POST['id_compra'] ?? '';
            $numero_documento = $_POST['numero_documento'] ?? '';
            $tipo_documento = $_POST['tipo_documento'] ?? '';
            $fecha_emision = $_POST['fecha_emision'] ?? date('Y-m-d');
            $id_proveedores = $_POST['id_proveedores'] ?? '';
            $condicion_pago = $_POST['condicion_pago'] ?? '';
            $plazo_pago = $_POST['plazo_pago'] ?? null;
            $fecha_vencimiento = $_POST['fecha_vencimiento'] ?? null;
            $total_compra = $_POST['total_compra'] ?? 0;
            $observaciones = $_POST['observaciones'] ?? '';
            $productos = json_decode($_POST['productos'], true);

            // Revertir el stock original
            $sql_detalle_original = "SELECT producto_id, cantidad FROM compras_detalle WHERE id_compra = ?";
            $stmt_detalle_original = $pdo->prepare($sql_detalle_original);
            $stmt_detalle_original->execute([$id_compra]);
            $detalle_original = $stmt_detalle_original->fetchAll(PDO::FETCH_ASSOC);

            foreach ($detalle_original as $item) {
                $sql_revertir_stock = "UPDATE catalogo_productos SET stock_actual = stock_actual - ? WHERE codigo_interno = ?";
                $stmt_revertir_stock = $pdo->prepare($sql_revertir_stock);
                $stmt_revertir_stock->execute([$item['cantidad'], $item['producto_id']]);
            }

            // Eliminar detalles de la compra original
            $sql_eliminar_detalle = "DELETE FROM compras_detalle WHERE id_compra = ?";
            $stmt_eliminar_detalle = $pdo->prepare($sql_eliminar_detalle);
            $stmt_eliminar_detalle->execute([$id_compra]);

            // Actualizar la cabecera de la compra
            $sql_cabecera = "UPDATE compras_cabecera SET numero_documento = ?, tipo_documento = ?, fecha_emision = ?, id_proveedores = ?, condicion_pago = ?, plazo_pago = ?, fecha_vencimiento = ?, total_compra = ?, observaciones = ? WHERE id_compra = ? AND codigo_institucion = ?";
            $stmt_cabecera = $pdo->prepare($sql_cabecera);
            $stmt_cabecera->execute([$numero_documento, $tipo_documento, $fecha_emision, $id_proveedores, $condicion_pago, $plazo_pago, $fecha_vencimiento, $total_compra, $observaciones, $id_compra, $codigo_institucion_sesion]);

            // Insertar los nuevos detalles de la compra
            foreach ($productos as $producto) {
                $sql_get_codigo = "SELECT codigo_interno, unidad_medida FROM catalogo_productos WHERE id_productos = ? AND codigo_institucion = ?";
                $stmt_get_codigo = $pdo->prepare($sql_get_codigo);
                $stmt_get_codigo->execute([$producto['id_productos'], $codigo_institucion_sesion]);
                $producto_info = $stmt_get_codigo->fetch(PDO::FETCH_ASSOC);

                if (!$producto_info) {
                    throw new Exception("El producto con ID " . $producto['id_productos'] . " no existe en el catálogo.");
                }

                $codigo_producto_final = $producto_info['codigo_interno'];
                $precio_costo = $producto['precio_costo'] ?? 0;
                
                $sql_detalle = "INSERT INTO compras_detalle (compra_id, producto_id, cantidad, precio_costo, precio_unitario, subtotal, iva, descuento, unidad_medida) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_detalle = $pdo->prepare($sql_detalle);
                $stmt_detalle->execute([
                    $id_compra,
                    $codigo_producto_final,
                    $producto['cantidad'],
                    $precio_costo,
                    $producto['precio_unitario'],
                    $producto['subtotal'],
                    $producto['iva'] ?? 0,
                    $producto['descuento'] ?? 0,
                    $producto_info['unidad_medida']
                ]);
            }

            $pdo->commit();
            echo json_encode(['respuesta' => true, 'mensaje' => 'Compra actualizada exitosamente.']);

        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al actualizar la compra: ' . $e->getMessage()]);
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
        $termino = $_POST['termino'] ?? '';
        try {
            $sql = "SELECT id_productos, codigo_interno, descripcion, unidad_medida, precio_costo, impuesto_aplicable, codigo_ganancia FROM catalogo_productos WHERE (id_productos = ? OR codigo_interno = ? OR codigo_barra = ?) AND codigo_institucion = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$termino, $termino, $termino, $codigo_institucion_sesion]);
            $producto = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($producto) {
                echo json_encode(['respuesta' => true, 'producto' => $producto]);
            } else {
                echo json_encode(['respuesta' => false, 'mensaje' => 'Producto no encontrado.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al buscar producto: ' . $e->getMessage()]);
        }
        break;
        
    case 'buscarProductoDescripcion':
        $draw = $_POST['draw'] ?? 1;
        $start = $_POST['start'] ?? 0;
        $length = $_POST['length'] ?? 10;
        $searchValue = $_POST['search']['value'] ?? '';
        $orderColumnIndex = $_POST['order'][0]['column'] ?? 0;
        $orderDir = $_POST['order'][0]['dir'] ?? 'asc';
        $columns = $_POST['columns'] ?? [];

        $orderColumns = [
            'id_productos', 'descripcion', 'precio_costo'
        ];
        $orderCol = $orderColumns[$orderColumnIndex] ?? 'id_productos';

        try {
            $sqlCount = "SELECT COUNT(id_productos) FROM catalogo_productos WHERE codigo_institucion = ?";
            $stmtCount = $pdo->prepare($sqlCount);
            $stmtCount->execute([$codigo_institucion_sesion]);
            $totalRecords = $stmtCount->fetchColumn();

            $sql = "SELECT id_productos, codigo_interno, descripcion, unidad_medida, precio_costo, impuesto_aplicable, codigo_ganancia
                    FROM catalogo_productos 
                    WHERE codigo_institucion = ?";
            
            $params = [$codigo_institucion_sesion];

            if (!empty($searchValue)) {
                $sql .= " AND (LOWER(descripcion) LIKE LOWER(?) OR LOWER(codigo_interno) LIKE LOWER(?))";
                $params[] = '%' . $searchValue . '%';
                $params[] = '%' . $searchValue . '%';
            }

            $sql .= " ORDER BY " . $orderCol . " " . strtoupper($orderDir);
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $length;
            $params[] = $start;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalFiltered = $totalRecords;
            if (!empty($searchValue)) {
                $sqlFilterCount = "SELECT COUNT(id_productos) FROM catalogo_productos WHERE codigo_institucion = ? AND (LOWER(descripcion) LIKE LOWER(?) OR LOWER(codigo_interno) LIKE LOWER(?))";
                $stmtFilterCount = $pdo->prepare($sqlFilterCount);
                $stmtFilterCount->execute([$codigo_institucion_sesion, '%' . $searchValue . '%', '%' . $searchValue . '%']);
                $totalFiltered = $stmtFilterCount->fetchColumn();
            }

            echo json_encode([
                "draw" => intval($draw),
                "recordsTotal" => intval($totalRecords),
                "recordsFiltered" => intval($totalFiltered),
                "data" => $productos
            ]);

        } catch (PDOException $e) {
            error_log("Error en buscarProductoDescripcion: " . $e->getMessage());
            echo json_encode([
                "draw" => intval($draw),
                "recordsTotal" => 0,
                "recordsFiltered" => 0,
                "data" => [],
                "error" => "Error al buscar productos: " . $e->getMessage()
            ]);
        }
        break;

    case 'obtenerDetalleImpuesto':
        $codigo_impuesto = $_POST['codigo_impuesto'] ?? '';
        try {
            $sql = "SELECT codigo, descripcion, porcentaje, tipo_impuesto, monto_fijo FROM cat_015 WHERE codigo = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$codigo_impuesto]);
            $impuesto = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($impuesto) {
                echo json_encode(['respuesta' => true, 'impuesto' => $impuesto]);
            } else {
                echo json_encode(['respuesta' => false, 'mensaje' => 'Impuesto no encontrado.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al obtener detalle del impuesto: ' . $e->getMessage()]);
        }
        break;
        
    case 'obtenerDetalleGanancia':
        $codigo_ganancia = $_POST['codigo_ganancia'] ?? '';
        try {
            $sql = "SELECT porcentaje FROM catalogo_ganancia WHERE codigo = ? AND codigo_institucion = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$codigo_ganancia, $codigo_institucion_sesion]);
            $ganancia = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($ganancia) {
                echo json_encode(['respuesta' => true, 'ganancia' => $ganancia]);
            } else {
                echo json_encode(['respuesta' => false, 'mensaje' => 'Ganancia no encontrada.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al obtener detalle de ganancia: ' . $e->getMessage()]);
        }
        break;

    case 'obtenerCatalogosCompra':
        try {
            $catalogos = [];
            $sql_tipos_doc = "SELECT codigo, descripcion FROM cat_002 ORDER BY descripcion";
            $catalogos['tipos_documento'] = $pdo->query($sql_tipos_doc)->fetchAll(PDO::FETCH_ASSOC);
            $sql_condiciones = "SELECT codigo, descripcion FROM cat_016 ORDER BY descripcion";
            $catalogos['condiciones_pago'] = $pdo->query($sql_condiciones)->fetchAll(PDO::FETCH_ASSOC);
            $sql_plazos = "SELECT codigo, descripcion FROM cat_018 ORDER BY descripcion";
            $catalogos['plazos_pago'] = $pdo->query($sql_plazos)->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['respuesta' => true, 'catalogos' => $catalogos]);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al obtener catálogos de compra: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['respuesta' => false, 'mensaje' => 'Acción no válida.']);
        break;
}
?>