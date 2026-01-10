<?php
// 1. INICIAR BUFFER: Captura cualquier error o warning invisible
ob_start();

// Iniciar la sesión si no está iniciada.
if (session_status() == PHP_SESSION_NONE) {
    session_name('FactuFacil');
    session_start();
}

// Establecer cabeceras JSON
header("Content-Type: application/json;charset=utf-8");

// Inicializamos variables
$respuestaOK = false;
$mensajeError = "No se puede ejecutar la aplicación.";
$contenidoOK = "";

// Ruta raíz
$path_root = trim($_SERVER['DOCUMENT_ROOT']);

// 2. USO DE INCLUDE_ONCE y verificación de archivo
$mainFile = $path_root . "/FactuFacil/includes/mainFunctions_.php";
$funcFile = $path_root . "/FactuFacil/includes/funciones.php";

if (file_exists($mainFile)) {
    include_once($mainFile);
}
if (file_exists($funcFile)) {
    include_once($funcFile);
}

global $dblink;
global $errorDbConexion;

// Validar conexión
if (isset($errorDbConexion) && $errorDbConexion === false && isset($dblink)) {
    
    // Validamos que existan datos POST
    if (isset($_POST['accion_buscar'])) {
        $accion = $_POST['accion_buscar'];

        switch ($accion) {
            case 'BuscarUser':
                // 3. CORRECCIÓN PHP 8: FILTER_SANITIZE_STRING ya no existe.
                // Usamos SPECIAL_CHARS para el usuario y obtenemos password crudo (limpio)
                $userInput = filter_input(INPUT_POST, 'txtnombre', FILTER_SANITIZE_SPECIAL_CHARS);
                $passInput = $_POST['txtpassword'] ?? ''; 

                if (empty($userInput) || empty($passInput)) {
                    $mensajeError = 'Usuario y/o Contraseña no pueden estar vacíos.';
                    break;
                }
                
                // Limpiar espacios
                $nombre = rtrim($userInput);
                $password_ingresado = rtrim($passInput);
                
                try {
                    // --- Lógica para el usuario "root" ---
                    if ($nombre === 'root') {
                        $sql = "SELECT u.id_usuario, u.nombre AS nombre_usuario, u.password 
                                FROM usuarios u 
                                WHERE u.nombre = :user AND u.estado = true LIMIT 1";
                        
                        $stmt = $dblink->prepare($sql);
                        $stmt->bindValue(':user', $nombre, PDO::PARAM_STR);
                        $stmt->execute();
                        $login = $stmt->fetch(PDO::FETCH_ASSOC);

                        // Verificar password
                        if ($login && password_verify($password_ingresado, trim($login['password']))) {
                            
                            // Buscar perfil (Puede que root no tenga registro en personal)
                            $sql_perfil = "SELECT up.codigo_perfil, cp.descripcion AS nombre_perfil
                                           FROM personal up
                                           INNER JOIN catalogo_perfil cp ON up.codigo_perfil = cp.codigo
                                           WHERE up.id_usuario = :id_usuario LIMIT 1";
                            
                            $stmt_perfil = $dblink->prepare($sql_perfil);
                            $stmt_perfil->bindValue(':id_usuario', $login['id_usuario'], PDO::PARAM_INT);
                            $stmt_perfil->execute();
                            $perfil = $stmt_perfil->fetch(PDO::FETCH_ASSOC);

                            // Variables de Sesión
                            $_SESSION['userLogin'] = true;
                            $_SESSION['userNombre'] = $login['nombre_usuario'];
                            $_SESSION['userID'] = $login['id_usuario'];
                            
                            // 4. PROTECCIÓN PHP 8: Validar si $perfil trajo datos antes de asignar
                            $_SESSION['codigo_perfil'] = ($perfil) ? $perfil['codigo_perfil'] : null;
                            $_SESSION['nombre_perfil'] = ($perfil) ? $perfil['nombre_perfil'] : 'Administrador Maestro';
                            
                            $_SESSION['codigo_personal'] = null;
                            $_SESSION['nombre_personal'] = 'Superusuario';
                            $_SESSION['codigo_institucion'] = null;
                            $_SESSION['institucion'] = 'Acceso Global';
                            $_SESSION['logo_uno'] = 'logo_generico.png';
                            $_SESSION['dbname'] = 'sistema_facturacion';

                            $respuestaOK = true;
                            $contenidoOK = 'Bienvenido al Sistema';
                        } else {
                            $mensajeError = 'Usuario o Contraseña incorrecta.';
                        }
                    } else {
                        // --- Lógica para usuarios normales ---
                        $sql = "SELECT 
                                    u.id_usuario, u.nombre AS nombre_usuario, u.password AS hashed_password,
                                    u.codigo_perfil, u.codigo_personal, u.codigo_institucion,
                                    p.nombres, p.apellidos,
                                    cp.descripcion AS nombre_perfil,
                                    i.nombre_institucion, i.logo_uno
                                FROM usuarios u
                                INNER JOIN personal p ON u.codigo_personal = p.id_personal
                                INNER JOIN catalogo_perfil cp ON u.codigo_perfil = cp.codigo
                                INNER JOIN instituciones i ON u.codigo_institucion = i.codigo_institucion
                                WHERE u.nombre = :nombre AND u.estado = TRUE LIMIT 1";
                        
                        $stmt = $dblink->prepare($sql);
                        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
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
                            $_SESSION['nombre_personal'] = trim($usuario['nombres']) . ' ' . trim($usuario['apellidos']);
                            $_SESSION['nombre_perfil'] = trim($usuario['nombre_perfil']);
                            $_SESSION['institucion'] = trim($usuario['nombre_institucion']);
                            $_SESSION['codigo_institucion'] = trim($usuario['codigo_institucion']);
                            
                            $logo = trim($usuario['logo_uno']);
                            $_SESSION['logo_uno'] = ($logo === '') ? 'logo_generico.png' : $logo;
                            
                            $_SESSION['dbname'] = 'sistema_facturacion';
                        } else {
                            $mensajeError = 'Usuario o Contraseña incorrecta.';
                        }
                    }
                } catch (PDOException $e) {
                    $mensajeError = 'Error de base de datos. Verifique logs.'; 
                    // No mostrar $e->getMessage() al usuario final por seguridad
                }
                break;

            default:
                $mensajeError = 'Acción no disponible.';
                break;
        }
    } else {
        $mensajeError = 'Faltan datos de autenticación.';
    }
} else {
    $mensajeError = "Error de conexión a la base de datos.";
}

// Armamos array
$salidaJson = array(
    "respuesta" => $respuestaOK,
    "mensaje" => $mensajeError,
    "contenido" => $contenidoOK
);

// 5. LIMPIEZA FINAL: Borrar warnings y enviar JSON puro
ob_end_clean();
echo json_encode($salidaJson);
exit;
?>