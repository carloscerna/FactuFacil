<?php
// admin/compras/crud_compras.php

session_name('FactuFacil');
session_start();

if (empty($_SESSION['userNombre'])) {
    header('Content-Type: application/json');
    echo json_encode(['respuesta' => false, 'mensaje' => 'Sesión no válida.']);
    exit();
}
global $pdo;
$path_root = trim($_SERVER['DOCUMENT_ROOT']);
include($path_root."/FactuFacil/includes/mainFunctions_.php");
include($path_root."/FactuFacil/admin/contabilidad/funciones/contabilidad_api.php");
/** @var PDO $dblink */
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
         // Decodificar datos que vienen del formulario manual
            $datos_cabecera = json_decode($_POST['compra_cabecera'], true);
            $datos_detalle  = json_decode($_POST['compra_detalle'], true);

            // --- 1. PREPARACIÓN DE VARIABLES DE CRÉDITO Y VENCIMIENTO ---
            $fecha_emision  = $datos_cabecera['fecha_emision'];
            $condicion_pago = $datos_cabecera['condicion_pago']; // 1: Contado, 2: Crédito
            
            // Extraemos los nuevos datos del JSON (si vienen vacíos, ponemos defaults)
            $dias_credito = intval($datos_cabecera['dias_credito'] ?? 0);
            $fecha_venc   = $datos_cabecera['fecha_vencimiento'] ?? null;

            // Lógica: Si es Crédito (2) y la fecha viene vacía, la calculamos aquí mismo
            if ($condicion_pago == '2') {
                if (empty($fecha_venc)) {
                    // Si no hay fecha ni días, asumimos 30 días por defecto
                    $dias_calc = ($dias_credito > 0) ? $dias_credito : 30; 
                    // Si días era 0, lo actualizamos a 30 para guardarlo bien
                    if($dias_credito == 0) $dias_credito = 30; 
                    
                    $fecha_venc = date('Y-m-d', strtotime($fecha_emision . " + $dias_calc days"));
                }
            } else {
                // Si es Contado, limpiamos estos campos para que no quede basura en la BD
                $dias_credito = 0;
                $fecha_venc   = null;
            }

            // --- 2. INSERTAR ENCABEZADO DE COMPRA (ACTUALIZADO) ---
            // Se agregaron las columnas: dias_credito, fecha_vencimiento
            $sql_cab = "INSERT INTO compras_cabecera 
                (codigo_institucion, numero_documento, id_proveedores, fecha_emision, tipo_documento, 
                 condicion_pago, plazo_pago, dias_credito, fecha_vencimiento, 
                 total_gravado, total_iva, total_compra, observaciones, fecha_creacion) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) RETURNING id_compra";
            
            $stmt_cab = $pdo->prepare($sql_cab);
            $stmt_cab->execute([
                $codigo_institucion_sesion,
                $datos_cabecera['numero_documento'],
                $datos_cabecera['proveedor_id'], // OJO: En tu JS lo llamas 'proveedor_id', verifica si en el array llega como 'id_proveedores' o 'proveedor_id'
                $fecha_emision,
                $datos_cabecera['tipo_dte'],     // Verifica si en tu JS es 'tipo_dte' o 'tipo_documento'
                $condicion_pago,
                $datos_cabecera['plazo_pago'] ?? '', // Texto libre (opcional)
                
                $dias_credito,  // <--- NUEVO CAMPO INT
                $fecha_venc,    // <--- NUEVO CAMPO DATE
                
                $datos_cabecera['total_gravado'],
                $datos_cabecera['total_iva'],
                $datos_cabecera['total_pagar'], // Verifica si es 'total_pagar' o 'total_compra'
                $datos_cabecera['observaciones'] ?? ''
            ]);
            
            $id_compra = $stmt_cab->fetchColumn();
           // 2. Procesar Detalle de Productos (MANUAL)
            foreach ($datos_detalle as $producto) {
                
               // --- A. OBTENER DATOS ---
               $precio_papel = floatval($producto['precio_unitario'] ?? $producto['precio_costo'] ?? 0);
               $cantidad_input = floatval($producto['cantidad']);

               // --- B. LÓGICA DE TIPOS DTE (CASUÍSTICA EL SALVADOR) ---
               $tipo_dte = $datos_cabecera['tipo_documento']; 
               
               $costo_neto_kardex = 0;
               $factor_cantidad = 1; // 1 = Entrada, -1 = Salida

               switch ($tipo_dte) {
                   case '01': // FACTURA (Trae IVA, hay que limpiar)
                       $costo_neto_kardex = round($precio_papel / 1.13, 4);
                       $factor_cantidad = 1;
                       break;

                   case '05': // NOTA DE CRÉDITO (Devolución -> RESTA Inventario)
                       $costo_neto_kardex = $precio_papel; // Asumimos viene neto
                       $factor_cantidad = -1; 
                       break;

                   //// Todos estos son precios NETOS y SUMAN inventario (CCF, Exportación, etc)
                   case '03': case '04': case '06': case '07': case '08': 
                   case '09': case '11': case '14': case '15':
                       $costo_neto_kardex = $precio_papel;
                       $factor_cantidad = 1;
                       break;

                   default:
                       $costo_neto_kardex = $precio_papel;
                       $factor_cantidad = 1;
                       break;
                }

                // --- C. DETECCIÓN INTELIGENTE DE IMPUESTO (Nuevo en Manual) ---
                // Si escribiste dinero en la columna de Exento, el producto debe ser Exento.
                $impuesto_codigo = $producto['impuesto_aplicable'] ?? '20'; // Valor que venía del JS (puede ser del catálogo o default)
                
                // Leemos los montos que ingresaste manualmente en la tabla
                $monto_exento_manual = floatval($producto['ventas_exentas'] ?? 0);
                $monto_nosuj_manual  = floatval($producto['ventas_no_sujetas'] ?? 0);

                if ($monto_exento_manual > 0 || $monto_nosuj_manual > 0) {
                    // Si hay dinero en estas columnas, forzamos el código de "Sin Impuesto"
                    $impuesto_codigo = '00'; 
                }

                // Obtener info del impuesto final
                $sql_imp = "SELECT porcentaje, tipo_impuesto, monto_fijo FROM cat_015 WHERE codigo = ?";
                $stmt_imp = $pdo->prepare($sql_imp);
                $stmt_imp->execute([$impuesto_codigo]);
                $info_imp = $stmt_imp->fetch(PDO::FETCH_ASSOC);

                // --- C. BUSCAR/FORZAR GANANCIA ---
                $ganancia_codigo = $producto['codigo_ganancia'] ?? ''; 
                
                $sql_gan = "SELECT porcentaje, codigo FROM catalogo_ganancia WHERE codigo = ? AND codigo_institucion = ?";
                $stmt_gan = $pdo->prepare($sql_gan);
                $stmt_gan->execute([$ganancia_codigo, $codigo_institucion_sesion]);
                $info_gan = $stmt_gan->fetch(PDO::FETCH_ASSOC);

                // Si no seleccionaste ganancia, FORZAMOS 'GAN002' (30%)
                if (!$info_gan) {
                    $codigo_ganancia_defecto = 'GAN002'; // Ajusta a tu código real
                    $stmt_gan_def = $pdo->prepare("SELECT porcentaje, codigo FROM catalogo_ganancia WHERE codigo = ? AND codigo_institucion = ?");
                    $stmt_gan_def->execute([$codigo_ganancia_defecto, $codigo_institucion_sesion]);
                    $info_gan = $stmt_gan_def->fetch(PDO::FETCH_ASSOC);
                    
                    if ($info_gan) {
                        $ganancia_codigo = $info_gan['codigo'];
                    } else {
                        // Fallback de emergencia
                        $ganancia_codigo = 'GAN002';
                        $info_gan = ['porcentaje' => 30.00]; 
                    }
                }

                // --- D. BUSCAR IMPUESTO (Para calcular Precio Venta) ---
                $impuesto_codigo = $producto['impuesto_aplicable'] ?? '20'; // IVA 13%
                $sql_imp = "SELECT porcentaje, tipo_impuesto, monto_fijo FROM cat_015 WHERE codigo = ?";
                $stmt_imp = $pdo->prepare($sql_imp);
                $stmt_imp->execute([$impuesto_codigo]);
                $info_imp = $stmt_imp->fetch(PDO::FETCH_ASSOC);

                // --- E. CÁLCULO DE PRECIO DE VENTA ---
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

                // Fórmula: (CostoNeto * (1.13)) * (1.30)
                $precio_base_con_impuestos = ($costo_neto_kardex * $factor_impuesto) + $monto_impuesto_fijo;
                $nuevo_precio_venta = round($precio_base_con_impuestos * $factor_ganancia, 4);

                // --- F. GESTIÓN DE CATÁLOGO (Crear si no existe / Actualizar si existe) ---
                
                // Verificar existencia
                $sql_find = "SELECT id_productos, codigo_interno FROM catalogo_productos WHERE codigo_interno = ? AND codigo_institucion = ?";
                $stmt_find = $pdo->prepare($sql_find);
                $stmt_find->execute([$producto['codigo_interno'], $codigo_institucion_sesion]);
                $producto_db = $stmt_find->fetch(PDO::FETCH_ASSOC);

                if ($producto_db) {
                    // ACTUALIZAR (Lógica existente)
                    $cantidad_a_mover = $cantidad_input * $factor_cantidad;
                    $sql_upd = "UPDATE catalogo_productos SET 
                                    stock_actual = stock_actual + ?, 
                                    precio_costo = ?, 
                                    precio_unitario = ?,
                                    codigo_ganancia = ?
                                WHERE codigo_interno = ? AND codigo_institucion = ?";
                    $pdo->prepare($sql_upd)->execute([
                        $cantidad_a_mover, $costo_neto_kardex, $nuevo_precio_venta, $ganancia_codigo,
                        $producto['codigo_interno'], $codigo_institucion_sesion
                    ]);
                } else {
                    // CREAR NUEVO (Lógica agregada para robustez)
                    // Si escribiste un código manual que no existe, lo creamos.
                    $sql_insert = "INSERT INTO catalogo_productos (
                        codigo_interno, codigo_institucion, codigo_categoria,
                        descripcion, precio_costo, precio_unitario, impuesto_aplicable, unidad_medida,
                        codigo_proveedor, stock_actual, fecha_vencimiento, codigo_ganancia
                    ) VALUES (?, ?, 'CAT008', ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id_productos"; // CAT008 por defecto si es manual puro

                    $stmt_ins = $pdo->prepare($sql_insert);
                    $stmt_ins->execute([
                        $producto['codigo_interno'], // Usamos el código que escribiste
                        $codigo_institucion_sesion,
                        $producto['descripcion'],
                        $costo_neto_kardex,
                        $nuevo_precio_venta,
                        $impuesto_codigo,
                        $producto['unidad_medida'] ?? '59', // Unidad por defecto
                        $producto['codigo_proveedor'] ?? $producto['codigo_interno'],
                        $cantidad_input,
                        null, // fecha vencimiento
                        $ganancia_codigo
                    ]);
                }

                // --- G. INSERTAR DETALLE ---
                $sql_det = "INSERT INTO compras_detalle 
                    (id_compra, codigo_producto, cantidad, precio_costo, precio_unitario, subtotal, iva, descuento, ventas_no_suj, ventas_exenta, ventas_gravada) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt_det = $pdo->prepare($sql_det);
                $stmt_det->execute([
                    $id_compra,
                    $producto['codigo_interno'],
                    $cantidad_input,
                    $costo_neto_kardex, 
                    $precio_papel,
                    $producto['subtotal'] ?? 0,
                    $producto['iva'] ?? 0,
                    $producto['descuento'] ?? 0,
                    $producto['ventas_no_sujetas'] ?? 0, // Usamos nombres del JS manual
                    $producto['ventas_exentas'] ?? 0,
                    $producto['ventas_gravadas'] ?? 0
                ]);
            }

           // =========================================================
            // 3. INTEGRACIÓN CONTABLE AUTOMÁTICA (COMPRA MANUAL)
            // =========================================================

            // A. OBTENER CUENTAS CONTABLES (Mapeo)
            // Asegúrate de tener estos códigos configurados en tu sistema
            $id_inventario = obtenerIdCuentaPorMapeo($pdo, $codigo_institucion_sesion, 'INVENTARIO_MERCADERIA');
            $id_iva_credito = obtenerIdCuentaPorMapeo($pdo, $codigo_institucion_sesion, 'IVA_CREDITO_FISCAL');
            $id_proveedores_cp = obtenerIdCuentaPorMapeo($pdo, $codigo_institucion_sesion, 'PROVEEDORES_CP');

           // --- 1. OBTENER Y SANITIZAR TODOS LOS TOTALES ---
            // Usamos '?? 0' para que si el dato no viene, asuma que es cero y no de error.
            $total_gravado = floatval($datos_cabecera['total_gravado'] ?? 0);
            $total_exenta  = floatval($datos_cabecera['total_exenta'] ?? 0);   // <-- Ahora sí la definimos
            $total_no_suj  = floatval($datos_cabecera['total_no_suj'] ?? 0);   // <-- Ahora sí la definimos
            $total_iva     = floatval($datos_cabecera['total_iva'] ?? 0);
            $total_pagar   = floatval($datos_cabecera['total_pagar'] ?? 0);

            // --- 2. CÁLCULO DE MONTO PARA INVENTARIO ---
            // Ahora la suma funcionará perfecta
            $monto_inventario = $total_gravado + $total_exenta + $total_no_suj;
            
            $monto_iva       = $total_iva;
            $monto_proveedor = $total_pagar;
            // C. DEFINIR ENCABEZADO DEL ASIENTO
            $datos_encabezado_asiento = [
                'fechaAsiento'    => $datos_cabecera['fecha_emision'],
                'tipoAsiento'     => 'Egreso', // O 'Diario' según tu lógica
                'concepto'        => "Registro de Compra Manual Doc. " . $datos_cabecera['numero_documento'] . " - " . ($datos_cabecera['nombre_proveedor'] ?? 'Proveedor Varios'),
                'usuarioRegistro' => $_SESSION['userNombre']
            ];

            // D. DEFINIR DETALLE (PARTIDA DOBLE)
            $datos_detalle_asiento = [
                // 1. CARGO: Inventario (Entrada de Mercancía - Valor Neto)
                [
                    'cuenta_id' => $id_inventario, 
                    'debito'    => $total_gravado, 
                    'credito'   => 0.00
                ],
                // 2. CARGO: IVA Crédito Fiscal (Impuesto a favor)
                [
                    'cuenta_id' => $id_iva_credito, 
                    'debito'    => $total_iva, 
                    'credito'   => 0.00
                ],
                // 3. ABONO: Cuentas por Pagar (Deuda al Proveedor)
                [
                    'cuenta_id' => $id_proveedores_cp, 
                    'debito'    => 0.00, 
                    'credito'   => $total_pagar
                ]
            ];

            // E. REGISTRAR ASIENTO
            $resultado_contable = registrarAsientoAutomatico(
                $pdo, 
                $codigo_institucion_sesion, 
                $datos_encabezado_asiento, 
                $datos_detalle_asiento
            );

            // Validar éxito del asiento
            if ($resultado_contable['respuesta']) {
                // Si se creó, actualizamos la compra con el ID del asiento generado
                $sql_link_asiento = "UPDATE compras_cabecera SET asiento_id = ? WHERE id_compra = ?";
                $pdo->prepare($sql_link_asiento)->execute([$resultado_contable['numero_asiento'], $id_compra]);
            } else {
                // Opcional: Si es crítico que exista contabilidad, descomenta la siguiente línea para revertir todo si falla el asiento
                // throw new Exception("Error al generar asiento contable: " . $resultado_contable['mensaje']);
            }

// --- DETERMINAR LA CUENTA DE SALIDA (HABER) ---
    $id_cuenta_salida = 0;
    $condicion_pago = $compra_data['condicion_pago'] ?? '1'; // 1: Contado, 2: Crédito
    
    if ($condicion_pago == '2') {
        // A. ES CRÉDITO -> Usamos Cuentas por Pagar Proveedores
        $id_cuenta_salida = obtenerIdCuentaPorMapeo($pdo, $codigo_institucion_sesion, 'PROVEEDORES_CP');
    } else {
        // B. ES CONTADO -> Buscamos según el Método (Efectivo, Banco, etc.)
        $forma_pago = $compra_data['forma_pago'] ?? '01'; // Default Efectivo
        
        // Buscamos en la tabla de configuración
        $sql_cuenta = "SELECT id_cuenta_contable FROM configuracion_cuentas_pago 
                       WHERE codigo_forma_pago = ? AND codigo_institucion = ?";
        $stmt_c = $pdo->prepare($sql_cuenta);
        $stmt_c->execute([$forma_pago, $codigo_institucion_sesion]);
        $res_cuenta = $stmt_c->fetch(PDO::FETCH_ASSOC);
        
        if ($res_cuenta) {
            $id_cuenta_salida = $res_cuenta['id_cuenta_contable'];
        } else {
            // FALLBACK: Si no configuraron la cuenta específica, usamos CAJA GENERAL por defecto
            // O lanza un error si prefieres ser estricto.
            $id_cuenta_salida = obtenerIdCuentaPorMapeo($pdo, $codigo_institucion_sesion, 'CAJA_GENERAL');
        }
    }

    // --- CONSTRUIR DETALLE DEL ASIENTO ---
    $datos_detalle_asiento = [
        // 1. CARGO: Inventario
        ['cuenta_id' => $id_inventario, 'debito' => $monto_inventario, 'credito' => 0.00],
        
        // 2. CARGO: IVA (si aplica)
        ['cuenta_id' => $id_iva_credito, 'debito' => $monto_iva, 'credito' => 0.00],
        
        // 3. ABONO: Cuenta de Salida (Banco, Caja o Proveedor)
        ['cuenta_id' => $id_cuenta_salida, 'debito' => 0.00, 'credito' => $monto_proveedor]
    ];

            $pdo->commit();
            echo json_encode(['respuesta' => true, 'mensaje' => 'Compra manual registrada y precios actualizados.']);

        } catch (Exception $e) {
            // CORRECCIÓN: Solo hacer rollback si la transacción sigue activa
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error en guardarCompra Manual: " . $e->getMessage());
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error: ' . $e->getMessage()]);
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
        
                // CÓDIGO CORREGIDO (Línea aprox 160-170)
               // 1. Insertar Encabezado de Compra (CORREGIDO SEGÚN TU TABLA)
            $sql_cab  = "INSERT INTO compras_cabecera 
                (codigo_institucion, numero_documento, id_proveedores, fecha_emision, tipo_documento, 
                 condicion_pago, plazo_pago, total_gravado, total_iva, total_compra, observaciones, fecha_creacion) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) RETURNING id_compra";
            
            $stmt_cab = $pdo->prepare($sql_cab);
            $stmt_cab->execute([
                $codigo_institucion_sesion,
                $datos_cabecera['numero_documento'],
                $datos_cabecera['id_proveedores'], // <--- AQUÍ USAMOS EL ID RELACIONAL
                $datos_cabecera['fecha_emision'],
                $datos_cabecera['tipo_documento'], 
                $datos_cabecera['condicion_pago'],
                $datos_cabecera['plazo_pago'] ?? null, // Agregué plazo_pago porque lo vi en tu tabla
                $datos_cabecera['total_gravado'],
                $datos_cabecera['total_iva'],
                $datos_cabecera['total_pagar'],
                $datos_cabecera['observaciones'] ?? ''
            ]);
            $id_compra = $stmt_cab->fetchColumn();
        
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

            // --- DETERMINAR LA CUENTA DE SALIDA (HABER) ---
                $id_cuenta_salida = 0;
                $condicion_pago = $compra_data['condicion_pago'] ?? '1'; // 1: Contado, 2: Crédito
                
                if ($condicion_pago == '2') {
                    // A. ES CRÉDITO -> Usamos Cuentas por Pagar Proveedores
                    $id_cuenta_salida = obtenerIdCuentaPorMapeo($pdo, $codigo_institucion_sesion, 'PROVEEDORES_CP');
                } else {
                    // B. ES CONTADO -> Buscamos según el Método (Efectivo, Banco, etc.)
                    $forma_pago = $compra_data['forma_pago'] ?? '01'; // Default Efectivo
                    
                    // Buscamos en la tabla de configuración
                    $sql_cuenta = "SELECT id_cuenta_contable FROM configuracion_cuentas_pago 
                                WHERE codigo_forma_pago = ? AND codigo_institucion = ?";
                    $stmt_c = $pdo->prepare($sql_cuenta);
                    $stmt_c->execute([$forma_pago, $codigo_institucion_sesion]);
                    $res_cuenta = $stmt_c->fetch(PDO::FETCH_ASSOC);
                    
                    if ($res_cuenta) {
                        $id_cuenta_salida = $res_cuenta['id_cuenta_contable'];
                    } else {
                        // FALLBACK: Si no configuraron la cuenta específica, usamos CAJA GENERAL por defecto
                        // O lanza un error si prefieres ser estricto.
                        $id_cuenta_salida = obtenerIdCuentaPorMapeo($pdo, $codigo_institucion_sesion, 'CAJA_GENERAL');
                    }
                }

                // --- CONSTRUIR DETALLE DEL ASIENTO ---
                $datos_detalle_asiento = [
                    // 1. CARGO: Inventario
                    ['cuenta_id' => $id_inventario, 'debito' => $monto_inventario, 'credito' => 0.00],
                    
                    // 2. CARGO: IVA (si aplica)
                    ['cuenta_id' => $id_iva_credito, 'debito' => $monto_iva, 'credito' => 0.00],
                    
                    // 3. ABONO: Cuenta de Salida (Banco, Caja o Proveedor)
                    ['cuenta_id' => $id_cuenta_salida, 'debito' => 0.00, 'credito' => $monto_proveedor]
                ];
                
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

        // =================================================================================
        // 3. PREPARACIÓN DE CONDICIONES DE PAGO (LÓGICA PRIORITARIA)
        // =================================================================================
        // Definimos la fecha de emisión desde el JSON para los cálculos
        $fecha_emision = $compra_data['fecha_emision'];

        // A. Condición de Pago: 
        // PRIORIDAD 1: Lo que viene en el $_POST (lo que seleccionaste en el select)
        // PRIORIDAD 2: Lo que dice el JSON (resumen -> condicionOperacion)
        // PRIORIDAD 3: Por defecto '1' (Contado)
        $condicion_pago = $_POST['condicion_pago'] ?? $compra_data['resumen']['condicionOperacion'] ?? '1';

        // B. Días y Vencimiento:
        $dias_credito = intval($_POST['dias_credito'] ?? 0);
        $fecha_venc   = $_POST['fecha_vencimiento'] ?? null;

        // C. Cálculo Automático (Si es Crédito):
        if ($condicion_pago == '2') {
            if (empty($fecha_venc)) {
                // Si el usuario no mandó fecha (o el JS falló), calculamos en PHP
                $dias_calc = ($dias_credito > 0) ? $dias_credito : 30; // Default 30 días
                
                // Si venía en 0, lo forzamos a 30 para guardar el dato correcto
                if ($dias_credito == 0) $dias_credito = 30;

                $fecha_venc = date('Y-m-d', strtotime($fecha_emision . " + $dias_calc days"));
            }
        } else {
            // Si es Contado, limpiamos para no ensuciar la BD
            $dias_credito = 0;
            $fecha_venc   = null;
        }

        // =================================================================================
        // 4. INSERTAR CABECERA (ACTUALIZADO CON NUEVOS CAMPOS)
        // =================================================================================
        $sql_cabecera = "INSERT INTO compras_cabecera (
                codigo_institucion, numero_documento, tipo_documento, fecha_emision,
                id_proveedores, condicion_pago, dias_credito, fecha_vencimiento, observaciones,
                total_no_suj, total_exenta, total_gravada, total_iva, total_descuento, total_compra, 
                tipo_dte, sello_recibido, firma_electronica, fecha_creacion
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
        $stmt_cabecera = $pdo->prepare($sql_cabecera);
        $stmt_cabecera->execute([
            $codigo_institucion_sesion,
            $compra_data['numero_control'],
            $compra_data['tipo_dte'],
            $fecha_emision,
            $proveedor_id,
            
            $condicion_pago, // <--- Ahora usa tu selección manual si existe
            $dias_credito,   // <--- NUEVO
            $fecha_venc,     // <--- NUEVO
            
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
// ============================================================
            // 1. VALIDACIÓN Y AUTO-GENERACIÓN DE CÓDIGO (Si viene NULL)
            // ============================================================
            if (empty($producto['codigo_proveedor']) || $producto['codigo_proveedor'] === 'null') {
                // Generamos un código genérico basado en la descripción.
                // Usamos MD5 para que si vuelves a comprar el mismo producto "sin código" 
                // con la misma descripción exacta, el sistema intente reconocerlo en lugar de crear duplicados.
                
                $descripcion_limpia = trim($producto['descripcion'] ?? 'SIN_NOMBRE');
                $hash_desc = strtoupper(substr(md5($descripcion_limpia), 0, 6)); // Ej: A1B2C3
                
                // Asignamos el código generado
                $producto['codigo_proveedor'] = "GEN-" . $hash_desc;
            }
            // ============================================================

        // --- A. LÓGICA DE COSTO NETO (Detectar si viene con IVA) ---
        $precio_papel = floatval($producto['precio_costo']); // Precio que viene en el JSON
        $tipo_dte_compra = $compra_data['tipo_dte'] ?? '03'; // 01: Factura, 03: CCF
            // --- B. LÓGICA AVANZADA DE TIPOS DTE (EL SALVADOR) ---
                $tipo_dte = $compra_data['tipo_dte']; // '01', '03', '05', '14', etc.
                
                $costo_neto_kardex = 0;
                $factor_cantidad = 1; // 1 = Entrada, -1 = Salida (Devolución)

                switch ($tipo_dte) {
                    case '01': // FACTURA (IVA Incluido)
                        // Legislación: Al ser contribuyente, debes separar el IVA para encontrar el costo real.
                        $costo_neto_kardex = round($precio_papel / 1.13, 4);
                        $factor_cantidad = 1;
                        break;

                    case '03': // COMPROBANTE DE CRÉDITO FISCAL (Neto)
                    case '11': // FACTURA DE EXPORTACIÓN (Generalmente tasa 0%)
                        // El precio ingresado ya es el costo neto.
                        $costo_neto_kardex = $precio_papel;
                        $factor_cantidad = 1;
                        break;

                    case '14': // SUJETO EXCLUIDO (Sin IVA)
                        // No hay IVA involucrado, el costo total es el precio pagado.
                        $costo_neto_kardex = $precio_papel;
                        $factor_cantidad = 1;
                        break;

                    case '04': // NOTA DE REMISIÓN
                        // Sirve para amparar traslado. Si se usa para ingresar stock, el valor es referencial neto.
                        $costo_neto_kardex = $precio_papel;
                        $factor_cantidad = 1;
                        break;

                    case '05': // NOTA DE CRÉDITO (Devolución sobre Compra)
                        // OJO: Una Nota de Crédito en Compras significa que DEVOLVISTE mercadería al proveedor.
                        // Por tanto, el inventario debe DISMINUIR.
                        // Asumimos que la NC hace referencia a un CCF, por lo que el precio viene Neto.
                        $costo_neto_kardex = $precio_papel;
                        $factor_cantidad = -1; // ¡ESTO RESTARÁ DEL INVENTARIO!
                        break;

                    default:
                        // Por seguridad, asumimos comportamiento de CCF (Neto)
                        $costo_neto_kardex = $precio_papel;
                        $factor_cantidad = 1;
                        break;
                }

        // --- B. DEFINIR PARÁMETROS DE VENTA ---
        $impuesto_codigo = $producto['impuesto_aplicable'] ?? '20'; // '20' suele ser IVA 13%
        
        // Intentamos usar el código del JSON, si falla, usaremos uno por defecto
        $ganancia_codigo_json = $producto['codigo_ganancia'] ?? null; 
        
        // --- C. CONSULTAR BD ---
        
        // 1. Impuesto
        $sql_imp = "SELECT porcentaje, tipo_impuesto, monto_fijo FROM cat_015 WHERE codigo = ?";
        $stmt_imp = $pdo->prepare($sql_imp);
        $stmt_imp->execute([$impuesto_codigo]);
        $info_imp = $stmt_imp->fetch(PDO::FETCH_ASSOC);

       // 2. Ganancia (Búsqueda Blindada)
       $info_gan = false;
            
       // A. Primero intentamos buscar con el código que trae el JSON (si trae alguno)
       if ($ganancia_codigo_json) {
           $sql_gan = "SELECT porcentaje, codigo FROM catalogo_ganancia WHERE codigo = ? AND codigo_institucion = ?";
           $stmt_gan = $pdo->prepare($sql_gan);
           $stmt_gan->execute([$ganancia_codigo_json, $codigo_institucion_sesion]);
           $info_gan = $stmt_gan->fetch(PDO::FETCH_ASSOC);
       }

       // B. Si NO encontró (o el JSON venía vacío), FORZAMOS 'GAN002' (30%)
       if (!$info_gan) {
           $codigo_ganancia_defecto = 'GAN002'; // <--- AQUÍ FORZAMOS TU CÓDIGO
           
           $sql_gan_def = "SELECT porcentaje, codigo FROM catalogo_ganancia WHERE codigo = ? AND codigo_institucion = ?";
           $stmt_gan_def = $pdo->prepare($sql_gan_def);
           $stmt_gan_def->execute([$codigo_ganancia_defecto, $codigo_institucion_sesion]);
           $info_gan = $stmt_gan_def->fetch(PDO::FETCH_ASSOC);
           
           if ($info_gan) {
               $ganancia_codigo = $info_gan['codigo'];
           } else {
               // C. Plan de emergencia: Si borraste GAN002, usar 30% fijo para que no dé error
               $ganancia_codigo = 'GAN002'; 
               $info_gan = ['porcentaje' => 30.00]; 
           }
       } else {
           $ganancia_codigo = $info_gan['codigo'];
       }

        // --- D. CÁLCULO PRECIO VENTA ---
        
        // Factor Impuesto (Para agregar IVA al costo neto)
        $factor_impuesto = 1;
        $monto_impuesto_fijo = 0;
        if ($info_imp) {
            if ($info_imp['tipo_impuesto'] === 'PORCENTAJE') {
                $factor_impuesto = 1 + ($info_imp['porcentaje'] / 100);
            } elseif ($info_imp['tipo_impuesto'] === 'MONETARIO') {
                $monto_impuesto_fijo = floatval($info_imp['monto_fijo']);
            }
        }

        // Factor Ganancia
        $factor_ganancia = 1 + (($info_gan['porcentaje'] ?? 0) / 100);

        // FÓRMULA FINAL: (CostoNeto * (1+IVA)) * (1+Ganancia)
        // Ejemplo: ($10.00 * 1.13) * 1.30 = $14.69
        $precio_base_con_impuestos = ($costo_neto_kardex * $factor_impuesto) + $monto_impuesto_fijo;
        $nuevo_precio_venta = round($precio_base_con_impuestos * $factor_ganancia, 4);

        // --- DEBUG LOG (Para verificar que ahora sí toma ganancia) ---
        error_log("PROD: " . ($producto['descripcion']));
        error_log("COSTO ORIG: $costo_neto_kardex | TIPO DTE: $tipo_dte_compra | COSTO NETO CALC: $costo_neto_kardex");
        error_log("GANANCIA USADA: " . ($info_gan['porcentaje'] ?? 0) . "% (Codigo: $ganancia_codigo)");
        error_log("PRECIO VENTA: $nuevo_precio_venta");
        // -------------------------------------------------------------

        // --- E. ACTUALIZACIÓN EN BASE DE DATOS ---

        // Buscar producto existente
        $sql_find = "SELECT id_productos, codigo_interno, stock_actual 
                     FROM catalogo_productos 
                     WHERE codigo_proveedor = ? AND codigo_institucion = ?";
        $stmt_find = $pdo->prepare($sql_find);
        $stmt_find->execute([$producto['codigo_proveedor'], $codigo_institucion_sesion]);
        $producto_db = $stmt_find->fetch(PDO::FETCH_ASSOC);

        if ($producto_db) {
            // ACTUALIZAR EXISTENTE
            $producto['id_productos'] = $producto_db['id_productos'];
            $producto['codigo_interno'] = $producto_db['codigo_interno'];

           // --- G. ACTUALIZAR MAESTRO DE PRODUCTOS (CATÁLOGO) ---
                
                // Calculamos la cantidad real a mover (Positiva o Negativa)
                $cantidad_a_mover = $producto['cantidad'] * $factor_cantidad;

                $sql_upd = "UPDATE catalogo_productos SET 
                                stock_actual = stock_actual + ?,  /* Si es NC, sumará un negativo (restando) */
                                precio_costo = ?,                 /* Actualizamos costo promedio/último */
                                precio_unitario = ?,
                                codigo_ganancia = ?
                            WHERE codigo_interno = ? AND codigo_institucion = ?";
                
                $pdo->prepare($sql_upd)->execute([
                    $cantidad_a_mover,     // <--- AQUÍ USAMOS LA CANTIDAD CON SIGNO
                    $costo_neto_kardex,
                    $nuevo_precio_venta,
                    $ganancia_codigo,
                    $producto['codigo_interno'],
                    $codigo_institucion_sesion
                ]);

        } else {
            // CREAR NUEVO
            if (empty($producto['codigo_categoria'])) {
                $producto['codigo_categoria'] = 'CAT008'; 
            }
            $codigo_producto_generado = generarCodigoProducto($pdo, $producto['codigo_categoria'], $codigo_institucion_sesion);

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
                $costo_neto_kardex,
                $nuevo_precio_venta,
                $impuesto_codigo,
                $producto['unidad_medida'],
                $producto['codigo_proveedor'],
                $producto['cantidad'],
                $producto['fecha_vencimiento'] ?? null,
                $ganancia_codigo
            ]);
            $producto['id_productos'] = $stmt_insert_producto->fetchColumn();
            $producto['codigo_interno'] = $codigo_producto_generado;
        }

        // Insertar detalle de compra
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
            $costo_neto_kardex, // Guardamos el neto
            $precio_papel, // Guardamos el precio original de la factura (con IVA si era 01)
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

       // =========================================================
            // 4. INTEGRACIÓN CONTABLE AUTOMÁTICA (CORREGIDA)
            // =========================================================

            // A. OBTENER CUENTAS
            $id_inventario = obtenerIdCuentaPorMapeo($pdo, $codigo_institucion_sesion, 'INVENTARIO_MERCADERIA');
            $id_iva_credito = obtenerIdCuentaPorMapeo($pdo, $codigo_institucion_sesion, 'IVA_CREDITO_FISCAL');
            $id_proveedores_cp = obtenerIdCuentaPorMapeo($pdo, $codigo_institucion_sesion, 'PROVEEDORES_CP');
            
            // B. OBTENER VALORES DEL JSON
            // Nota: En DTE Factura (01), 'totalGravada' suele ser el valor CON IVA incluido.
            // En DTE CCF (03), 'totalGravada' suele ser el valor NETO.
            $json_gravada = floatval($compra_data['resumen']['total_gravada'] ?? 0);
            $json_iva     = floatval($compra_data['resumen']['total_iva'] ?? 0);
            $json_total   = floatval($compra_data['resumen']['total_pagar'] ?? 0);
            $json_exenta  = floatval($compra_data['resumen']['total_exenta'] ?? 0);
            $json_nosuj   = floatval($compra_data['resumen']['total_no_suj'] ?? 0);

            // C. DETERMINAR MONTOS PARA EL ASIENTO SEGÚN TIPO DOCUMENTO
            $tipo_dte = $compra_data['tipo_dte'] ?? '03';
            
            $monto_inventario = 0;
            $monto_iva        = $json_iva;
            $monto_proveedor  = $json_total; // Lo que realmente se paga

            if ($tipo_dte === '01') {
                // CASO FACTURA: El total a pagar ($6.00) incluye IVA.
                // Inventario = Total Pagar - IVA - Exentas/NoSujetas
                // Ejemplo: $6.00 - $0.69 = $5.31 (Costo Neto)
                // (Asumiendo que json_gravada trae el bruto)
                $monto_inventario = $json_gravada - $json_iva; 
                
                // Ajuste de seguridad: Si hay exentas, se suman directo al inventario
                $monto_inventario += ($json_exenta + $json_nosuj);

            } else {
                // CASO CCF (03) y OTROS: El total gravado es Neto.
                // Inventario = Total Gravado + Exentas + No Sujetas
                $monto_inventario = $json_gravada + $json_exenta + $json_nosuj;
            }

            // D. CONSTRUIR ENCABEZADO
            $datos_encabezado = [
                'fechaAsiento'    => $compra_data['fecha_emision'] ?? date('Y-m-d'), 
                'tipoAsiento'     => 'Egreso', 
                'concepto'        => "Registro automático de Compra DTE No. " . $compra_data['numero_control'] . " del proveedor " . $compra_data['emisor_nombre'],
                'usuarioRegistro' => $usuario_activo
            ];

            // E. CONSTRUIR DETALLE (Partida Doble)
            $datos_detalle_asiento = [
                // 1. DÉBITO: Inventario (Costo Neto)
                [
                    'cuenta_id' => $id_inventario, 
                    'debito'    => $monto_inventario, 
                    'credito'   => 0.00
                ],
                
                // 2. DÉBITO: IVA (Impuesto)
                [
                    'cuenta_id' => $id_iva_credito, 
                    'debito'    => $monto_iva, 
                    'credito'   => 0.00
                ],
                
                // 3. CRÉDITO: Proveedor (Total a Pagar)
                [
                    'cuenta_id' => $id_proveedores_cp, 
                    'debito'    => 0.00, 
                    'credito'   => $monto_proveedor
                ],
            ];

            // F. REGISTRAR
            $resultado_contable = registrarAsientoAutomatico(
                $pdo, 
                $codigo_institucion_sesion, 
                $datos_encabezado, 
                $datos_detalle_asiento
            );

            if ($resultado_contable['respuesta']) {
                $pdo->prepare("UPDATE compras_cabecera SET asiento_id = ? WHERE id_compra = ?")->execute([$resultado_contable['numero_asiento'], $id_compra]);
            } else {
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
                // CORRECCIÓN: Agregamos búsqueda por 'codigo_proveedor'
                // Usamos CAST(id_productos AS TEXT) para evitar errores de PostgreSQL si el término tiene letras
                $sql = "SELECT id_productos, codigo_interno, codigo_proveedor, descripcion, 
                               unidad_medida, precio_costo, precio_unitario, 
                               impuesto_aplicable, codigo_ganancia 
                        FROM catalogo_productos 
                        WHERE (CAST(id_productos AS TEXT) = ? 
                               OR codigo_interno = ? 
                               OR codigo_barra = ? 
                               OR codigo_proveedor = ?) 
                        AND codigo_institucion = ?";
                
                $stmt = $pdo->prepare($sql);
                // Pasamos el término 4 veces (uno para cada campo del OR) + el código de institución
                $stmt->execute([$termino, $termino, $termino, $termino, $codigo_institucion_sesion]);
                
                $producto = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($producto) {
                    // Cálculo rápido de sugerencia de precio (opcional, igual el JS recalcula)
                    // Esto es solo para que el JSON vaya completo
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
                //  MAPEO DETALLE (productos) CON AUTO-GENERACIÓN DE CÓDIGO
                // =============================
               // =============================
                //  MAPEO DETALLE CON DETECCION FISCAL INTELIGENTE
                // =============================
                $productos = [];
                $contador_sin_codigo = 1;

                foreach ($mapeo['productos_dte'] as $item) {
                    $codigo_proveedor_producto = $item['codigo'] ?? null;
                    $descripcion_producto      = $item['descripcion'] ?? null;
        
                    // 1. GENERAR CÓDIGO SI FALTA
                    if (empty($codigo_proveedor_producto) || $codigo_proveedor_producto === 'null') {
                        $descripcion_limpia = trim($descripcion_producto ?? 'SIN_NOMBRE');
                        $hash_desc = strtoupper(substr(md5($descripcion_limpia), 0, 6)); 
                        $codigo_proveedor_producto = "GEN-" . $hash_desc;
                    }

                    // Buscar producto en catálogo interno
                    $sql_find_product = "SELECT id_productos, codigo_interno, impuesto_aplicable, codigo_ganancia, precio_costo, unidad_medida 
                                            FROM catalogo_productos 
                                            WHERE codigo_interno = ? AND codigo_institucion = ?";
                    $stmt_find_product = $pdo->prepare($sql_find_product);
                    $stmt_find_product->execute([$codigo_proveedor_producto, $codigo_institucion_sesion]);
                    $producto_db = $stmt_find_product->fetch(PDO::FETCH_ASSOC);
        
                    // -------------------------------------------------------------
                    // 2. DETECCIÓN DE ESTATUS FISCAL (EXENTO / NO SUJETO / GRAVADO)
                    // -------------------------------------------------------------
                    // Analizamos dónde viene el dinero en el JSON para determinar el impuesto por defecto
                    $codigo_impuesto_detectado = '20'; // Por defecto asumimos Gravado (IVA 13%)

                    $monto_exento = floatval($item['ventaExenta'] ?? 0);
                    $monto_nosuj  = floatval($item['ventaNoSuj'] ?? 0);
                    
                    if ($monto_exento > 0) {
                        // Si trae valor en Exento, asignamos código '00' o el que uses para "Sin Impuesto"
                        $codigo_impuesto_detectado = '00'; 
                    } elseif ($monto_nosuj > 0) {
                        // Si trae valor en No Sujeto
                        $codigo_impuesto_detectado = '00'; 
                    }
                    
                    // Si el producto YA EXISTE en BD, respetamos su config. 
                    // Si NO EXISTE, usamos lo que detectamos en el JSON.
                    $impuesto_final = $producto_db['impuesto_aplicable'] ?? $codigo_impuesto_detectado;
                    // -------------------------------------------------------------

                    $producto_mapeado = [
                        'id_productos'       => $producto_db['id_productos'] ?? null,
                        'codigo_interno'     => $producto_db['codigo_interno'] ?? null,
                        'codigo_proveedor'   => $codigo_proveedor_producto,
                        'descripcion'        => $descripcion_producto,
                        'cantidad'           => $item['cantidad'] ?? 0,
                        'precio_unitario'    => $item['precioUni'] ?? 0, // Precio DTE
                        'precio_costo'       => $item['precioUni'] ?? 0, // Costo base
                        'iva'                => $item['ivaItem'] ?? 0,
                        'descuento'          => $item['montoDescu'] ?? 0,
                        'venta_gravada'      => $item['ventaGravada'] ?? 0,
                        'venta_exenta'       => $item['ventaExenta'] ?? 0,
                        'venta_no_suj'       => $item['ventaNoSuj'] ?? 0,
                        'unidad_medida'      => $producto_db['unidad_medida'] ?? ($item['uniMedida'] ?? null),
                        
                        // Aquí aplicamos el impuesto detectado
                        'impuesto_aplicable' => $impuesto_final,
                        
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
// --- NUEVO CASE PARA LLENAR EL SELECT ---
    case 'obtenerMetodosPago':
        $stmt = $pdo->query("SELECT codigo, descripcion FROM cat_017_forma_pago ORDER BY codigo ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    default:
        echo json_encode(['respuesta' => false, 'mensaje' => 'Acción no válida.']);
        break;
}
?>