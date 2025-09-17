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

// La función ahora acepta el código de la institución
function generarCodigoProveedor($pdo, $codigo_institucion_sesion) {
    try {
        $prefijo_tipo = 'PROV';
        $año_actual = date('y');

        $pdo->beginTransaction();
        
        // Se crea un código_tipo único para la institución
        $codigo_tipo_completo = $prefijo_tipo . '_' . $codigo_institucion_sesion;
        
        $sql = "SELECT ultimo_numero FROM correlativos WHERE codigo_tipo = ? FOR UPDATE";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$codigo_tipo_completo]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $ultimo_numero = $result ? $result['ultimo_numero'] : 0;
        $nuevo_numero = $ultimo_numero + 1;

        if ($result) {
            $sql_update = "UPDATE correlativos SET ultimo_numero = ? WHERE codigo_tipo = ?";
        } else {
            $sql_update = "INSERT INTO correlativos (ultimo_numero, codigo_tipo) VALUES (?, ?)";
        }
        
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([$nuevo_numero, $codigo_tipo_completo]);

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
            $nombre_empresa = $_POST['nombre_empresa'] ?? '';
            $giro = $_POST['codigo_giro'] ?? '';
            $direccion = $_POST['direccion'] ?? '';
            $nit_con_guiones = $_POST['nit'] ?? '';            
            $nit = str_replace("-", "", $nit_con_guiones);

            $nrc = $_POST['numero_registro'] ?? '';
            $telefono = $_POST['telefono'] ?? '';
            $correo_electronico = $_POST['correo_electronico'] ?? '';
            $nombres = $_POST['nombres'] ?? '';
            $apellidos = $_POST['apellidos'] ?? '';
            $dui = $_POST['dui'] ?? '';
            $telefono_celular = $_POST['telefono_celular'] ?? '';
            $telefono_residencia = $_POST['telefono_residencia'] ?? '';
            $codigo_estatus = $_POST['codigo_estatus'] ?? '';
            $codigo_departamento = $_POST['codigo_departamento'] ?? '';
            $codigo_municipio = $_POST['codigo_municipio'] ?? '';
            $codigo_distrito = $_POST['codigo_distrito'] ?? '';
            $codigo_pais = $_POST['codigo_pais'] ?? '';


    
            try {
                if (empty($id_proveedores)) {
                    // Se llama a la función con el parámetro de la sesión
                    $codigo = generarCodigoProveedor($pdo, $codigo_institucion_sesion);
                    if (!$codigo) {
                        throw new Exception("No se pudo generar el código del proveedor.");
                    }
                    
                    $sql = "INSERT INTO proveedores (codigo, codigo_institucion, nombre_empresa, giro, direccion, nit, nrc, correo_electronico, codigo_giro, nombres, apellidos, dui, telefono_celular, telefono_residencia, codigo_estatus, codigo_departamento, 
                                                        codigo_municipio, codigo_distrito, codigo_pais) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$codigo, $codigo_institucion_sesion, $nombre_empresa, $giro, $direccion, $nit, $nrc, $correo_electronico, $giro, $nombres, $apellidos, $dui, $telefono_celular, $telefono_residencia, $codigo_estatus, $codigo_departamento, 
                                                $codigo_municipio, $codigo_distrito, $codigo_pais]);
                    echo json_encode(['respuesta' => true, 'mensaje' => 'Proveedor creado exitosamente.']);
                } else {
                    $sql = "UPDATE proveedores SET nombre_empresa = ?, giro = ?, direccion = ?, nit = ?, nrc = ?, correo_electronico = ?, codigo_giro = ?, nombres = ?, apellidos = ?, dui = ?, telefono_celular = ?, telefono_residencia = ?, codigo_estatus = ?, codigo_departamento = ?, codigo_municipio = ?, codigo_distrito = ?, codigo_pais = ?
                     WHERE id_proveedores = ? AND codigo_institucion = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nombre_empresa, $giro, $direccion, $nit, $nrc, $correo_electronico, $giro, $nombres, $apellidos, $dui, $telefono_celular, $telefono_residencia, $codigo_estatus, $codigo_departamento, $codigo_municipio, $codigo_distrito, $codigo_pais, $id_proveedores, $codigo_institucion_sesion]);
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