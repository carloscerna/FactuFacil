<?php
// admin/usuarios/Usuarios.php

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
$accion = $_POST['accion'] ?? '';

// Obtener los datos de sesión para permisos y filtro
$codigo_perfil_sesion = $_SESSION['codigo_perfil'] ?? '';
$codigo_institucion_sesion = $_SESSION['codigo_institucion'] ?? '';
$dbname_sesion = $_SESSION['dbname'] ?? '';

// Función para hashear la contraseña
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Recortar espacios en blanco
foreach ($_POST as $key => $value) {
    if (is_string($value)) {
        $_POST[$key] = trim($value);
    }
}

switch ($accion) {
    case 'ReadUsers':
        try {
            if ($codigo_perfil_sesion === '99') {
                $sql = "
                    SELECT u.id_usuario, u.nombre AS username,
                           btrim(p.nombres || ' ' || p.apellidos) AS nombre_personal,
                           cp.descripcion AS nombre_perfil,
                           i.nombre_institucion AS nombre_institucion_usuario,
                           u.estado
                    FROM usuarios u
                    LEFT JOIN personal p ON u.codigo_personal = p.id_personal
                    LEFT JOIN catalogo_perfil cp ON u.codigo_perfil = cp.codigo
                    LEFT JOIN instituciones i ON p.codigo_institucion = i.codigo_institucion
                    ORDER BY u.nombre
                ";
                $stmt = $pdo->query($sql);
            } else {
                $sql = "
                    SELECT u.id_usuario, u.nombre AS username, u.estado,
                           btrim(p.nombres || ' ' || p.apellidos) AS nombre_personal,
                           cp.descripcion AS nombre_perfil,
                           i.nombre_institucion AS nombre_institucion_usuario, u.estado
                    FROM usuarios u
                    INNER JOIN personal p ON u.codigo_personal = p.id_personal
                    INNER JOIN catalogo_perfil cp ON u.codigo_perfil = cp.codigo
                    INNER JOIN instituciones i ON p.codigo_institucion = i.codigo_institucion
                    WHERE p.codigo_institucion = :codigo_institucion_sesion
                    ORDER BY u.nombre
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':codigo_institucion_sesion', $codigo_institucion_sesion, PDO::PARAM_STR);
                $stmt->execute();
            }
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($users as &$user) {
                $user['estado'] = $user['estado'] ? 'Activo' : 'Inactivo';
                $user['acciones'] = '
                    <button class="btn btn-info btn-sm edit-user me-1 rounded-pill" data-id="' . htmlspecialchars($user['id_usuario']) . '" title="Editar"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-danger btn-sm delete-user rounded-pill" data-id="' . htmlspecialchars($user['id_usuario']) . '" title="Eliminar"><i class="fas fa-trash"></i></button>
                ';
            }
            echo json_encode(['respuesta' => true, 'contenido' => $users]);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al cargar usuarios: ' . $e->getMessage()]);
        }
        break;

    case 'GetUserById':
        $userId = $_POST['userId'];
        try {
            $sql = "
                SELECT u.id_usuario, trim(u.nombre) AS username, u.codigo_personal, u.codigo_perfil
                FROM usuarios u
                WHERE u.id_usuario = ?
            ";
            if ($codigo_perfil_sesion !== '99') {
                $sql .= " AND u.codigo_institucion = ?";
            }
            $stmt = $pdo->prepare($sql);
            if ($codigo_perfil_sesion !== '99') {
                $stmt->execute([$userId, $codigo_institucion_sesion]);
            } else {
                $stmt->execute([$userId]);
            }
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['respuesta' => true, 'contenido' => $user]);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => 'Error al obtener usuario: ' . $e->getMessage()]);
        }
        break;

    case 'CreateUser':
        $username = $_POST['username'];
        $password = hashPassword($_POST['password']);
        $personalId = $_POST['personalId'];
        $profileCode = $_POST['profileCode'];
        $estado = true; // Por defecto, un nuevo usuario está activo
        
        try {
            $pdo->beginTransaction();
            $checkQuery = "SELECT COUNT(*) FROM usuarios WHERE nombre = ?";
            $stmt = $pdo->prepare($checkQuery);
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("El nombre de usuario ya existe. Por favor, elija otro.");
            }

            // Obtener el codigo_institucion desde el registro de personal
            $queryPersonal = "SELECT codigo_institucion FROM personal WHERE id_personal = ?";
            $stmtPersonal = $pdo->prepare($queryPersonal);
            $stmtPersonal->execute([$personalId]);
            $personal = $stmtPersonal->fetch(PDO::FETCH_ASSOC);
            if (!$personal || $personal['codigo_institucion'] !== $codigo_institucion_sesion && $codigo_perfil_sesion !== '99') {
                throw new Exception("El personal no pertenece a tu institución.");
            }
            $codigo_institucion_personal = $personal['codigo_institucion'];
            
            $query = "INSERT INTO usuarios (nombre, password, codigo_personal, codigo_perfil, codigo_institucion, base_de_datos, estado)
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$username, $password, $personalId, $profileCode, $codigo_institucion_personal, $dbname_sesion, $estado]);

            $pdo->commit();
            echo json_encode(['respuesta' => true, 'mensaje' => 'Usuario creado exitosamente.']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['respuesta' => false, 'mensaje' => "Error al crear usuario: " . $e->getMessage()]);
        }
        break;

    case 'UpdateUser':
        $userId = $_POST['userId'];
        $username = trim($_POST['username']);
        $personalId = $_POST['personalId'];
        $profileCode = $_POST['profileCode'];
        $estado = ($_POST['estado'] === 'true') ? true : false;
        $password = $_POST['password'] ?? '';
        
        try {
            $pdo->beginTransaction();
            $checkQuery = "SELECT COUNT(*) FROM usuarios WHERE nombre = ? AND id_usuario != ?";
            $stmt = $pdo->prepare($checkQuery);
            $stmt->execute([$username, $userId]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("El nombre de usuario ya existe para otro usuario. Por favor, elija otro.");
            }
            
            $queryPersonal = "SELECT codigo_institucion FROM personal WHERE id_personal = ?";
            $stmtPersonal = $pdo->prepare($queryPersonal);
            $stmtPersonal->execute([$personalId]);
            $personal = $stmtPersonal->fetch(PDO::FETCH_ASSOC);
            if (!$personal || $personal['codigo_institucion'] !== $codigo_institucion_sesion && $codigo_perfil_sesion !== '99') {
                throw new Exception("El personal no pertenece a tu institución.");
            }
            $codigo_institucion_personal = $personal['codigo_institucion'];

            $query = "UPDATE usuarios SET nombre = ?, codigo_personal = ?, codigo_perfil = ?, codigo_institucion = ?, estado = ? WHERE id_usuario = ?";
            $params = [$username, $personalId, $profileCode, $codigo_institucion_personal, $estado, $userId];
            if (!empty($password)) {
                $password = hashPassword($password);
                $query = "UPDATE usuarios SET nombre = ?, password = ?, codigo_personal = ?, codigo_perfil = ?, codigo_institucion = ?, estado = ? WHERE id_usuario = ?";
                array_splice($params, 1, 0, $password);
            }
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);

            $pdo->commit();
            echo json_encode(['respuesta' => true, 'mensaje' => 'Usuario actualizado exitosamente.']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['respuesta' => false, 'mensaje' => "Error al actualizar usuario: " . $e->getMessage()]);
        }
        break;

    case 'DeleteUser':
        $userId = $_POST['userId'];
        try {
            $pdo->beginTransaction();
            $sql = "DELETE FROM usuarios WHERE id_usuario = ?";
            if ($codigo_perfil_sesion !== '99') {
                $sql .= " AND codigo_institucion = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$userId, $codigo_institucion_sesion]);
            } else {
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$userId]);
            }
            $pdo->commit();
            echo json_encode(['respuesta' => true, 'mensaje' => 'Usuario eliminado exitosamente.']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['respuesta' => false, 'mensaje' => "Error al eliminar usuario: " . $e->getMessage()]);
        }
        break;

    case 'GetProfiles':
        $query = "SELECT codigo, descripcion FROM catalogo_perfil ORDER BY descripcion";
        try {
            $stmt = $pdo->query($query);
            $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['respuesta' => true, 'contenido' => $profiles]);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => "Error al cargar perfiles: " . $e->getMessage()]);
        }
        break;

    case 'GetPersonal':
        try {
            if ($codigo_perfil_sesion === '99') {
                $sql = "SELECT id_personal, nombres, apellidos FROM personal ORDER BY nombres, apellidos";
                $stmt = $pdo->query($sql);
            } else {
                $sql = "SELECT id_personal, nombres, apellidos FROM personal WHERE codigo_institucion = :codigo_institucion_sesion ORDER BY nombres, apellidos";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':codigo_institucion_sesion', $codigo_institucion_sesion, PDO::PARAM_STR);
                $stmt->execute();
            }
            $personal = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['respuesta' => true, 'contenido' => $personal]);
        } catch (PDOException $e) {
            echo json_encode(['respuesta' => false, 'mensaje' => "Error al cargar personal: " . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['respuesta' => false, 'mensaje' => 'Esta acción no se encuentra disponible']);
        break;
}
?>