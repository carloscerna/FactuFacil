<?php
session_name('FactuFacil');
session_start();

// Es utilizado en templateEngine.inc.php
$root = '';

// Include the Twig template engine and the database connection
require_once('includes/templateEngine.inc.php');
require_once('includes/conexion.inc.php'); // Nuestro nuevo archivo de conexión

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['txtnombre'], $_POST['txtpassword'])) {
    $user = trim($_POST['txtnombre']);
    $pass = $_POST['txtpassword'];

    if ($dblink) {
        try {
            // Consulta para buscar al usuario
            $sql = "SELECT u.nombre_usuario, u.password, u.nombre_personal, p.nombre_perfil, u.codigo_perfil,
                           u.codigo_institucion, i.nombre_institucion, i.logo_uno, u.dbname
                    FROM usuarios u
                    LEFT JOIN perfiles p ON u.codigo_perfil = p.codigo_perfil
                    LEFT JOIN instituciones i ON u.codigo_institucion = i.codigo_institucion
                    WHERE u.nombre_usuario = :user_name";
            $stmt = $dblink->prepare($sql);
            $stmt->bindParam(':user_name', $user, PDO::PARAM_STR);
            $stmt->execute();
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario && password_verify($pass, $usuario['password'])) {
                // Si el usuario existe y la contraseña es correcta, guardar los datos en la sesión
                $_SESSION['userLogin'] = true;
                $_SESSION['userID'] = $usuario['id_usuario'];
                $_SESSION['userNombre'] = $usuario['nombre_usuario'];
                $_SESSION['nombre_personal'] = $usuario['nombre_personal'];
                $_SESSION['codigo_perfil'] = $usuario['codigo_perfil'];
                $_SESSION['nombre_perfil'] = $usuario['nombre_perfil'];
                $_SESSION['codigo_institucion'] = $usuario['codigo_institucion'];
                $_SESSION['institucion'] = $usuario['nombre_institucion'];
                $_SESSION['logo_uno'] = $usuario['logo_uno'];
                $_SESSION['dbname'] = $usuario['dbname'];
                
                header('Location: index.php');
                exit();
            } else {
                $error_message = "Usuario o contraseña incorrectos.";
            }
        } catch (PDOException $e) {
            $error_message = "Error en la base de datos: " . $e->getMessage();
            error_log($e->getMessage());
        }
    } else {
        $error_message = "No se pudo conectar a la base de datos.";
    }
}

// Renderizar la plantilla de login, pasando el mensaje de error si existe
$twig->display('layout_login.html', ['error_message' => $error_message]);