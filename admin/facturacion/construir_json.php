<?php
// admin/facturacion/construir_json.php
session_name('FactuFacil');
session_start();
header('Content-Type: application/json');

include("../../includes/mainFunctions_.php");
$pdo = $dblink;

$id_venta = $_POST['id_venta'] ?? '3';
$codigo_institucion = $_SESSION['codigo_institucion'];

if(empty($id_venta)) {
    echo json_encode(['respuesta' => false, 'mensaje' => 'Falta ID Venta']);
    exit;
}

try {
    // 1. OBTENER DATOS DE LA VENTA (CABECERA)
    $sqlCab = "SELECT v.*, 
                c.nit as cliente_nit, 
                c.numero_registro as cliente_nrc, -- <--- CORREGIDO (Antes c.nrc)
                -- Lógica para el Nombre (Empresa o Persona)
                CASE 
                    WHEN c.nombre_empresa IS NOT NULL AND TRIM(c.nombre_empresa) <> '' THEN c.nombre_empresa
                    ELSE CONCAT(c.nombres, ' ', c.apellidos)
                END as nombre_completo,
                c.direccion as cliente_direccion,
                c.correo_electronico, 
                c.telefono_celular as telefono, -- <--- CORREGIDO (Usamos celular si es el principal)
                TO_CHAR(v.fecha_emision, 'YYYY-MM-DD') as fecha_dte,
                TO_CHAR(v.fecha_creacion, 'HH24:MI:SS') as hora_dte
               FROM ventas_cabecera v
               JOIN clientes c ON v.id_cliente = c.id_clientes
               WHERE v.id_venta = ? AND v.codigo_institucion = ?";
    $stmtCab = $pdo->prepare($sqlCab);
    $stmtCab->execute([$id_venta, $codigo_institucion]);
    $venta = $stmtCab->fetch(PDO::FETCH_ASSOC);

    if(!$venta) throw new Exception("Venta no encontrada.");
    // 1.5 OBTENER DATOS DEL EMISOR (TU EMPRESA)
        $sqlEmisor = "SELECT * FROM configuracion_facturacion WHERE codigo_institucion = ?";
        $stmtEmisor = $pdo->prepare($sqlEmisor);
        $stmtEmisor->execute([$codigo_institucion]);
        $emisorDB = $stmtEmisor->fetch(PDO::FETCH_ASSOC);

        if(!$emisorDB) throw new Exception("No hay configuración de facturación para esta empresa.");


    // 2. GENERAR CÓDIGOS DTE (Si no existen)
    if(empty($venta['codigo_generacion'])) {
        // Generar UUID V4
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        // Generar Numero Control (Ejemplo DTE-01-E0001-00000000001)
        // OJO: Esto debe llevar un correlativo real. Por ahora usamos el ID venta.
        $num_control = 'DTE-' . $venta['tipo_documento'] . '-E0001-' . str_pad($id_venta, 15, "0", STR_PAD_LEFT);
        
        // Guardar en BD
        $pdo->prepare("UPDATE ventas_cabecera SET codigo_generacion = ?, numero_control = ? WHERE id_venta = ?")
            ->execute([$uuid, $num_control, $id_venta]);
            
        $venta['codigo_generacion'] = $uuid;
        $venta['numero_control'] = $num_control;
    }

    // 3. OBTENER DETALLE
    $sqlDet = "SELECT * FROM ventas_detalle WHERE id_venta = ?";
    $stmtDet = $pdo->prepare($sqlDet);
    $stmtDet->execute([$id_venta]);
    $detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

    // ============================================================
    // CONSTRUCCIÓN DEL JSON (ESTRUCTURA MINISTERIO HACIENDA SV)
    // ============================================================

    $cuerpoDocumento = [];
    $numItem = 1;
    
    foreach($detalles as $d) {
        $cuerpoDocumento[] = [
            "numItem" => $numItem++,
            "tipoItem" => 1, // 1=Bien, 2=Servicio
            "numeroDocumento" => null,
            "cantidad" => floatval($d['cantidad']),
            "codigo" => $d['codigo_producto'],
            "codTributo" => null, 
            "uniMedida" => 59, // 59=Unidad (Debería venir del producto)
            "descripcion" => $d['descripcion'],
            "precioUni" => floatval($d['precio_unitario']),
            "montoDescu" => 0,
            "ventaNoSuj" => 0,
            "ventaExenta" => 0,
            "ventaGravada" => floatval($d['subtotal']),
            "tributos" => ["20"], // 20 = IVA 13%
            "psv" => 0,
            "noGravado" => 0
        ];
    }

    $jsonDTE = [
        "identificacion" => [
            "version" => 3, // Versión actual del esquema
            "ambiente" => "00", // 00=Pruebas, 01=Producción
            "tipoDte" => $venta['tipo_documento'], // 01=Factura, 03=CCF
            "numeroControl" => $venta['numero_control'],
            "codigoGeneracion" => strtoupper($venta['codigo_generacion']),
            "tipoModelo" => 1, // 1=Previo, 2=Diferido
            "tipoOperacion" => 1, // 1=Normal
            "fecEmi" => $venta['fecha_dte'],
            "horEmi" => $venta['hora_dte'],
            "tipoMoneda" => "USD",
            "tipoContingencia" => null,
            "motivoContin" => null
        ],
        "documentoRelacionado" => null,
        "emisor" => [
            "nit" => $emisorDB['nit'],
            "nrc" => $emisorDB['nrc'],
            "nombre" => $emisorDB['nombre_comercial'], // O razon_social según prefieras
            "codActividad" => $emisorDB['codigo_actividad'],
            "descActividad" => $emisorDB['descripcion_actividad'],
            "direccion" => [
                "departamento" => $emisorDB['direccion_departamento'],
                "municipio" => $emisorDB['direccion_municipio'],
                "complemento" => $emisorDB['direccion_complemento']
            ],
            "telefono" => $emisorDB['telefono'] ?? '2222-0000',
            "correo" => $emisorDB['correo']
        ],
        "receptor" => [
            "nit" => $venta['cliente_nit'] ?: "00000000000000", // NIT Genérico si no tiene
            "nrc" => $venta['cliente_nrc'],
            "nombre" => $venta['nombre_completo'],
            "codActividad" => null, 
            "descActividad" => null,
            "direccion" => [
                "departamento" => "02", // Deberías tener esto en tu tabla clientes
                "municipio" => "10",
                "complemento" => $venta['cliente_direccion']
            ],
            "telefono" => $venta['telefono'],
            "correo" => $venta['correo_electronico']
        ],
        "otrosDocumentos" => null,
        "ventaTercero" => null,
        "cuerpoDocumento" => $cuerpoDocumento,
        "resumen" => [
            "totalNoSuj" => 0,
            "totalExenta" => 0,
            "totalGravada" => floatval($venta['total_gravado']),
            "subTotalVentas" => floatval($venta['total_gravado']),
            "descuNoSuj" => 0,
            "descuExenta" => 0,
            "descuGravada" => 0,
            "porcentajeDescuento" => 0,
            "totalDescu" => 0,
            "tributos" => [
                [
                    "codigo" => "20",
                    "descripcion" => "Impuesto al Valor Agregado 13%",
                    "valor" => floatval($venta['total_iva'])
                ]
            ],
            // CORRECCIÓN AQUÍ: Usamos 'total_venta' en lugar de 'total_pagar'
            "subTotal" => floatval($venta['total_venta']),
            "ivaPerci1" => 0,
            "ivaGani" => 0,
            "totalLetras" => "SON: " . number_format($venta['total_venta'], 2), // Formato simple por ahora
            "totalPagar" => floatval($venta['total_venta']),
            "totalIva" => floatval($venta['total_iva']),
            "saldoFavor" => 0,
            "condicionOperacion" => ($venta['condicion_pago'] == 'CONTADO') ? 1 : 2,
            "pagos" => [
                [
                    "codigo" => "01", // Billetes
                    "montoPago" => floatval($venta['total_venta']), // CORREGIDO AQUÍ TAMBIÉN
                    "referencia" => null,
                    "plazo" => null,
                    "periodo" => null
                ]
            ]
        ],
        "extension" => null,
        "apendice" => null
    ];

    echo json_encode(['respuesta' => true, 'json_dte' => $jsonDTE], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['respuesta' => false, 'mensaje' => $e->getMessage()]);
}
?>