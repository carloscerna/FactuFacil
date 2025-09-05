<?php
// admin/personal/crud_personal.php

session_name('FactuFacil');
session_start();

if (empty($_SESSION['userNombre'])) {
    header('Content-Type: application/json');
    echo json_encode(['respuesta' => false, 'mensaje' => 'Sesión no válida.']);
    exit();
}

// ruta de los archivos con su carpeta
$path_root = trim($_SERVER['DOCUMENT_ROOT']);
include($path_root."/FactuFacil/includes/mainFunctions_.php");
$pdo = $dblink;
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// Recortar espacios en blanco para todos los datos del formulario (paso de seguridad)
foreach ($_POST as $key => $value) {
    if (is_string($value)) {
        $_POST[$key] = trim($value);
    }
}
// Obtener el código de la institución de la sesión
$codigo_institucion_sesion = $_SESSION['codigo_institucion'];

switch ($accion) {
    case 'listarPersonal':
        try {
            $sql = "
                SELECT 
                    p.id_personal, 
                    p.nombres, 
                    p.apellidos, 
                    p.dui, 
                    p.telefono_celular,
                    p.correo_electronico,
                    c_cargo.descripcion AS cargo, 
                    c_estatus.descripcion AS estatus
                FROM 
                    personal p
                LEFT JOIN 
                    catalogo_cargo c_cargo ON p.codigo_cargo = c_cargo.codigo
                LEFT JOIN 
                    catalogo_estatus c_estatus ON p.codigo_estatus = c_estatus.codigo
                WHERE
                    p.codigo_institucion = :codigo_institucion_sesion
                ORDER BY 
                    p.apellidos, p.nombres
            ";
            $stmt = $pdo->query($sql);
            $stmt->bindParam(':codigo_institucion_sesion', $codigo_institucion_sesion, PDO::PARAM_STR);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['data' => $data]);
        } catch (PDOException $e) {
            echo json_encode(['data' => []]);
        }
        break;

    case 'obtenerPersonal':
        $id = $_POST['id_personal'];
        try {
            // Se agrega la condición WHERE para asegurar que solo se obtienen datos de la institución actual
            $stmt = $pdo->prepare("SELECT * FROM personal WHERE id_personal = ? AND codigo_institucion = ?");
            $stmt->execute([$id, $codigo_institucion_sesion]);
            $personal = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['respuesta' => true, 'personal' => $personal]);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al obtener datos.']);
        }
        break;
    
    case 'obtenerCatalogos':
        try {
            $catalogos = [];
            $catalogos['genero'] = $pdo->query("SELECT codigo AS codigo, descripcion FROM catalogo_genero")->fetchAll(PDO::FETCH_ASSOC);
            $catalogos['estadocivil'] = $pdo->query("SELECT codigo AS codigo, descripcion FROM catalogo_estado_civil")->fetchAll(PDO::FETCH_ASSOC);
            $catalogos['estatus'] = $pdo->query("SELECT codigo AS codigo, descripcion FROM catalogo_estatus")->fetchAll(PDO::FETCH_ASSOC);
            $catalogos['especialidad'] = $pdo->query("SELECT codigo AS codigo, descripcion FROM catalogo_especialidad")->fetchAll(PDO::FETCH_ASSOC);
            $catalogos['estudio'] = $pdo->query("SELECT codigo AS codigo, descripcion FROM catalogo_estudios")->fetchAll(PDO::FETCH_ASSOC);
            $catalogos['vivienda'] = $pdo->query("SELECT codigo AS codigo, descripcion FROM catalogo_vivienda")->fetchAll(PDO::FETCH_ASSOC);
            $catalogos['tipolicencia'] = $pdo->query("SELECT codigo AS codigo, descripcion FROM catalogo_tipo_licencia")->fetchAll(PDO::FETCH_ASSOC);
            $catalogos['afp'] = $pdo->query("SELECT codigo AS codigo, descripcion FROM catalogo_afp")->fetchAll(PDO::FETCH_ASSOC);
            $catalogos['cargo'] = $pdo->query("SELECT codigo AS codigo, descripcion FROM catalogo_cargo")->fetchAll(PDO::FETCH_ASSOC);
            $catalogos['departamento'] = $pdo->query("SELECT codigo AS codigo, descripcion FROM catalogo_departamentos")->fetchAll(PDO::FETCH_ASSOC);
            $catalogos['municipio'] = $pdo->query("SELECT codigo AS codigo, descripcion FROM catalogo_municipios")->fetchAll(PDO::FETCH_ASSOC);
            $catalogos['zonaresidencia'] = $pdo->query("SELECT codigo AS codigo, descripcion FROM catalogo_zona_residencia")->fetchAll(PDO::FETCH_ASSOC);
            
            // Reviso las tablas, algunos select tienen nombres diferentes
            $catalogos['distrito'] = $pdo->query("SELECT codigo AS codigo, descripcion FROM catalogo_distritos")->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['respuesta' => true, 'catalogos' => $catalogos]);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al obtener catálogos.']);
        }
        break;
        
    case 'crearActualizar':
        // Lógica para crear o actualizar un registro de personal
        $id_personal = $_POST['id_personal'];
        $nombres = $_POST['nombres'];
        $apellidos = $_POST['apellidos'];
        $dui = $_POST['dui'];
        $nit = $_POST['nit'];
        $isss = $_POST['isss'];
        $fecha_nacimiento = $_POST['fecha_nacimiento'];
        $fecha_ingreso = $_POST['fecha_ingreso'];
        $salario = $_POST['salario'];
        $pago_diario = $_POST['pago_diario'];
        $codigo_genero = $_POST['codigo_genero'];
        $codigo_estado_civil = $_POST['codigo_estado_civil'];
        $telefono_celular = $_POST['telefono_celular'];
        $correo_electronico = $_POST['correo_electronico'];
        $direccion = $_POST['direccion'];
        $codigo_estatus = $_POST['codigo_estatus'];
        $codigo_cargo = $_POST['codigo_cargo'];
        $codigo_departamento = $_POST['codigo_departamento'];
        $codigo_municipio = $_POST['codigo_municipio'];
        $codigo_distrito = $_POST['codigo_distrito'];
        $codigo_institucion = $_POST['codigo_institucion']; // Asignar el código de la institución de la sesión
        // ... (resto de los campos del formulario) ...
        
        try {
            if (empty($id_personal)) {
                // Crear un nuevo registro
                $sql = "INSERT INTO personal (nombres, apellidos, dui, nit, isss, fecha_nacimiento, fecha_ingreso, salario, pago_diario, codigo_genero, codigo_estado_civil, telefono_celular, correo_electronico, direccion, codigo_estatus, codigo_cargo, codigo_departamento, codigo_municipio, codigo_distrito, codigo_institucion) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombres, $apellidos, $dui, $nit, $isss, $fecha_nacimiento, $fecha_ingreso, $salario, $pago_diario, $codigo_genero, $codigo_estado_civil, $telefono_celular, $correo_electronico, $direccion, $codigo_estatus, $codigo_cargo, $codigo_departamento, $codigo_municipio, $codigo_distrito, $codigo_institucion]);
            } else {
                // Actualizar un registro existente
                $sql = "UPDATE personal SET nombres = ?, apellidos = ?, dui = ?, nit = ?, isss = ?, fecha_nacimiento = ?, fecha_ingreso = ?, salario = ?, pago_diario = ?, codigo_genero = ?, codigo_estado_civil = ?, telefono_celular = ?, correo_electronico = ?, direccion = ?, codigo_estatus = ?, codigo_cargo = ?, codigo_departamento = ?, codigo_municipio = ?, codigo_distrito = ?, codigo_institucion = ? WHERE id_personal = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombres, $apellidos, $dui, $nit, $isss, $fecha_nacimiento, $fecha_ingreso, $salario, $pago_diario, $codigo_genero, $codigo_estado_civil, $telefono_celular, $correo_electronico, $direccion, $codigo_estatus, $codigo_cargo, $codigo_departamento, $codigo_municipio, $codigo_distrito, $codigo_institucion, $id_personal]);
            }
            echo json_encode(['respuesta' => true, 'mensaje' => 'Registro de personal guardado exitosamente.']);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al guardar el registro: ' . $e->getMessage()]);
        }
        break;

    case 'eliminar':
        $id_personal = $_POST['id_personal'];
        try {
            $stmt = $pdo->prepare("DELETE FROM personal WHERE id_personal = ?");
            $stmt->execute([$id_personal]);
            echo json_encode(['respuesta' => true, 'mensaje' => 'Registro de personal eliminado exitosamente.']);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al eliminar el registro.']);
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
 case 'obtenerInstituciones':
        try {
            $sql = "SELECT codigo_institucion, nombre_institucion FROM instituciones ORDER BY nombre_institucion";
            $stmt = $pdo->query($sql);
            $instituciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['respuesta' => true, 'instituciones' => $instituciones]);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al obtener instituciones.']);
        }
        break;
    default:
        echo json_encode(['respuesta' => false, 'mensaje' => 'Acción no válida.']);
        break;
}
?>