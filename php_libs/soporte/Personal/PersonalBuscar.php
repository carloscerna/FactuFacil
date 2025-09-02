<?php
//session_name('demoUI');
//session_start();
// limpiar cache.
clearstatcache();
// Script para ejecutar AJAX
// cambiar a utf-8.
header("Content-Type: text/html;charset=iso-8859-1");
// Insertar y actualizar tabla de usuarios
sleep(0);

// Inicializamos variables de mensajes y JSON
$respuestaOK = false;
$mensajeError = "No se puede ejecutar la aplicaci�n";
$contenidoOK = "";
$lista = "";
$arreglo = array();
$datos = array();
// ruta de los archivos con su carpeta
    $path_root=trim($_SERVER['DOCUMENT_ROOT']);
    
// Incluimos el archivo de funciones y conexi�n a la base de datos

include($path_root."/FactuFacil/includes/mainFunctions_.php");

// Validar conexi�n con la base de datos
if($errorDbConexion == false){
	// Validamos qe existan las variables post
	if(isset($_POST) && !empty($_POST)){
		if(!empty($_POST['accion_buscar'])){
			$_POST['accion'] = $_POST['accion_buscar'];
		}
		// Verificamos las variables de acci�n
		switch ($_POST['accion']) {
		case 'BuscarTodos':
				// Armamos el query.
				$query = "SELECT p.id_personal, p.codigo_estatus, cat_cargo.descripcion, btrim(p.nombres || CAST(' ' AS VARCHAR) || p.apellidos) AS nombre_empleado, p.telefono_celular,
							to_char(p.fecha_nacimiento,'dd/mm/yyyy') as fecha_nacimiento, p.edad, 
							p.dui, p.nit, p.isss
                                FROM personal p
									INNER JOIN catalogo_cargo cat_cargo ON cat_cargo.codigo = p.codigo_cargo
                                    WHERE p.codigo_cargo <> ''
                                        ORDER BY p.codigo_estatus ASC, p.id_personal DESC
						";
                /*$query = "SELECT p.id_personal, p.codigo, btrim(p.nombres || CAST(' ' AS VARCHAR) || p.apellidos) AS nombre_empleado, p.telefono_residencia, telefono_celular,
                            to_char(p.fecha_nacimiento,'dd/mm/yyyy') as fecha_nacimiento, p.edad, p.codigo_estatus,
                            (SELECT SUM(fianza)-SUM(devolucion) as saldo_fianza from fianzas where codigo = p.codigo),
                            (SELECT SUM(prestamos)-SUM(descuentos) as saldo_prestamo from prestamos where codigo = p.codigo)
                                FROM personal p
                                    WHERE p.codigo <> '' and p.codigo_estatus = '01'
                                        ORDER BY nombre_empleado
						";*/
				// Ejecutamos el Query.
				$consulta = $dblink -> query($query);
				// Validar si hay registros.
				if($consulta -> rowCount() != 0){
					$respuestaOK = true;
					$num = 0;
					// convertimos el objeto
					while($listado = $consulta -> fetch(PDO::FETCH_BOTH))
					{
						$arreglo["data"][] = $listado;						
					}
					$mensajeError = "Si Registro";
				}
				else{
					$respuestaOK = true;
					$contenidoOK = '';
					$mensajeError =  'No Registro';
					$arreglo["data"][] = '{"sEcho":1,
											"sEcho":1,
											"iTotalRecords":"0",
											"iTotalDisplayRecords":"0",
											"aaData":[]
										}';						
				}
			break;

			case 'eliminarA':
				// Armamos el query
				//$query = "DELETE FROM alumno WHERE id_alumno = $_POST[id_user]";

				// Ejecutamos el query
					$count = $dblink -> exec($query);
				
				// Validamos que se haya actualizado el registro
				if($count != 0){
					$respuestaOK = true;
					$mensajeError = 'Se ha eliminado el registro correctamente'.$query;

					$contenidoOK = 'Se ha Eliminado '.$count.' Registro(s).';

				}else{
					$mensajeError = 'No se ha eliminado el registro'.$query;
				}
			break;
        
			default:
				$mensajeError = 'Esta acci�n no se encuentra disponible';
			break;
		}
	}
	else{
		$mensajeError = 'No se puede ejecutar la aplicaci�n';}
}
else{
	$mensajeError = 'No se puede establecer conexi�n con la base de datos';}
// Salida de la Array con JSON.
	if($_POST["accion"] === "BuscarTodos" or $_POST["accion"] === "BuscarTodosCodigo"){
		echo json_encode($arreglo);	
	}elseif($_POST["accion"] === "BuscarCodigo" or $_POST["accion"] === "GenerarCodigoNuevo" or $_POST["accion"] === "EditarRegistro"){
		echo json_encode($datos);
		}
	else{
		// Armamos array para convertir a JSON
		$salidaJson = array("respuesta" => $respuestaOK,
			"mensaje" => $mensajeError,
			"contenido" => $contenidoOK);
		echo json_encode($salidaJson);
	}
?>