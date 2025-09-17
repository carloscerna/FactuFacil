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

function generarCodigoProducto($pdo, $codigo_categoria, $codigo_institucion_sesion) {
    $nuevo_codigo = "";
    try {
        $pdo->beginTransaction();
        $codigo_tipo = 'PRODUCTO_' . $codigo_institucion_sesion;

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

        $correlativo_formateado = str_pad($nuevo_numero, 6, '0', STR_PAD_LEFT);
        $nuevo_codigo = $codigo_categoria . $correlativo_formateado;

       // $pdo->commit();
        return $nuevo_codigo;

    } catch (PDOException $e) {
        //$pdo->rollBack();
        return "Error en la generación del código: . $nuevo_codigo " . $e->getMessage();
    }
}

// Asegúrate de que la función generarCodigoProveedor esté disponible
function generarCodigoProveedor($pdo, $codigo_institucion_sesion) {
    $nuevo_codigo = "";
    try {
        $prefijo_tipo = 'PROV';
        $año_actual = date('y');

       
        $codigo_tipo_completo = $prefijo_tipo . '_' . $codigo_institucion_sesion;
        
        $sql = "SELECT ultimo_numero FROM correlativos WHERE codigo_tipo = ? FOR UPDATE";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$codigo_tipo_completo]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $ultimo_numero = $result ? $result['ultimo_numero'] : 0;
        $nuevo_numero = $ultimo_numero + 1;

        if ($result) {
            $sql_update = "UPDATE correlativos SET ultimo_numero = ? WHERE codigo_tipo = ?";
        } else {
            $sql_update = "INSERT INTO correlativos (ultimo_numero, codigo_tipo) VALUES (?, ?)";
        }
        
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([$nuevo_numero, $codigo_tipo_completo]);

        $correlativo_formateado = str_pad($nuevo_numero, 6, '0', STR_PAD_LEFT);
        $nuevo_codigo = $correlativo_formateado . $año_actual;

        return $nuevo_codigo;

    } catch (PDOException $e) {
        //$pdo->rollBack();
        return "Error en la generación del código: . $nuevo_codigo " . $e->getMessage();
    }
}

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
                $sql_get_info_producto = "SELECT codigo_interno, unidad_medida FROM catalogo_productos WHERE id_productos = ? AND codigo_institucion = ?";
                $stmt_get_info = $pdo->prepare($sql_get_info_producto);
                $stmt_get_info->execute([$producto['id_productos'], $codigo_institucion_sesion]);
                $producto_info = $stmt_get_info->fetch(PDO::FETCH_ASSOC);

                if (!$producto_info) {
                    throw new Exception("El producto con ID " . $producto['id_productos'] . " no existe en el catálogo.");
                }

                $sql_detalle = "INSERT INTO compras_detalle (id_compra, codigo_producto, cantidad, precio_costo, precio_unitario, subtotal, iva, descuento) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_detalle = $pdo->prepare($sql_detalle);
                $stmt_detalle->execute([
                    $id_compra,
                    $producto_info['codigo_interno'],
                    $producto['cantidad'],
                    $producto['precio_costo'] ?? 0,
                    $producto['precio_unitario'],
                    $producto['subtotal'],
                    $producto['iva'] ?? 0,
                    $producto['descuento'] ?? 0
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

            $sql_detalle = "SELECT d.*, p.descripcion, p.id_productos FROM compras_detalle d 
                            INNER JOIN catalogo_productos p ON d.codigo_producto = p.codigo_interno 
                            WHERE d.id_compra = ?";
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

            $sql_detalle_original = "SELECT codigo_producto, cantidad FROM compras_detalle WHERE id_compra = ?";
            $stmt_detalle_original = $pdo->prepare($sql_detalle_original);
            $stmt_detalle_original->execute([$id_compra]);
            $detalle_original = $stmt_detalle_original->fetchAll(PDO::FETCH_ASSOC);

            foreach ($detalle_original as $item) {
                $sql_revertir_stock = "UPDATE catalogo_productos SET stock_actual = stock_actual - ? WHERE codigo_interno = ?";
                $stmt_revertir_stock = $pdo->prepare($sql_revertir_stock);
                $stmt_revertir_stock->execute([$item['cantidad'], $item['codigo_producto']]);
            }

            $sql_eliminar_detalle = "DELETE FROM compras_detalle WHERE id_compra = ?";
            $stmt_eliminar_detalle = $pdo->prepare($sql_eliminar_detalle);
            $stmt_eliminar_detalle->execute([$id_compra]);

            $sql_cabecera = "UPDATE compras_cabecera SET numero_documento = ?, tipo_documento = ?, fecha_emision = ?, id_proveedores = ?, condicion_pago = ?, plazo_pago = ?, fecha_vencimiento = ?, total_compra = ?, observaciones = ? WHERE id_compra = ? AND codigo_institucion = ?";
            $stmt_cabecera = $pdo->prepare($sql_cabecera);
            $stmt_cabecera->execute([$numero_documento, $tipo_documento, $fecha_emision, $id_proveedores, $condicion_pago, $plazo_pago, $fecha_vencimiento, $total_compra, $observaciones, $id_compra, $codigo_institucion_sesion]);

            foreach ($productos as $producto) {
                $sql_get_info_producto = "SELECT codigo_interno, unidad_medida FROM catalogo_productos WHERE id_productos = ? AND codigo_institucion = ?";
                $stmt_get_info = $pdo->prepare($sql_get_info_producto);
                $stmt_get_info->execute([$producto['id_productos'], $codigo_institucion_sesion]);
                $producto_info = $stmt_get_info->fetch(PDO::FETCH_ASSOC);

                if (!$producto_info) {
                    throw new Exception("El producto con ID " . $producto['id_productos'] . " no existe en el catálogo.");
                }

                $sql_detalle = "INSERT INTO compras_detalle (id_compra, codigo_producto, cantidad, precio_costo, precio_unitario, subtotal, iva, descuento) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_detalle = $pdo->prepare($sql_detalle);
                $stmt_detalle->execute([
                    $id_compra,
                    $producto_info['codigo_interno'],
                    $producto['cantidad'],
                    $producto['precio_costo'] ?? 0,
                    $producto['precio_unitario'],
                    $producto['subtotal'],
                    $producto['iva'] ?? 0,
                    $producto['descuento'] ?? 0
                ]);
            }

            $pdo->commit();
            echo json_encode(['respuesta' => true, 'mensaje' => 'Compra actualizada exitosamente.']);

        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al actualizar la compra: ' . $e->getMessage()]);
        }
        break;

    
        
        case 'guardarCompraProcesada':
            $pdo->beginTransaction();
            try {
                $compra_data = json_decode($_POST['compra_data'], true);
                $productos_data = json_decode($_POST['productos_data'], true);
    
                // Validar que el proveedor exista
                $sql_proveedor = "SELECT id_proveedores FROM proveedores WHERE nit = ? AND codigo_institucion = ?";
                $stmt_proveedor = $pdo->prepare($sql_proveedor);
                $stmt_proveedor->execute([$compra_data['proveedor_nit'], $codigo_institucion_sesion]);
                $proveedor_id = $stmt_proveedor->fetchColumn();
    
                if (!$proveedor_id) {
                    // Si no existe, lo crea
                    $codigo_proveedor_generado = generarCodigoProveedor($pdo, $codigo_institucion_sesion);
                    if (strpos($codigo_proveedor_generado, 'Error') !== false) {
                         throw new Exception("No se pudo generar el código para el nuevo proveedor. Motivo: " . $codigo_proveedor_generado);
                    }
                    $sql_insert_proveedor = "INSERT INTO proveedores (codigo_institucion, codigo, nit, nombre_empresa) VALUES (?, ?, ?, ?)";
                    $stmt_insert_proveedor = $pdo->prepare($sql_insert_proveedor);
                    $stmt_insert_proveedor->execute([$codigo_institucion_sesion, $codigo_proveedor_generado, $compra_data['proveedor_nit'], $compra_data['proveedor_nombre']]);
                    $proveedor_id = $pdo->lastInsertId('proveedores_id_proveedores_seq');
                }
    
                // Insertar la cabecera de la compra
                $sql_cabecera = "INSERT INTO compras_cabecera (codigo_institucion, numero_documento, tipo_documento, fecha_emision, id_proveedores, condicion_pago, total_compra, observaciones, total_iva, total_descuento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_cabecera = $pdo->prepare($sql_cabecera);
                $stmt_cabecera->execute([
                    $codigo_institucion_sesion,
                    $compra_data['numero_documento'],
                    $compra_data['tipo_documento'],
                    $compra_data['fecha_emision'],
                    $proveedor_id,
                    $compra_data['condicion_pago'],
                    $compra_data['total_compra'],
                    $compra_data['observaciones'],
                    $compra_data['total_iva'],
                    $compra_data['total_descuento']
                ]);
                $id_compra = $pdo->lastInsertId('compras_cabecera_id_compra_seq');
    
                // Insertar los productos y actualizar el catálogo
                foreach ($productos_data as $producto) {
                    // Si el producto no estaba en el catálogo, se crea
                    if (empty($producto['id_productos'])) {
                            // si no trae categoría, le asignamos una por defecto
                                if (empty($producto['codigo_categoria'])) {
                                    $producto['codigo_categoria'] = 'GEN'; // <-- categoría genérica
                                }
                        $codigo_producto_generado = generarCodigoProducto($pdo, $producto['codigo_categoria'], $codigo_institucion_sesion);
                        $sql_insert_producto = "INSERT INTO catalogo_productos (codigo_interno, codigo_institucion, descripcion, precio_costo, impuesto_aplicable, unidad_medida, codigo_proveedor) VALUES (?, ?, ?, ?, ?, ?, ?)";
                        $stmt_insert_producto = $pdo->prepare($sql_insert_producto);
                        $stmt_insert_producto->execute([
                            $codigo_producto_generado, 
                            $codigo_institucion_sesion, 
                            $producto['descripcion'], 
                            $producto['precio_costo'], 
                            $producto['impuesto_aplicable'], 
                            $producto['unidad_medida'],
                            $producto['codigo_proveedor']
                        ]);
                        $producto['id_productos'] = $pdo->lastInsertId('productos_id_productos_seq');
                    } else {
                        // Si ya existe, se actualiza el precio de costo
                        $sql_update_producto = "UPDATE catalogo_productos SET precio_costo = ? WHERE id_productos = ?";
                        $stmt_update_producto = $pdo->prepare($sql_update_producto);
                        $stmt_update_producto->execute([$producto['precio_costo'], $producto['id_productos']]);
                    }
                    
                    // Insertar el detalle de la compra
                    $sql_detalle = "INSERT INTO compras_detalle (id_compra, codigo_producto, cantidad, precio_costo, precio_unitario, subtotal, iva, descuento) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt_detalle = $pdo->prepare($sql_detalle);
                    $stmt_detalle->execute([
                        $id_compra,
                        $producto['codigo_interno'],
                        $producto['cantidad'],
                        $producto['precio_costo'],
                        $producto['precio_unitario'],
                        $producto['subtotal'],
                        $producto['iva'],
                        $producto['descuento']
                    ]);
                }
                
                $pdo->commit();
                echo json_encode(['respuesta' => true, 'mensaje' => 'Compra guardada exitosamente.', 'id_compra' => $id_compra]);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['respuesta' => false, 'mensaje' => 'Error al guardar la compra: ' . $e->getMessage() . $codigo_producto_generado]);
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

    case 'listarCompras':
        try {
            $sql = "SELECT 
                        c.id_compra,
                        c.numero_documento,
                        c.fecha_emision,
                        p.nombre_empresa AS proveedor_nombre,
                        c.total_compra
                    FROM compras_cabecera c
                    INNER JOIN proveedores p ON c.id_proveedores = p.id_proveedores
                    WHERE c.codigo_institucion = :codigo_institucion_sesion
                    ORDER BY c.fecha_emision DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':codigo_institucion_sesion', $codigo_institucion_sesion, PDO::PARAM_STR);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['data' => $data]);
        } catch (PDOException $e) {
            echo json_encode(['data' => []]);
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

/*
        case 'procesarJsonDte':
            try {
                if (!isset($_FILES['json_file'])) {
                    throw new Exception("No se ha subido ningún archivo.");
                }
                if ($_FILES['json_file']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("Error en la subida del archivo. Código: " . $_FILES['json_file']['error']);
                }
    
                $json_data = file_get_contents($_FILES['json_file']['tmp_name']);
                $json_data_cleaned = utf8_encode($json_data);
                $dte = json_decode($json_data_cleaned, true);
                
                if ($dte === null && json_last_error() !== JSON_ERROR_NONE) {
                    $error_mensaje = "Error al decodificar el archivo JSON: " . json_last_error_msg();
                    throw new Exception($error_mensaje);
                }
    
                $mapeo = [
                    'numero_documento' => $dte['identificacion']['numeroControl'] ?? null,
                    'tipo_documento' => $dte['identificacion']['tipoDte'] ?? null,
                    'fecha_emision' => $dte['identificacion']['fecEmi'] ?? null,
                    'condicion_pago' => $dte['resumen']['condicionOperacion'] ?? null,
                    'proveedor_nit' => $dte['emisor']['nit'] ?? null,
                    'proveedor_nombre' => $dte['emisor']['nombre'] ?? null,
                    'observaciones' => $dte['extension']['observaciones'] ?? null,
                    'productos_dte' => $dte['cuerpoDocumento'] ?? [],
                    'total_compra' => $dte['resumen']['totalPagar'] ?? 0,
                    'total_iva' => $dte['resumen']['totalIva'] ?? 0,
                    'total_descuento' => $dte['resumen']['totalDescu'] ?? 0
                ];
    
                $productos = [];
                foreach ($mapeo['productos_dte'] as $item) {
                    $codigo_proveedor_producto = $item['codigo'] ?? null;
                    $descripcion_producto = $item['descripcion'] ?? null;
                    
                    // Buscar el producto en tu catálogo
                    $sql_find_product = "SELECT id_productos, codigo_interno, impuesto_aplicable, codigo_ganancia, precio_costo, unidad_medida FROM catalogo_productos WHERE codigo_interno = ? AND codigo_institucion = ?";
                    $stmt_find_product = $pdo->prepare($sql_find_product);
                    $stmt_find_product->execute([$codigo_proveedor_producto, $codigo_institucion_sesion]);
                    $producto_db = $stmt_find_product->fetch(PDO::FETCH_ASSOC);
    
                    $producto_mapeado = [
                        'id_productos' => $producto_db['id_productos'] ?? null,
                        'codigo_interno' => $producto_db['codigo_interno'] ?? null,
                        'codigo_proveedor' => $codigo_proveedor_producto, // Guardamos el código del proveedor
                        'descripcion' => $descripcion_producto,
                        'cantidad' => $item['cantidad'] ?? 0,
                        'precio_costo' => $item['precioUni'] ?? 0,
                        'precio_unitario' => $item['precioUni'] ?? 0,
                        'iva' => $item['ivaItem'] ?? 0,
                        'descuento' => $item['montoDescu'] ?? 0,
                        'impuesto_aplicable' => $producto_db['impuesto_aplicable'] ?? null,
                        'unidad_medida' => $producto_db['unidad_medida'] ?? null,
                        'en_catalogo' => $producto_db ? true : false
                    ];
                    $productos[] = $producto_mapeado;
                }
                
                // Devolver los datos del DTE al frontend para la vista previa
                echo json_encode(['respuesta' => true, 'compra' => $mapeo, 'productos' => $productos]);
    
            } catch (Exception $e) {
                echo json_encode(['respuesta' => false, 'mensaje' => 'Error al procesar el DTE: ' . $e->getMessage()]);
            }
            break;

            */
            case 'procesarJsonDte':
                try {
                    if (!isset($_FILES['json_file'])) {
                        throw new Exception("No se ha subido ningún archivo.");
                    }
                    if ($_FILES['json_file']['error'] !== UPLOAD_ERR_OK) {
                        throw new Exception("Error en la subida del archivo. Código: " . $_FILES['json_file']['error']);
                    }

                    $json_data = file_get_contents($_FILES['json_file']['tmp_name']);
                    $json_data = mb_convert_encoding($json_data, 'UTF-8', 'UTF-8, ISO-8859-1');
                    $json_data = preg_replace('/^\xEF\xBB\xBF/', '', $json_data);

                    $dte = json_decode($json_data, true);


            
                    //$json_data = file_get_contents($_FILES['json_file']['tmp_name']);
                    //$json_data_cleaned = utf8_encode($json_data);
                   // $dte = json_decode($json_data_cleaned, true);
            
                    if ($dte === null && json_last_error() !== JSON_ERROR_NONE) {
                        throw new Exception("Error al decodificar el archivo JSON: " . json_last_error_msg());
                    }
            
                    // =============================
                    //  VALIDACIÓN de secciones clave
                    // =============================
                        // Secciones obligatorias (las claves pueden tener alias)
                        $secciones_obligatorias = [
                            "identificacion"   => ["identificacion"],
                            "emisor"           => ["emisor"],
                            "cuerpoDocumento"  => ["cuerpoDocumento"],
                            "resumen"          => ["resumen"],
                            "firma"            => ["firmaElectronica", "firma", "Firma"], // acepta cualquiera
                            "selloRecibido"    => ["selloRecibido", "SelloRecibido"]
                        ];

                        $errores = [];
                        foreach ($secciones_obligatorias as $nombre_logico => $variantes) {
                            $encontrada = false;
                            foreach ($variantes as $variante) {
                                if (isset($dte[$variante])) {
                                    // normalizamos el nombre para el mapeo
                                    $dte[$nombre_logico] = $dte[$variante];
                                    $encontrada = true;
                                    break;
                                }
                            }
                            if (!$encontrada) {
                                $errores[] = "Falta la sección: $nombre_logico";
                            }
                        }
            
                    if (!empty($errores)) {
                        echo json_encode([
                            'respuesta' => false,
                            'errores' => $errores
                        ]);
                        exit;
                    }
            
                    // =============================
                    //  MAPEO CABECERA (compra)
                    // =============================
                    $mapeo = [
                        // IDENTIFICACION
                        'numero_control'     => $dte['identificacion']['numeroControl'] ?? null,
                        'tipo_dte'           => $dte['identificacion']['tipoDte'] ?? null,
                        'fecha_emision'      => $dte['identificacion']['fecEmi'] ?? null,
                        'hora_emision'       => $dte['identificacion']['horEmi'] ?? null,
                        'codigo_generacion'  => $dte['identificacion']['codigoGeneracion'] ?? null,
                        'ambiente'           => $dte['identificacion']['ambiente'] ?? null,
                        'tipo_modelo'        => $dte['identificacion']['tipoModelo'] ?? null,
                        'tipo_operacion'     => $dte['identificacion']['tipoOperacion'] ?? null,
                        'tipo_moneda'        => $dte['identificacion']['tipoMoneda'] ?? null,
            
                        // EMISOR
                        'emisor_nit'         => $dte['emisor']['nit'] ?? null,
                        'emisor_nrc'         => $dte['emisor']['nrc'] ?? null,
                        'emisor_nombre'      => $dte['emisor']['nombre'] ?? null,
                        'emisor_nombre_comercial' => $dte['emisor']['nombreComercial'] ?? null,
                        'emisor_cod_actividad'    => $dte['emisor']['codActividad'] ?? null,
                        'emisor_desc_actividad'   => $dte['emisor']['descActividad'] ?? null,
                        'emisor_tipo_establecimiento' => $dte['emisor']['tipoEstablecimiento'] ?? null,
                        'emisor_telefono'    => $dte['emisor']['telefono'] ?? null,
                        'emisor_correo'      => $dte['emisor']['correo'] ?? null,
                        'emisor_direccion'   => isset($dte['emisor']['direccion']) ? json_encode($dte['emisor']['direccion']) : null,
            
                        // RESUMEN
                        'total_gravada'      => $dte['resumen']['totalGravada'] ?? 0,
                        'total_exenta'       => $dte['resumen']['totalExenta'] ?? 0,
                        'total_no_suj'       => $dte['resumen']['totalNoSuj'] ?? 0,
                        'total_iva'          => $dte['resumen']['totalIva'] ?? 0,
                        'total_descuento'    => $dte['resumen']['totalDescu'] ?? 0,
                        'total_pagar'        => $dte['resumen']['totalPagar'] ?? 0,
            
                        // FIRMA Y SELLO
                        'firma_electronica'  => $dte['Firma'] ?? null,
                        'sello_recibido'     => $dte['selloRecibido'] ?? null,
            
                        // OBSERVACIONES (si vienen)
                        'observaciones'      => $dte['extension']['observaciones'] ?? null,
            
                        // Guardamos cuerpoDocumento crudo por si acaso
                        'productos_dte'      => $dte['cuerpoDocumento'] ?? []
                    ];
            
                    // =============================
                    //  MAPEO DETALLE (productos)
                    // =============================
                    $productos = [];
                    foreach ($mapeo['productos_dte'] as $item) {
                        $codigo_proveedor_producto = $item['codigo'] ?? null;
                        $descripcion_producto      = $item['descripcion'] ?? null;
            
                        // Buscar producto en catálogo interno
                        $sql_find_product = "SELECT id_productos, codigo_interno, impuesto_aplicable, codigo_ganancia, precio_costo, unidad_medida 
                                             FROM catalogo_productos 
                                             WHERE codigo_interno = ? AND codigo_institucion = ?";
                        $stmt_find_product = $pdo->prepare($sql_find_product);
                        $stmt_find_product->execute([$codigo_proveedor_producto, $codigo_institucion_sesion]);
                        $producto_db = $stmt_find_product->fetch(PDO::FETCH_ASSOC);
            
                        $producto_mapeado = [
                            'id_productos'       => $producto_db['id_productos'] ?? null,
                            'codigo_interno'     => $producto_db['codigo_interno'] ?? null,
                            'codigo_proveedor'   => $codigo_proveedor_producto,
                            'descripcion'        => $descripcion_producto,
                            'cantidad'           => $item['cantidad'] ?? 0,
                            'precio_unitario'    => $item['precioUni'] ?? 0,
                            'precio_costo'       => $item['precioUni'] ?? 0,
                            'iva'                => $item['ivaItem'] ?? 0,
                            'descuento'          => $item['montoDescu'] ?? 0,
                            'venta_gravada'      => $item['ventaGravada'] ?? 0,
                            'venta_exenta'       => $item['ventaExenta'] ?? 0,
                            'venta_no_suj'       => $item['ventaNoSuj'] ?? 0,
                            'unidad_medida'      => $producto_db['unidad_medida'] ?? ($item['uniMedida'] ?? null),
                            'impuesto_aplicable' => $producto_db['impuesto_aplicable'] ?? null,
                            'en_catalogo'        => $producto_db ? true : false
                        ];
                        $productos[] = $producto_mapeado;
                    }
            
                    // =============================
                    //  RESPUESTA
                    // =============================
                    echo json_encode([
                        'respuesta' => true,
                        'compra'    => $mapeo,
                        'productos' => $productos
                    ]);
            
                } catch (Exception $e) {
                    echo json_encode([
                        'respuesta' => false,
                        'error'     => $e->getMessage()
                    ]);
                }
                break;
            
             
    default:
        echo json_encode(['respuesta' => false, 'mensaje' => 'Acción no válida.']);
        break;
}
?>