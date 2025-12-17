<?php
// funciones/contabilidad_api.php

//session_name('FactuFacil');
//session_start();

// 1. VALIDACIÓN Y CONFIGURACIÓN INICIAL
if (empty($_SESSION['userNombre']) || empty($_SESSION['codigo_institucion'])) {
    header('Content-Type: application/json');
    echo json_encode(['respuesta' => false, 'mensaje' => 'Sesión o institución no válidas.']);
    exit();
}

$path_root = trim($_SERVER['DOCUMENT_ROOT']);
// Asegúrate de que esta ruta sea correcta para tu proyecto
include($path_root."/FactuFacil/includes/mainFunctions_.php"); 

// Usamos dblink, que es tu variable de conexión PDO
$db = $dblink; 
$codigo_institucion_activa = $_SESSION['codigo_institucion']; 
/**
 * Registra un asiento contable completo (Encabezado + Detalle) en una transacción.
 * * @param PDO $pdo Objeto de conexión a la base de datos.
 * @param string $codigo_institucion Código de la empresa activa.
 * @param array $datos_encabezado [fechaAsiento, tipoAsiento, concepto, usuarioRegistro]
 * @param array $datos_detalle Array de líneas de detalle [cuenta_id, debito, credito]
 * @return array Respuesta [respuesta => true/false, mensaje => '...', numero_asiento => X]
 */
function registrarAsientoAutomatico($pdo, $codigo_institucion, $datos_encabezado, $datos_detalle) {
    
    // Validaciones rápidas
    if (empty($datos_detalle) || empty($datos_encabezado['fechaAsiento'])) {
        return ['respuesta' => false, 'mensaje' => 'Datos insuficientes para el asiento.'];
    }

    // Validación de Balance (Partida Doble)
    $totalDebito = array_sum(array_column($datos_detalle, 'debito'));
    $totalCredito = array_sum(array_column($datos_detalle, 'credito'));
    if (abs($totalDebito - $totalCredito) > 0.01) {
        return ['respuesta' => false, 'mensaje' => "Error: El asiento no balancea. Débito: $totalDebito, Crédito: $totalCredito"];
    }

    $fechaAsiento = $datos_encabezado['fechaAsiento'];
    $concepto = $datos_encabezado['concepto'];
    $tipoAsiento = $datos_encabezado['tipoAsiento'] ?? 'Diario';
    $usuarioRegistro = $datos_encabezado['usuarioRegistro'] ?? 'Sistema';

    try {
      // Verificamos si ya venimos dentro de una transacción (como en Compras)
            $transaccion_propia = false;
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $transaccion_propia = true; // Marcamos que esta función es la dueña
            }

        // 1. CALCULAR EL PRÓXIMO NÚMERO DE ASIENTO (Usando la lógica que ya implementaste)
        $sql_next_num = "SELECT COALESCE(MAX(numero_asiento), 0) + 1 as next_number
                                FROM asientos_contables 
                                WHERE codigo_institucion = :codigo_institucion 
                                AND EXTRACT(YEAR FROM fecha_asiento) = EXTRACT(YEAR FROM CAST(:fecha_asiento AS DATE))";
        $stmt_next_num = $pdo->prepare($sql_next_num);
        $stmt_next_num->bindParam(':codigo_institucion', $codigo_institucion);
        $stmt_next_num->bindParam(':fecha_asiento', $fechaAsiento);
        $stmt_next_num->execute();
        $next_numero_asiento = $stmt_next_num->fetch(PDO::FETCH_ASSOC)['next_number'];

        // 2. INSERCIÓN DEL ENCABEZADO (ASIENTOS_CONTABLES)
        $sql_encabezado = "INSERT INTO asientos_contables (codigo_institucion, numero_asiento, fecha_asiento, concepto, tipo_asiento, estado, usuario_registro) VALUES (:cod_inst, :num, :fecha, :conc, :tipo, 'APROBADO', :user) RETURNING id";
        $stmt_encabezado = $pdo->prepare($sql_encabezado);
        $stmt_encabezado->execute([
            ':cod_inst' => $codigo_institucion,
            ':num' => $next_numero_asiento,
            ':fecha' => $fechaAsiento,
            ':conc' => $concepto,
            ':tipo' => $tipoAsiento,
            ':user' => $usuarioRegistro
        ]);
        $asiento_id = $stmt_encabezado->fetch(PDO::FETCH_ASSOC)['id'];

        // 3. INSERCIÓN DEL DETALLE (DETALLE_ASIENTOS)
        $sql_detalle = "INSERT INTO detalle_asientos (asiento_id, cuenta_id, debito, credito) VALUES (:asiento_id, :cuenta_id, :debito, :credito)";
        $stmt_detalle = $pdo->prepare($sql_detalle);

        foreach ($datos_detalle as $linea) {
            $stmt_detalle->execute([
                ':asiento_id' => $asiento_id,
                ':cuenta_id' => $linea['cuenta_id'],
                ':debito' => $linea['debito'],
                ':credito' => $linea['credito']
            ]);
        }

        // Solo hacemos commit si nosotros abrimos la transacción
            if ($transaccion_propia) {
                $pdo->commit();
            }
        return ['respuesta' => true, 'mensaje' => 'Asiento registrado automáticamente (No. ' . $next_numero_asiento . ')', 'numero_asiento' => $next_numero_asiento];

    } catch (PDOException $e) {
        // Solo hacemos rollback si nosotros abrimos la transacción
        if ($transaccion_propia) {
            $pdo->rollBack();
        }
        return ['respuesta' => false, 'mensaje' => 'Error de BD al registrar asiento: ' . $e->getMessage()];
    }
}

/**
 * Obtiene el ID de una cuenta contable a partir de su clave de mapeo.
 */
function obtenerIdCuentaPorMapeo($pdo, $codigo_institucion, $clave_mapeo) {
    $sql = "SELECT cuenta_id 
            FROM configuracion_contable 
            WHERE codigo_institucion = :codigo AND clave_mapeo = :clave";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':codigo' => $codigo_institucion, ':clave' => $clave_mapeo]);
    
    $resultado = $stmt->fetchColumn();
    
    if (!$resultado) {
        // Lanza un error para detener la transacción si falta configuración
        throw new Exception("ERROR CRÍTICO: Falta asignar la cuenta para la clave de mapeo: " . $clave_mapeo);
    }
    
    return (int)$resultado;
}
/**
 * Marca un asiento existente como ANULADO y genera un asiento de reversión si es necesario.
 * @param PDO $pdo Objeto de conexión.
 * @param int $asiento_id ID del asiento a anular.
 * @param string $usuario_anula Usuario que realiza la anulación.
 * @return array Respuesta.
 */
function anularAsientoContable($pdo, $asiento_id, $usuario_anula) {
    try {
        $pdo->beginTransaction();

        // 1. Obtener datos del asiento a anular
        $sql_original = "SELECT * FROM asientos_contables WHERE id = :id AND estado = 'APROBADO'";
        $stmt_original = $pdo->prepare($sql_original);
        $stmt_original->execute([':id' => $asiento_id]);
        $asiento_original = $stmt_original->fetch(PDO::FETCH_ASSOC);

        if (!$asiento_original) {
            $pdo->rollBack();
            return ['respuesta' => false, 'mensaje' => 'Asiento no encontrado o ya anulado/borrador.'];
        }

        // 2. Obtener detalle original
        $sql_detalle = "SELECT cuenta_id, debito, credito FROM detalle_asientos WHERE asiento_id = :id";
        $stmt_detalle = $pdo->prepare($sql_detalle);
        $stmt_detalle->execute([':id' => $asiento_id]);
        $detalle_original = $stmt_detalle->fetchAll(PDO::FETCH_ASSOC);

        // 3. Marcar el asiento original como ANULADO
        $sql_update_estado = "UPDATE asientos_contables SET estado = 'ANULADO', usuario_registro = :user WHERE id = :id";
        $pdo->prepare($sql_update_estado)->execute([':user' => $usuario_anula, ':id' => $asiento_id]);

        // 4. GENERAR EL ASIENTO DE REVERSIÓN
        // Invertimos el débito y crédito del detalle original
        $detalle_reversion = [];
        foreach ($detalle_original as $linea) {
            $detalle_reversion[] = [
                'cuenta_id' => $linea['cuenta_id'],
                'debito' => $linea['credito'], // El débito se convierte en crédito
                'credito' => $linea['debito']   // El crédito se convierte en débito
            ];
        }

        $datos_encabezado_rev = [
            'fechaAsiento' => date('Y-m-d'), 
            'tipoAsiento' => 'Ajuste', // Los reversos son típicamente ajustes
            'concepto' => "Reversión del Asiento No. {$asiento_original['numero_asiento']} por actualización de compra.",
            'usuarioRegistro' => $usuario_anula
        ];

        // Se usa la función de registro para crear el nuevo asiento de reversión
        $resultado_reversion = registrarAsientoAutomatico(
            $pdo, 
            $asiento_original['codigo_institucion'], 
            $datos_encabezado_rev, 
            $detalle_reversion
        );

        if (!$resultado_reversion['respuesta']) {
            throw new Exception("Fallo la creación del asiento de reversión: " . $resultado_reversion['mensaje']);
        }
        
        $pdo->commit();
        return ['respuesta' => true, 'mensaje' => 'Asiento original anulado y reversión registrada.'];

    } catch (Exception $e) {
        $pdo->rollBack();
        return ['respuesta' => false, 'mensaje' => 'Error al anular el asiento: ' . $e->getMessage()];
    }
}
// Mueve todo el código del CRUD a esta función (y elimina el código original del crud_asientos.php)
?>