<?php
// admin/proveedores/crud_proveedores.php

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

// --- Function to generate supplier code ---
function generarCodigoProveedor($pdo) {
    try {
        $prefijo_tipo = 'PROV';
        $año_actual = date('y');

        $pdo->beginTransaction();
        $sql = "SELECT ultimo_numero FROM correlativos WHERE codigo_tipo = ? FOR UPDATE";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$prefijo_tipo]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $ultimo_numero = $result ? $result['ultimo_numero'] : 0;
        $nuevo_numero = $ultimo_numero + 1;

        if ($result) {
            $sql_update = "UPDATE correlativos SET ultimo_numero = ? WHERE codigo_tipo = ?";
        } else {
            $sql_update = "INSERT INTO correlativos (ultimo_numero, codigo_tipo) VALUES (?, ?)";
        }
        
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([$nuevo_numero, $prefijo_tipo]);

        $correlativo_formateado = str_pad($nuevo_numero, 6, '0', STR_PAD_LEFT);
        $nuevo_codigo = $correlativo_formateado . $año_actual;

        $pdo->commit();
        return $nuevo_codigo;

    } catch (PDOException $e) {
        $pdo->rollBack();
        return null;
    }
}

switch ($accion) {
    case 'listarProveedores':
        try {
            if ($codigo_perfil_sesion === '99') {
                $sql = "SELECT id_proveedores, codigo, nombres, apellidos, telefono_celular, nombre_empresa FROM proveedores ORDER BY nombres, apellidos";
                $stmt = $pdo->query($sql);
            } else {
                $sql = "SELECT id_proveedores, codigo, nombres, apellidos, telefono_celular, nombre_empresa FROM proveedores WHERE codigo_institucion = :codigo_institucion_sesion ORDER BY nombres, apellidos";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':codigo_institucion_sesion', $codigo_institucion_sesion, PDO::PARAM_STR);
                $stmt->execute();
            }
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['data' => $data]);
        } catch (PDOException $e) {
            echo json_encode(['data' => []]);
        }
        break;

    case 'obtenerProveedor':
        $id = $_POST['id_proveedores'];
        try {
            if ($codigo_perfil_sesion === '99') {
                $stmt = $pdo->prepare("SELECT * FROM proveedores WHERE id_proveedores = ?");
                $stmt->execute([$id]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM proveedores WHERE id_proveedores = ? AND codigo_institucion = ?");
                $stmt->execute([$id, $codigo_institucion_sesion]);
            }
            $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['respuesta' => true, 'proveedor' => $proveedor]);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al obtener datos.']);
        }
        break;

    case 'crearActualizar':
        $id_proveedores = $_POST['id_proveedores'] ?? '';
        $codigo = $_POST['codigo'] ?? '';
        $nombres = $_POST['nombres'] ?? '';
        $apellidos = $_POST['apellidos'] ?? '';
        $nombre_empresa = $_POST['nombre_empresa'] ?? '';
        $codigo_pais = $_POST['codigo_pais'] ?? '';
        $codigo_giro = $_POST['codigo_giro'] ?? '';
        $direccion = $_POST['direccion'] ?? '';
        $codigo_departamento = $_POST['codigo_departamento'] ?? '';
        $codigo_municipio = $_POST['codigo_municipio'] ?? '';
        $codigo_distrito = $_POST['codigo_distrito'] ?? '';
        $dui = $_POST['dui'] ?? '';
        $nit = $_POST['nit'] ?? '';
        $numero_registro = $_POST['numero_registro'] ?? '';
        $telefono_residencia = $_POST['telefono_residencia'] ?? '';
        $telefono_celular = $_POST['telefono_celular'] ?? '';
        $codigo_estatus = $_POST['codigo_estatus'] ?? '';
        $correo_electronico = $_POST['correo_electronico'] ?? '';

        try {
            if (empty($id_proveedores)) {
                $codigo_generado = generarCodigoProveedor($pdo);
                if (!$codigo_generado) {
                    throw new Exception("No se pudo generar el código del proveedor.");
                }
                $sql = "INSERT INTO proveedores (codigo, codigo_institucion, nombres, apellidos, nombre_empresa, direccion, codigo_departamento, codigo_municipio, codigo_distrito, dui, nit, numero_registro, telefono_residencia, telefono_celular, fecha_creacion, codigo_estatus, codigo_pais, codigo_giro, correo_electronico) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$codigo_generado, $codigo_institucion_sesion, $nombres, $apellidos, $nombre_empresa, $direccion, $codigo_departamento, $codigo_municipio, $codigo_distrito, $dui, $nit, $numero_registro, $telefono_residencia, $telefono_celular, $codigo_estatus, $codigo_pais, $codigo_giro, $correo_electronico]);
                echo json_encode(['respuesta' => true, 'mensaje' => 'Proveedor creado exitosamente.', 'nuevo_codigo' => $codigo_generado]);
            } else {
                $sql = "UPDATE proveedores SET codigo = ?, nombres = ?, apellidos = ?, nombre_empresa = ?,  direccion = ?, codigo_departamento = ?, codigo_municipio = ?, codigo_distrito = ?, dui = ?, nit = ?, numero_registro = ?, telefono_residencia = ?, telefono_celular = ?, codigo_estatus = ?, codigo_pais = ?, codigo_giro = ?, correo_electronico = ? WHERE id_proveedores = ? AND codigo_institucion = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$codigo, $nombres, $apellidos, $nombre_empresa, $direccion, $codigo_departamento, $codigo_municipio, $codigo_distrito, $dui, $nit, $numero_registro, $telefono_residencia, $telefono_celular, $codigo_estatus, $codigo_pais, $codigo_giro, $correo_electronico, $id_proveedores, $codigo_institucion_sesion]);
                echo json_encode(['respuesta' => true, 'mensaje' => 'Proveedor guardado exitosamente.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al guardar: ' . $e->getMessage()]);
        }
        break;
        
    case 'eliminar':
        $id_proveedores = $_POST['id_proveedores'];
        try {
            if ($codigo_perfil_sesion === '99') {
                $stmt = $pdo->prepare("DELETE FROM proveedores WHERE id_proveedores = ?");
                $stmt->execute([$id_proveedores]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM proveedores WHERE id_proveedores = ? AND codigo_institucion = ?");
                $stmt->execute([$id_proveedores, $codigo_institucion_sesion]);
            }
            echo json_encode(['respuesta' => true, 'mensaje' => 'Proveedor eliminado.']);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al eliminar.']);
        }
        break;

    case 'obtenerCatalogos':
        try {
            $catalogos = [];
            $catalogos['departamentos'] = $pdo->query("SELECT codigo, descripcion FROM catalogo_departamentos ORDER BY descripcion")->fetchAll(PDO::FETCH_ASSOC);
            $catalogos['estatus'] = $pdo->query("SELECT codigo, descripcion FROM catalogo_estatus ORDER BY descripcion")->fetchAll(PDO::FETCH_ASSOC);
            $catalogos['paises'] = $pdo->query("SELECT codigo, descripcion FROM cat_020 ORDER BY descripcion")->fetchAll(PDO::FETCH_ASSOC);
            $catalogos['giros'] = $pdo->query("SELECT codigo, descripcion FROM cat_019 ORDER BY descripcion")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['respuesta' => true, 'catalogos' => $catalogos]);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al obtener catálogos.']);
        }
        break;
    
    case 'obtenerMunicipios':
        $codigo_departamento = $_POST['codigo_departamento'] ?? '';
        try {
            $sql = "SELECT codigo, descripcion FROM catalogo_municipios WHERE codigo_departamento = ? ORDER BY descripcion";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$codigo_departamento]);
            $municipios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['respuesta' => true, 'municipios' => $municipios]);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al obtener municipios.']);
        }
        break;

    case 'obtenerDistritos':
        $codigo_municipio = $_POST['codigo_municipio'] ?? '';
        $codigo_departamento = $_POST['codigo_departamento'] ?? '';
        try {
            $sql = "SELECT TRIM(codigo) AS codigo, descripcion FROM catalogo_distritos WHERE codigo_municipio = ? AND codigo_departamento = ? ORDER BY descripcion";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$codigo_municipio, $codigo_departamento]);
            $distritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['respuesta' => true, 'distritos' => $distritos]);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al obtener distritos.']);
        }
        break;
        
    default:
        echo json_encode(['respuesta' => false, 'mensaje' => 'Acción no válida.']);
        break;
}
?>