<?php

session_name('FactuFacil');
session_start();

if (empty($_SESSION['userNombre'])) {
    header('Content-Type: application/json');
    echo json_encode(['respuesta' => false, 'mensaje' => 'Sesión no válida.']);
    exit();
}
// ruta de los archivos con su carpeta
$path_root = trim($_SERVER['DOCUMENT_ROOT']);

// archivos que se incluyen.
include($path_root . "/factufacil/includes/funciones.php");
include($path_root . "/factufacil/includes/mainFunctions_.php");
// Llamar a la libreria fpdf
include($path_root . "/factufacil/php_libs/fpdf/fpdf.php");
// Llamar a la libreria fpdf_curved_text para textos curvos (si se usa)
// include($path_root . "/factufacil/php_libs/fpdf/fpdf_curved_text.php"); // Descomentar si realmente usas esta funcionalidad para títulos

// cambiar a utf-8.
header("Content-Type: text/html; charset=UTF-8");

// 1. VALIDACIÓN Y CONFIGURACIÓN INICIAL
if (empty($_SESSION['userNombre']) || empty($_SESSION['codigo_institucion'])) {
    header('Content-Type: application/json'); // O redirigir a login, según tu flujo
    echo json_encode(['respuesta' => false, 'mensaje' => 'Sesión o institución no válidas.']);
    exit();
}

// Usamos dblink, que es tu variable de conexión PDO
$db = $dblink; 
$codigo_institucion_activa = $_SESSION['codigo_institucion']; 

// 2. RECUPERAR FECHAS DEL FORMULARIO
// Si no vienen por GET, establecer un rango predeterminado (ej. el año actual)
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-01-01');
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-12-31');

// Clase PDF extendida (sin cambios en Header o Footer, excepto la adición de setFechasReporte)
class PDF extends FPDF
{
    private $fecha_inicio_reporte;
    private $fecha_fin_reporte;

    // Métodos para establecer las fechas
    public function setFechasReporte($inicio, $fin) {
        $this->fecha_inicio_reporte = $inicio;
        $this->fecha_fin_reporte = $fin;
    }

    //Cabecera de página
    function Header()
    {
        //Logo
        $img = $_SERVER['DOCUMENT_ROOT'] . '/factufacil/img/' . $_SESSION['logo_uno'];
        if (file_exists($img)) {
            $this->Image($img, 5, 4, 24, 24);
        }
        
        //Arial bold 14
        $this->SetFont('Arial', 'B', 14);
        //Título de la institución
        $this->SetY(10);
        $this->SetX(30);
        $this->Cell(0, 5, utf8_decode($_SESSION['institucion']), 0, 1, 'C');
        
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, utf8_decode('Balance de Comprobación'), 0, 1, 'C');
        
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, utf8_decode('Período del ' . date('d/m/Y', strtotime($this->fecha_inicio_reporte)) . ' al ' . date('d/m/Y', strtotime($this->fecha_fin_reporte))), 0, 1, 'C');
        
        // Salto de línea
        $this->Ln(8);
        
        // Línea divisoria
        $this->SetLineWidth(0.5);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(3); // Espacio después de la línea
    }

    //Pie de página
    function Footer()
    {
        //Posición: a 1.5 cm del final
        $this->SetY(-15);
        //Arial italic 8
        $this->SetFont('Arial','I',8);
        //Número de página
        $this->Cell(0,10,utf8_decode('Página ').$this->PageNo().'/{nb}',0,0,'C');
        // Fecha y hora de generación
        $this->Cell(0,10,utf8_decode('Generado el: ').date('d/m/Y H:i:s'),0,0,'R');
    }

    //Tabla coloreada para el Balance de Comprobación
    function FancyTable($header, $data)
    {
        // Colores, ancho de línea y fuente en negrita para el encabezado
        $this->SetFillColor(230, 230, 230); // Gris claro
        $this->SetTextColor(0);
        $this->SetDrawColor(128, 128, 128); // Gris medio
        $this->SetLineWidth(.3);
        $this->SetFont('Arial', 'B', 8);

        // Anchos de las columnas (ajustados para Balance de Comprobación en Letter)
        // Código, Cuenta, Total Débito, Total Crédito, Saldo Deudor, Saldo Acreedor
        $w = array(18, 60, 25, 25, 25, 25); 

        // Cabecera
        for ($i = 0; $i < count($header); $i++) {
            $this->Cell($w[$i], 7, utf8_decode($header[$i]), 1, 0, 'C', true);
        }
        $this->Ln();

        // Restauración de colores y fuente para los datos
        $this->SetFillColor(245, 245, 245); // Gris muy claro para filas alternas
        $this->SetTextColor(0);
        $this->SetFont('Arial', '', 8);

        // Datos
        $fill = false;
        $total_debito = 0;
        $total_credito = 0;
        $total_saldo_deudor_col = 0; // Suma de la columna Saldo Deudor
        $total_saldo_acreedor_col = 0; // Suma de la columna Saldo Acreedor

        foreach ($data as $row) {
            // Calcular saldos Deudor y Acreedor para la impresión en las columnas
            $saldo_deudor = 0;
            $saldo_acreedor = 0;
            if ($row['saldo_final'] > 0) {
                $saldo_deudor = $row['saldo_final'];
            } else {
                $saldo_acreedor = abs($row['saldo_final']);
            }

            $this->Cell($w[0], 6, utf8_decode($row['codigo']), 'LR', 0, 'L', $fill);
            $this->Cell($w[1], 6, utf8_decode($row['nombre']), 'LR', 0, 'L', $fill);
            $this->Cell($w[2], 6, number_format($row['total_debito_acumulado'], 2, '.', ','), 'LR', 0, 'R', $fill);
            $this->Cell($w[3], 6, number_format($row['total_credito_acumulado'], 2, '.', ','), 'LR', 0, 'R', $fill);
            $this->Cell($w[4], 6, number_format($saldo_deudor, 2, '.', ','), 'LR', 0, 'R', $fill);
            $this->Cell($w[5], 6, number_format($saldo_acreedor, 2, '.', ','), 'LR', 0, 'R', $fill);
            $this->Ln();
            $fill = !$fill;

            // Sumar para los totales
            $total_debito += $row['total_debito_acumulado'];
            $total_credito += $row['total_credito_acumulado'];
            $total_saldo_deudor_col += $saldo_deudor;
            $total_saldo_acreedor_col += $saldo_acreedor;
        }

        // Línea de cierre de la tabla
        $this->Cell(array_sum($w), 0, '', 'T');
        $this->Ln();

        // Totales
        $this->SetFont('Arial', 'B', 8);
        $this->Cell($w[0] + $w[1], 7, utf8_decode('TOTALES:'), 'TB', 0, 'R', true);
        $this->Cell($w[2], 7, number_format($total_debito, 2, '.', ','), 'TB', 0, 'R', true);
        $this->Cell($w[3], 7, number_format($total_credito, 2, '.', ','), 'TB', 0, 'R', true);
        $this->Cell($w[4], 7, number_format($total_saldo_deudor_col, 2, '.', ','), 'TB', 0, 'R', true);
        $this->Cell($w[5], 7, number_format($total_saldo_acreedor_col, 2, '.', ','), 'TB', 0, 'R', true);
        $this->Ln();
    }
}

//************************************************************************************************************************
// PROCESO PARA OBTENER LOS DATOS DEL BALANCE DE COMPROBACIÓN
//************************************************************************************************************************
$data_balance = [];

try {
    $query = "
        SELECT
            cc.codigo,
            cc.nombre,
            cc.naturaleza,
            COALESCE(SUM(CASE WHEN ac.fecha_asiento BETWEEN :fecha_inicio AND :fecha_fin THEN da.debito ELSE 0 END), 0) AS total_debito_acumulado,
            COALESCE(SUM(CASE WHEN ac.fecha_asiento BETWEEN :fecha_inicio AND :fecha_fin THEN da.credito ELSE 0 END), 0) AS total_credito_acumulado,
            
            CASE 
                WHEN cc.naturaleza = 'Deudor' 
                    THEN COALESCE(SUM(CASE WHEN ac.fecha_asiento BETWEEN :fecha_inicio AND :fecha_fin THEN da.debito ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN ac.fecha_asiento BETWEEN :fecha_inicio AND :fecha_fin THEN da.credito ELSE 0 END), 0)
                WHEN cc.naturaleza = 'Acreedor' 
                    THEN COALESCE(SUM(CASE WHEN ac.fecha_asiento BETWEEN :fecha_inicio AND :fecha_fin THEN da.credito ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN ac.fecha_asiento BETWEEN :fecha_inicio AND :fecha_fin THEN da.debito ELSE 0 END), 0)
                ELSE 0 
            END AS saldo_final
        FROM
            cuentas_contables cc
        LEFT JOIN
            detalle_asientos da ON cc.id = da.cuenta_id
        LEFT JOIN
            asientos_contables ac ON da.asiento_id = ac.id
        WHERE
            cc.codigo_institucion = :codigo_institucion
            AND ac.estado = 'APROBADO'
            AND ac.fecha_asiento BETWEEN :fecha_inicio AND :fecha_fin -- Filtrar por fecha aquí también para el GROUP BY
        GROUP BY
            cc.codigo, cc.nombre, cc.naturaleza
        ORDER BY
            cc.codigo ASC;
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':fecha_inicio', $fecha_inicio, PDO::PARAM_STR);
    $stmt->bindParam(':fecha_fin', $fecha_fin, PDO::PARAM_STR);
    $stmt->bindParam(':codigo_institucion', $codigo_institucion_activa, PDO::PARAM_INT);
    $stmt->execute();
    $data_balance = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Manejo de errores de la base de datos
    error_log("Error al obtener datos del Balance de Comprobación: " . $e->getMessage());
    echo json_encode(['respuesta' => false, 'mensaje' => 'Error al cargar los datos del balance: ' . $e->getMessage()]);
    exit();
}

// Creando el Informe.
$pdf = new PDF('P', 'mm', 'Letter');
$pdf->setFechasReporte($fecha_inicio, $fecha_fin); // Pasar las fechas al objeto PDF

#Establecemos los márgenes izquierda, arriba y derecha: 
$pdf->SetMargins(10, 10, 10); // Ajuste de márgenes para más espacio en Letter
#Establecemos el margen inferior: 
$pdf->SetAutoPageBreak(true, 15); // Auto salto de página a 15mm del final
$pdf->AliasNbPages(); // Para mostrar N de M páginas
$pdf->AddPage();

// Títulos de las columnas para el Balance de Comprobación
$header_balance = array(
    utf8_decode('Código'), 
    utf8_decode('Cuenta'), 
    utf8_decode('Debe'), 
    utf8_decode('Haber'), 
    utf8_decode('Saldo Deudor'), 
    utf8_decode('Saldo Acreedor')
);

// Llamamos a la función FancyTable con los encabezados y los datos procesados
$pdf->FancyTable($header_balance, $data_balance);

// Salida del pdf.
$modo = 'I'; // Envia al navegador (I), Descarga el archivo (D), Guardar el fichero en un local(F).
$print_nombre = 'Balance_Comprobacion_' . date('Ymd_His') . '.pdf'; // Nombre más único
$pdf->Output($print_nombre, $modo);
?>