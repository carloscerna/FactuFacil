<?php
session_name('FactuFacil');
session_start();
$_SESSION['path_root'] = trim($_SERVER['DOCUMENT_ROOT']);
// Es utilizando en templateEngine.inc.php
$root = '';

if(!empty($_SESSION) && $_SESSION['userLogin'] == true){
    include('includes/templateEngine.inc.php');

    $twig->display('Catalogo/Perfiles.html',array(
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
}else{
    header("Location:login.php");
}
