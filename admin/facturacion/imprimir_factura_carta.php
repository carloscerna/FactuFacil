<?php
// admin/facturacion/imprimir_factura_carta.php
session_name('FactuFacil');
session_start();

$path_root = trim($_SERVER['DOCUMENT_ROOT']);
include($path_root."/FactuFacil/includes/mainFunctions_.php");
include($path_root."/FactuFacil/php_libs/fpdf/fpdf.php");
include($path_root."/FactuFacil/includes/NumerosALetras.php"); 

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
            c.direccion as cli_dir, 
            c.telefono_celular as cli_tel, 
            c.giro as cli_actividad,
            
            -- DATOS DE TU EMPRESA (Desde tabla 'instituciones')
            ci.nombre_institucion as emisor_nombre,  
            ci.nombre_legal as emisor_razon,         
            ci.nit as emisor_nit, 
            ci.nrc as emisor_nrc, 
            ci.direccion as emisor_dir, 
            ci.telefono as emisor_tel,
            ci.logo_uno as emisor_logo,              
            
            TO_CHAR(v.fecha_emision, 'DD-MM-YYYY') as fecha_fmt,
            TO_CHAR(v.fecha_creacion, 'HH24:MI:SS') as hora_fmt
        FROM ventas_cabecera v
        JOIN clientes c ON v.id_cliente = c.id_clientes
        -- CAMBIO AQUÍ: Nombre de tabla corregido
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

class PDF extends FPDF {
    public $d; 

    function Header() {
        // --- 1. LOGO ---
        $logoName = !empty($this->d['emisor_logo']) ? $this->d['emisor_logo'] : 'logo_empresa.png';
        $logoPath = '../../img/' . $logoName; 
        
        if(file_exists($logoPath)) {
            $this->Image($logoPath, 10, 10, 30); 
        } else {
            $this->SetFillColor(230, 230, 230);
            $this->Rect(10, 10, 30, 20, 'F');
            $this->SetXY(10, 18);
            $this->SetFont('Arial','B',8);
            $this->Cell(30, 4, "SIN LOGO", 0, 0, 'C');
        }

        // --- 2. DATOS EMISOR ---
        $this->SetXY(45, 10);
        $this->SetFont('Arial','B',12);
        $this->Cell(80, 6, utf8_decode($this->d['emisor_nombre']), 0, 1, 'L'); 
        
        $this->SetX(45);
        $this->SetFont('Arial','B',8);
        $this->Cell(80, 4, utf8_decode($this->d['emisor_razon']), 0, 1, 'L'); 
        
        $this->SetX(45);
        $this->SetFont('Arial','',7);
        $this->MultiCell(80, 3, utf8_decode(substr($this->d['emisor_dir'], 0, 120)), 0, 'L');
        
        $this->SetX(45);
        $this->Cell(80, 4, "Tel: ".$this->d['emisor_tel'], 0, 1, 'L');
        $this->SetX(45);
        $this->Cell(80, 4, "NIT: ".$this->d['emisor_nit']."  NRC: ".$this->d['emisor_nrc'], 0, 1, 'L');

        // --- 3. CAJA DTE ---
        $xBox = 135; $yBox = 10; $wBox = 70; $hBox = 32;
        $this->Rect($xBox, $yBox, $wBox, $hBox);
        
        $this->SetXY($xBox, $yBox + 2);
        $this->SetFont('Arial','B',10);
        $titulo_doc = ($this->d['tipo_documento'] == '03') ? "CRÉDITO FISCAL ELECTRÓNICO" : "FACTURA ELECTRÓNICA";
        $this->Cell($wBox, 5, utf8_decode($titulo_doc), 0, 1, 'C');
        
        $this->SetFont('Arial','B',7);
        $this->SetXY($xBox + 2, $yBox + 8);
        $this->Cell(25, 4, "COD. GENERACION:", 0, 0);
        $this->SetFont('Arial','',6.5);
        $this->Cell(40, 4, utf8_decode($this->d['codigo_generacion']), 0, 1);
        
        $this->SetX($xBox + 2);
        $this->SetFont('Arial','B',7);
        $this->Cell(25, 4, "NUM. CONTROL:", 0, 0);
        $this->SetFont('Arial','',7);
        $this->Cell(40, 4, utf8_decode($this->d['numero_control']), 0, 1);
        
        $this->SetX($xBox + 2);
        $this->SetFont('Arial','B',7);
        $this->Cell(25, 4, "SELLO:", 0, 0);
        $this->SetFont('Arial','',6);
        $this->Cell(40, 4, substr($this->d['sello_recepcion'], 0, 22)."...", 0, 1);

        // --- 4. DATOS CLIENTE ---
        $this->SetY(45);
        $this->SetFont('Arial','B',8);
        $this->Cell(15, 5, "CLIENTE:", 0, 0);
        $this->SetFont('Arial','',8);
        $this->Cell(100, 5, utf8_decode($this->d['nombre_cliente']), 0, 0);
        
        $this->SetFont('Arial','B',8);
        $this->Cell(15, 5, "FECHA:", 0, 0);
        $this->SetFont('Arial','',8);
        $this->Cell(30, 5, $this->d['fecha_fmt'], 0, 1);
        
        $this->SetFont('Arial','B',8);
        $this->Cell(15, 5, "DIR:", 0, 0);
        $this->SetFont('Arial','',8);
        $this->Cell(100, 5, utf8_decode(substr($this->d['cli_dir'],0,55)), 0, 0);
        
        $this->SetFont('Arial','B',8);
        $this->Cell(15, 5, "NIT:", 0, 0);
        $this->SetFont('Arial','',8);
        $this->Cell(30, 5, $this->d['cli_nit'], 0, 1);

        $this->Ln(4);
        
        // --- 5. TABLA ---
        $this->SetFillColor(240);
        $this->SetFont('Arial','B',8);
        $this->Cell(12, 6, "CANT", 1, 0, 'C', 1);
        $this->Cell(20, 6, "UNIDAD", 1, 0, 'C', 1);
        $this->Cell(25, 6, "CODIGO", 1, 0, 'C', 1);
        $this->Cell(93, 6, "DESCRIPCION", 1, 0, 'L', 1);
        $this->Cell(20, 6, "P.UNIT", 1, 0, 'R', 1);
        $this->Cell(25, 6, "TOTAL", 1, 1, 'R', 1);
    }

    function Footer() {
        $this->SetY(-20);
        $this->SetFont('Arial','I',7);
        $this->Cell(0, 4, utf8_decode("Este documento es una Representación Gráfica de DTE"), 0, 1, 'C');
        $this->Cell(0, 4, "Pagina ".$this->PageNo()."/{nb}", 0, 0, 'C');
    }
    
    function PrintQR($x, $y) {
        $this->Rect($x, $y, 25, 25);
        $this->SetXY($x, $y+10);
        $this->SetFont('Arial','B',8);
        $this->Cell(25, 5, "QR CODE", 0, 0, 'C');
    }
}

$pdf = new PDF('P','mm','Letter');
$pdf->d = $datos;
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','',8);

foreach($detalles as $row) {
    if($pdf->GetY() > 220) $pdf->AddPage();
    
    $pdf->Cell(12, 5, $row['cantidad'], 0, 0, 'C');
    $pdf->Cell(20, 5, "59", 0, 0, 'C');
    $pdf->Cell(25, 5, $row['codigo_producto'], 0, 0, 'C');
    $pdf->Cell(93, 5, utf8_decode(substr($row['descripcion'],0,60)), 0, 0, 'L');
    $pdf->Cell(20, 5, number_format($row['precio_unitario'], 2), 0, 0, 'R');
    $pdf->Cell(25, 5, number_format($row['subtotal'], 2), 0, 1, 'R');
}
$pdf->Cell(195, 0, '', 'T', 1);

// TOTALES
$pdf->Ln(5);
$yStart = $pdf->GetY();
if($yStart > 230) { $pdf->AddPage(); $yStart = 10; }

// Izquierda
$pdf->SetFont('Arial','B',8);
$total_letras = function_exists('convertirNumerosALetras') ? convertirNumerosALetras(number_format($datos['total_venta'],2)) : "SON: ".$datos['total_venta'];
$pdf->MultiCell(110, 5, $total_letras, 0, 'L');

$pdf->Ln(2);
$pdf->SetFont('Arial','B',7);
$pdf->Cell(30,4,"Sello Recepcion:",0,1);
$pdf->SetFont('Arial','',6);
$pdf->MultiCell(110, 3, utf8_decode($datos['sello_recepcion']), 0, 'L');
$pdf->PrintQR(10, $pdf->GetY()+5);

// Derecha
$pdf->SetXY(135, $yStart);
$pdf->SetFont('Arial','',9);
$pdf->Cell(35, 5, "Suma Gravada:", 0, 0, 'R');
$pdf->Cell(35, 5, "$ ".number_format($datos['total_gravado'], 2), 1, 1, 'R');

$pdf->SetX(135);
$pdf->Cell(35, 5, "IVA (13%):", 0, 0, 'R');
$pdf->Cell(35, 5, "$ ".number_format($datos['total_iva'], 2), 1, 1, 'R');

$pdf->SetX(135);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(35, 8, "TOTAL A PAGAR:", 0, 0, 'R');
$pdf->Cell(35, 8, "$ ".number_format($datos['total_venta'], 2), 1, 1, 'R');

$pdf->Output('I', 'Factura_'.$datos['numero_documento'].'.pdf');
?>