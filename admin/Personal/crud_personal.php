<?php
// admin/personal/crud_personal.php

session_name('FactuFacil');
session_start();

// Verifica que el usuario esté autenticado.
if (empty($_SESSION['userNombre'])) {
    header('Content-Type: application/json');
    echo json_encode(['respuesta' => false, 'mensaje' => 'Sesión no válida.']);
    exit();
}
// ruta de los archivos con su carpeta
    $path_root=trim($_SERVER['DOCUMENT_ROOT']);    
	include($path_root."/FactuFacil/includes/mainFunctions_.php");
$pdo = $dblink;
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

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
                ORDER BY 
                    p.apellidos, p.nombres
            ";
            $stmt = $pdo->query($sql);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['data' => $data]);
        } catch (PDOException $e) {
            echo json_encode(['data' => []]);
        }
        break;

    case 'obtenerPersonal':
        $id = $_POST['id_personal'];
        try {
            $stmt = $pdo->prepare("SELECT * FROM personal WHERE id_personal = ?");
            $stmt->execute([$id]);
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
            $catalogos['estado_civil'] = $pdo->query("SELECT codigo AS codigo, descripcion FROM catalogo_estado_civil")->fetchAll(PDO::FETCH_ASSOC);
            $catalogos['estatus'] = $pdo->query("SELECT codigo AS codigo, descripcion FROM catalogo_estatus")->fetchAll(PDO::FETCH_ASSOC);
            $catalogos['especialidad'] = $pdo->query("SELECT codigo AS codigo, descripcion FROM catalogo_especialidad")->fetchAll(PDO::FETCH_ASSOC);
            $catalogos['estudio'] = $pdo->query("SELECT codigo AS codigo, descripcion FROM catalogo_estudio")->fetchAll(PDO::FETCH_ASSOC);
            $catalogos['vivienda'] = $pdo->query("SELECT codigo_vivienda AS codigo, descripcion FROM catalogo_vivienda")->fetchAll(PDO::FETCH_ASSOC);
            $catalogos['tipo_licencia'] = $pdo->query("SELECT codigo_tipo_licencia AS codigo, descripcion FROM catalogo_tipo_licencia")->fetchAll(PDO::FETCH_ASSOC);
            $catalogos['afp'] = $pdo->query("SELECT codigo_afp AS codigo, descripcion FROM catalogo_afp")->fetchAll(PDO::FETCH_ASSOC);
            $catalogos['cargo'] = $pdo->query("SELECT codigo_cargo AS codigo, descripcion FROM catalogo_cargo")->fetchAll(PDO::FETCH_ASSOC);
            $catalogos['ruta'] = $pdo->query("SELECT codigo_ruta AS codigo, descripcion FROM catalogo_ruta")->fetchAll(PDO::FETCH_ASSOC);
            $catalogos['departamento'] = $pdo->query("SELECT codigo_departamento AS codigo, descripcion FROM catalogo_departamento")->fetchAll(PDO::FETCH_ASSOC);
            $catalogos['municipio'] = $pdo->query("SELECT codigo_municipio AS codigo, descripcion FROM catalogo_municipio")->fetchAll(PDO::FETCH_ASSOC);
            $catalogos['zona_residencia'] = $pdo->query("SELECT codigo_zona_residencia AS codigo, descripcion FROM catalogo_zona_residencia")->fetchAll(PDO::FETCH_ASSOC);

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
        // ... (resto de los campos del formulario) ...
        
        try {
            if (empty($id_personal)) {
                // Crear un nuevo registro
                $sql = "INSERT INTO personal (nombres, apellidos, dui, nit, isss, fecha_nacimiento, fecha_ingreso, salario, pago_diario, codigo_genero, codigo_estado_civil, telefono_celular, correo_electronico, direccion, codigo_estatus, codigo_cargo) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombres, $apellidos, $dui, $nit, $isss, $fecha_nacimiento, $fecha_ingreso, $salario, $pago_diario, $codigo_genero, $codigo_estado_civil, $telefono_celular, $correo_electronico, $direccion, $codigo_estatus, $codigo_cargo]);
            } else {
                // Actualizar un registro existente
                $sql = "UPDATE personal SET nombres = ?, apellidos = ?, dui = ?, nit = ?, isss = ?, fecha_nacimiento = ?, fecha_ingreso = ?, salario = ?, pago_diario = ?, codigo_genero = ?, codigo_estado_civil = ?, telefono_celular = ?, correo_electronico = ?, direccion = ?, codigo_estatus = ?, codigo_cargo = ? WHERE id_personal = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombres, $apellidos, $dui, $nit, $isss, $fecha_nacimiento, $fecha_ingreso, $salario, $pago_diario, $codigo_genero, $codigo_estado_civil, $telefono_celular, $correo_electronico, $direccion, $codigo_estatus, $codigo_cargo, $id_personal]);
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
        
    default:
        echo json_encode(['respuesta' => false, 'mensaje' => 'Acción no válida.']);
        break;
}
?>