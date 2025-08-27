<?php
session_name('FactuFacil');
session_start();
$_SESSION['path_root'] = trim($_SERVER['DOCUMENT_ROOT']);
// Es utilizando en templateEngine.inc.php
$root = ''; // <--- ¡Asegúrate de que esta línea esté presente!

// Asegurarse de que el usuario está logueado y tiene el perfil correcto
$allowed_profiles = ['01', '99', '04']; // Solo administradores o ROOT pueden acceder a esta página

if (!empty($_SESSION) && $_SESSION['userLogin'] == true && in_array($_SESSION['codigo_perfil'], $allowed_profiles)) {
    include('includes/templateEngine.inc.php');

    $twig->display('layout_menu_admin.html', array(
        "userName" => $_SESSION['userNombre'],
        "userID" => $_SESSION['userID'],
        "dbname" => $_SESSION['dbname'],
        "codigo_perfil" => $_SESSION['codigo_perfil'],
        "codigo_personal" => $_SESSION['codigo_personal'],
        "logo_uno" => $_SESSION['logo_uno'],
        "nombre_institucion" => $_SESSION['institucion'],
        "nombre_personal" => $_SESSION['nombre_personal'],
        "nombre_perfil" => $_SESSION['nombre_perfil'],
        "codigo_institucion" => $_SESSION['codigo_institucion']
    ));
} else {
    // Redirigir si no está logueado o no tiene permisos
    header("Location: login.php");
    exit();
}
?>