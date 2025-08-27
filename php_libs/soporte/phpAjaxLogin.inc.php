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
                // Validar y sanear los datos de entrada.
                // filter_input es preferible a acceder directamente a $_POST
                $nombre = filter_input(INPUT_POST, 'txtnombre', FILTER_SANITIZE_STRING);
                $password_ingresado = filter_input(INPUT_POST, 'txtpassword', FILTER_UNSAFE_RAW); // No sanear password antes de verificar hash

                if (empty($nombre) || empty($password_ingresado)) {
                    $mensajeError = 'Usuario y/o Contraseña no pueden estar vacíos.';
                    break;
                }

                // USO DE PREPARED STATEMENTS PARA PREVENIR INYECCIÓN SQL
                $query = "SELECT u.nombre, u.id_usuario, u.base_de_datos, u.codigo_escuela, u.codigo_perfil, u.codigo_personal,
                                 btrim(p.nombres || CAST(' ' AS VARCHAR) || p.apellidos) as nombre_personal,
                                 cat_perfil.descripcion as nombre_perfil, u.password as hashed_password
                          FROM usuarios u
                          INNER JOIN personal p ON p.id_personal = u.codigo_personal
                          INNER JOIN catalogo_perfil cat_perfil ON cat_perfil.codigo = u.codigo_perfil
                          WHERE u.nombre = :nombre LIMIT 1";

                try {
                    $stmt = $dblink->prepare($query);
					$nombre = trim($nombre); // Asegurarse de que el nombre esté limpio y sin espacios innecesarios
                    $stmt->bindParam(':nombre', $nombre, PDO::PARAM_STR);
                    $stmt->execute();
                    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($usuario) {
						// Dentro del if ($usuario) en phpAjaxLogin.inc.php, justo antes del password_verify
					//var_dump("Password Ingresado (texto plano): '" . $password_ingresado . "' (Longitud: " . strlen($password_ingresado) . ")");
					//var_dump("Hash desde DB: '" . $usuario['hashed_password'] . "' (Longitud: " . strlen($usuario['hashed_password']) . ")");
					//var_dump("Resultado de password_verify: " . (password_verify($password_ingresado, $usuario['hashed_password']) ? 'TRUE' : 'FALSE'));
						// Si no ves los logs de error, puedes probar con var_dump directamente en la respuesta AJAX (recuerda comentar el header y poner exit())

                        // VERIFICACIÓN DE CONTRASEÑA HASHED (MUY IMPORTANTE)
                        // Asumiendo que u.password ahora almacena un hash de la contraseña.
                        // Si no es así, DEBES migrar tus contraseñas a hashes.
                   // *** SOLUCIÓN: Aplicar trim() al hash recuperado de la base de datos ***
                    $stored_hash = trim($usuario['hashed_password']);
                        if (password_verify($password_ingresado, $stored_hash)) {
                            $respuestaOK = true;
                            $contenidoOK = "Si";
                            $mensajeError = "Conexión Exitosa.";

                            // Establecer variables de sesión para el usuario
                            $_SESSION['userLogin'] = true;
                            $_SESSION['userNombre'] = trim($usuario['nombre']);
                            $_SESSION['userID'] = $usuario['id_usuario'];
                            $_SESSION['dbname'] = trim($usuario['base_de_datos']); // La BD puede cambiar por usuario
                            $_SESSION['codigo_escuela'] = trim($usuario['codigo_escuela']);
                            $_SESSION['codigo_perfil'] = trim($usuario['codigo_perfil']);
                            $_SESSION['codigo_personal'] = trim($usuario['codigo_personal']);
                            $_SESSION['nombre_personal'] = trim($usuario['nombre_personal']);
                            $_SESSION['nombre_perfil'] = trim($usuario['nombre_perfil']);
                            $_SESSION['autentica'] = "SI"; // Variable de autenticación

                            // ***** IMPORTANTE: Reestablecer la conexión a la nueva base de datos del usuario si 'dbname' ha cambiado *****
                            // Esta es la parte crítica donde el código original hacía una segunda inclusión de mainFunctions_.php
                            // Una mejor práctica sería tener una función de conexión reusable.
                            // Para mantener la lógica original de reconexión, podríamos hacer esto:
                            if (isset($_SESSION['dbname'])) {//&& $_SESSION['dbname'] !== $database) { // Si la BD del usuario es diferente a la de la conexión actual
                                // Una forma de reconectar:
                                // Asume que mainFunctions_.php puede ser incluido de nuevo para reestablecer $dblink con la nueva $_SESSION['dbname']
                                // O, mejor aún, crea una función de conexión reutilizable.
                                // Por simplicidad, re-incluimos, pero idealmente se usaría una función.
                                // La variable $database en mainFunctions_.php se actualizará con $_SESSION['dbname']
                                include($path_root . "/registro_academico/includes/mainFunctions_.php");
                                if ($errorDbConexion) {
                                    $respuestaOK = false;
                                    $contenidoOK = "Error dbname";
                                    $mensajeError = "No se pudo conectar a la base de datos de la institución.";
                                    break;
                                }
                            }
							//print $_SESSION['dbname'] ?? 'No database name set in session.';
                            // Obtener datos de la institución DESPUÉS de asegurar la conexión a la BD correcta
                            if ($dblink) { // Asegúrate de que $dblink esté válido después de la posible reconexión
                                $query_institucion = "SELECT inf.id_institucion, inf.codigo_departamento, inf.codigo_municipio, inf.codigo_institucion, inf.nombre_institucion, inf.direccion_institucion, inf.telefono_uno,
                                                            depa.codigo, depa.nombre as nombre_departamento,
                                                            mu.codigo, mu.codigo_departamento, mu.nombre as nombre_municipio, inf.numero_acuerdo,
                                                            btrim(p.nombres || CAST(' ' AS VARCHAR) || p.apellidos) as nombre_completo,
                                                            inf.se_extiende_la_presente, inf.dia_entrega, inf.logo_uno, inf.logo_dos, inf.imagen_firma, inf.imagen_sello
                                                        FROM informacion_institucion inf
                                                        INNER JOIN personal p ON p.id_personal = CAST(inf.nombre_director AS INTEGER)
                                                        INNER JOIN departamento depa ON depa.codigo = inf.codigo_departamento
                                                        INNER JOIN municipio mu ON mu.codigo = inf.codigo_municipio AND mu.codigo_departamento = inf.codigo_departamento
                                                        WHERE inf.codigo_institucion = :codigo_institucion LIMIT 1";

                                $stmt_institucion = $dblink->prepare($query_institucion);
                                $stmt_institucion->bindParam(':codigo_institucion', $_SESSION['codigo_escuela'], PDO::PARAM_STR);
                                $stmt_institucion->execute();
                                $institucionData = $stmt_institucion->fetch(PDO::FETCH_ASSOC);

                                if ($institucionData) {
                                    $_SESSION['institucion'] = trim($institucionData['nombre_institucion']);
                                    $_SESSION['direccion'] = trim($institucionData['direccion_institucion']);
                                    $_SESSION['codigo'] = trim($institucionData['codigo_institucion']);
                                    $_SESSION['telefono'] = trim($institucionData['telefono_uno']);
                                    $_SESSION['codigo_municipio'] = trim($institucionData['codigo_municipio']);
                                    $_SESSION['nombre_municipio'] = convertirtexto(trim($institucionData['nombre_municipio']));
                                    $_SESSION['codigo_departamento'] = trim($institucionData['codigo_departamento']);
                                    $_SESSION['nombre_departamento'] = convertirtexto(trim($institucionData['nombre_departamento']));
                                    $_SESSION['nombre_director'] = trim($institucionData['nombre_completo']);
                                    $_SESSION['numero_acuerdo'] = trim($institucionData['numero_acuerdo']);
                                    $_SESSION['se_extiende'] = trim($institucionData['se_extiende_la_presente']);
                                    $_SESSION['dia_entrega'] = convertirtexto(trim($institucionData['dia_entrega']));
                                    $_SESSION['logo_uno'] = trim($institucionData['logo_uno']);
                                    $_SESSION['logo_dos'] = trim($institucionData['logo_dos']);
                                    $_SESSION['imagen_firma'] = trim($institucionData['imagen_firma']);
                                    $_SESSION['imagen_sello'] = trim($institucionData['imagen_sello']);
                                    $_SESSION['codigo_institucion'] = trim($institucionData['codigo_institucion']);

                                    // Variables para la Matrícula (manteniendo lógica original)
                                    $_SESSION["s_codigo_ann_lectivo"] = "01";
                                    $_SESSION["s_codigo_modalidad"] = "01";
                                    $_SESSION["s_codigo_grado_seccion_turno"] = "01";

                                } else {
                                    $respuestaOK = false;
                                    $contenidoOK = "Error Institucion";
                                    $mensajeError = "No existen datos de la institución para el código de escuela.";
                                    // Limpiar sesión si la institución no se encuentra
                                    session_destroy();
                                }
                            } else {
                                $respuestaOK = false;
                                $contenidoOK = "Error dbname";
                                $mensajeError = "No se pudo establecer la conexión a la base de datos de la institución.";
                                session_destroy();
                            }
                        } else {
                            $respuestaOK = false;
                            $contenidoOK = 'Error Usuario';
                            $mensajeError = 'Usuario o Contraseña incorrecta.'; // Mensaje genérico por seguridad
                        }
                    } else {
                        $respuestaOK = false;
                        $contenidoOK = 'Error Usuario';
                        $mensajeError = 'Usuario o Contraseña incorrecta.'; // Mensaje genérico
                    }
                } catch (PDOException $e) {
                    $respuestaOK = false;
                    $contenidoOK = 'Error Interno';
                    $mensajeError = 'Error en la consulta de base de datos durante el login.';
                    // En producción, aquí se registraría $e->getMessage() en un log, no al usuario.
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