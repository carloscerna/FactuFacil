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

// ==============================================================================
// FUNCIÓN AUXILIAR: REGISTRAR INGRESO DE DINERO (VENTA)
// ==============================================================================
function procesar_ingreso_dinero($pdo, $cod_inst, $destino, $id_cuenta, $monto) {
    // $destino: 'BANCO' o 'CAJA'
    $id_cuenta_contable = null;

    if ($destino === 'BANCO') {
        $stmt = $pdo->prepare("SELECT id_cuenta_contable FROM fin_bancos_cuentas WHERE id = ? AND codigo_institucion = ?");
        $stmt->execute([$id_cuenta, $cod_inst]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new Exception("Cuenta bancaria destino no encontrada.");

        // SUMAR Dinero
        $stmtUpd = $pdo->prepare("UPDATE fin_bancos_cuentas SET saldo_actual = saldo_actual + ? WHERE id = ?");
        $stmtUpd->execute([$monto, $id_cuenta]);
        $id_cuenta_contable = $row['id_cuenta_contable'];

    } elseif ($destino === 'CAJA') {
        $stmt = $pdo->prepare("SELECT id_cuenta_contable FROM fin_cajas WHERE id = ? AND codigo_institucion = ?");
        $stmt->execute([$id_cuenta, $cod_inst]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new Exception("Caja destino no encontrada.");

        // SUMAR Dinero
        $stmtUpd = $pdo->prepare("UPDATE fin_cajas SET saldo_actual = saldo_actual + ? WHERE id = ?");
        $stmtUpd->execute([$monto, $id_cuenta]);
        $id_cuenta_contable = $row['id_cuenta_contable'];
    }

    return $id_cuenta_contable;
}

try {
    switch ($accion) {
        
        // --- 1. CARGAR CUENTAS PARA RECIBIR DINERO ---
        case 'listar_cuentas_ingreso':
            try {
                $data = ['bancos' => [], 'cajas' => []];
                // Cajas
                $stmtC = $pdo->prepare("SELECT id, nombre_caja, saldo_actual FROM fin_cajas WHERE codigo_institucion = ? AND estado = 'A'");
                $stmtC->execute([$codigo_institucion]);
                $data['cajas'] = $stmtC->fetchAll(PDO::FETCH_ASSOC);
                // Bancos
                $stmtB = $pdo->prepare("SELECT id, nombre_banco, numero_cuenta, saldo_actual FROM fin_bancos_cuentas WHERE codigo_institucion = ? AND estado = 'A'");
                $stmtB->execute([$codigo_institucion]);
                $data['bancos'] = $stmtB->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['respuesta' => true, 'datos' => $data]);
            } catch (Exception $e) {
                echo json_encode(['respuesta' => false, 'mensaje' => $e->getMessage()]);
            }
            break;

        // --- 2. BUSCAR PRODUCTO ---
        case 'buscar_producto_venta':
            $termino = $_POST['termino'] ?? '';
            $sql = "SELECT id_productos, codigo_interno, descripcion, precio_unitario AS precio_venta, 
                           stock_actual, precio_costo, impuesto_aplicable, unidad_medida 
                    FROM catalogo_productos 
                    WHERE (codigo_interno ILIKE ? OR descripcion ILIKE ? OR codigo_barra ILIKE ?) 
                    AND codigo_institucion = ? AND stock_actual > 0 LIMIT 20";
            $stmt = $pdo->prepare($sql);
            $busqueda = "%$termino%";
            $stmt->execute([$busqueda, $busqueda, $busqueda, $codigo_institucion]);
            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['respuesta' => true, 'productos' => $productos]);
            break;

        // --- 3. GUARDAR VENTA (CAPA 1) ---
        case 'guardar_venta':
            $pdo->beginTransaction();

            // Recibir Datos
            $datos_venta = json_decode($_POST['venta_cabecera'], true);
            $productos   = json_decode($_POST['venta_detalle'], true);
            
            // Datos de Tesorería (Dónde entra la plata)
            $destino_pago     = $datos_venta['destino_pago'] ?? 'CAJA'; // BANCO/CAJA
            $id_cuenta_destino= $datos_venta['id_cuenta_destino'] ?? null;
            $condicion_pago   = $datos_venta['condicion_pago']; // CONTADO/CREDITO

            // A. Insertar Cabecera
            $sql_cab = "INSERT INTO ventas_cabecera 
                (codigo_institucion, numero_documento, tipo_documento, fecha_emision, id_cliente, condicion_pago, 
                 total_gravado, total_iva, total_venta, usuario_registro, fecha_creacion)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) RETURNING id_venta";
            
            $stmt_cab = $pdo->prepare($sql_cab);
            $stmt_cab->execute([
                $codigo_institucion,
                $datos_venta['numero_documento'],
                $datos_venta['tipo_documento'],
                $datos_venta['fecha_emision'],
                $datos_venta['id_cliente'],
                $condicion_pago,
                $datos_venta['total_gravado'],
                $datos_venta['total_iva'],
                $datos_venta['total_pagar'],
                $usuario_activo
            ]);
            $id_venta = $stmt_cab->fetchColumn();

            // B. Procesar Detalles y Calcular Costo de Venta
            $total_costo_venta = 0;

            foreach ($productos as $prod) {
                // Verificar Stock
                $sql_stock = "SELECT stock_actual, precio_costo FROM catalogo_productos WHERE codigo_interno = ? AND codigo_institucion = ?";
                $stmt_check = $pdo->prepare($sql_stock);
                $stmt_check->execute([$prod['codigo'], $codigo_institucion]);
                $info_actual = $stmt_check->fetch(PDO::FETCH_ASSOC);

                if (!$info_actual || $info_actual['stock_actual'] < $prod['cantidad']) {
                    throw new Exception("Stock insuficiente para: " . $prod['descripcion']);
                }

                $costo_historico = $info_actual['precio_costo'];

                // Insertar Detalle
                $sql_det = "INSERT INTO ventas_detalle 
                    (id_venta, codigo_producto, descripcion, cantidad, precio_unitario, precio_costo_historico, subtotal, iva)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $pdo->prepare($sql_det)->execute([
                    $id_venta, $prod['codigo'], $prod['descripcion'], $prod['cantidad'],
                    $prod['precio_venta'], $costo_historico, $prod['subtotal'], $prod['iva']
                ]);

                // Descontar Inventario
                $sql_upd = "UPDATE catalogo_productos SET stock_actual = stock_actual - ? WHERE codigo_interno = ? AND codigo_institucion = ?";
                $pdo->prepare($sql_upd)->execute([$prod['cantidad'], $prod['codigo'], $codigo_institucion]);

                // Acumular Costo
                $total_costo_venta += ($costo_historico * $prod['cantidad']);
            }

            // =========================================================
            // C. INTEGRACIÓN CONTABLE Y TESORERÍA (VENTA)
            // =========================================================
            
            // 1. Obtener Cuentas Base
            $id_ingreso_venta = obtenerIdCuentaPorMapeo($pdo, $codigo_institucion, 'INGRESOS_VENTA');
            $id_iva_debito    = obtenerIdCuentaPorMapeo($pdo, $codigo_institucion, 'IVA_DEBITO_FISCAL');
            $id_costo_venta   = obtenerIdCuentaPorMapeo($pdo, $codigo_institucion, 'COSTO_VENTA');
            $id_inventario    = obtenerIdCuentaPorMapeo($pdo, $codigo_institucion, 'INVENTARIO_MERCADERIA');
            $id_clientes_cxBj = obtenerIdCuentaPorMapeo($pdo, $codigo_institucion, 'CLIENTES_CXC'); // Cuentas por Cobrar

            // 2. Determinar Cuenta del DEBE (Donde entra el valor)
            $id_cuenta_debe = null;

            if ($condicion_pago === 'CONTADO') {
                // Ingresa dinero a Caja o Banco
                if (!$id_cuenta_destino) {
                    // Fallback: Caja General
                    $stmtDef = $pdo->prepare("SELECT id FROM fin_cajas WHERE codigo_institucion = ? LIMIT 1");
                    $stmtDef->execute([$codigo_institucion]);
                    $id_cuenta_destino = $stmtDef->fetchColumn();
                    $destino_pago = 'CAJA';
                }
                
                // Ejecutar Ingreso de Dinero
                $id_cuenta_debe = procesar_ingreso_dinero($pdo, $codigo_institucion, $destino_pago, $id_cuenta_destino, $datos_venta['total_pagar']);
                
                if(!$id_cuenta_debe) throw new Exception("Error al identificar cuenta contable de ingreso.");

            } else {
                // Es Crédito: Genera Cuenta por Cobrar
                $id_cuenta_debe = $id_clientes_cxBj;
            }

            // 3. Armar Asiento (Registro Compuesto: Venta + Costo)
            $detalle_asiento = [];

            // --- BLOQUE 1: LA VENTA (Precio de Venta) ---
            // DEBE: Caja/Banco o Clientes
            $detalle_asiento[] = ['cuenta_id' => $id_cuenta_debe, 'debito' => $datos_venta['total_pagar'], 'credito' => 0.00];
            // HABER: Ingresos por Venta (Neto)
            $detalle_asiento[] = ['cuenta_id' => $id_ingreso_venta, 'debito' => 0.00, 'credito' => $datos_venta['total_gravado']];
            // HABER: IVA Débito (Si aplica)
            if ($datos_venta['total_iva'] > 0) {
                $detalle_asiento[] = ['cuenta_id' => $id_iva_debito, 'debito' => 0.00, 'credito' => $datos_venta['total_iva']];
            }

            // --- BLOQUE 2: EL COSTO (Salida de Inventario) ---
            // DEBE: Costo de Venta
            $detalle_asiento[] = ['cuenta_id' => $id_costo_venta, 'debito' => $total_costo_venta, 'credito' => 0.00];
            // HABER: Inventario (Baja por costo promedio)
            $detalle_asiento[] = ['cuenta_id' => $id_inventario, 'debito' => 0.00, 'credito' => $total_costo_venta];

            // 4. Guardar Asiento
            $datos_encabezado = [
                'fechaAsiento'    => $datos_venta['fecha_emision'],
                'tipoAsiento'     => 'Ingreso',
                'concepto'        => "Venta Factura #" . $datos_venta['numero_documento'] . " (" . $condicion_pago . ")",
                'usuarioRegistro' => $usuario_activo
            ];

            $res_contable = registrarAsientoAutomatico($pdo, $codigo_institucion, $datos_encabezado, $detalle_asiento);

            if ($res_contable['respuesta']) {
                $pdo->prepare("UPDATE ventas_cabecera SET asiento_id = ? WHERE id_venta = ?")
                    ->execute([$res_contable['numero_asiento'], $id_venta]);
            }

            $pdo->commit();
            echo json_encode(['respuesta' => true, 'mensaje' => 'Venta procesada exitosamente. Asiento #' . $res_contable['numero_asiento'], 'id_venta' => $id_venta]);
            break;

        default:
            echo json_encode(['respuesta' => false, 'mensaje' => 'Acción no reconocida']);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    echo json_encode(['respuesta' => false, 'mensaje' => $e->getMessage()]);
}
?>