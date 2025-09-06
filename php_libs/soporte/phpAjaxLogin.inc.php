<?php
// Iniciar la sesión si no está iniciada. Siempre debe ir al principio.
if (session_status() == PHP_SESSION_NONE) {
    session_name('FactuFacil');
    session_start();
}

// Establecer el tipo de contenido para la respuesta AJAX como JSON.
header("Content-Type: application/json;charset=utf-8");

// Variable para la conexión. Asumimos que mainFunctions_.php la gestiona.
global $dblink;
global $errorDbConexion;

// Inicializamos variables de respuesta JSON
$respuestaOK = false;
$mensajeError = "No se puede ejecutar la aplicación.";
$contenidoOK = "";

// Ruta raíz de los archivos (usado para includes).
$path_root = trim($_SERVER['DOCUMENT_ROOT']);

// Incluimos el archivo de funciones y conexión a la base de datos.
// Se asume que mainFunctions_.php establece $dblink y $errorDbConexion.
// Es crucial que mainFunctions_.php no haga un 'die()' directo en caso de error de conexión si esperas un JSON.
include($path_root . "/FactuFacil/includes/mainFunctions_.php");
// Incluir funciones auxiliares si son necesarias (ej. convertirtexto)
include($path_root . "/FactuFacil/includes/funciones.php");

// Validar conexión con la base de datos.
// $errorDbConexion es establecido en mainFunctions_.php
if ($errorDbConexion === false && isset($dblink)) {
    // Validamos que existan las variables POST y la acción.
    if (isset($_POST) && !empty($_POST) && isset($_POST['accion_buscar'])) {
        $accion = $_POST['accion_buscar'];

        switch ($accion) {
            case 'BuscarUser':
            $user = filter_input(INPUT_POST, 'txtnombre', FILTER_SANITIZE_STRING);
            $password_ingresado = filter_input(INPUT_POST, 'txtpassword', FILTER_UNSAFE_RAW);

            if (empty($user) || empty($password_ingresado)) {
                $mensajeError = 'Usuario y/o Contraseña no pueden estar vacíos.';
                break;
            }
            
            // Limpiar espacios en blanco de la derecha de los datos de entrada
            $nombre = rtrim($user);
            $password_ingresado = rtrim($password_ingresado);
            
            try {
                // Lógica para el usuario "root"
                if ($user === 'root') {
                    $sql = "
                        SELECT
                            u.id_usuario,
                            u.nombre AS nombre_usuario,
                            u.password
                        FROM
                            usuarios u
                        WHERE
                            u.nombre = :user AND u.estado = true
                    ";
                    $stmt = $dblink->prepare($sql);
                    $stmt->bindParam(':user', $nombre, PDO::PARAM_STR);
                    $stmt->execute();
                    $login = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($login && password_verify($password_ingresado, trim($login['password']))) {
                        // Si el usuario "root" existe, buscamos su perfil.
                        $sql_perfil = "
                            SELECT
                                up.codigo_perfil,
                                cp.descripcion AS nombre_perfil
                            FROM
                                personal up
                            INNER JOIN
                                catalogo_perfil cp ON up.codigo_perfil = cp.codigo
                            WHERE
                                up.id_usuario = :id_usuario
                        ";
                        $stmt_perfil = $dblink->prepare($sql_perfil);
                        $stmt_perfil->bindParam(':id_usuario', $login['id_usuario'], PDO::PARAM_INT);
                        $stmt_perfil->execute();
                        $perfil = $stmt_perfil->fetch(PDO::FETCH_ASSOC);

                        // Asignamos las variables de sesión para el superusuario
                        $_SESSION['userLogin'] = true;
                        $_SESSION['userNombre'] = $login['nombre_usuario'];
                        $_SESSION['userID'] = $login['id_usuario'];
                        $_SESSION['codigo_perfil'] = $perfil['codigo_perfil'] ?? null;
                        $_SESSION['nombre_perfil'] = $perfil['nombre'] ?? 'Administrador Maestro';
                        $_SESSION['codigo_personal'] = null;
                        $_SESSION['nombre_personal'] = 'Superusuario';
                        $_SESSION['codigo_institucion'] = null;
                        $_SESSION['institucion'] = 'Acceso Global';
                        $_SESSION['logo_uno'] = 'logo_generico.png';
                        $_SESSION['dbname'] = 'sistema_facturacion';

                        $respuestaOK = true;
                        $contenidoOK = 'Bienvenido al Sistema';
                    } else {
                        $respuestaOK = false;
                        $contenidoOK = 'Error Usuario';
                        $mensajeError = 'Usuario o Contraseña incorrecta.';
                    }
                } else {
                    // Lógica para usuarios normales (requiere institución)
                    $sql = "
                        SELECT 
                            u.id_usuario, u.nombre AS nombre_usuario, u.password AS hashed_password,
                            u.codigo_perfil, u.codigo_personal, u.codigo_institucion,
                            p.nombres, p.apellidos,
                            cp.descripcion AS nombre_perfil,
                            i.nombre_institucion, i.logo_uno
                        FROM usuarios u
                        INNER JOIN personal p ON u.codigo_personal = p.id_personal
                        INNER JOIN catalogo_perfil cp ON u.codigo_perfil = cp.codigo
                        INNER JOIN instituciones i ON u.codigo_institucion = i.codigo_institucion
                        WHERE u.nombre = :nombre AND u.estado = TRUE LIMIT 1
                    ";
                    $stmt = $dblink->prepare($sql);
                    $stmt->bindParam(':nombre', $user, PDO::PARAM_STR);
                    $stmt->execute();
                    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($usuario && password_verify($password_ingresado, trim($usuario['hashed_password']))) {
                        $respuestaOK = true;
                        $contenidoOK = 'Conexión Exitosa.';

                        $_SESSION['userLogin'] = true;
                        $_SESSION['userNombre'] = trim($usuario['nombre_usuario']);
                        $_SESSION['userID'] = $usuario['id_usuario'];
                        $_SESSION['codigo_perfil'] = trim($usuario['codigo_perfil']);
                        $_SESSION['codigo_personal'] = trim($usuario['codigo_personal']);
                        $_SESSION['nombre_personal'] = trim($usuario['nombres'] . ' ' . $usuario['apellidos']);
                        $_SESSION['nombre_perfil'] = trim($usuario['nombre_perfil']);
                        $_SESSION['institucion'] = trim($usuario['nombre_institucion']);
                        $_SESSION['codigo_institucion'] = trim($usuario['codigo_institucion']);
                        $_SESSION['logo_uno'] = trim($usuario['logo_uno'] === '' ? 'logo_generico.png' : $usuario['logo_uno']);
                        $_SESSION['dbname'] = 'sistema_facturacion';
                    } else {
                        $respuestaOK = false;
                        $contenidoOK = 'Error Usuario';
                        $mensajeError = 'Usuario o Contraseña incorrecta.';
                    }
                }
            } catch (PDOException $e) {
                $respuestaOK = false;
                $contenidoOK = 'Error Interno';
                $mensajeError = 'Error en la consulta de base de datos durante el login.';
            }
            break;
                

            default:
                $mensajeError = 'Esta acción no se encuentra disponible.';
                break;
        }
    } else {
        $mensajeError = 'No se recibieron datos para procesar la autenticación.';
    }
} else {
    // Si la conexión a la DB inicial falla
    $respuestaOK = false;
    $contenidoOK = "Error dbname";
    $mensajeError = "Error de conexión inicial a la base de datos.";
}

// Armamos array para convertir a JSON
$salidaJson = array(
    "respuesta" => $respuestaOK,
    "mensaje" => $mensajeError,
    "contenido" => $contenidoOK
);

echo json_encode($salidaJson);
?>