<?php
// Limpiar caché.
clearstatcache();
// Cambiar a utf-8.
header("Content-Type: text/html;charset=iso-8859-1"); // Asegúrate de que esto sea compatible con tu base de datos y la codificación de caracteres.

// Variable para la conexión.
$errorDbConexion = false;
// Inicializamos variables de mensajes y JSON
$respuestaOK = false;
$mensajeError = "No se puede ejecutar la aplicación";
$contenidoOK = "";

// Ruta de los archivos con su carpeta (ajusta según tu estructura de carpetas)
    $path_root = trim($_SERVER['DOCUMENT_ROOT']);
// Incluimos el archivo de funciones y conexión a la base de datos
	include($path_root."/FactuFacil/includes/mainFunctions_.php");


// Función para hashear la contraseña (importante para la seguridad)
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Función para verificar la contraseña (no necesaria para este script, pero útil para el login)
function verifyPassword($password, $hashedPassword) {
    return password_verify($password, $hashedPassword);
}

// Validar conexión con la base de datos
if ($errorDbConexion == false) {
    // Validamos que existan las variables POST
    if (isset($_POST) && !empty($_POST)) {
        // Verificamos la acción solicitada
        switch ($_POST['accion']) {
            case 'ReadUsers':
                // Consulta para obtener todos los usuarios con sus detalles, incluyendo la institución
                $query = "SELECT u.id_usuario, u.nombre AS username, btrim(p.nombres || ' ' || p.apellidos) AS nombre_personal, 
                                 cp.descripcion AS nombre_perfil,
                                 ii.nombre_institucion AS nombre_institucion_usuario
                          FROM usuarios u
                          INNER JOIN personal p ON u.codigo_personal = p.id_personal
                          INNER JOIN catalogo_perfil cp ON u.codigo_perfil = cp.codigo
                          LEFT JOIN instituciones ii ON u.codigo_escuela = ii.codigo_institucion
                          ORDER BY u.nombre"; // Ordenar para una visualización consistente
                
                try {
                    $consulta = $dblink->query($query);
                    $users = $consulta->fetchAll(PDO::FETCH_ASSOC);

                    // Añadir botones de acción a cada usuario para DataTables
                    foreach ($users as &$user) {
                        $user['acciones'] = '
                            <button class="btn btn-info btn-sm edit-user me-1 rounded-pill" data-id="' . htmlspecialchars($user['id_usuario']) . '" title="Editar"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-danger btn-sm delete-user rounded-pill" data-id="' . htmlspecialchars($user['id_usuario']) . '" title="Eliminar"><i class="fas fa-trash"></i></button>
                        ';
                    }

                    $respuestaOK = true;
                    $contenidoOK = $users;
                    $mensajeError = "Usuarios cargados correctamente.";
                } catch (PDOException $e) {
                    $respuestaOK = false;
                    $mensajeError = "Error al cargar usuarios: " . $e->getMessage();
                }
                break;

            case 'GetUserById':
                // Obtener detalles de un usuario específico por ID, incluyendo codigo_escuela
                $userId = trim($_POST['userId']);
                $query = "SELECT u.id_usuario, u.nombre AS username, u.codigo_personal, u.codigo_perfil, u.codigo_escuela
                          FROM usuarios u WHERE u.id_usuario = ?";
                
                try {
                    $stmt = $dblink->prepare($query);
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($user) {
                        $respuestaOK = true;
                        $contenidoOK = $user;
                        $mensajeError = "Usuario encontrado.";
                    } else {
                        $respuestaOK = false;
                        $contenidoOK = "Error";
                        $mensajeError = "Usuario no encontrado.";
                    }
                } catch (PDOException $e) {
                    $respuestaOK = false;
                    $mensajeError = "Error al obtener usuario: " . $e->getMessage();
                }
                break;

            case 'CreateUser':
                // Crear un nuevo usuario, incluyendo codigo_escuela
                $username = trim($_POST['username']);
                $password = hashPassword(trim($_POST['password'])); // Hashear la contraseña
                $personalId = trim($_POST['personalId']);
                $profileCode = trim($_POST['profileCode']);
                $schoolCode = trim($_POST['schoolCode']); // Nuevo campo

                // Iniciar una transacción para asegurar la integridad de los datos
                $dblink->beginTransaction();
                try {
                    // Verificar si el nombre de usuario ya existe
                    $checkQuery = "SELECT COUNT(*) FROM usuarios WHERE nombre = ?";
                    $stmt = $dblink->prepare($checkQuery);
                    $stmt->execute([$username]);
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception("El nombre de usuario ya existe. Por favor, elija otro.");
                    }

                    // Insertar en la tabla 'usuarios'
                    // Asegúrate de que 'base_de_datos' sea apropiado para tu lógica de negocio.
                    $dbName = $_SESSION['dbname'] ?? 'sistema_facturacion'; // Usa la base de datos de la sesión o una por defecto

                    $query = "INSERT INTO usuarios (nombre, password, codigo_personal, codigo_perfil, base_de_datos, codigo_escuela)
                              VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $dblink->prepare($query);
                    $stmt->execute([$username, $password, $personalId, $profileCode, $dbName, $schoolCode]);

                    $dblink->commit();
                    $respuestaOK = true;
                    $mensajeError = "Usuario creado exitosamente.";
                } catch (Exception $e) {
                    $dblink->rollBack();
                    $respuestaOK = false;
                    $mensajeError = "Error al crear usuario: " . $e->getMessage();
                }
                break;

            case 'UpdateUser':
                // Actualizar un usuario existente, incluyendo codigo_escuela
                $userId = trim($_POST['userId']);
                $username = trim($_POST['username']);
                $personalId = trim($_POST['personalId']);
                $profileCode = trim($_POST['profileCode']);
                $schoolCode = trim($_POST['schoolCode']); // Nuevo campo
                $password = trim($_POST['password']); // Nueva contraseña, puede estar vacía

                $dblink->beginTransaction();
                try {
                    // Verificar si el nombre de usuario ya existe para otro usuario
                    $checkQuery = "SELECT COUNT(*) FROM usuarios WHERE nombre = ? AND id_usuario != ?";
                    $stmt = $dblink->prepare($checkQuery);
                    $stmt->execute([$username, $userId]);
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception("El nombre de usuario ya existe para otro usuario. Por favor, elija otro.");
                    }

                    $query = "UPDATE usuarios SET nombre = ?, codigo_personal = ?, codigo_perfil = ?, codigo_escuela = ? WHERE id_usuario = ?";
                    $params = [$username, $personalId, $profileCode, $schoolCode, $userId];

                    // Si se proporciona una contraseña, actualizarla
                    if (!empty($password)) {
                        $password = hashPassword($password);
                        $query = "UPDATE usuarios SET nombre = ?, password = ?, codigo_personal = ?, codigo_perfil = ?, codigo_escuela = ? WHERE id_usuario = ?";
                        array_splice($params, 1, 0, $password); // Insertar la contraseña después del nombre de usuario
                    }

                    $stmt = $dblink->prepare($query);
                    $stmt->execute($params);

                    $dblink->commit();
                    $respuestaOK = true;
                    $mensajeError = "Usuario actualizado exitosamente.";
                } catch (Exception $e) {
                    $dblink->rollBack();
                    $respuestaOK = false;
                    $mensajeError = "Error al actualizar usuario: " . $e->getMessage();
                }
                break;

            case 'DeleteUser':
                // Eliminar un usuario
                $userId = trim($_POST['userId']);

                $dblink->beginTransaction();
                try {
                    $query = "DELETE FROM usuarios WHERE id_usuario = ?";
                    $stmt = $dblink->prepare($query);
                    $stmt->execute([$userId]);

                    $dblink->commit();
                    $respuestaOK = true;
                    $mensajeError = "Usuario eliminado exitosamente.";
                } catch (Exception $e) {
                    $dblink->rollBack();
                    $respuestaOK = false;
                    $mensajeError = "Error al eliminar usuario: " . $e->getMessage();
                }
                break;

            case 'GetProfiles':
                // Obtener todos los perfiles disponibles
                $query = "SELECT codigo, descripcion FROM catalogo_perfil ORDER BY descripcion";
                try {
                    $stmt = $dblink->query($query);
                    $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $respuestaOK = true;
                    $contenidoOK = $profiles;
                } catch (PDOException $e) {
                    $respuestaOK = false;
                    $mensajeError = "Error al cargar perfiles: " . $e->getMessage();
                }
                break;

            case 'GetPersonal':
                // Obtener datos del personal para el dropdown
                // Considera si solo debes mostrar personal no asociado a un usuario existente.
                // Aquí se muestran todos para simplificar.
                $query = "SELECT id_personal, nombres, apellidos FROM personal ORDER BY nombres, apellidos";
                try {
                    $stmt = $dblink->query($query);
                    $personal = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $respuestaOK = true;
                    $contenidoOK = $personal;
                } catch (PDOException $e) {
                    $respuestaOK = false;
                    $mensajeError = "Error al cargar personal: " . $e->getMessage();
                }
                break;
            
            case 'GetInstituciones': // Nueva acción para obtener instituciones
                $query = "SELECT codigo_institucion, nombre_institucion FROM instituciones ORDER BY nombre_institucion";
                try {
                    $stmt = $dblink->query($query);
                    $instituciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $respuestaOK = true;
                    $contenidoOK = $instituciones;
                } catch (PDOException $e) {
                    $respuestaOK = false;
                    $mensajeError = "Error al cargar instituciones: " . $e->getMessage();
                }
                break;

            default:
                $mensajeError = 'Esta acción no se encuentra disponible';
                break;
        }
    } else {
        $mensajeError = 'No se puede ejecutar la aplicación (no se recibieron datos POST)';
    }
} else {
    // Si la conexión a la base de datos falló al inicio
    $respuestaOK = false;
    $contenidoOK = "Error dbname";
    // $mensajeError ya se estableció en el bloque try-catch de conexión
}

// Armamos array para convertir a JSON
$salidaJson = array(
    "respuesta" => $respuestaOK,
    "mensaje" => $mensajeError,
    "contenido" => $contenidoOK
);

echo json_encode($salidaJson);
?>
