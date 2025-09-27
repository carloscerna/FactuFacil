<?php
// contabilidad/cuentas/crud_cuentas.php

session_name('FactuFacil');
session_start();

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

// 2. Definición de la Acción
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

if ($accion === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Extracción y sanitización de datos del encabezado (asientos_contables)
    $fechaAsiento = filter_input(INPUT_POST, 'fechaAsiento', FILTER_SANITIZE_SPECIAL_CHARS);
    $tipoAsiento  = filter_input(INPUT_POST, 'tipoAsiento', FILTER_SANITIZE_SPECIAL_CHARS);
    $concepto     = filter_input(INPUT_POST, 'concepto', FILTER_SANITIZE_SPECIAL_CHARS);
    
    // Extracción de datos del detalle (detalle_asientos)
    // El frontend envía un array llamado 'detalle'
    $detalle = $_POST['detalle'] ?? [];

    if (empty($fechaAsiento) || empty($concepto) || empty($detalle)) {
        echo json_encode(['respuesta' => false, 'mensaje' => 'Faltan datos requeridos en el encabezado o el detalle.']);
        exit;
    }

    // El estado inicial es siempre BORRADOR
    $estado = 'BORRADOR';
    $usuario_registro = $_SESSION['usuario_login'] ?? 'Sistema'; // Usa el usuario logueado

    try {
        // INICIO DE LA TRANSACCIÓN CRÍTICA
        $db->beginTransaction();

        // 3. CALCULAR EL PRÓXIMO NÚMERO DE ASIENTO SECUENCIAL
        $sql_next_num = "
            SELECT COALESCE(MAX(numero_asiento), 0) + 1 AS next_number
            FROM asientos_contables
            WHERE codigo_institucion = :codigo_institucion
              AND EXTRACT(YEAR FROM fecha_asiento) = EXTRACT(YEAR FROM :fecha_asiento)
        ";
        $stmt_next_num = $db->prepare($sql_next_num);
        $stmt_next_num->bindParam(':codigo_institucion', $codigo_institucion);
        $stmt_next_num->bindParam(':fecha_asiento', $fechaAsiento);
        $stmt_next_num->execute();
        $next_numero_asiento = $stmt_next_num->fetch(PDO::FETCH_ASSOC)['next_number'];

        // 4. INSERCIÓN DEL ENCABEZADO (ASIENTOS_CONTABLES)
        $sql_encabezado = "
            INSERT INTO asientos_contables (
                codigo_institucion, numero_asiento, fecha_asiento, concepto, tipo_asiento, estado, usuario_registro
            ) VALUES (
                :codigo_institucion, :numero_asiento, :fecha_asiento, :concepto, :tipo_asiento, :estado, :usuario_registro
            ) RETURNING id
        ";
        $stmt_encabezado = $db->prepare($sql_encabezado);
        
        $stmt_encabezado->bindParam(':codigo_institucion', $codigo_institucion);
        $stmt_encabezado->bindParam(':numero_asiento', $next_numero_asiento);
        $stmt_encabezado->bindParam(':fecha_asiento', $fechaAsiento);
        $stmt_encabezado->bindParam(':concepto', $concepto);
        $stmt_encabezado->bindParam(':tipo_asiento', $tipoAsiento);
        $stmt_encabezado->bindParam(':estado', $estado);
        $stmt_encabezado->bindParam(':usuario_registro', $usuario_registro);

        $stmt_encabezado->execute();
        
        // Obtenemos el ID del asiento recién insertado (RETURNING id)
        $asiento_id = $stmt_encabezado->fetch(PDO::FETCH_ASSOC)['id'];

        // 5. INSERCIÓN DEL DETALLE (DETALLE_ASIENTOS)
        $sql_detalle = "
            INSERT INTO detalle_asientos (
                asiento_id, cuenta_id, debito, credito, descripcion_linea, centro_costo_id
            ) VALUES (
                :asiento_id, :cuenta_id, :debito, :credito, :descripcion_linea, :centro_costo_id
            )
        ";
        $stmt_detalle = $db->prepare($sql_detalle);

        // Validar y registrar cada línea del detalle
        foreach ($detalle as $linea) {
            $cuenta_id = $linea['cuenta_id'] ?? null;
            $debito    = floatval($linea['debito'] ?? 0.00);
            $credito   = floatval($linea['credito'] ?? 0.00);
            $descripcion_linea = $concepto; // Usamos el concepto general como descripción por defecto
            $centro_costo_id = null; // De momento, es nulo, se puede expandir después

            // Validación mínima de la línea
            if (!$cuenta_id || ($debito <= 0 && $credito <= 0)) {
                // Si la línea está incompleta o sin valor, la saltamos.
                continue; 
            }

            $stmt_detalle->bindParam(':asiento_id', $asiento_id);
            $stmt_detalle->bindParam(':cuenta_id', $cuenta_id);
            $stmt_detalle->bindParam(':debito', $debito);
            $stmt_detalle->bindParam(':credito', $credito);
            $stmt_detalle->bindParam(':descripcion_linea', $descripcion_linea);
            $stmt_detalle->bindParam(':centro_costo_id', $centro_costo_id);
            
            $stmt_detalle->execute();
        }

        // 6. CIERRE DE LA TRANSACCIÓN: ÉXITO
        $db->commit();
        echo json_encode([
            'respuesta' => true, 
            'mensaje' => 'Asiento contable registrado con éxito.',
            'numero_asiento' => $next_numero_asiento
        ]);

    } catch (PDOException $e) {
        // CIERRE DE LA TRANSACCIÓN: ERROR
        $db->rollBack();
        error_log("Error al registrar asiento: " . $e->getMessage());
        echo json_encode([
            'respuesta' => false, 
            'mensaje' => 'Error de base de datos al registrar el asiento. Detalles: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['respuesta' => false, 'mensaje' => 'Acción no válida o método incorrecto.']);
}
?>