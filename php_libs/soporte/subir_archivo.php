<?php
// ruta de los archivos con su carpeta
    $path_root=trim($_SERVER['DOCUMENT_ROOT']);
// archivos que se incluyen.
	include($path_root."/FactuFacil/includes/funciones_2.php");
	$ruta = '../files/'; //Decalaramos una variable con la ruta en donde almacenaremos los archivos
	$mensage = '';//Declaramos una variable mensaje quue almacenara el resultado de las operaciones.
foreach ($_FILES as $key) //Iteramos el arreglo de archivos
{	

	if($key['error'] == UPLOAD_ERR_OK )//Si el archivo se paso correctamente Ccontinuamos 
		{
			$NombreOriginal = replace_3($key['name']);//Obtenemos el nombre original del archivo
			$temporal = $key['tmp_name']; //Obtenemos la ruta Original del archivo
			$Destino = $ruta.$NombreOriginal;	//Creamos una ruta de destino con la variable ruta y el nombre original del archivo
			$tamaño = $key['size'];

			move_uploaded_file($temporal, $Destino); //Movemos el archivo temporal a la ruta especificada
			chmod($Destino,07777);
		}

	if ($key['error']=="") //Si no existio ningun error, retornamos un mensaje por cada archivo subido
		{
			$NombreOriginal = $key['name'];//Obtenemos el nombre original del archivo
			$mensage .= 'Archivo <b>'.$NombreOriginal.'</b> Subido correctamente. <br>';
		}
	if ($key['error']!="")//Si existio algún error retornamos un el error por cada archivo.
		{
			$key['error'] == UPLOAD_ERR_OK;
			$NombreOriginal = $key['name'];//Obtenemos el nombre original del archivo
			$mensage .= 'No se pudo subir el archivo <b>'.$NombreOriginal.'</b> debido al siguiente Error: \n'.$key['error']; 
		}
}
echo $mensage;// Regresamos los mensajes generados al cliente