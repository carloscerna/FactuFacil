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
            $user = $_POST['txtnombre'] ?? '';
            $password = $_POST['txtpassword'] ?? '';
            
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
                    $stmt->bindParam(':user', $user, PDO::PARAM_STR);
                    $stmt->execute();
                    $login = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($login && password_verify($password, trim($login['password']))) {
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
                            u.id_usuario,
                            u.nombre AS nombre_usuario,
                            u.password,
                            up.codigo_perfil,
                            p.nombres,
                            p.apellidos,
                            p.codigo_personal,
                            i.codigo_institucion,
                            i.nombre_institucion,
                            i.logo_uno,
                            cp.descripcion AS nombre_perfil
                        FROM
                            usuarios u
                        INNER JOIN
                            usuarios_personal up ON u.id_usuario = up.id_usuario_bigint
                        INNER JOIN
                            personal p ON up.id_usuario_bigint = p.id_usuario_bigint
                        INNER JOIN
                            instituciones i ON p.codigo_institucion = i.codigo_institucion
                        INNER JOIN
                            catalogo_perfil cp ON up.codigo_perfil = cp.codigo_perfil
                        WHERE
                            u.nombre = :user
                            AND u.estado = true
                            AND p.codigo_estatus = '01'
                    ";
                    $stmt = $dblink->prepare($sql);
                    $stmt->bindParam(':user', $user, PDO::PARAM_STR);
                    $stmt->execute();
                    $login = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($login) {
                        if (password_verify($password, $login['password'])) {
                            // Autenticación exitosa, asignamos las variables de sesión
                            $_SESSION['userLogin'] = true;
                            $_SESSION['userNombre'] = $login['nombre_usuario'];
                            $_SESSION['userID'] = $login['id_usuario'];
                            $_SESSION['codigo_perfil'] = trim($login['codigo_perfil']);
                            $_SESSION['codigo_personal'] = trim($login['codigo_personal']);
                            $_SESSION['nombre_personal'] = trim($login['nombres'] . ' ' . $login['apellidos']);
                            $_SESSION['nombre_perfil'] = $login['nombre_perfil'];
                            $_SESSION['codigo_institucion'] = trim($login['codigo_institucion']);
                            $_SESSION['institucion'] = $login['nombre_institucion'];
                            $_SESSION['logo_uno'] = $login['logo_uno'];
                            $respuestaOK = true;
                            $contenidoOK = 'Bienvenido al Sistema';
                        } else {
                            $respuestaOK = false;
                            $contenidoOK = 'Error Usuario';
                            $mensajeError = 'Usuario o Contraseña incorrecta.';
                        }
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