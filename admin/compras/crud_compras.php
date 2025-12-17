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
include($path_root."/FactuFacil/admin/contabilidad/funciones/contabilidad_api.php");
$pdo = $dblink;
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

$codigo_perfil_sesion = $_SESSION['codigo_perfil'] ?? '';
$codigo_institucion_sesion = $_SESSION['codigo_institucion'] ?? '';
$usuario_activo = $_SESSION['userNombre'] ?? 'Sistema';


function generarCodigoProducto($pdo, $codigo_categoria, $codigo_institucion_sesion) {
    $nuevo_codigo = "";
    try {
        //$pdo->beginTransaction();
        $codigo_tipo = 'PRODUCTO_' . $codigo_institucion_sesion;

        $sql = "SELECT ultimo_numero FROM correlativos WHERE codigo_tipo = ? FOR UPDATE";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$codigo_tipo]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $ultimo_numero = $result ? (int)$result['ultimo_numero'] : 0;
        $nuevo_numero  = $ultimo_numero + 1;

        if ($result) {
            $sql_update = "UPDATE correlativos SET ultimo_numero = ? WHERE codigo_tipo = ?";
        } else {
            $sql_update = "INSERT INTO correlativos (ultimo_numero, codigo_tipo) VALUES (?, ?)";
        }

        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([$nuevo_numero, $codigo_tipo]);

        // Armar el nuevo código
        $correlativo_formateado = str_pad($nuevo_numero, 6, '0', STR_PAD_LEFT);
        $nuevo_codigo = $codigo_categoria . $correlativo_formateado;

       // $pdo->commit(); // ✅ IMPORTANTE: guardar la transacción
        return $nuevo_codigo;

    } catch (PDOException $e) {
        //$pdo->rollBack(); // ✅ liberar transacción si hay error
        return "Error en la generación del código: $nuevo_codigo " . $e->getMessage();
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

        // Si hay plazo pero no fecha vencimiento, calcularla
        if ($plazo_pago && !$fecha_vencimiento) {
            $fecha_vencimiento = date('Y-m-d', strtotime("$fecha_emision + $plazo_pago days"));
        }
        
        // --- ACUMULADORES CONTABLES ---
        $asiento_total_neto = 0;
        $asiento_total_iva = 0;
        
        // ----------------------------------------------------------------------
        // 1. INSERCIÓN DE CABECERA
        // ----------------------------------------------------------------------

        $sql_cabecera = "INSERT INTO compras_cabecera 
            (codigo_institucion, numero_documento, tipo_documento, fecha_emision, id_proveedores, condicion_pago, plazo_pago, fecha_vencimiento, total_compra, observaciones) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_cabecera = $pdo->prepare($sql_cabecera);
        $stmt_cabecera->execute([
            $codigo_institucion_sesion,
            $numero_documento,
            $tipo_documento,
            $fecha_emision,
            $id_proveedores,
            $condicion_pago,
            $plazo_pago,
            $fecha_vencimiento,
            $total_compra,
            $observaciones
        ]);
        $id_compra = $pdo->lastInsertId('compras_cabecera_id_compra_seq');

        // ----------------------------------------------------------------------
        // 2. INSERCIÓN DE DETALLE Y CÁLCULO DE TOTALES CONTABLES
        // ----------------------------------------------------------------------
        foreach ($productos as $producto) {
            $sql_get_info_producto = "SELECT codigo_interno, unidad_medida FROM catalogo_productos WHERE id_productos = ? AND codigo_institucion = ?";
            $stmt_get_info = $pdo->prepare($sql_get_info_producto);
            $stmt_get_info->execute([$producto['id_productos'], $codigo_institucion_sesion]);
            $producto_info = $stmt_get_info->fetch(PDO::FETCH_ASSOC);

            if (!$producto_info) {
                throw new Exception("El producto con ID " . $producto['id_productos'] . " no existe en el catálogo.");
            }

            // Cálculos
            $precio_unitario_con_iva = floatval($producto['precio_unitario']); // Precio con IVA recibido
            $cantidad = floatval($producto['cantidad']);
            
            $precio_costo_neto = round($precio_unitario_con_iva / 1.13, 4); // Valor Neto (Base)
            $iva_unitario = round($precio_unitario_con_iva - $precio_costo_neto, 4); // IVA Unitario

            // Acumulación de totales para el asiento
            $asiento_total_neto += $precio_costo_neto * $cantidad;
            $asiento_total_iva += $iva_unitario * $cantidad;
            
            // ... (Resto de tu lógica de ganancia y cálculo de subtotal)

            $sql_detalle = "INSERT INTO compras_detalle 
                (id_compra, codigo_producto, cantidad, precio_unitario, subtotal, iva, descuento, ventas_gravada) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt_detalle = $pdo->prepare($sql_detalle);
            $stmt_detalle->execute([
                $id_compra,
                $producto_info['codigo_interno'],
                $cantidad,
                number_format($producto['precio_unitario'], 4, '.', ''), // Tu formato original
                number_format($producto['subtotal'], 4, '.', ''),        // Tu formato original
                $producto['iva'] ?? 0,
                $producto['descuento'] ?? 0,
                $producto['subtotal'] ?? 0
            ]);

        }
        
        // Redondeo final de totales contables
        $asiento_total_neto = round($asiento_total_neto, 2);
        $asiento_total_iva = round($asiento_total_iva, 2);
        
        // El total a pagar debe ser el total del documento
        $asiento_total_compra = round($total_compra, 2); 
        
        // --- 3. CREACIÓN DEL ASIENTO CONTABLE (PLAN B) ---

        // A. Obtener Claves de Mapeo
        $id_inventario = obtenerIdCuentaPorMapeo($pdo, $codigo_institucion_sesion, 'INVENTARIO_MERCADERIA');
        $id_iva_credito = obtenerIdCuentaPorMapeo($pdo, $codigo_institucion_sesion, 'IVA_CREDITO_FISCAL');
        
        // Usamos una lógica simple: si la condición es CRÉDITO, afectamos proveedores, sino, CAJA.
        $cuenta_credito_pago = ($condicion_pago === 'CREDITO') 
                                ? obtenerIdCuentaPorMapeo($pdo, $codigo_institucion_sesion, 'PROVEEDORES_CP') 
                                : obtenerIdCuentaPorMapeo($pdo, $codigo_institucion_sesion, 'CAJA_PAGOS_COMPRA');
        
        // B. Construir el asiento
        $datos_encabezado = [
            'fechaAsiento' => $fecha_emision, 
            'tipoAsiento' => 'Egreso', 
            'concepto' => "Registro manual de Compra Doc. No. " . $numero_documento,
            'usuarioRegistro' => $usuario_activo
        ];

        $datos_detalle = [
            // LÍNEA 1: DÉBITO - Inventario/Gasto (Valor Neto)
            ['cuenta_id' => $id_inventario, 'debito' => $asiento_total_neto, 'credito' => 0.00],
            
            // LÍNEA 2: DÉBITO - IVA Crédito Fiscal
            ['cuenta_id' => $id_iva_credito, 'debito' => $asiento_total_iva, 'credito' => 0.00],
            
            // LÍNEA 3: CRÉDITO - Pago (Proveedor o Caja/Banco)
            ['cuenta_id' => $cuenta_credito_pago, 'debito' => 0.00, 'credito' => $asiento_total_compra],
        ];

        // D. Registrar el asiento
        $resultado_contable = registrarAsientoAutomatico(
            $pdo, 
            $codigo_institucion_sesion, 
            $datos_encabezado, 
            $datos_detalle
        );

        if (!$resultado_contable['respuesta']) {
            // Si el asiento falla, lanza una excepción para hacer ROLLBACK de TODA la compra.
            throw new Exception("Error Contable: El asiento no pudo registrarse. " . $resultado_contable['mensaje']);
        }
        
        // E. VINCULAR EL ASIENTO A LA CABECERA
        $sql_vincular_asiento = "UPDATE compras_cabecera SET asiento_id = ? WHERE id_compra = ?";
        $pdo->prepare($sql_vincular_asiento)->execute([$resultado_contable['numero_asiento'], $id_compra]);
        
        // ----------------------------------------------------------------------
        // 4. COMMIT FINAL
        // ----------------------------------------------------------------------
        $pdo->commit();
        echo json_encode([
            'respuesta' => true,
            'mensaje' => 'Compra guardada y asiento contable No. ' . $resultado_contable['numero_asiento'] . ' registrado correctamente.',
            'id_compra' => $id_compra
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode([
            'respuesta' => false,
            'mensaje' => 'Error al guardar la compra: ' . $e->getMessage()
        ]);
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
                $total_compra = $_POST['total_compra'] ?? 0; // Total FINAL de la compra (incluye todo)
                $observaciones = $_POST['observaciones'] ?? '';
                $productos = json_decode($_POST['productos'], true);
        
                $usuario_activo = $_SESSION['userNombre'] ?? 'Sistema';
        
                // --- ACUMULADORES CONTABLES ---
                $nuevo_total_neto = 0; // Gravado + Exento + No Sujeto
                $nuevo_total_iva = 0;
                
                // --- 1. REVERSIÓN DE STOCK Y ELIMINACIÓN DE DETALLE ORIGINAL ---
                
                // Revertir stock de detalle original
                $sql_detalle_original = "SELECT codigo_producto, cantidad FROM compras_detalle WHERE id_compra = ?";
                $stmt_detalle_original = $pdo->prepare($sql_detalle_original);
                $stmt_detalle_original->execute([$id_compra]);
                $detalle_original = $stmt_detalle_original->fetchAll(PDO::FETCH_ASSOC);
        
                foreach ($detalle_original as $item) {
                    $sql_revertir_stock = "UPDATE catalogo_productos SET stock_actual = stock_actual - ? WHERE codigo_interno = ?";
                    $stmt_revertir_stock = $pdo->prepare($sql_revertir_stock);
                    $stmt_revertir_stock->execute([$item['cantidad'], $item['codigo_producto']]);
                }
        
                // Eliminar detalle previo
                $sql_eliminar_detalle = "DELETE FROM compras_detalle WHERE id_compra = ?";
                $stmt_eliminar_detalle = $pdo->prepare($sql_eliminar_detalle);
                $stmt_eliminar_detalle->execute([$id_compra]);
        
                // --- 2. OBTENER ID DEL ASIENTO ORIGINAL Y ANULARLO ---
                
                // Se asume que compras_cabecera tiene la columna asiento_id
                $sql_get_asiento = "SELECT asiento_id FROM compras_cabecera WHERE id_compra = ?";
                $stmt_get_asiento = $pdo->prepare($sql_get_asiento);
                $stmt_get_asiento->execute([$id_compra]);
                $asiento_id_original = $stmt_get_asiento->fetchColumn();
                
                if ($asiento_id_original) {
                    $resultado_anulacion = anularAsientoContable($pdo, $asiento_id_original, $usuario_activo);
                    if (!$resultado_anulacion['respuesta']) {
                        // Si la anulación falla, abortamos TODA la actualización.
                        throw new Exception("Error Contable Crítico: Falló la anulación del asiento anterior: " . $resultado_anulacion['mensaje']);
                    }
                }
        
                // --- 3. ACTUALIZAR CABECERA E INSERTAR NUEVO DETALLE ---
        
                // Actualizar cabecera
                $sql_cabecera = "UPDATE compras_cabecera 
                                 SET numero_documento = ?, tipo_documento = ?, fecha_emision = ?, 
                                     id_proveedores = ?, condicion_pago = ?, plazo_pago = ?, fecha_vencimiento = ?, 
                                     total_compra = ?, observaciones = ? 
                                 WHERE id_compra = ? AND codigo_institucion = ?";
                $stmt_cabecera = $pdo->prepare($sql_cabecera);
                $stmt_cabecera->execute([
                    $numero_documento, $tipo_documento, $fecha_emision, $id_proveedores,
                    $condicion_pago, $plazo_pago, $fecha_vencimiento, $total_compra,
                    $observaciones, $id_compra, $codigo_institucion_sesion
                ]);
        
                // Insertar nuevo detalle y recalcular totales
                foreach ($productos as $producto) {
                    $sql_get_info_producto = "SELECT codigo_interno, unidad_medida 
                                              FROM catalogo_productos 
                                              WHERE id_productos = ? AND codigo_institucion = ?";
                    $stmt_get_info = $pdo->prepare($sql_get_info_producto);
                    $stmt_get_info->execute([$producto['id_productos'], $codigo_institucion_sesion]);
                    $producto_info = $stmt_get_info->fetch(PDO::FETCH_ASSOC);
        
                    if (!$producto_info) {
                        throw new Exception("El producto con ID " . $producto['id_productos'] . " no existe en el catálogo.");
                    }
        
                    // --- Cálculos (Tu lógica original) ---
                    $precio_unitario = floatval($producto['precio_unitario']); // viene con IVA
                    $precio_costo = round($precio_unitario / 1.13, 4);          // sin IVA
                    $iva = round($precio_unitario - $precio_costo, 4);
        
                    $impuesto_aplicable = '20'; // cat_015 (IVA 13%)
                    $codigo_ganancia = '02';    // fijo
        
                    $porcentaje_ganancia = ($codigo_ganancia === '01') ? 0.30 : 0.0;
                    $precio_unitario_final = $precio_costo * (1 + 0.13) * (1 + $porcentaje_ganancia);
        
                    $subtotal = $producto['cantidad'] * $precio_unitario_final;
                    
                    // ACUMULACIÓN PARA EL ASIENTO
                    $nuevo_total_iva += $iva * $producto['cantidad'];
                    $nuevo_total_neto += $precio_costo * $producto['cantidad']; // Usamos el costo (valor neto)
                    
                    // --- Inserción de Detalle (Tu código original) ---
                    $sql_detalle = "INSERT INTO compras_detalle 
                        (id_compra, codigo_producto, cantidad, precio_costo, precio_unitario, subtotal, iva, descuento, impuesto_aplicable, codigo_ganancia) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt_detalle = $pdo->prepare($sql_detalle);
                    $stmt_detalle->execute([
                        $id_compra,
                        $producto_info['codigo_interno'],
                        $producto['cantidad'],
                        $precio_costo,
                        $precio_unitario_final,
                        $subtotal,
                        $iva,
                        $producto['descuento'] ?? 0,
                        $impuesto_aplicable,
                        $codigo_ganancia
                    ]);
                }
                
                // Redondeo final de totales contables
                $nuevo_total_iva = round($nuevo_total_iva, 2);
                $nuevo_total_neto = round($nuevo_total_neto, 2);
                $nuevo_total_compra = round($nuevo_total_neto + $nuevo_total_iva, 2); // El total pagado debe cuadrar
        
                // --- 4. CREACIÓN DEL NUEVO ASIENTO CON DATOS CORREGIDOS ---
        
                // A. Obtener Claves de Mapeo
                $id_inventario = obtenerIdCuentaPorMapeo($pdo, $codigo_institucion_sesion, 'INVENTARIO_MERCADERIA');
                $id_iva_credito = obtenerIdCuentaPorMapeo($pdo, $codigo_institucion_sesion, 'IVA_CREDITO_FISCAL');
                $id_proveedores_cp = obtenerIdCuentaPorMapeo($pdo, $codigo_institucion_sesion, 'PROVEEDORES_CP');
                
                // B. Construir el nuevo asiento
                $datos_encabezado_nuevo = [
                    'fechaAsiento' => $fecha_emision, 
                    'tipoAsiento' => 'Egreso', 
                    'concepto' => "Corrección/Actualización de Compra No. " . $numero_documento,
                    'usuarioRegistro' => $usuario_activo
                ];
        
                $datos_detalle_nuevo = [
                    // DÉBITO - Inventario/Gasto (Valor Neto)
                    ['cuenta_id' => $id_inventario, 'debito' => $nuevo_total_neto, 'credito' => 0.00],
                    
                    // DÉBITO - IVA Crédito Fiscal
                    ['cuenta_id' => $id_iva_credito, 'debito' => $nuevo_total_iva, 'credito' => 0.00],
                    
                    // CRÉDITO - Proveedores (Total a pagar)
                    ['cuenta_id' => $id_proveedores_cp, 'debito' => 0.00, 'credito' => $nuevo_total_compra],
                ];
        
                // C. Registrar el nuevo asiento
                $resultado_contable_nuevo = registrarAsientoAutomatico(
                    $pdo, 
                    $codigo_institucion_sesion, 
                    $datos_encabezado_nuevo, 
                    $datos_detalle_nuevo
                );
        
                if (!$resultado_contable_nuevo['respuesta']) {
                    throw new Exception("Error Contable: No se pudo registrar el nuevo asiento corregido. " . $resultado_contable_nuevo['mensaje']);
                }
        
                // D. Actualizar el ID del asiento en compras_cabecera (CRÍTICO)
                $sql_update_asiento_id = "UPDATE compras_cabecera SET asiento_id = ? WHERE id_compra = ?";
                $pdo->prepare($sql_update_asiento_id)->execute([$resultado_contable_nuevo['numero_asiento'], $id_compra]);
        
        
                // --- 5. COMMIT FINAL ---
                $pdo->commit();
                echo json_encode([
                    'respuesta' => true, 
                    'mensaje' => 'Compra y contabilidad actualizadas exitosamente. Asiento corregido No. ' . $resultado_contable_nuevo['numero_asiento'],
                    'id_compra' => $id_compra
                ]);
        
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode([
                    'respuesta' => false, 
                    'mensaje' => 'Error al actualizar la compra: ' . $e->getMessage()
                ]);
            }
            break;


    
        
        case 'guardarCompraProcesada':

    $pdo->beginTransaction();
    try {
            // AGREGAR ESTA LÍNEA AQUÍ:
        $usuario_activo = $_SESSION['userNombre'] ?? 'Sistema';

        $compra_data = json_decode($_POST['compra_data'], true);
        $productos_data = json_decode($_POST['productos_data'], true);

        if (!$compra_data || !$productos_data) {
            throw new Exception("Datos de la compra inválidos.");
        }
        // 1. Validar/crear proveedor
        $sql_proveedor = "SELECT id_proveedores FROM proveedores WHERE nit = ? AND codigo_institucion = ?";
        $stmt_proveedor = $pdo->prepare($sql_proveedor);
        $stmt_proveedor->execute([$compra_data['emisor_nit'], $codigo_institucion_sesion]);
        $proveedor_id = $stmt_proveedor->fetchColumn();

        if (!$proveedor_id) {
            $codigo_proveedor_generado = generarCodigoProveedor($pdo, $codigo_institucion_sesion);
            if (strpos($codigo_proveedor_generado, 'Error') !== false) {
                throw new Exception("No se pudo generar código para proveedor: $codigo_proveedor_generado");
            }
            $sql_insert_proveedor = "INSERT INTO proveedores (codigo_institucion, codigo, nit, nombre_empresa) 
                                     VALUES (?, ?, ?, ?)";
            $stmt_insert_proveedor = $pdo->prepare($sql_insert_proveedor);
            $stmt_insert_proveedor->execute([
                $codigo_institucion_sesion, 
                $codigo_proveedor_generado, 
                $compra_data['emisor_nit'], 
                $compra_data['emisor_nombre']
            ]);
            $proveedor_id = $pdo->lastInsertId('proveedores_id_proveedores_seq');
        }

        // 2. Insertar cabecera con resumen completo
        $sql_cabecera = "INSERT INTO compras_cabecera (
                codigo_institucion, numero_documento, tipo_documento, fecha_emision,
                id_proveedores, condicion_pago, observaciones,
                total_no_suj, total_exenta, total_gravada, total_iva, total_descuento, total_compra, tipo_dte, sello_recibido, firma_electronica
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_cabecera = $pdo->prepare($sql_cabecera);
        $stmt_cabecera->execute([
            $codigo_institucion_sesion,
            $compra_data['numero_control'],
            $compra_data['tipo_dte'],
            $compra_data['fecha_emision'],
            $proveedor_id,
            $compra_data['tipo_operacion'],
            $compra_data['observaciones'] ?? null,
            $compra_data['resumen']['total_no_suj'] ?? 0,
            $compra_data['resumen']['total_exenta'] ?? 0,
            $compra_data['resumen']['total_gravada'] ?? 0,
            $compra_data['resumen']['total_iva'] ?? 0,
            $compra_data['resumen']['total_descuento'] ?? 0,
            $compra_data['resumen']['total_pagar'] ?? 0,
            $compra_data['tipo_dte'],
            $compra_data['sello_recibido'] ?? null,
            $compra_data['firma_electronica'] ?? null
        ]);
        $id_compra = $pdo->lastInsertId('compras_cabecera_id_compra_seq');

      // 3. Procesar productos
        foreach ($productos_data as $producto) {
            if (empty($producto['codigo_proveedor'])) {
                throw new Exception("Producto sin código de proveedor: " . ($producto['descripcion'] ?? 'Sin descripción'));
            }

            // --- LÓGICA DE PRECIOS AUTOMÁTICA ---
            // 1. Definir valores por defecto
            $impuesto_codigo = $producto['impuesto_aplicable'] ?? '20'; 
            $ganancia_codigo = $producto['codigo_ganancia'] ?? '02';    
            $nuevo_costo = floatval($producto['precio_costo']);

            // 2. Obtener porcentajes reales de la BD
            // A. Impuesto
            $sql_imp = "SELECT porcentaje, tipo_impuesto, monto_fijo FROM cat_015 WHERE codigo = ?";
            $stmt_imp = $pdo->prepare($sql_imp);
            $stmt_imp->execute([$impuesto_codigo]);
            $info_imp = $stmt_imp->fetch(PDO::FETCH_ASSOC);

            // B. Ganancia
            $sql_gan = "SELECT porcentaje FROM catalogo_ganancia WHERE codigo = ? AND codigo_institucion = ?";
            $stmt_gan = $pdo->prepare($sql_gan);
            $stmt_gan->execute([$ganancia_codigo, $codigo_institucion_sesion]);
            $info_gan = $stmt_gan->fetch(PDO::FETCH_ASSOC);

            // 3. Calcular Precio de Venta
            $factor_impuesto = 1;
            $monto_impuesto_fijo = 0;
            
            if ($info_imp) {
                if ($info_imp['tipo_impuesto'] === 'PORCENTAJE') {
                    $factor_impuesto = 1 + ($info_imp['porcentaje'] / 100);
                } elseif ($info_imp['tipo_impuesto'] === 'MONETARIO') {
                    $monto_impuesto_fijo = floatval($info_imp['monto_fijo']);
                }
            }

            $factor_ganancia = 1 + (($info_gan['porcentaje'] ?? 0) / 100);

            // FÓRMULA: (Costo + Impuestos) * Ganancia
            $precio_base_con_impuestos = ($nuevo_costo * $factor_impuesto) + $monto_impuesto_fijo;
            $nuevo_precio_venta = round($precio_base_con_impuestos * $factor_ganancia, 4);

            // ------------------------------------

            // Buscar producto por codigo_proveedor
            // CORRECCIÓN: Usamos 'stock_actual'
            $sql_find = "SELECT id_productos, codigo_interno, stock_actual 
                         FROM catalogo_productos 
                         WHERE codigo_proveedor = ? AND codigo_institucion = ?";
            $stmt_find = $pdo->prepare($sql_find);
            $stmt_find->execute([$producto['codigo_proveedor'], $codigo_institucion_sesion]);
            $producto_db = $stmt_find->fetch(PDO::FETCH_ASSOC);

            if ($producto_db) {
                // A. PRODUCTO EXISTENTE: Actualizar Costo, Stock Y PRECIO VENTA
                $producto['id_productos'] = $producto_db['id_productos'];
                $producto['codigo_interno'] = $producto_db['codigo_interno'];

                // CORRECCIÓN: Cambiado 'existencias' por 'stock_actual'
                $sql_update = "UPDATE catalogo_productos 
                               SET precio_costo = ?, 
                                   precio_unitario = ?,
                                   stock_actual = stock_actual + ?
                               WHERE id_productos = ?";
                $pdo->prepare($sql_update)->execute([
                    $nuevo_costo, 
                    $nuevo_precio_venta, 
                    $producto['cantidad'], 
                    $producto['id_productos']
                ]);

            } else {
                // B. PRODUCTO NUEVO: Insertar todo
                if (empty($producto['codigo_categoria'])) {
                    $producto['codigo_categoria'] = 'CAT008'; 
                }
                $codigo_producto_generado = generarCodigoProducto($pdo, $producto['codigo_categoria'], $codigo_institucion_sesion);

                // CORRECCIÓN: Cambiado 'existencias' por 'stock_actual' en el INSERT
                $sql_insert_producto = "INSERT INTO catalogo_productos (
                    codigo_interno, codigo_institucion, codigo_categoria,
                    descripcion, precio_costo, precio_unitario, impuesto_aplicable, unidad_medida,
                    codigo_proveedor, stock_actual, fecha_vencimiento, codigo_ganancia
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                RETURNING id_productos";

                $stmt_insert_producto = $pdo->prepare($sql_insert_producto);
                $stmt_insert_producto->execute([
                    $codigo_producto_generado,
                    $codigo_institucion_sesion,
                    $producto['codigo_categoria'],
                    $producto['descripcion'],
                    $nuevo_costo,
                    $nuevo_precio_venta,
                    $impuesto_codigo,
                    $producto['unidad_medida'],
                    $producto['codigo_proveedor'],
                    $producto['cantidad'], // stock inicial = cantidad comprada
                    $producto['fecha_vencimiento'] ?? null,
                    $ganancia_codigo
                ]);
                $producto['id_productos'] = $stmt_insert_producto->fetchColumn();
                $producto['codigo_interno'] = $codigo_producto_generado;
            }

            // Insertar detalle de compra (Histórico)
            $sql_detalle = "INSERT INTO compras_detalle (
                    id_compra, codigo_producto, cantidad, unidad_medida, 
                    precio_costo, precio_unitario, iva, descuento,
                    ventas_no_suj, ventas_exenta, ventas_gravada
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_detalle = $pdo->prepare($sql_detalle);
            $stmt_detalle->execute([
                $id_compra,
                $producto['codigo_interno'],
                $producto['cantidad'],
                $producto['unidad_medida'],
                $nuevo_costo,
                $producto['precio_unitario'], // Precio de compra original (del JSON)
                $producto['iva'],
                $producto['descuento'],
                $producto['venta_no_suj'] ?? 0,
                $producto['venta_exenta'] ?? 0,
                $producto['venta_gravada'] ?? 0
            ]);
            
            // (Opcional) Actualización extra de vencimientos si lo necesitas, usando stock_actual
            // Si ya actualizamos arriba, este bloque podría ser redundante, pero si lo dejas
            // asegúrate que use la columna correcta si decides descomentarlo.
        }

        // ============================= comita transacción
          // =========================================================
        // 4. INTEGRACIÓN CONTABLE AUTOMÁTICA
        // =========================================================

        // A. OBTENER LAS CLAVES DE MAFEO (Necesitas estas cuentas configuradas)
        // Usamos una cuenta genérica para el costo/inventario.
        // Asumimos que el tipo de pago es CRÉDITO para este ejemplo.
        $id_inventario = obtenerIdCuentaPorMapeo($pdo, $codigo_institucion_sesion, 'INVENTARIO_MERCADERIA');
        $id_iva_credito = obtenerIdCuentaPorMapeo($pdo, $codigo_institucion_sesion, 'IVA_CREDITO_FISCAL');
        $id_proveedores_cp = obtenerIdCuentaPorMapeo($pdo, $codigo_institucion_sesion, 'PROVEEDORES_CP');
        
       // B. CALCULAR LOS MONTOS (CORREGIDO)
        $total_gravada = floatval($compra_data['resumen']['total_gravada'] ?? 0);
        $total_exenta_nosuj = floatval(($compra_data['resumen']['total_exenta'] ?? 0) + ($compra_data['resumen']['total_no_suj'] ?? 0));
        $total_iva = floatval($compra_data['resumen']['total_iva'] ?? 0);

        // Forzamos que el total a pagar sea la suma matemática exacta de lo que cargamos
        // Esto evita el error de "El asiento no balancea" por diferencias de centavos en el JSON original
        $total_compra = $total_gravada + $total_exenta_nosuj + $total_iva;

        // C. CONSTRUIR EL ASIENTO (Partida Doble)

        $datos_encabezado = [
            'fechaAsiento' => $compra_data['fecha_emision'] ?? date('Y-m-d'), 
            'tipoAsiento' => 'Egreso', // Tipo de póliza
            'concepto' => "Registro automático de Compra DTE No. " . $compra_data['numero_control'] . " del proveedor " . $compra_data['emisor_nombre'],
            'usuarioRegistro' => $usuario_activo // Usar la sesión actual
        ];

        $datos_detalle = [
            // LÍNEA 1: DÉBITO - Inventario (Costo de la mercancía sin IVA)
            ['cuenta_id' => $id_inventario, 'debito' => $total_gravada + $total_exenta_nosuj, 'credito' => 0.00],
            
            // LÍNEA 2: DÉBITO - IVA Crédito Fiscal (El IVA que se tiene a favor)
            ['cuenta_id' => $id_iva_credito, 'debito' => $total_iva, 'credito' => 0.00],
            
            // LÍNEA 3: CRÉDITO - Proveedores (Pasivo, la deuda total)
            ['cuenta_id' => $id_proveedores_cp, 'debito' => 0.00, 'credito' => $total_compra],
        ];

        // D. VALIDACIÓN Y REGISTRO
        $resultado_contable = registrarAsientoAutomatico(
            $pdo, 
            $codigo_institucion_sesion, 
            $datos_encabezado, 
            $datos_detalle
        );

        if (!$resultado_contable['respuesta']) {
            // Si el asiento falla, lanza una excepción para hacer ROLLBACK de TODA la compra.
            throw new Exception("Error Contable: El asiento no pudo registrarse. " . $resultado_contable['mensaje']);
        }

        // =============================
        // FINAL DE LA TRANSACCIÓN
        // =============================
        $pdo->commit();
        echo json_encode([
            'respuesta' => true, 
            'mensaje'   => 'Compra guardada y asiento contable No. ' . $resultado_contable['numero_asiento'] . ' registrado exitosamente.', 
            'id_compra' => $id_compra
        ]);

    } catch (Exception $e) {
        // CORRECCIÓN: Solo hacer rollback si la transacción sigue activa
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode([
            'respuesta' => false, 
            'mensaje'   => 'Error al guardar la compra: ' . $e->getMessage()
        ]);
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

    $orderColumns = ['id_productos', 'descripcion', 'precio_costo'];
    $orderCol = $orderColumns[$orderColumnIndex] ?? 'id_productos';

    try {
        // Total registros
        $sqlCount = "SELECT COUNT(id_productos) FROM catalogo_productos WHERE codigo_institucion = ?";
        $stmtCount = $pdo->prepare($sqlCount);
        $stmtCount->execute([$codigo_institucion_sesion]);
        $totalRecords = $stmtCount->fetchColumn();

        // Consulta principal
        $sql = "SELECT id_productos, codigo_interno, descripcion, unidad_medida, precio_costo, impuesto_aplicable, codigo_ganancia, codigo_proveedor
                FROM catalogo_productos 
                WHERE codigo_institucion = ?";
        $params = [$codigo_institucion_sesion];

            if (!empty($searchValue)) {
                $sql .= " AND (
                    LOWER(descripcion) LIKE LOWER(?) 
                    OR LOWER(codigo_interno) LIKE LOWER(?) 
                    OR LOWER(codigo_proveedor) LIKE LOWER(?) 
                    OR CAST(id_productos AS TEXT) LIKE ? 
                    OR LOWER(codigo_barra) LIKE LOWER(?)
                )";

                $params[] = '%' . $searchValue . '%';
                $params[] = '%' . $searchValue . '%';
                $params[] = '%' . $searchValue . '%';
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

       // 🔍 Ajustar precio con impuesto + ganancia
                foreach ($productos as &$p) {
                    $precioBase = $p['precio_costo'];

                    // --- Impuesto ---
                    if (!empty($p['impuesto_aplicable']) && $p['impuesto_aplicable'] !== '00') {
                        $sqlImp = "SELECT tipo_impuesto, porcentaje, monto_fijo, descripcion 
                                FROM cat_015 
                                WHERE codigo = ?";
                        $stmtImp = $pdo->prepare($sqlImp);
                        $stmtImp->execute([$p['impuesto_aplicable']]);
                        $impuesto = $stmtImp->fetch(PDO::FETCH_ASSOC);

                        if ($impuesto) {
                            if ($impuesto['tipo_impuesto'] === 'PORCENTAJE') {
                                $precioBase = $precioBase * (1 + ($impuesto['porcentaje'] / 100));
                            } elseif ($impuesto['tipo_impuesto'] === 'MONETARIO') {
                                $precioBase = $precioBase + $impuesto['monto_fijo'];
                            }
                            $p['impuesto_descripcion'] = $impuesto['descripcion'];
                        }
                    }

                    // --- Ganancia ---
                    if (!empty($p['codigo_ganancia'])) {
                        $sqlGan = "SELECT porcentaje FROM catalogo_ganancia WHERE codigo = ? AND codigo_institucion = ?";
                        $stmtGan = $pdo->prepare($sqlGan);
                        $stmtGan->execute([$p['codigo_ganancia'], $codigo_institucion_sesion]);
                        $gan = $stmtGan->fetch(PDO::FETCH_ASSOC);

                        if ($gan) {
                            $precioBase = $precioBase * (1 + ($gan['porcentaje'] / 100));
                        }
                    }

                    // Guardamos calculado con 4 decimales
                    $p['precio_unitario_final'] = number_format($precioBase, 4, '.', '');
                    $p['subtotal'] = number_format($precioBase, 4, '.', ''); // cantidad inicial = 1
                }


        // Conteo filtrado
        $totalFiltered = $totalRecords;
        if (!empty($searchValue)) {
            $sqlFilterCount = "SELECT COUNT(id_productos) 
                               FROM catalogo_productos 
                               WHERE codigo_institucion = ? 
                               AND (LOWER(descripcion) LIKE LOWER(?) OR LOWER(codigo_interno) LIKE LOWER(?))";
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
                // Inyectar firma si no existe en ninguna variante
                        $firma_variantes = [
                            "firmaElectronica", "firma", "Firma",
                            "respuestaHacienda.firma",
                            "respuestaHacienda.firmaElectronica",
                            "respuestaHacienda.Firma"
                        ];

                        $firma_encontrada = false;
                        foreach ($firma_variantes as $variante) {
                            $parts = explode('.', $variante);
                            $tmp = $dte;
                            foreach ($parts as $p) {
                                if (isset($tmp[$p])) {
                                    $firma_encontrada = true;
                                    break 2;
                                }
                                if (!is_array($tmp)) break;
                                $tmp = $tmp[$p] ?? null;
                            }
                        }

                        if (!$firma_encontrada) {
                            $dte['firma'] = 'ninguna';
                        }

                    // Secciones obligatorias (las claves pueden tener alias)
                    $secciones_obligatorias = [
                        "identificacion"   => ["identificacion"],
                        "emisor"           => ["emisor"],
                        "cuerpoDocumento"  => ["cuerpoDocumento"],
                        "resumen"          => ["resumen"],
                        "firma"            => ["firmaElectronica", "firma", "Firma", "respuestaHacienda.firma", "respuestaHacienda.firmaElectronica", "respuestaHacienda.Firma"],
                        "selloRecibido"    => ["selloRecibido", "SelloRecibido", "respuestaHacienda.selloRecibido", "respuestaHacienda.SelloRecibido"]
                    ];

                $errores = [];
                foreach ($secciones_obligatorias as $nombre_logico => $variantes) {
                    $encontrada = false;
                    foreach ($variantes as $variante) {
                        // Soporte para subniveles con punto, ej: "respuestaHacienda.firma"
                        $parts = explode('.', $variante);
                        $tmp = $dte;
                        foreach ($parts as $p) {
                            if (isset($tmp[$p])) {
                                $tmp = $tmp[$p];
                            } else {
                                $tmp = null;
                                break;
                            }
                        }
                        if ($tmp !== null) {
                            // normalizamos al nombre lógico
                            $dte[$nombre_logico] = $tmp;
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

                // FIRMA Y SELLO (buscando en raíz y en respuestaHacienda)
                'firma_electronica'  => 
                    $dte['firma'] 
                    ?? $dte['firmaElectronica'] 
                    ?? $dte['Firma'] 
                    ?? ($dte['respuestaHacienda']['firma'] ?? null)
                    ?? ($dte['respuestaHacienda']['firmaElectronica'] ?? null)
                    ?? ($dte['respuestaHacienda']['Firma'] ?? 'ninguna'),



                    'sello_recibido'     => 
                        $dte['selloRecibido'] 
                        ?? ($dte['respuestaHacienda']['selloRecibido'] ?? null)
                        ?? ($dte['respuestaHacienda']['SelloRecibido'] ?? null),

                    // OBSERVACIONES
                    'observaciones'      => $dte['extension']['observaciones'] ?? null,

                    // PRODUCTOS
                    'productos_dte'      => $dte['cuerpoDocumento'] ?? [],

                    'resumen' => [
                        'total_no_suj'    => $dte['resumen']['totalNoSuj'] ?? 0,
                        'total_exenta'    => $dte['resumen']['totalExenta'] ?? 0,
                        'total_gravada'   => $dte['resumen']['totalGravada'] ?? 0,
                        'total_iva'       => $dte['resumen']['totalIva'] ?? 0,
                        'total_descuento' => $dte['resumen']['totalDescu'] ?? 0,
                        'total_pagar'     => $dte['resumen']['totalPagar'] ?? 0
                    ]
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
                // Después de mapear los datos de compra
                        $nit_emisor = $mapeo['emisor_nit'] ?? null;
                        $nombre_emisor = $mapeo['emisor_nombre'] ?? null;

                        if ($nit_emisor) {
                            // Verificar si ya existe el proveedor
                            $sql_proveedor = "SELECT id_proveedores, codigo FROM proveedores 
                                            WHERE nit = ? AND codigo_institucion = ?";
                            $stmt_proveedor = $pdo->prepare($sql_proveedor);
                            $stmt_proveedor->execute([$nit_emisor, $codigo_institucion_sesion]);
                            $proveedor = $stmt_proveedor->fetch(PDO::FETCH_ASSOC);

                            if (!$proveedor) {
                                // Crear nuevo proveedor
                                $codigo_proveedor_generado = generarCodigoProveedor($pdo, $codigo_institucion_sesion);
                                $sql_insert_proveedor = "INSERT INTO proveedores 
                                    (codigo_institucion, codigo, nit, nombre_empresa) 
                                    VALUES (?, ?, ?, ?)";
                                $stmt_insert_proveedor = $pdo->prepare($sql_insert_proveedor);
                                $stmt_insert_proveedor->execute([
                                    $codigo_institucion_sesion,
                                    $codigo_proveedor_generado,
                                    $nit_emisor,
                                    $nombre_emisor
                                ]);
                                $proveedor_id = $pdo->lastInsertId('proveedores_id_proveedores_seq');
                            } else {
                                $proveedor_id = $proveedor['id_proveedores'];
                            }

                            // Devolver también el proveedor actual para refrescar el select en frontend
                            $mapeo['proveedor_id'] = $proveedor_id;
                            $mapeo['proveedor_nombre'] = $nombre_emisor;
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

            case 'validarNumeroDocumento':
                try {
                    $numero_documento = $_POST['numero_documento'] ?? '';

                    if (empty($numero_documento)) {
                        echo json_encode(['existe' => false]);
                        break;
                    }

                    $sql = "SELECT COUNT(*) FROM compras_cabecera 
                            WHERE numero_documento = ? AND codigo_institucion = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$numero_documento, $codigo_institucion_sesion]);
                    $count = $stmt->fetchColumn();

                    echo json_encode([
                        'existe' => $count > 0
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'existe' => false,
                        'error' => $e->getMessage()
                    ]);
                }
                break;


    default:
        echo json_encode(['respuesta' => false, 'mensaje' => 'Acción no válida.']);
        break;
}
?>