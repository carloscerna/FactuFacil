<?php
// admin/contabilidad/cuentas/index.php
session_name('FactuFacil');
session_start();

// 1. Validación de Seguridad
if (empty($_SESSION['userNombre']) || empty($_SESSION['codigo_institucion'])) {
    header('Location: /FactuFacil');
    exit();
} else {
// Es utilizando en templateEngine.inc.php
$root = '';
    include('includes/templateEngine.inc.php');

    // 3. Renderizado de la Vista
    // Apunta a la plantilla que crearemos en el siguiente paso
    $twig->display('contabilidad/cuentas/catalogo_cuentas.html', array(
        "userName"          => $_SESSION['userNombre'] ?? '',
        "userID"            => $_SESSION['userID'] ?? 0,
        "codigo_perfil"     => $_SESSION['codigo_perfil'] ?? 0,
        "logo_uno"          => $_SESSION['logo_uno'] ?? '',
        "codigo_personal" => $_SESSION['codigo_personal'] ?? '',
        "nombre_institucion" => $_SESSION['institucion'] ?? $_SESSION['nombre_institucion'] ?? '',
        "nombre_personal"   => $_SESSION['nombre_personal'] ?? '',
        "nombre_perfil"     => $_SESSION['nombre_perfil'] ?? '',
        "codigo_institucion" => $_SESSION['codigo_institucion']
    ));
}
?>