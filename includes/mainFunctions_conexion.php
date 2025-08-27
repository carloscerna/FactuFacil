<?php
// iniciar sesssion.
session_name('FactuFacil');
session_start();

// Nombre de la base de datos.
	$dataname = $_SESSION['dbname'];
// omitir errores.
	ini_set("display_error", true);
// variables para la conexion.
    $host = 'localhost';
    $port = 5432;
    $database = $dataname;
    $username = 'postgres';
    $password = 'Orellana';
/*Construimos el DSN (Data Source Name). Esta cadena indicará la información de nuestro servidor. */
    $dsn = "pgsql:host=$host;port=$port;dbname=$database";
// Creamos el objeto
    $dblink = new PDO($dsn, $username, $password);
	$dblink->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
// Variable que indica el status de la conexión a la base de datos
	$errorDbConexion = false;


function calcularedad($fecha)
{
	date_default_timezone_set('America/Mexico_City');
	$cadena = explode("/",$fecha);
	$año = $cadena[2];
	$mes = $cadena[1];
	$dia = $cadena[0];
	$añoActual = date("Y");
	$mesActual = date("m"); // 12
	$diaActual = date("d"); // 31
	// pasar valores a datos enteros.
	$año = (int)$año;
	$mes = (int)$mes;
	$dia = (int)$dia;
	
	$añoActual = (int)$añoActual;
	$mesActual = (int)$mesActual;
	$diaActual = (int)$diaActual;
	//$mesActual = 12;
	//$diaActual = 31;
	
	// Calcular edad en base al año.
		$edad = $añoActual - $año;	
	//print ("Year Nacimiento - "). $año . " mes - " . $mes . " dia - " . $dia . "<br>";
	//print ("Year Actual - "). $añoActual . " mes - " . $mesActual . " dia - " . $diaActual;
	if ($año >= $añoActual){
		return $fecha;
		//echo "Ingrese una fecha menor a la actual.";
	}else{
		if ($mesActual < $mes){
			$edad--;}
		elseif($diaActual < $dia && $mesActual === $mes){
			// Preguntar por el día.
				$edad--;
		}
	}
	// Retornar la edad ya calculada
		return $edad;
}

// Calcula la edad (formato: año/mes/dia)
function edad($edad){
date_default_timezone_set('America/Mexico_City');
    list($dia,$mes,$anio) = explode("/",$edad);
    $dia_dif = date("d") - $dia;
    $mes_dif = date("m") - $mes;
    $anio_dif = date("Y") - $anio;
        
    if ($dia_dif < 0 || $mes_dif < 0)
        $anio_dif--;
        return $anio_dif;
}
////////////////////////////////////////////////////
//Convierte fecha de mysql a normal
////////////////////////////////////////////////////
function cambiaf_a_normal($fecha)
{
	if(empty($fecha)){$fecha = '2000-01-01';}
    $cad = preg_split('/ /',$fecha);
    $sub_cad = preg_split('/-/',$cad[0]);
    $fecha_formateada = $sub_cad[2].'/'.$sub_cad[1].'/'.$sub_cad[0];
    return $fecha_formateada;
}
// fecha año/mes/dia.
function fechaYMD()
{
	// Inciar variable global datos y mensajes de error.
    date_default_timezone_set('America/El_Salvador');
    $day=date("d");
    $month=date("m");
    $year=date("Y");
    $date=$day."/".$month."/".$year;
    $fecha=$year."-".$month."-".$day;
    //
    return $fecha;
}
?>