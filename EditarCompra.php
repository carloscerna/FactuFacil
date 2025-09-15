<?php
session_name('FactuFacil');
session_start();

// Comprobar si existen las variables de SESSION.
if(empty($_SESSION['userNombre']))
{
    header('Location: /FactuFacil');
}else{
    // Es utilizado en templateEngine.inc.php
    $root = '';
    include('includes/templateEngine.inc.php');

    // Obtener el id_compra de la URL
    $id_compra = $_GET['id_compra'] ?? null;

    if ($id_compra === null) {
        // Redirigir a la lista si no se proporciona un ID
        header('Location: ListadoCompras.html');
        exit();
    }

    $twig->display('compras/EditarCompra.html',array(
        "userName" => $_SESSION['userNombre'],
        "userID" => $_SESSION['userID'],
        "codigo_perfil" => $_SESSION['codigo_perfil'],
        "codigo_personal" => $_SESSION['codigo_personal'],
        "logo_uno" => $_SESSION['logo_uno'],
        "nombre_institucion" => $_SESSION['institucion'],
        "nombre_personal" => $_SESSION['nombre_personal'],
        "nombre_perfil" => $_SESSION['nombre_perfil'],
        "codigo_institucion" => $_SESSION['codigo_institucion'],
        "id_compra" => $id_compra // Pasamos el ID de la compra a la vista
    ));
}
?>