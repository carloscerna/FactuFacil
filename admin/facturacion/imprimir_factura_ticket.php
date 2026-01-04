<?php
// admin/facturacion/imprimir_factura_ticket.php
session_name('FactuFacil');
session_start();

$path_root = trim($_SERVER['DOCUMENT_ROOT']);
include($path_root."/FactuFacil/includes/mainFunctions_.php");
include($path_root."/FactuFacil/php_libs/fpdf/fpdf.php");

if (empty($_SESSION['userNombre'])) die("Acceso denegado");

$id_venta = $_GET['id'] ?? 0;
$codigo_institucion = $_SESSION['codigo_institucion'];
$pdo = $dblink;

// 1. CONSULTA OPTIMIZADA (CORREGIDA: TABLA 'instituciones')
$sql = "SELECT v.*, 
            -- Datos del Cliente
            CASE 
                WHEN c.nombre_empresa IS NOT NULL AND TRIM(c.nombre_empresa) <> '' THEN c.nombre_empresa
                ELSE CONCAT(c.nombres, ' ', c.apellidos)
            END as nombre_cliente,
            c.nit as cli_nit, 
            c.numero_registro as cli_nrc, 
            
            -- DATOS DE TU EMPRESA (Desde tabla 'instituciones')
            ci.nombre_institucion as emisor_nombre, 
            ci.nit as emisor_nit, 
            ci.nrc as emisor_nrc, 
            ci.direccion as emisor_dir,
            ci.logo_uno as emisor_logo,
            
            TO_CHAR(v.fecha_emision, 'DD-MM-YYYY') as fecha_fmt,
            TO_CHAR(v.fecha_creacion, 'HH24:MI:SS') as hora_fmt
        FROM ventas_cabecera v
        JOIN clientes c ON v.id_cliente = c.id_clientes
        -- CAMBIO IMPORTANTE: Nombre de tabla corregido a 'instituciones'
        INNER JOIN instituciones ci ON v.codigo_institucion = ci.codigo_institucion
        WHERE v.id_venta = ? AND v.codigo_institucion = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id_venta, $codigo_institucion]);
$datos = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$datos) die("Factura no encontrada.");

// 2. DETALLES
$stmtDet = $pdo->prepare("SELECT * FROM ventas_detalle WHERE id_venta = ?");
$stmtDet->execute([$id_venta]);
$detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

class PDF_Ticket extends FPDF {
    function Header() {} 
    function Footer() {}
}

// Configuración de página (80mm ancho)
$pdf = new PDF_Ticket('P','mm',array(80, 260)); 
$pdf->AddPage();
$pdf->SetMargins(4, 4, 4);
$pdf->SetAutoPageBreak(true, 2);

// --- LOGO ---
// Busca el nombre del archivo en la BD. Si está vacío, busca 'logo_empresa.png' por defecto.
$logoName = !empty($datos['emisor_logo']) ? $datos['emisor_logo'] : 'logo_empresa.png';
$logoPath = '../../img/' . $logoName; 

if(file_exists($logoPath)) {
    // Ajusta la posición y tamaño del logo
    $pdf->Image($logoPath, 25, 5, 30); 
    $pdf->Ln(15);
} else {
    $pdf->Ln(2);
}

// --- DATOS EMISOR (Zafiro) ---
$pdf->SetFont('Arial','B',10);
$pdf->MultiCell(72, 5, utf8_decode($datos['emisor_nombre']), 0, 'C');

$pdf->SetFont('Arial','',8);
// Cortamos la dirección para que no sea excesivamente larga en el ticket
$pdf->MultiCell(72, 4, utf8_decode(substr($datos['emisor_dir'],0,80)), 0, 'C');
$pdf->Cell(72, 4, "NIT: ".$datos['emisor_nit'], 0, 1, 'C');
$pdf->Cell(72, 4, "NRC: ".$datos['emisor_nrc'], 0, 1, 'C');

$pdf->Ln(2);
$pdf->SetFont('Arial','B',9);
$pdf->Cell(72, 4, "FACTURA ELECTRONICA", 0, 1, 'C');
$pdf->SetFont('Arial','',7);
$pdf->MultiCell(72, 3, "Cod: ".$datos['codigo_generacion'], 0, 'C');
$pdf->Cell(72, 4, "Fecha: ".$datos['fecha_fmt']." ".$datos['hora_fmt'], 0, 1, 'C');

$pdf->Ln(2);
$pdf->Cell(72, 0, '', 'T', 1); // Línea separadora
$pdf->Ln(1);

// --- CLIENTE ---
$pdf->SetFont('Arial','B',8);
$pdf->Cell(12, 4, "Cliente:", 0, 0);
$pdf->SetFont('Arial','',8);
$pdf->MultiCell(60, 4, utf8_decode($datos['nombre_cliente']), 0, 'L');

$pdf->Ln(2);

// --- DETALLES ---
$pdf->SetFont('Arial','B',7);
$pdf->Cell(8, 4, "Cant", 0, 0);
$pdf->Cell(38, 4, "Descrip.", 0, 0);
$pdf->Cell(12, 4, "P.U.", 0, 0, 'R');
$pdf->Cell(14, 4, "Total", 0, 1, 'R');
$pdf->Ln(4);

$pdf->SetFont('Arial','',7);
foreach($detalles as $row) {
    $pdf->Cell(8, 4, $row['cantidad'], 0, 0);
    // Recortamos descripción a 22 caracteres para que quepa en una linea
    $nombre = substr($row['descripcion'], 0, 22);
    $pdf->Cell(38, 4, utf8_decode($nombre), 0, 0);
    $pdf->Cell(12, 4, number_format($row['precio_unitario'], 2), 0, 0, 'R');
    $pdf->Cell(14, 4, number_format($row['subtotal'], 2), 0, 1, 'R');
}

$pdf->Ln(2);
$pdf->Cell(72, 0, '', 'T', 1);
$pdf->Ln(2);

// --- TOTALES ---
$pdf->SetFont('Arial','B',10);
$pdf->Cell(45, 6, "TOTAL:", 0, 0, 'R');
$pdf->Cell(27, 6, "$ ".number_format($datos['total_venta'], 2), 0, 1, 'R');

$pdf->Ln(5);
$pdf->SetFont('Arial','',6);
$pdf->MultiCell(72, 3, "Sello: ".$datos['sello_recepcion'], 0, 'C');

$pdf->Output('I', 'Ticket.pdf');
?>