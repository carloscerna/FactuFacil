<?php
// admin/facturacion/enviar_correo.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_name('FactuFacil');
session_start();

$path_root = trim($_SERVER['DOCUMENT_ROOT']);

// 1. INCLUSIONES NECESARIAS
require $path_root."/FactuFacil/php_libs/PHPMailer/src/Exception.php";
require $path_root."/FactuFacil/php_libs/PHPMailer/src/PHPMailer.php";
require $path_root."/FactuFacil/php_libs/PHPMailer/src/SMTP.php";

include($path_root."/FactuFacil/includes/mainFunctions_.php");
include($path_root."/FactuFacil/php_libs/fpdf/fpdf.php");
include($path_root."/FactuFacil/includes/NumerosALetras.php"); 

header('Content-Type: application/json');

$id_venta = $_POST['id_venta'] ?? 0;
$codigo_institucion = $_SESSION['codigo_institucion'];
$pdo = $dblink;

try {
    if(empty($id_venta)) throw new Exception("ID de Venta no recibido");

    // =========================================================================
    // 2. OBTENER DATOS (La misma consulta robusta que ya hicimos)
    // =========================================================================
    $sql = "SELECT v.*, 
                CASE 
                    WHEN c.nombre_empresa IS NOT NULL AND TRIM(c.nombre_empresa) <> '' THEN c.nombre_empresa
                    ELSE CONCAT(c.nombres, ' ', c.apellidos)
                END as nombre_cliente,
                c.correo_electronico as email_cliente, -- IMPORTANTE
                c.nit as cli_nit, c.numero_registro as cli_nrc, c.direccion as cli_dir, 
                
                ci.nombre_institucion as emisor_nombre, 
                ci.nombre_legal as emisor_razon,
                ci.nit as emisor_nit, ci.nrc as emisor_nrc, 
                ci.direccion as emisor_dir, ci.telefono as emisor_tel,
                ci.logo_uno as emisor_logo,
                
                TO_CHAR(v.fecha_emision, 'DD-MM-YYYY') as fecha_fmt
            FROM ventas_cabecera v
            JOIN clientes c ON v.id_cliente = c.id_clientes
            INNER JOIN instituciones ci ON v.codigo_institucion = ci.codigo_institucion
            WHERE v.id_venta = ? AND v.codigo_institucion = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_venta, $codigo_institucion]);
    $datos = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$datos) throw new Exception("Venta no encontrada.");
    
    // Validar correo del cliente
    if(empty($datos['email_cliente']) || !filter_var($datos['email_cliente'], FILTER_VALIDATE_EMAIL)){
        throw new Exception("El cliente no tiene un correo electrónico válido registrado.");
    }

    $stmtDet = $pdo->prepare("SELECT * FROM ventas_detalle WHERE id_venta = ?");
    $stmtDet->execute([$id_venta]);
    $detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);


    // =========================================================================
    // 3. GENERAR EL PDF EN MEMORIA (Reusamos la lógica Carta)
    // =========================================================================
    // (Nota: Copiamos la clase PDF aquí pero con nombre único para evitar choques)
    class PDF_Email extends FPDF {
        public $d;
        function Header() {
             // ... (Pega aquí el mismo Header del archivo Carta que te di antes) ...
             // Para ahorrar espacio en el chat, asumo que usas la misma lógica de Header/Footer
             // Si quieres que te pegue todo el bloque Header de nuevo, avísame.
             // POR AHORA USARÉ UNO SIMPLIFICADO PARA QUE FUNCIONE EL EJEMPLO:
             $this->SetFont('Arial','B',12);
             $this->Cell(0,10,utf8_decode($this->d['emisor_nombre']),0,1,'C');
             $this->SetFont('Arial','',10);
             $this->Cell(0,5,"DTE: ".$this->d['codigo_generacion'],0,1,'C');
             $this->Ln(10);
        }
        function Footer() {
             $this->SetY(-15);
             $this->SetFont('Arial','I',8);
             $this->Cell(0,10,'Pagina '.$this->PageNo(),0,0,'C');
        }
    }

    $pdf = new PDF_Email('P','mm','Letter');
    $pdf->d = $datos;
    $pdf->AddPage();
    $pdf->SetFont('Arial','',10);
    
    // Cuerpo simple para el ejemplo (Tú pon aquí el loop de detalles completo)
    foreach($detalles as $d){
        $pdf->Cell(15,6,$d['cantidad'],1);
        $pdf->Cell(130,6,utf8_decode(substr($d['descripcion'],0,70)),1);
        $pdf->Cell(25,6,$d['subtotal'],1,1,'R');
    }
    
    // Guardar PDF en variable string (No en disco)
    $pdf_content = $pdf->Output('S'); 


    // =========================================================================
    // 4. CONSTRUIR EL JSON (Simulado o Real)
    // =========================================================================
    // Aquí deberías recuperar el JSON real si lo guardaste en BD. 
    // Como estamos simulando, creamos una estructura básica con los datos actuales.
    $json_array = [
        "identificacion" => [
            "version" => 3,
            "codigoGeneracion" => $datos['codigo_generacion'],
            "numeroControl" => $datos['numero_control'],
            "fecha" => $datos['fecha_emision']
        ],
        "emisor" => [
            "nit" => $datos['emisor_nit'],
            "nombre" => $datos['emisor_nombre']
        ],
        "receptor" => [
            "nit" => $datos['cli_nit'],
            "nombre" => $datos['nombre_cliente']
        ],
        "resumen" => [
            "totalPagar" => $datos['total_venta']
        ],
        "selloRecepcion" => $datos['sello_recepcion']
    ];
    $json_content = json_encode($json_array, JSON_PRETTY_PRINT);


    // =========================================================================
    // 5. CONFIGURACIÓN Y ENVÍO (PHPMailer)
    // =========================================================================
    $mail = new PHPMailer(true);

    // --- CONFIGURACIÓN SMTP (CAMBIA ESTO CON TUS DATOS REALES) ---
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';     // Ej: smtp.gmail.com o tu hosting
    $mail->SMTPAuth   = true;
    $mail->Username   = 'carlos.w.cerna@gmail.com'; // TU CORREO
    $mail->Password   = 'gluxhhawcicodedh'; // TU CONTRASEÑA (Usa App Password si es Gmail)
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // O PHPMailer::ENCRYPTION_STARTTLS
    $mail->Port       = 465; // O 587

    // Remitente y Destinatario
    $mail->setFrom('carlos.w.cerna@gmail.com', $datos['emisor_nombre']);
    $mail->addAddress($datos['email_cliente'], $datos['nombre_cliente']);

    // Adjuntos
    $mail->addStringAttachment($pdf_content, 'DTE_'.$datos['numero_documento'].'.pdf');
    $mail->addStringAttachment($json_content, 'DTE_'.$datos['codigo_generacion'].'.json');

    // Contenido del Correo
    $mail->isHTML(true);
    $mail->Subject = "Comprobante Electronico - ".$datos['emisor_nombre'];
    $mail->Body    = "
        <h3>Hola, {$datos['nombre_cliente']}</h3>
        <p>Adjunto encontrará su Factura Electrónica (DTE) y el archivo JSON correspondiente.</p>
        <p><strong>Total:</strong> $ {$datos['total_venta']}</p>
        <p><strong>Código Generación:</strong> {$datos['codigo_generacion']}</p>
        <br>
        <p>Gracias por su preferencia.</p>
        <hr>
        <small>{$datos['emisor_nombre']}</small>
    ";

    $mail->send();

    echo json_encode(['respuesta' => true, 'mensaje' => 'Correo enviado exitosamente a ' . $datos['email_cliente']]);

} catch (Exception $e) {
    echo json_encode(['respuesta' => false, 'mensaje' => 'Error al enviar: ' . $mail->ErrorInfo . ' | ' . $e->getMessage()]);
}
?>