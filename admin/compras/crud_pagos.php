<?php
// admin/compras/crud_pagos.php

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

$pdo = $dblink;
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
$codigo_institucion_sesion = $_SESSION['codigo_institucion'] ?? '';
$usuario_activo = $_SESSION['userNombre'] ?? 'Sistema';

header('Content-Type: application/json');

// --- FUNCIÓN AUXILIAR PARA RESTAR DINERO (Reutilizada de Compras) ---
function procesar_salida_pago($pdo, $cod_inst, $origen_full, $monto) {
    // $origen_full viene como "BANCO_5" o "CAJA_1"
    
    $parts = explode('_', $origen_full);
    $tipo = $parts[0]; // BANCO o CAJA
    $id = $parts[1];   // ID numérico
    $id_cuenta_contable = null;

    if ($tipo === 'BANCO') {
        $sql = "SELECT id_cuenta_contable, saldo_actual FROM fin_bancos_cuentas WHERE id = ? AND codigo_institucion = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id, $cod_inst]);
        $row = $stmt->fetch();
        
        if(!$row) throw new Exception("Cuenta bancaria no encontrada.");
        // if($row['saldo_actual'] < $monto) throw new Exception("Saldo insuficiente en Banco.");

        $upd = "UPDATE fin_bancos_cuentas SET saldo_actual = saldo_actual - ? WHERE id = ?";
        $pdo->prepare($upd)->execute([$monto, $id]);
        $id_cuenta_contable = $row['id_cuenta_contable'];

    } elseif ($tipo === 'CAJA') {
        $sql = "SELECT id_cuenta_contable, saldo_actual FROM fin_cajas WHERE id = ? AND codigo_institucion = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id, $cod_inst]);
        $row = $stmt->fetch();

        if(!$row) throw new Exception("Caja no encontrada.");
        
        $upd = "UPDATE fin_cajas SET saldo_actual = saldo_actual - ? WHERE id = ?";
        $pdo->prepare($upd)->execute([$monto, $id]);
        $id_cuenta_contable = $row['id_cuenta_contable'];
    }

    return $id_cuenta_contable;
}

switch ($accion) {
    
    case 'listarCuentasPorPagar':
        try {
            $sql = "SELECT 
                        c.id_compra,
                        c.fecha_emision,
                        c.numero_documento,
                        p.nombre_empresa as nombre_proveedor, 
                        c.total_compra,
                        c.fecha_vencimiento,
                        COALESCE((SELECT SUM(monto_abonado) FROM compras_pagos WHERE id_compra = c.id_compra), 0) as total_abonado
                    FROM compras_cabecera c
                    JOIN proveedores p ON c.id_proveedores = p.id_proveedores
                    WHERE c.codigo_institucion = ? 
                    AND c.condicion_pago = '2' 
                    ORDER BY c.fecha_emision ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$codigo_institucion_sesion]);
            $todas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $pendientes = [];
            foreach ($todas as $fila) {
                $saldo = floatval($fila['total_compra']) - floatval($fila['total_abonado']);
                if ($saldo > 0.001) {
                    $fila['saldo_pendiente'] = number_format($saldo, 2, '.', '');
                    $pendientes[] = $fila;
                }
            }
            
            echo json_encode(['data' => $pendientes]);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    // --- NUEVO: OBTENER BANCOS Y CAJAS REALES (Igual que en Compras) ---
    case 'obtenerCuentasTesoreria':
        try {
            $cuentas = [];
            
            // 1. Cajas
            $sqlC = "SELECT id, nombre_caja as nombre, saldo_actual, 'CAJA' as tipo FROM fin_cajas WHERE codigo_institucion = ? AND estado = 'A'";
            $stmtC = $pdo->prepare($sqlC);
            $stmtC->execute([$codigo_institucion_sesion]);
            while($row = $stmtC->fetch(PDO::FETCH_ASSOC)){
                // Generamos ID compuesto para el select (ej: CAJA_1)
                $cuentas[] = [
                    'id_compuesto' => 'CAJA_' . $row['id'],
                    'texto' => 'Caja: ' . $row['nombre'] . ' ($' . $row['saldo_actual'] . ')'
                ];
            }

            // 2. Bancos
            $sqlB = "SELECT id, nombre_banco, numero_cuenta, saldo_actual, 'BANCO' as tipo FROM fin_bancos_cuentas WHERE codigo_institucion = ? AND estado = 'A'";
            $stmtB = $pdo->prepare($sqlB);
            $stmtB->execute([$codigo_institucion_sesion]);
            while($row = $stmtB->fetch(PDO::FETCH_ASSOC)){
                $cuentas[] = [
                    'id_compuesto' => 'BANCO_' . $row['id'],
                    'texto' => 'Banco: ' . $row['nombre_banco'] . ' - ' . $row['numero_cuenta'] . ' ($' . $row['saldo_actual'] . ')'
                ];
            }
            
            echo json_encode($cuentas);
            
        } catch (Exception $e) {
            echo json_encode([]); 
        }
        break;

    case 'guardarAbono':
        try {
            if (empty($_POST['id_compra']) || empty($_POST['monto']) || empty($_POST['id_cuenta_tesoreria'])) {
                throw new Exception("Datos incompletos. Seleccione una cuenta de pago.");
            }

            $pdo->beginTransaction();

            $id_compra = $_POST['id_compra'];
            $monto     = floatval($_POST['monto']);
            $id_cuenta_origen = $_POST['id_cuenta_tesoreria']; // Viene como "BANCO_1" o "CAJA_1"
            $ref       = $_POST['referencia'] ?? '';
            $fecha     = $_POST['fecha_pago'];

            // 1. DESCONTAR DINERO (TESORERÍA)
            // Esta función devuelve el ID Contable de la Caja/Banco afectado
            $id_cuenta_haber_contable = procesar_salida_pago($pdo, $codigo_institucion_sesion, $id_cuenta_origen, $monto);

            if (!$id_cuenta_haber_contable) {
                throw new Exception("La cuenta seleccionada no tiene configuración contable.");
            }

            // 2. INSERTAR HISTORIAL DE PAGO
            // Nota: Guardamos el ID compuesto en un campo de texto o adaptamos la tabla. 
            // Para simplificar, guardamos 0 en 'id_cuenta_tesoreria' antigua y usamos la referencia para saber cuál fue.
            $sql_hist = "INSERT INTO compras_pagos (id_compra, fecha_pago, monto_abonado, referencia_pago, usuario_registro)
                         VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql_hist);
            $desc_ref = $ref . " (Pago desde " . $id_cuenta_origen . ")";
            $stmt->execute([$id_compra, $fecha, $monto, $desc_ref, $usuario_activo]);
            $id_pago = $pdo->lastInsertId(); // Si necesitas el ID

            // 3. GENERAR PARTIDA CONTABLE (PROVEEDORES vs BANCO)
            
            // A. Obtener cuenta de Pasivo (Proveedores)
            $id_proveedores_cp = obtenerIdCuentaPorMapeo($pdo, $codigo_institucion_sesion, 'PROVEEDORES_CP');

            // B. Buscar info de la compra para el concepto
            $stmtInfo = $pdo->prepare("SELECT numero_documento, p.nombre_empresa FROM compras_cabecera c JOIN proveedores p ON c.id_proveedores = p.id_proveedores WHERE id_compra = ?");
            $stmtInfo->execute([$id_compra]);
            $infoCompra = $stmtInfo->fetch();

            $datos_encabezado = [
                'fechaAsiento'    => $fecha,
                'tipoAsiento'     => 'Egreso',
                'concepto'        => "Abono a Proveedor " . $infoCompra['nombre_empresa'] . " Doc: " . $infoCompra['numero_documento'],
                'usuarioRegistro' => $usuario_activo
            ];

            $datos_detalle = [
                // CARGO: Proveedores (Disminuye Pasivo)
                ['cuenta_id' => $id_proveedores_cp, 'debito' => $monto, 'credito' => 0.00],
                
                // ABONO: Banco/Caja (Disminuye Activo)
                ['cuenta_id' => $id_cuenta_haber_contable, 'debito' => 0.00, 'credito' => $monto]
            ];

            $res_contable = registrarAsientoAutomatico($pdo, $codigo_institucion_sesion, $datos_encabezado, $datos_detalle);

            if (!$res_contable['respuesta']) {
                throw new Exception("Error Contable: " . $res_contable['mensaje']);
            }

            // Opcional: Actualizar el pago con el ID del asiento
            // $pdo->prepare("UPDATE compras_pagos SET id_asiento = ? WHERE id = ?")->execute([$res_contable['numero_asiento'], $id_pago]);

            $pdo->commit();
            echo json_encode(['respuesta' => true, 'mensaje' => 'Pago registrado, saldo descontado y partida generada.']);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['respuesta' => false, 'mensaje' => $e->getMessage()]);
        }
        break;

    case 'listarHistorialPagos':
        try {
            $sql = "SELECT 
                        p.fecha_pago,
                        pr.nombre_empresa, 
                        c.numero_documento,
                        p.monto_abonado,
                        p.referencia_pago
                    FROM compras_pagos p
                    INNER JOIN compras_cabecera c ON p.id_compra = c.id_compra
                    INNER JOIN proveedores pr ON c.id_proveedores = pr.id_proveedores
                    WHERE c.codigo_institucion = ?
                    ORDER BY p.id DESC"; // Asumiendo que 'id' es la PK de compras_pagos
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$codigo_institucion_sesion]);
            $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['data' => $historial]);

        } catch (Exception $e) {
            echo json_encode(['data' => [], 'error' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['respuesta' => false, 'mensaje' => 'Acción no válida']);
        break;
}
?>