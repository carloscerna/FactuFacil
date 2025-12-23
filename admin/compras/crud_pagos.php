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
        try {
            // Consulta para traer bancos y cajas activos
            $sql = "SELECT id, nombre_cuenta, tipo_cuenta, saldo_actual 
                    FROM tesoreria_cuentas 
                    WHERE codigo_institucion = ? AND estado = 'ACTIVO'";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$codigo_institucion_sesion]);
            $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // IMPORTANTE: Devolver JSON puro
            echo json_encode($resultado);
            
        } catch (Exception $e) {
            // Si falla, devolvemos array vacío
            echo json_encode([]); 
        }
        break;

  case 'guardarAbono':
        try {
            // Validar datos mínimos
            if (empty($_POST['id_compra']) || empty($_POST['monto'])) {
                throw new Exception("Datos incompletos.");
            }

            $pdo->beginTransaction();

            $id_compra = $_POST['id_compra'];
            $monto     = floatval($_POST['monto']);
            $id_cuenta = $_POST['id_cuenta_tesoreria']; 
            $ref       = $_POST['referencia'] ?? '';
            $fecha     = $_POST['fecha_pago'];

            // 1. Insertar Historial de Pago
            $sql_hist = "INSERT INTO compras_pagos (id_compra, fecha_pago, monto_abonado, referencia_pago, id_cuenta_tesoreria, usuario_registro)
                         VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql_hist);
            $stmt->execute([$id_compra, $fecha, $monto, $ref, $id_cuenta, $usuario_activo]);

            // 2. Actualizar Saldo en Tesorería (Resta el dinero)
            $sql_upd = "UPDATE tesoreria_cuentas SET saldo_actual = saldo_actual - ? WHERE id = ?";
            $pdo->prepare($sql_upd)->execute([$monto, $id_cuenta]);

            $pdo->commit();
            
            // IMPORTANTE: Esto es lo que recibe el JS
            echo json_encode(['respuesta' => true, 'mensaje' => 'Pago registrado correctamente.']);

        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['respuesta' => false, 'mensaje' => $e->getMessage()]);
        }
        break;
        case 'listarHistorialPagos':
                try {
                    // VERIFICACIÓN DE COLUMNAS:
                    // Asegúrate que en tu tabla 'compras_pagos' la llave primaria se llame 'id' o 'id_pago'.
                    // Aquí asumo que se llama 'id' basado en tu mensaje anterior.
                    
                    $sql = "SELECT 
                                p.fecha_pago,
                                pr.nombre_empresa, 
                                c.numero_documento,
                                p.monto_abonado,
                                p.referencia_pago,
                                COALESCE(t.nombre_cuenta, 'Sin cuenta') as banco
                            FROM compras_pagos p
                            INNER JOIN compras_cabecera c ON p.id_compra = c.id_compra
                            INNER JOIN proveedores pr ON c.id_proveedores = pr.id_proveedores
                            LEFT JOIN tesoreria_cuentas t ON p.id_cuenta_tesoreria = t.id
                            WHERE c.codigo_institucion = ?
                            ORDER BY p.id DESC"; 
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$codigo_institucion_sesion]);
                    $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode(['data' => $historial]);

                } catch (Exception $e) {
                    // AHORA SÍ VEREMOS EL ERROR
                    echo json_encode(['data' => [], 'error' => $e->getMessage()]);
                }
        break;
    default:
        echo json_encode(['respuesta' => false, 'mensaje' => 'Acción no válida']);
        break;
}
?>