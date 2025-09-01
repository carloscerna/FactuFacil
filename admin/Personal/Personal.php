<?php
// admin/personal/Personal.php

session_name('FactuFacil');
session_start();

// Validar que el usuario esté autenticado
if(empty($_SESSION['userNombre'])) {
    // Si no hay una sesión activa, redirigir al login
    header('Location: /FactuFacil');
    exit();
}

// La variable $root se utiliza en templateEngine.inc.php
$root = '';

// Incluir el motor de plantillas Twig
include('includes/templateEngine.inc.php');

// Pasar las variables de sesión a la vista de Twig y renderizarla
$twig->display('personal/Personal.html', array(
    "userName" => $_SESSION['userNombre'],
    "userID" => $_SESSION['userID'],
    "codigo_perfil" => $_SESSION['codigo_perfil'],
    "codigo_personal" => $_SESSION['codigo_personal'],
    "logo_uno" => $_SESSION['logo_uno'],
    "nombre_institucion" => $_SESSION['institucion'],
    "nombre_personal" => $_SESSION['nombre_personal'],
    "nombre_perfil" => $_SESSION['nombre_perfil'],
    "codigo_institucion" => $_SESSION['codigo_institucion']
));
?>