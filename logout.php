<?php
session_name('FactuFacil');
session_start();

// vaciamos  las variables sesion.
if(!empty($_SESSION)){
    $_SESSION = array();
    session_destroy();
}

header("location:index.php");
?>