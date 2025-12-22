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
// Asegúrate de que estas rutas sean correctas en tu servidor
include($path_root."/FactuFacil/includes/mainFunctions_.php");
include($path_root."/FactuFacil/admin/contabilidad/funciones/contabilidad_api.php");

/** @var PDO $dblink */
$pdo = $dblink;
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

$codigo_perfil_sesion = $_SESSION['codigo_perfil'] ?? '';
$codigo_institucion_sesion = $_SESSION['codigo_institucion'] ?? '';
$usuario_activo = $_SESSION['userNombre'] ?? 'Sistema';

header('Content-Type: application/json');

switch ($accion) {
    
    case 'listarCuentasPorPagar':
        try {
           // CORRECCIÓN: Usamos p.nombre_empresa como nombre_proveedor
            // Si quieres ser más robusto y mostrar nombre+apellido si la empresa está vacía,
            // puedes usar: COALESCE(NULLIF(p.nombre_empresa, ''), CONCAT(p.nombres, ' ', p.apellidos)) as nombre_proveedor
            
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
                
                // Solo enviamos las que tienen deuda viva (mayor a $0.00)
                // Usamos un pequeño margen (0.001) para evitar problemas de decimales flotantes
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

    case 'obtenerCuentasTesoreria':
        // Llenar el select del modal con Bancos y Cajas
        try {
            $sql = "SELECT id, nombre_cuenta, tipo_cuenta, saldo_actual 
                    FROM tesoreria_cuentas 
                    WHERE codigo_institucion = ? AND estado = 'ACTIVO'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$codigo_institucion_sesion]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            echo json_encode([]);
        }
        break;

    case 'guardarAbono':
        try {
            $pdo->beginTransaction();

            $id_compra = $_POST['id_compra'];
            $monto     = floatval($_POST['monto']);
            $id_cuenta = $_POST['id_cuenta_tesoreria']; // ID de tesoreria_cuentas
            $ref       = $_POST['referencia'];
            $fecha     = $_POST['fecha_pago'];

            // 1. Validaciones
            if ($monto <= 0) throw new Exception("El monto debe ser mayor a 0");

            // 2. Obtener datos de la cuenta de tesorería (para saber su cuenta contable y validar saldo)
            $stmt = $pdo->prepare("SELECT id_cuenta_contable, nombre_cuenta, saldo_actual FROM tesoreria_cuentas WHERE id = ?");
            $stmt->execute([$id_cuenta]);
            $cuenta_tesoreria = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$cuenta_tesoreria) throw new Exception("Cuenta de tesorería no válida");

            // Opcional: Validar si hay saldo suficiente en Banco/Caja
            /*
            if (floatval($cuenta_tesoreria['saldo_actual']) < $monto) {
                throw new Exception("Saldo insuficiente en " . $cuenta_tesoreria['nombre_cuenta']);
            }
            */

            // 3. Obtener datos de la compra (para saber el proveedor y documento)
            $stmt = $pdo->prepare("SELECT numero_documento, id_proveedores FROM compras_cabecera WHERE id_compra = ?");
            $stmt->execute([$id_compra]);
            $datos_compra = $stmt->fetch(PDO::FETCH_ASSOC);

            // 4. GENERAR ASIENTO CONTABLE
            // CARGO (Debe): Proveedores (Disminuye deuda 2101)
            // ABONO (Haber): Banco/Caja (Sale dinero 1101/1102)
            
            // Buscar ID cuenta proveedor (Usando función de contabilidad_api.php si existe, o query directa)
            // Asumimos cuenta proveedores estándar. Si tienes mapeo específico por proveedor, ajústalo aquí.
            $stmt = $pdo->prepare("SELECT id FROM cuentas_contables WHERE codigo LIKE '2101%' AND codigo_institucion = ? LIMIT 1"); 
            $stmt->execute([$codigo_institucion_sesion]);
            $id_cuenta_proveedor = $stmt->fetchColumn();
            
            if (!$id_cuenta_proveedor) throw new Exception("No se encontró la cuenta contable de Proveedores (2101...)");

            // a) Insertar Cabecera Asiento
            $sql_asiento = "INSERT INTO asientos_contables (codigo_institucion, numero_asiento, fecha_asiento, concepto, tipo_asiento, estado, usuario_registro) 
                            VALUES (?, (SELECT COALESCE(MAX(numero_asiento),0)+1 FROM asientos_contables WHERE codigo_institucion = ?), ?, ?, 'Egreso', 'APROBADO', ?) RETURNING id";
            $stmt_as = $pdo->prepare($sql_asiento);
            $concepto = "Pago a Fac. " . $datos_compra['numero_documento'] . " Ref: " . $ref;
            $stmt_as->execute([$codigo_institucion_sesion, $codigo_institucion_sesion, $fecha, $concepto, $usuario_activo]);
            $id_asiento = $stmt_as->fetchColumn();

            // b) Insertar Detalles Asiento
            // A. Cargo a Proveedor (Debe)
            $pdo->prepare("INSERT INTO detalle_asientos (asiento_id, cuenta_id, debito, credito) VALUES (?, ?, ?, 0)")
                ->execute([$id_asiento, $id_cuenta_proveedor, $monto]);
            
            // B. Abono a Banco/Caja (Haber)
            $pdo->prepare("INSERT INTO detalle_asientos (asiento_id, cuenta_id, debito, credito) VALUES (?, ?, 0, ?)")
                ->execute([$id_asiento, $cuenta_tesoreria['id_cuenta_contable'], $monto]);

            // 5. REGISTRAR EN HISTORIAL DE PAGOS
            $sql_hist = "INSERT INTO compras_pagos (id_compra, fecha_pago, monto_abonado, referencia_pago, id_cuenta_tesoreria, id_asiento, usuario_registro)
                         VALUES (?, ?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql_hist)->execute([$id_compra, $fecha, $monto, $ref, $id_cuenta, $id_asiento, $usuario_activo]);

            // 6. ACTUALIZAR SALDO EN TABLA TESORERÍA
            $pdo->prepare("UPDATE tesoreria_cuentas SET saldo_actual = saldo_actual - ? WHERE id = ?")
                ->execute([$monto, $id_cuenta]);

            $pdo->commit();
            echo json_encode(['respuesta' => true, 'mensaje' => 'Pago registrado y contabilizado correctamente.']);

        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error: ' . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['respuesta' => false, 'mensaje' => 'Acción no válida']);
        break;
}
?>