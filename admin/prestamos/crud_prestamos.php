<?php
// admin/prestamos/crud_prestamos.php

session_name('FactuFacil');
session_start();

// Validación de sesión básica
if (empty($_SESSION['userNombre']) || empty($_SESSION['codigo_institucion'])) {
    header('Content-Type: application/json');
    echo json_encode(['respuesta' => false, 'mensaje' => 'Sesión no válida o institución no definida.']);
    exit();
}

global $pdo;
$path_root = trim($_SERVER['DOCUMENT_ROOT']);
include($path_root."/FactuFacil/includes/mainFunctions_.php");
include($path_root."/FactuFacil/admin/contabilidad/funciones/contabilidad_api.php");

/** @var PDO $dblink */
$pdo = $dblink;
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// Variables de Sesión
$usuario_activo = $_SESSION['userNombre'];
$cod_institucion = $_SESSION['codigo_institucion']; // <--- LA CLAVE

header('Content-Type: application/json');

switch ($accion) {
    
    case 'listar_prestamistas':
        try {
            // Filtramos por codigo_institucion
            $sql = "SELECT id, nombre_prestamista 
                    FROM fin_prestamistas 
                    WHERE codigo_institucion = :inst AND estado = 'A' 
                    ORDER BY nombre_prestamista ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':inst' => $cod_institucion]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['respuesta' => true, 'data' => $data]);
        } catch (Exception $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => $e->getMessage()]);
        }
        break;

  // --- SECCIÓN 2: REGISTRO DE PRÉSTAMO (CON CUOTAS Y CONTABILIDAD) ---
  case 'guardar_prestamo':
    try {
        $pdo->beginTransaction();

        $id_prestamista = $_POST['id_prestamista'];
        $monto = (float)$_POST['monto'];
        $fecha = $_POST['fecha'];
        $destino = $_POST['destino']; 
        $id_cuenta = ($destino === 'BANCO') ? $_POST['id_cuenta_banco'] : null;
        $concepto = $_POST['concepto'];
        
        // NUEVOS CAMPOS PARA CUOTAS
        $num_cuotas = (int)$_POST['num_cuotas']; 
        $fecha_primer_pago = $_POST['fecha_inicio_pago'];

        // 1. Insertar Cabecera del Préstamo
        $sql = "INSERT INTO fin_prestamos_ingreso 
                (codigo_institucion, id_prestamista, fecha_ingreso, monto, destino_fondos, id_cuenta_banco, concepto, usuario_registro)
                VALUES (:inst, :prov, :fecha, :monto, :dest, :cta, :con, :usr) RETURNING id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':inst' => $cod_institucion, ':prov' => $id_prestamista, ':fecha' => $fecha, 
            ':monto' => $monto, ':dest' => $destino, ':cta' => $id_cuenta, 
            ':con' => $concepto, ':usr' => $usuario_activo
        ]);
        $id_prestamo = $stmt->fetchColumn();

        // 2. Generar Tabla de Amortización (Cuotas)
        $monto_cuota = $monto / $num_cuotas;
        // Redondear a 2 decimales, ajustar la última cuota si es necesario (simplificado aquí)
        $monto_cuota = round($monto_cuota, 2);
        
        $fecha_obj = new DateTime($fecha_primer_pago);
        
        $sqlCuota = "INSERT INTO fin_prestamos_cuotas (codigo_institucion, id_prestamo, numero_cuota, fecha_vencimiento, monto_cuota) VALUES (?, ?, ?, ?, ?)";
        $stmtC = $pdo->prepare($sqlCuota);

        for ($i = 1; $i <= $num_cuotas; $i++) {
            $stmtC->execute([$cod_institucion, $id_prestamo, $i, $fecha_obj->format('Y-m-d'), $monto_cuota]);
            $fecha_obj->modify('+1 month'); // Siguiente mes
        }

       // 3. Afectar Saldo (Ingreso de dinero)
       if ($destino === 'BANCO') {
        // CORRECCIÓN: Usar 'fin_bancos_cuentas' en lugar de 'bancos_cuentas'
        $sqlCheck = "SELECT id FROM fin_bancos_cuentas WHERE id = :id AND codigo_institucion = :inst";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute([':id' => $id_cuenta, ':inst' => $cod_institucion]);
        
        if($stmtCheck->rowCount() > 0){
            // CORRECCIÓN: Usar 'fin_bancos_cuentas'
            $sqlBanco = "UPDATE fin_bancos_cuentas SET saldo_actual = saldo_actual + :monto WHERE id = :id";
            $stmtB = $pdo->prepare($sqlBanco);
            $stmtB->execute([':monto' => $monto, ':id' => $id_cuenta]);
        } else {
            throw new Exception("La cuenta bancaria seleccionada no existe o no pertenece a su institución.");
        }
    } else {
        // CASO CAJA: Asegurarnos de actualizar 'fin_cajas'
        // Asumimos que actualizamos la Caja General por defecto de la institución
        $sqlCaja = "UPDATE fin_cajas SET saldo_actual = saldo_actual + :monto WHERE codigo_institucion = :inst AND nombre_caja = 'Caja General Principal'";
        $stmtC = $pdo->prepare($sqlCaja);
        $stmtC->execute([':monto' => $monto, ':inst' => $cod_institucion]);
    }

        // --- INTEGRACIÓN CONTABLE AUTOMÁTICA ---
        $id_cuenta_contable_activo = null;

        if ($destino === 'BANCO') {
            // Si va a banco, buscamos la cuenta contable de ese banco específico
            // (En tu SQL de preparación hicimos que Bancos tuviera id_cuenta_contable = 36)
            $stmtAux = $pdo->prepare("SELECT id_cuenta_contable FROM fin_bancos_cuentas WHERE id = ?");
            $stmtAux->execute([$id_cuenta]);
            $id_cuenta_contable_activo = $stmtAux->fetchColumn();
        } else {
            // Si va a caja, buscamos la cuenta contable de la caja
            // (En tu SQL de preparación hicimos que Cajas tuviera id_cuenta_contable = 35)
            $stmtAux = $pdo->prepare("SELECT id_cuenta_contable FROM fin_cajas WHERE codigo_institucion = ? LIMIT 1");
            $stmtAux->execute([$cod_institucion]);
            $id_cuenta_contable_activo = $stmtAux->fetchColumn();
        }

        // Si encontramos cuenta contable (35 o 36), generamos la partida
        if ($id_cuenta_contable_activo) {
            $datos_contables = [
                'fecha' => $fecha,
                'monto' => $monto,
                'usuario' => $usuario_activo,
                'id_cuenta_activo' => $id_cuenta_contable_activo
            ];
            
            // Llamamos a la función que pusimos al final del archivo
            registrar_partida_prestamo($pdo, $cod_institucion, $id_prestamo, $datos_contables);
        }
        // -

        $pdo->commit();
        echo json_encode(['respuesta' => true, 'mensaje' => 'Préstamo registrado y cuotas generadas correctamente.']);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['respuesta' => false, 'mensaje' => 'Error: ' . $e->getMessage()]);
    }
    break;

// 1. Listar para la tabla (DataTable o Tabla simple)
    case 'tabla_prestamistas':
        try {
            $sql = "SELECT id, nombre_prestamista, telefono, estado 
                    FROM fin_prestamistas 
                    WHERE codigo_institucion = :inst AND estado = 'A' 
                    ORDER BY id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':inst' => $cod_institucion]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['respuesta' => true, 'data' => $result]);
        } catch (Exception $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => $e->getMessage()]);
        }
        break;


        // --- SECCIÓN 3: GESTIÓN DE DEUDAS (NUEVO) ---

        case 'listar_estado_deuda':
            try {
                // Obtener préstamos con saldo pendiente
                // Sumamos las cuotas pendientes para saber el saldo real
                $sql = "SELECT p.id, pr.nombre_prestamista, p.fecha_ingreso, p.monto as monto_original,
                            (SELECT SUM(monto_cuota) FROM fin_prestamos_cuotas c WHERE c.id_prestamo = p.id AND c.estado = 'PENDIENTE') as saldo_pendiente,
                            (SELECT MIN(fecha_vencimiento) FROM fin_prestamos_cuotas c WHERE c.id_prestamo = p.id AND c.estado = 'PENDIENTE') as proximo_vencimiento
                        FROM fin_prestamos_ingreso p
                        JOIN fin_prestamistas pr ON p.id_prestamista = pr.id
                        WHERE p.codigo_institucion = :inst
                        HAVING saldo_pendiente > 0 -- Solo mostrar deudas activas
                        ORDER BY proximo_vencimiento ASC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':inst' => $cod_institucion]);
                echo json_encode(['respuesta' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            } catch (Exception $e) { echo json_encode(['respuesta' => false, 'mensaje' => $e->getMessage()]); }
            break;

        case 'ver_cuotas_prestamo':
            try {
                $id_prestamo = $_POST['id_prestamo'];
                $sql = "SELECT * FROM fin_prestamos_cuotas 
                        WHERE id_prestamo = :id AND codigo_institucion = :inst 
                        ORDER BY numero_cuota ASC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':id' => $id_prestamo, ':inst' => $cod_institucion]);
                echo json_encode(['respuesta' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            } catch (Exception $e) { echo json_encode(['respuesta' => false, 'mensaje' => $e->getMessage()]); }
            break;

        case 'pagar_cuota':
            try {
                $pdo->beginTransaction();
                $id_cuota = $_POST['id_cuota'];
                $origen_pago = $_POST['origen_pago']; // 'CAJA' o 'BANCO'
                $id_cuenta_origen = $_POST['id_cuenta_origen'] ?? null;

                // 1. Obtener datos de la cuota
                $sqlC = "SELECT monto_cuota, id_prestamo FROM fin_prestamos_cuotas WHERE id = :id";
                $stmtC = $pdo->prepare($sqlC);
                $stmtC->execute([':id' => $id_cuota]);
                $cuotaData = $stmtC->fetch(PDO::FETCH_ASSOC);
                $monto = $cuotaData['monto_cuota'];

                // 2. Descontar dinero (Activo Disminuye)
                if ($origen_pago === 'BANCO') {
                    $sqlUpdateBanco = "UPDATE fin_bancos_cuentas SET saldo_actual = saldo_actual - :monto WHERE id = :id";
                    $stmtB = $pdo->prepare($sqlUpdateBanco);
                    $stmtB->execute([':monto' => $monto, ':id' => $id_cuenta_origen]);
                }
                // (Si es Caja, insertar en tabla movimientos_caja como EGRESO)

                // 3. Marcar Cuota como PAGADA
                $sqlUpd = "UPDATE fin_prestamos_cuotas 
                        SET estado = 'PAGADO', fecha_pago_real = NOW() 
                        WHERE id = :id";
                $stmtUpd = $pdo->prepare($sqlUpd);
                $stmtUpd->execute([':id' => $id_cuota]);

                // 4. ASIENTO CONTABLE (Pago)
                // DEBE: Préstamos por Pagar (Pasivo disminuye) | HABER: Banco (Activo disminuye)
                $pdo->commit();
                echo json_encode(['respuesta' => true, 'mensaje' => 'Cuota pagada exitosamente.']);
    
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['respuesta' => false, 'mensaje' => 'Error al pagar: ' . $e->getMessage()]);
            }
            break;

    // 2. Guardar o Editar Prestamista
    case 'guardar_prestamista_catalogo':
        try {
            $id = $_POST['id_prestamista'] ?? ''; // Si viene vacío es NUEVO
            $nombre = $_POST['nombre_prestamista'];
            $telefono = $_POST['telefono'];

            if (empty($id)) {
                // INSERTAR
                $sql = "INSERT INTO fin_prestamistas (codigo_institucion, nombre_prestamista, telefono, estado) 
                        VALUES (:inst, :nom, :tel, 'A')";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':inst' => $cod_institucion,
                    ':nom' => $nombre,
                    ':tel' => $telefono
                ]);
            } else {
                // ACTUALIZAR
                $sql = "UPDATE fin_prestamistas 
                        SET nombre_prestamista = :nom, telefono = :tel 
                        WHERE id = :id AND codigo_institucion = :inst";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':nom' => $nombre,
                    ':tel' => $telefono,
                    ':id' => $id,
                    ':inst' => $cod_institucion
                ]);
            }
            echo json_encode(['respuesta' => true, 'mensaje' => 'Guardado correctamente']);
        } catch (Exception $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => $e->getMessage()]);
        }
        break;

    // 3. Eliminar (Borrado lógico)
    case 'eliminar_prestamista':
        try {
            $id = $_POST['id'];
            $sql = "UPDATE fin_prestamistas SET estado = 'I' WHERE id = :id AND codigo_institucion = :inst";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id, ':inst' => $cod_institucion]);
            echo json_encode(['respuesta' => true, 'mensaje' => 'Eliminado correctamente']);
        } catch (Exception $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => $e->getMessage()]);
        }
        break;

    // 4. Obtener uno solo para editar
    case 'obtener_prestamista':
        try {
            $id = $_POST['id'];
            $sql = "SELECT * FROM fin_prestamistas WHERE id = :id AND codigo_institucion = :inst";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id, ':inst' => $cod_institucion]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['respuesta' => true, 'data' => $data]);
        } catch (Exception $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => $e->getMessage()]);
        }
        break;
        case 'guardar_banco':
            try {
                // Recibimos datos y sanitizamos
                $nombre = $_POST['nombre_banco'];
                $numero = $_POST['numero_cuenta'];
                // CORRECCIÓN DEL ERROR: Si no viene tipo_cuenta, asumimos 'AHORROS'
                $tipo   = $_POST['tipo_cuenta'] ?? 'AHORROS'; 
                $saldo  = !empty($_POST['saldo_inicial']) ? $_POST['saldo_inicial'] : 0;
                
                // Campos nuevos El Salvador
                $cci     = $_POST['cci'] ?? ''; // Código de Cuenta Interbancaria
                $titular = $_POST['titular_cuenta'] ?? ''; // Por si difiere del nombre de la empresa
    
                // Validación básica de CCI (Opcional)
                /* if(!empty($cci) && strlen($cci) !== 20) {
                   throw new Exception("El CCI debe tener 20 dígitos según formato SV.");
                }
                */
    
                $sql = "INSERT INTO fin_bancos_cuentas (codigo_institucion, nombre_banco, numero_cuenta, tipo_cuenta, saldo_actual, cci, titular_cuenta) 
                        VALUES (:inst, :nom, :num, :tipo, :saldo, :cci, :titular)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':inst' => $cod_institucion,
                    ':nom'  => $nombre, 
                    ':num'  => $numero, 
                    ':tipo' => $tipo, 
                    ':saldo'=> $saldo,
                    ':cci'  => $cci,
                    ':titular' => $titular
                ]);
                echo json_encode(['respuesta' => true, 'mensaje' => 'Cuenta bancaria creada correctamente.']);
            } catch (Exception $e) { 
                echo json_encode(['respuesta' => false, 'mensaje' => $e->getMessage()]); 
            }
            break;
            case 'guardar_saldo_caja':
                try {
                    $nuevo_saldo = $_POST['saldo_caja'];
                    
                    // Actualizamos la Caja General (asumiendo que es la única o la principal del usuario)
                    // Si tuvieras múltiples cajas, aquí recibirías el ID de la caja
                    $sql = "UPDATE fin_cajas 
                            SET saldo_actual = :saldo 
                            WHERE codigo_institucion = :inst AND nombre_caja = 'Caja General Principal'";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':saldo' => $nuevo_saldo,
                        ':inst' => $cod_institucion
                    ]);
                    
                    // Opcional: Si no existía (0 rows affected), la creamos
                    if ($stmt->rowCount() == 0) {
                        $sqlInsert = "INSERT INTO fin_cajas (codigo_institucion, nombre_caja, saldo_actual) VALUES (:inst, 'Caja General Principal', :saldo)";
                        $stmtInsert = $pdo->prepare($sqlInsert);
                        $stmtInsert->execute([':inst' => $cod_institucion, ':saldo' => $nuevo_saldo]);
                    }
        
                    echo json_encode(['respuesta' => true, 'mensaje' => 'Saldo de Caja actualizado correctamente.']);
        
                } catch (Exception $e) {
                    echo json_encode(['respuesta' => false, 'mensaje' => $e->getMessage()]);
                }
                break;
    case 'listar_bancos_combo':
        try {
            $sql = "SELECT id, CONCAT(nombre_banco, ' - ', numero_cuenta, ' ($', saldo_actual, ')') as texto 
                    FROM fin_bancos_cuentas WHERE codigo_institucion = :inst AND estado = 'A'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':inst' => $cod_institucion]);
            echo json_encode(['respuesta' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (Exception $e) { echo json_encode(['respuesta' => false, 'mensaje' => $e->getMessage()]); }
        break;

    // --- REPORTE COMPLETO PARA DATATABLE ---
    case 'listar_prestamos_completo':
        try {
            $sql = "SELECT 
                        pi.id, 
                        pr.nombre_prestamista,
                        pi.fecha_ingreso,
                        pi.monto as monto_original,
                        pi.destino_fondos,
                        -- Calculamos saldo sumando cuotas pendientes
                        (SELECT COALESCE(SUM(monto_cuota),0) FROM fin_prestamos_cuotas c WHERE c.id_prestamo = pi.id AND c.estado = 'PENDIENTE') as saldo_pendiente,
                        -- Próximo vencimiento
                        (SELECT MIN(fecha_vencimiento) FROM fin_prestamos_cuotas c WHERE c.id_prestamo = pi.id AND c.estado = 'PENDIENTE') as proximo_vencimiento,
                        pi.concepto
                    FROM fin_prestamos_ingreso pi
                    JOIN fin_prestamistas pr ON pi.id_prestamista = pr.id
                    WHERE pi.codigo_institucion = :inst
                    ORDER BY pi.fecha_ingreso DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':inst' => $cod_institucion]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['respuesta' => true, 'data' => $data]);
        } catch (Exception $e) { echo json_encode(['respuesta' => false, 'mensaje' => $e->getMessage()]); }
        break;
        case 'obtener_resumen_saldos':
            try {
                // 1. Total en Bancos
                $sqlB = "SELECT SUM(saldo_actual) as total_banco FROM fin_bancos_cuentas WHERE codigo_institucion = :inst AND estado = 'A'";
                $stmtB = $pdo->prepare($sqlB);
                $stmtB->execute([':inst' => $cod_institucion]);
                $resB = $stmtB->fetch(PDO::FETCH_ASSOC);
                $totalBanco = $resB['total_banco'] ?? 0;
    
                // 2. Total en Cajas
                $sqlC = "SELECT SUM(saldo_actual) as total_caja FROM fin_cajas WHERE codigo_institucion = :inst AND estado = 'A'";
                $stmtC = $pdo->prepare($sqlC);
                $stmtC->execute([':inst' => $cod_institucion]);
                $resC = $stmtC->fetch(PDO::FETCH_ASSOC);
                $totalCaja = $resC['total_caja'] ?? 0;
    
                // 3. Deuda Total (Opcional, para ver pasivo vs activo)
                // (Ya teníamos esta query en listar_estado_deuda, la simplificamos aquí)
                /* $sqlD = ... */ 
    
                echo json_encode([
                    'respuesta' => true, 
                    'data' => [
                        'bancos' => number_format($totalBanco, 2, '.', ''),
                        'caja'   => number_format($totalCaja, 2, '.', ''),
                        'total_disponible' => number_format($totalBanco + $totalCaja, 2, '.', '')
                    ]
                ]);
    
            } catch (Exception $e) {
                echo json_encode(['respuesta' => false, 'mensaje' => $e->getMessage()]);
            }
            break;


    default:
        echo json_encode(['respuesta' => false, 'mensaje' => 'Acción no válida']);
        break;
}


// ==============================================================================
// FUNCIÓN AUXILIAR: GENERAR PARTIDA CONTABLE (Al final del archivo)
// ==============================================================================
function registrar_partida_prestamo($pdo, $cod_inst, $id_prestamo, $datos) {
    
    // 1. Buscar ID de la cuenta "Préstamos Bancarios" (Código 2103)
    // Usamos el código '2103' que creamos en el SQL
    $stmtP = $pdo->prepare("SELECT id FROM cuentas_contables WHERE codigo = '2103' AND codigo_institucion = :inst LIMIT 1");
    $stmtP->execute([':inst' => $cod_inst]);
    $cuenta_pasivo_id = $stmtP->fetchColumn(); 
    
    // Fallback: Si no existe la 2103, intentamos buscar la genérica de pasivo 2101
    if(!$cuenta_pasivo_id) {
        $stmtFallback = $pdo->prepare("SELECT id FROM cuentas_contables WHERE codigo = '2101' AND codigo_institucion = :inst LIMIT 1");
        $stmtFallback->execute([':inst' => $cod_inst]);
        $cuenta_pasivo_id = $stmtFallback->fetchColumn();
    }
    
    // Si aun así no hay cuenta, lanzamos error para no descuadrar contabilidad
    if(!$cuenta_pasivo_id) throw new Exception("Error Contable: No se encuentra cuenta (2103) para registrar el Pasivo.");

    // 2. Calcular número de asiento (Correlativo)
    $stmtCorr = $pdo->prepare("SELECT COALESCE(MAX(numero_asiento), 0) + 1 FROM asientos_contables WHERE codigo_institucion = :inst");
    $stmtCorr->execute([':inst' => $cod_inst]);
    $num_asiento = $stmtCorr->fetchColumn();

    // 3. Insertar Encabezado (Partida)
    $sqlCab = "INSERT INTO asientos_contables 
               (codigo_institucion, numero_asiento, fecha_asiento, concepto, tipo_asiento, estado, usuario_registro)
               VALUES (:inst, :num, :fecha, :conc, 'DIARIO', 'MAYORIZADO', :usr) 
               RETURNING id";
    
    $stmt = $pdo->prepare($sqlCab);
    $stmt->execute([
        ':inst' => $cod_inst,
        ':num' => $num_asiento,
        ':fecha' => $datos['fecha'],
        ':conc' => "Préstamo Recibido #" . $id_prestamo,
        ':usr' => $datos['usuario']
    ]);
    $id_asiento = $stmt->fetchColumn();

    // 4. Detalle 1: DEBE (Entra Dinero -> Activo Aumenta: Caja o Banco)
    $sqlDet = "INSERT INTO detalle_asientos (asiento_id, cuenta_id, debito, credito, descripcion_linea) VALUES (?, ?, ?, ?, ?)";
    $stmtDet = $pdo->prepare($sqlDet);
    
    $stmtDet->execute([
        $id_asiento, 
        $datos['id_cuenta_activo'], // Viene del parámetro (ID 35 o 36)
        $datos['monto'], 
        0.00, 
        "Ingreso de fondos (Préstamo)"
    ]);

    // 5. Detalle 2: HABER (Nace Deuda -> Pasivo Aumenta)
    $stmtDet->execute([
        $id_asiento, 
        $cuenta_pasivo_id, 
        0.00, 
        $datos['monto'], 
        "Registro de Obligación Financiera"
    ]);

    return $id_asiento;
}

?>