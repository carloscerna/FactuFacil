<?php
// ruta de los archivos con su carpeta
    $path_root=trim($_SERVER['DOCUMENT_ROOT']);
// archivos que se incluyen.
    include($path_root."/FactuFacil/includes/so_version.php");
// Extrar la información del sistema operativo.
    //$info["os"];
// Ejecutar. Depende de la condición.
    if($info["os"] === "LINUX"){
        exec("sudo sh pg_backup.sh");    
    }
    if($info["os"] === "WIN"){
        exec("backup.bat");    
    }

//
// Establecer formato para la fecha.
// 
   date_default_timezone_set('America/El_Salvador');
   setlocale(LC_TIME, 'spanish');
// Grabar y Mostrar Fecha y Hora.
    $date = date_create();
    $cadena_fecha_actual = date_format($date, 'd-m-Y g:i a');
    //print $cadena_fecha_actual;
// Guardar Información en la tabla. control_respaldos.
    include($path_root."/FactuFacil/includes/mainFunctions_conexion.php");
// Construir Consulta.
    $query = "INSERT INTO control_respaldos (fecha_hora) VALUES ('$cadena_fecha_actual')";
//  Ejecución del Query.
    $resultadoQuery = $dblink -> query($query);
    //print strlen($cadena_fecha_actual);

?>