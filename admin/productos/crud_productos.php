<?php
// admin/productos/crud_productos.php

session_name('FactuFacil');
session_start();

if (empty($_SESSION['userNombre'])) {
    header('Content-Type: application/json');
    echo json_encode(['respuesta' => false, 'mensaje' => 'Sesión no válida.']);
    exit();
}

$path_root = trim($_SERVER['DOCUMENT_ROOT']);
include($path_root."/FactuFacil/includes/mainFunctions_.php");
$pdo = $dblink;
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

foreach ($_POST as $key => $value) {
    if (is_string($value)) {
        $_POST[$key] = trim($value);
    }
}

$codigo_perfil_sesion = $_SESSION['codigo_perfil'] ?? '';
$codigo_institucion_sesion = $_SESSION['codigo_institucion'] ?? '';

function generarCodigoProducto($pdo, $codigo_categoria, $codigo_institucion_sesion) {
    try {
        $pdo->beginTransaction();
        $codigo_tipo = 'PRODUCTO_' . $codigo_institucion_sesion;

        $sql = "SELECT ultimo_numero FROM correlativos WHERE codigo_tipo = ? FOR UPDATE";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$codigo_tipo]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $ultimo_numero = $result ? $result['ultimo_numero'] : 0;
        $nuevo_numero = $ultimo_numero + 1;

        if ($result) {
            $sql_update = "UPDATE correlativos SET ultimo_numero = ? WHERE codigo_tipo = ?";
        } else {
            $sql_update = "INSERT INTO correlativos (ultimo_numero, codigo_tipo) VALUES (?, ?)";
        }
        
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([$nuevo_numero, $codigo_tipo]);

        $correlativo_formateado = str_pad($nuevo_numero, 6, '0', STR_PAD_LEFT);
        $nuevo_codigo = $codigo_categoria . $correlativo_formateado;

        $pdo->commit();
        return $nuevo_codigo;

    } catch (PDOException $e) {
        $pdo->rollBack();
        return null;
    }
}

switch ($accion) {
    case 'listarProductos':
        try {
            $sql = "SELECT p.id_productos, p.codigo_interno, p.descripcion, p.precio_unitario, p.stock_actual, c.descripcion AS categoria_descripcion
                    FROM catalogo_productos p
                    LEFT JOIN catalogo_categoria c ON p.codigo_categoria = c.codigo AND p.codigo_institucion = c.codigo_institucion
                    WHERE p.codigo_institucion = :codigo_institucion_sesion
                    ORDER BY p.descripcion";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':codigo_institucion_sesion', $codigo_institucion_sesion, PDO::PARAM_STR);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['data' => $data]);
        } catch (PDOException $e) {
            echo json_encode(['data' => []]);
        }
        break;

    case 'obtenerProducto':
        $id = $_POST['id_productos'];
        try {
            $sql = "SELECT * FROM catalogo_productos WHERE id_productos = ? AND codigo_institucion = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id, $codigo_institucion_sesion]);
            $producto = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['respuesta' => true, 'producto' => $producto]);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al obtener datos.']);
        }
        break;
        
     case 'crearActualizar':
        $id_productos = $_POST['id_productos'] ?? '';
        $codigo_categoria = $_POST['codigo_categoria'] ?? '';
        $descripcion = $_POST['descripcion'] ?? '';
        $precio_costo = $_POST['precio_costo'] ?? 0;
        $stock_actual = $_POST['stock_actual'] ?? 0;
        $stock_minimo = $_POST['stock_minimo'] ?? 1;
        $unidad_medida = $_POST['unidad_medida'] ?? '';
        $tipo_item = $_POST['tipo_item'] ?? '';
        $impuesto_aplicable = $_POST['impuesto_aplicable'] ?? '';
        $codigo_barra = $_POST['codigo_barra'] ?? '';
        $comentario = $_POST['comentario'] ?? '';
        $codigo_fiscal = $_POST['codigo_fiscal'] ?? '';
        
        $porcentaje_impuesto = $_POST['porcentaje_impuesto'] ?? 0; // Se usará solo si el tipo es PORCENTAJE
        $monto_impuesto = $_POST['monto_impuesto'] ?? 0; // Nuevo campo para el monto fijo
        $tipo_impuesto = $_POST['tipo_impuesto'] ?? ''; // Nuevo campo para el tipo de impuesto
        $porcentaje_ganancia = $_POST['porcentaje_ganancia'] ?? 0;
        
        $precio_con_impuesto = 0;
        
        // Determinar el cálculo del impuesto
        if ($tipo_impuesto === 'PORCENTAJE') {
            $factor_impuesto = 1 + ($porcentaje_impuesto / 100);
            $precio_con_impuesto = $precio_costo * $factor_impuesto;
        } elseif ($tipo_impuesto === 'MONETARIO') {
            $precio_con_impuesto = $precio_costo + $monto_impuesto;
        } else {
            $precio_con_impuesto = $precio_costo; // Por si no hay impuesto
        }
        
        // Calcular el precio final con la ganancia
        $factor_ganancia = 1 + ($porcentaje_ganancia / 100);
        $precio_unitario_calculado = $precio_con_impuesto * $factor_ganancia;
        
        try {
            if (empty($id_productos)) {
                $codigo_generado = generarCodigoProducto($pdo, $codigo_categoria, $codigo_institucion_sesion);
                if (!$codigo_generado) {
                    throw new Exception("No se pudo generar el código del producto.");
                }
                $sql = "INSERT INTO catalogo_productos (codigo_interno, codigo_institucion, codigo_categoria, codigo_fiscal, descripcion, unidad_medida, tipo_item, impuesto_aplicable, stock_actual, stock_minimo, precio_costo, precio_unitario, codigo_barra, comentario) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$codigo_generado, $codigo_institucion_sesion, $codigo_categoria, $codigo_fiscal, $descripcion, $unidad_medida, $tipo_item, $impuesto_aplicable, $stock_actual, $stock_minimo, $precio_costo, $precio_unitario_calculado, $codigo_barra, $comentario]);
                echo json_encode(['respuesta' => true, 'mensaje' => 'Producto creado exitosamente.', 'nuevo_codigo' => $codigo_generado]);
            } else {
                $codigo_interno = $_POST['codigo_interno'];
                $sql = "UPDATE catalogo_productos SET codigo_categoria = ?, codigo_fiscal = ?, descripcion = ?, unidad_medida = ?, tipo_item = ?, impuesto_aplicable = ?, stock_actual = ?, stock_minimo = ?, precio_costo = ?, precio_unitario = ?, codigo_barra = ?, comentario = ? WHERE id_productos = ? AND codigo_institucion = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$codigo_categoria, $codigo_fiscal, $descripcion, $unidad_medida, $tipo_item, $impuesto_aplicable, $stock_actual, $stock_minimo, $precio_costo, $precio_unitario_calculado, $codigo_barra, $comentario, $id_productos, $codigo_institucion_sesion]);
                echo json_encode(['respuesta' => true, 'mensaje' => 'Producto guardado exitosamente.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al guardar: ' . $e->getMessage()]);
        } catch (Exception $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error: ' . $e->getMessage()]);
        }
        break;

    case 'eliminar':
        $id_productos = $_POST['id_productos'];
        try {
            $sql = "DELETE FROM catalogo_productos WHERE id_productos = ? AND codigo_institucion = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_productos, $codigo_institucion_sesion]);
            echo json_encode(['respuesta' => true, 'mensaje' => 'Producto eliminado.']);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al eliminar: ' . $e->getMessage()]);
        }
        break;

    case 'obtenerCatalogos':
        try {
            $catalogos = [];
            
            $sql_categorias = "SELECT codigo, descripcion FROM catalogo_categoria WHERE codigo_institucion = :codigo_institucion ORDER BY descripcion";
            $stmt_categorias = $pdo->prepare($sql_categorias);
            $stmt_categorias->bindParam(':codigo_institucion', $codigo_institucion_sesion);
            $stmt_categorias->execute();
            $catalogos['categorias'] = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);
            
            $sql_unidades = "SELECT codigo, descripcion FROM cat_014 ORDER BY descripcion";
            $catalogos['unidades_medida'] = $pdo->query($sql_unidades)->fetchAll(PDO::FETCH_ASSOC);

            $sql_tipos = "SELECT codigo, descripcion FROM cat_003 ORDER BY descripcion";
            $catalogos['tipos_item'] = $pdo->query($sql_tipos)->fetchAll(PDO::FETCH_ASSOC);

            // Fetch both percentage and monetary value, and the type from cat_015
            $sql_impuestos = "SELECT codigo, descripcion, porcentaje, monto_fijo, tipo_impuesto FROM cat_015 ORDER BY descripcion";
            $catalogos['impuestos'] = $pdo->query($sql_impuestos)->fetchAll(PDO::FETCH_ASSOC);
            
            $sql_ganancia = "SELECT codigo, descripcion, porcentaje FROM catalogo_ganancia WHERE codigo_institucion = :codigo_institucion ORDER BY descripcion";
            $stmt_ganancia = $pdo->prepare($sql_ganancia);
            $stmt_ganancia->bindParam(':codigo_institucion', $codigo_institucion_sesion);
            $stmt_ganancia->execute();
            $catalogos['ganancias'] = $stmt_ganancia->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['respuesta' => true, 'catalogos' => $catalogos]);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al obtener catálogos: ' . $e->getMessage()]);
        }
        break;

        case 'obtenerPorcentajeImpuesto':
            $codigo_impuesto = $_POST['codigo_impuesto'] ?? '';
            try {
                $sql = "SELECT porcentaje, monto_fijo, tipo_impuesto FROM cat_015 WHERE codigo = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$codigo_impuesto]);
                $impuesto = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($impuesto) {
                    echo json_encode(['respuesta' => true, 'impuesto' => $impuesto]);
                } else {
                    echo json_encode(['respuesta' => false, 'mensaje' => 'Impuesto no encontrado.']);
                }
            } catch (PDOException $e) {
                echo json_encode(['respuesta' => false, 'mensaje' => 'Error al obtener impuesto: ' . $e->getMessage()]);
            }
            break;

    default:
        echo json_encode(['respuesta' => false, 'mensaje' => 'Acción no válida.']);
        break;
}
?>