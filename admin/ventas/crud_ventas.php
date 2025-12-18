<?php
// admin/ventas/crud_ventas.php
session_name('FactuFacil');
session_start();

header('Content-Type: application/json');
$path_root = trim($_SERVER['DOCUMENT_ROOT']);
include($path_root."/FactuFacil/includes/mainFunctions_.php");
include($path_root."/FactuFacil/admin/contabilidad/funciones/contabilidad_api.php");
/** @var PDO $dblink */
$pdo = $dblink;
$accion = $_POST['accion'] ?? '';
$codigo_institucion = $_SESSION['codigo_institucion'];
$usuario_activo = $_SESSION['userNombre'] ?? 'Sistema';

try {
    switch ($accion) {
        
        // ------------------------------------------------------------------
        // 1. BUSCAR PRODUCTO (Adaptado a tu tabla catalogo_productos)
        // ------------------------------------------------------------------
        case 'buscar_producto_venta':
            $termino = $_POST['termino'] ?? '';
            
            // CORRECCIÓN: Usamos 'stock_actual' y 'precio_unitario' según tu crud_productos.php
            $sql = "SELECT 
                        id_productos, 
                        codigo_interno, 
                        descripcion, 
                        precio_unitario AS precio_venta, 
                        stock_actual, 
                        precio_costo 
                    FROM catalogo_productos 
                    WHERE (codigo_interno ILIKE ? OR descripcion ILIKE ? OR codigo_barra ILIKE ?) 
                    AND codigo_institucion = ? 
                    AND stock_actual > 0 
                    LIMIT 20";
            
            $stmt = $pdo->prepare($sql);
            $busqueda = "%$termino%";
            $stmt->execute([$busqueda, $busqueda, $busqueda, $codigo_institucion]);
            
            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['respuesta' => true, 'productos' => $productos]);
            break;

        // ------------------------------------------------------------------
        // 2. GUARDAR LA VENTA
        // ------------------------------------------------------------------
        case 'guardar_venta':
            $pdo->beginTransaction();

            $datos_venta = json_decode($_POST['venta_cabecera'], true);
            $productos = json_decode($_POST['venta_detalle'], true);

            // A. Insertar Cabecera
            // Asegúrate que la tabla ventas_cabecera exista (te pasé el SQL antes)
            $sql_cab = "INSERT INTO ventas_cabecera 
                (codigo_institucion, numero_documento, tipo_documento, fecha_emision, id_cliente, condicion_pago, 
                 total_gravado, total_iva, total_venta, usuario_registro)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id_venta";
            
            $stmt_cab = $pdo->prepare($sql_cab);
            $stmt_cab->execute([
                $codigo_institucion,
                $datos_venta['numero_documento'],
                $datos_venta['tipo_documento'],
                $datos_venta['fecha_emision'],
                $datos_venta['id_cliente'],
                $datos_venta['condicion_pago'],
                $datos_venta['total_gravado'],
                $datos_venta['total_iva'],
                $datos_venta['total_pagar'],
                $usuario_activo
            ]);
            $id_venta = $stmt_cab->fetchColumn();

            // Acumulador para el Costo de Ventas (Contabilidad)
            $total_costo_venta = 0;

            // B. Procesar Detalles y RESTAR Inventario
            foreach ($productos as $prod) {
                // Verificar Stock Actual una última vez (concurrencia)
                // CORRECCIÓN: Usamos 'stock_actual'
                $sql_stock = "SELECT stock_actual, precio_costo FROM catalogo_productos WHERE codigo_interno = ? AND codigo_institucion = ?";
                $stmt_check = $pdo->prepare($sql_stock);
                $stmt_check->execute([$prod['codigo'], $codigo_institucion]);
                $info_actual = $stmt_check->fetch(PDO::FETCH_ASSOC);

                if (!$info_actual || $info_actual['stock_actual'] < $prod['cantidad']) {
                    throw new Exception("Stock insuficiente para: " . $prod['descripcion'] . ". Disponible: " . ($info_actual['stock_actual'] ?? 0));
                }

                // Insertar Detalle
                $sql_det = "INSERT INTO ventas_detalle 
                    (id_venta, codigo_producto, descripcion, cantidad, precio_unitario, precio_costo_historico, subtotal, iva)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_det = $pdo->prepare($sql_det);
                $stmt_det->execute([
                    $id_venta,
                    $prod['codigo'], // Código interno
                    $prod['descripcion'],
                    $prod['cantidad'],
                    $prod['precio_venta'],
                    $info_actual['precio_costo'], // Costo Histórico
                    $prod['subtotal'],
                    $prod['iva']
                ]);

                // Descontar Inventario
                // CORRECCIÓN: Usamos 'stock_actual'
                $sql_upd = "UPDATE catalogo_productos SET stock_actual = stock_actual - ? WHERE codigo_interno = ? AND codigo_institucion = ?";
                $pdo->prepare($sql_upd)->execute([$prod['cantidad'], $prod['codigo'], $codigo_institucion]);

                // Sumar al costo total de la partida contable
                $total_costo_venta += ($info_actual['precio_costo'] * $prod['cantidad']);
            }

            // ----------------------------------------------------
            // C. ASIENTO CONTABLE (DOBLE: Ingreso + Costo)
            // ----------------------------------------------------
            
            // 1. Obtener IDs de Cuentas (Mapeos)
            $id_caja        = obtenerIdCuentaPorMapeo($pdo, $codigo_institucion, 'CAJA_GENERAL'); 
            $id_ingreso     = obtenerIdCuentaPorMapeo($pdo, $codigo_institucion, 'INGRESOS_VENTA');
            $id_iva_debito  = obtenerIdCuentaPorMapeo($pdo, $codigo_institucion, 'IVA_DEBITO_FISCAL');
            $id_costo       = obtenerIdCuentaPorMapeo($pdo, $codigo_institucion, 'COSTO_VENTA');
            $id_inventario  = obtenerIdCuentaPorMapeo($pdo, $codigo_institucion, 'INVENTARIO_MERCADERIA');

            // 2. Armar las líneas del asiento
            $detalle_asiento = [];

            // --- PARTE 1: LA VENTA (Dinero entra) ---
            // DEBE: Caja General (Total Factura)
            $detalle_asiento[] = ['cuenta_id' => $id_caja, 'debito' => $datos_venta['total_pagar'], 'credito' => 0.00];
            // HABER: Ventas (Subtotal neto)
            $detalle_asiento[] = ['cuenta_id' => $id_ingreso, 'debito' => 0.00, 'credito' => $datos_venta['total_gravado']];
            // HABER: IVA Débito Fiscal
            $detalle_asiento[] = ['cuenta_id' => $id_iva_debito, 'debito' => 0.00, 'credito' => $datos_venta['total_iva']];

            // --- PARTE 2: EL COSTO (Mercadería sale) ---
            // DEBE: Costo de Venta
            $detalle_asiento[] = ['cuenta_id' => $id_costo, 'debito' => $total_costo_venta, 'credito' => 0.00];
            // HABER: Inventario
            $detalle_asiento[] = ['cuenta_id' => $id_inventario, 'debito' => 0.00, 'credito' => $total_costo_venta];

            // 3. Registrar
            $datos_encabezado = [
                'fechaAsiento' => $datos_venta['fecha_emision'],
                'tipoAsiento' => 'Ingreso',
                'concepto' => 'Venta Factura #' . $datos_venta['numero_documento'],
                'usuarioRegistro' => $usuario_activo
            ];

            $res_contable = registrarAsientoAutomatico($pdo, $codigo_institucion, $datos_encabezado, $detalle_asiento);

            if (!$res_contable['respuesta']) {
                throw new Exception("Error Contable: " . $res_contable['mensaje']);
            }

            // Vincular asiento a la venta
            $pdo->prepare("UPDATE ventas_cabecera SET asiento_id = ? WHERE id_venta = ?")
                ->execute([$res_contable['numero_asiento'], $id_venta]);

            $pdo->commit();
            echo json_encode(['respuesta' => true, 'mensaje' => 'Venta registrada con éxito. Asiento #' . $res_contable['numero_asiento'], 'id_venta' => $id_venta]);
            break;

        default:
            echo json_encode(['respuesta' => false, 'mensaje' => 'Acción no reconocida']);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    echo json_encode(['respuesta' => false, 'mensaje' => $e->getMessage()]);
}
?>