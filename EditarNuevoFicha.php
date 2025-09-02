<?php
session_name('demoUI');
session_start();

// Comprobar si existen las variables de SESSION.
if(empty($_SESSION['userNombre']))
{
    header('Location: /FactuFacil');
}else{
// Es utilizando en templateEngine.inc.php
$root = '';
$Id = $_REQUEST['id'];
$accion = $_REQUEST['accion'];
$_SESSION['id_personal'] = $_REQUEST['id'];
    include('includes/templateEngine.inc.php');

    $twig->display('/Personal/EditarNuevoFicha.html',array(
        "userName" => $_SESSION['userNombre'],
        "userID" => $_SESSION['userID'],
        "codigo_perfil" => $_SESSION['codigo_perfil'],
        "codigo_personal" => $_SESSION['codigo_personal'],
        "logo_uno" => $_SESSION['logo_uno'],
        "nombre_institucion" => $_SESSION['institucion'],
        "nombre_personal" => $_SESSION['nombre_personal'],
        "nombre_perfil" => $_SESSION['nombre_perfil'],
        "codigo_institucion" => $_SESSION['codigo_institucion'],
        "id" => $Id,
        "accion" => $accion
    ));
}
?>