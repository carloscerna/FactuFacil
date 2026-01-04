<?php
// admin/facturacion/transmitir_dte.php
session_name('FactuFacil');
session_start();
header('Content-Type: application/json');

include("../../includes/mainFunctions_.php");
$pdo = $dblink;

$id_venta = $_POST['id_venta'] ?? '';
$codigo_institucion = $_SESSION['codigo_institucion'];
$usuario_activo = $_SESSION['userNombre'];

if(empty($id_venta)) {
    echo json_encode(['respuesta' => false, 'mensaje' => 'ID Venta no recibido']);
    exit;
}

try {
    // 1. VERIFICAR SI YA FUE TRANSMITIDA
    $sqlCheck = "SELECT sello_recepcion FROM ventas_cabecera WHERE id_venta = ? AND codigo_institucion = ?";
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute([$id_venta, $codigo_institucion]);
    $venta = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if(!empty($venta['sello_recepcion'])) {
        throw new Exception("Esta factura ya fue transmitida a Hacienda previamente.");
    }

    // =================================================================================
    // AQUÍ INICIA LA MAGIA (SIMULACIÓN vs REALIDAD)
    // =================================================================================
    
    // --- MODO SIMULACIÓN: ON ---
    // (Fingimos que Hacienda nos respondió bien)
    
    // Generamos un Sello falso (Simulando el de 40 caracteres de Hacienda)
    $sello_simulado = "SIM" . strtoupper(bin2hex(random_bytes(18))); 
    $fecha_procesamiento = date('Y-m-d H:i:s');
    $estado = 'PROCESADO';
    $mensaje_mh = "Documento Recibido con Éxito (SIMULACIÓN)";

    /* --- MODO REALIDAD (FUTURO) ---
    Aquí pondremos el código cURL que toma el JSON generado en el paso anterior,
    lo envía al puerto 8081 (Firmador) y recibe la respuesta real.
    
    $response = enviar_al_firmador($json_dte);
    $sello_simulado = $response['selloRecibido'];
    */

    // =================================================================================
    // 3. GUARDAR RESULTADO EN BASE DE DATOS (ESTO ES 100% REAL)
    // =================================================================================
    
    // Actualizamos la venta con el sello que "recibimos"
    $sqlUpdate = "UPDATE ventas_cabecera 
                  SET sello_recepcion = ?, 
                      fh_procesamiento = ?,
                      estado_dte = ? 
                  WHERE id_venta = ?";
                  
    // Nota: Si no creaste la columna 'estado_dte', el SQL fallará. 
    // Si falla, borra la línea "estado_dte = ?," del query.
    
    // Si aún no tienes la columna estado_dte, corre: 
    // ALTER TABLE ventas_cabecera ADD COLUMN estado_dte VARCHAR(20) DEFAULT 'PENDIENTE';

    $stmtUpd = $pdo->prepare($sqlUpdate);
    $stmtUpd->execute([$sello_simulado, $fecha_procesamiento, $estado, $id_venta]);

    echo json_encode([
        'respuesta' => true,
        'mensaje' => $mensaje_mh,
        'sello' => $sello_simulado,
        'fecha' => $fecha_procesamiento
    ]);

} catch (Exception $e) {
    echo json_encode(['respuesta' => false, 'mensaje' => $e->getMessage()]);
}
?>