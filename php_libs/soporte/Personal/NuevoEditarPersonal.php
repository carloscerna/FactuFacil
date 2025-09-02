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
$fila_array = 0;
// ruta de los archivos con su carpeta
    $path_root=trim($_SERVER['DOCUMENT_ROOT']);    
// Incluimos el archivo de funciones y conexi�n a la base de datos
	include($path_root."/FactuFacil/includes/funciones.php");
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
			case "GenerarCodigoNuevo":
				$ann_ = substr(trim($_POST['ann']),2,2);	// Año en Curso. pasar a dos digitos.
					$query = "SELECT id_personal FROM personal WHERE  = '$ann_'
							ORDER BY codigo_empleado_numero_entero DESC LIMIT 1";
				// Ejecutamos el Query.
				$consulta = $dblink -> query($query);
				// Verificar si existen registros.
				if($consulta -> rowCount() != 0){
					$respuestaOK = true;
					// convertimos el objeto
					while($listado = $consulta -> fetch(PDO::FETCH_BOTH))
					{
						$codigo_entero = trim($listado['codigo_empleado_numero_entero']) + 1;
						$codigo_string = (string) $codigo_entero;
						//$codigo_nuevo = codigos_nuevos($codigo_string);
						// Generar el Código
						$codigo_nuevo = $codigo_nuevo . $ann_;
						// retornar variable que contendrá el nuevo código.
						$datos[$fila_array]["codigo_nuevo"] = $codigo_nuevo;
					}
					$mensajeError = "Nuevo Código: " . $codigo_nuevo . "Generado.";
				}
				else{
						// Generar el Código
						$codigo_nuevo = "001" . $ann_;
						// retornar variable que contendrá el nuevo código.
						$datos[$fila_array]["codigo_nuevo"] = $codigo_nuevo;
							$respuestaOK = true;
							$contenidoOK = '';
							$mensajeError =  'No hay Generación de Código.';
				}
			break;
			case 'BuscarCodigo':
				$codigo = trim($_POST['codigo']);
				// Armamos el query.
				$query = "SELECT id_personal, codigo FROM personal WHERE codigo = '$codigo'";
				// Ejecutamos el Query.
				$consulta = $dblink -> query($query);
				// Verificar la consulta
					if($consulta -> rowCount() != 0){
						$respuestaOK = true;
						$contenidoOK = "";
						$mensajeError =  'El Código Ya Existe.';
					}else{
						$respuestaOK = false;
						$contenidoOK = "";
						$mensajeError =  'El Código, no existe puede Continuar...';					
						}
			break;
			case 'BuscarPorId':
				$id_ = trim($_POST['id_x']);
				// Armamos el query.
				$query = "SELECT p.id_personal, TRIM(p.nombres) as nombre, TRIM(p.apellidos) as apellido, btrim(p.nombres || CAST(' ' AS VARCHAR) || p.apellidos) AS nombre_empleado, p.telefono_residencia, p.telefono_celular,
					p.fecha_nacimiento, p.edad, p.codigo_estatus, p.codigo_municipio, p.codigo_departamento, p.telefono_residencia, p.direccion, p.foto, p.codigo_genero, p.codigo_estado_civil, p.correo_electronico,
					p.tipo_sangre, p.codigo_estudio, p.codigo_vivienda, p.codigo_afp, p.nombre_conyuge,
					p.codigo_cargo, p.fecha_ingreso, p.fecha_retiro, 
					p.numero_cuenta,
					p.codigo_tipo_licencia, p.licencia, p.dui, p.nit, p.isss, p.afp, 
					p.comentario
						FROM personal p
							WHERE id_personal = '$id_'
								ORDER BY nombre_empleado"
						;
				// Ejecutamos el Query.
				$consulta = $dblink -> query($query);
				// Validar si hay registros.
				if($consulta -> rowCount() != 0){
					$respuestaOK = true;
					$num = 0;
					// convertimos el objeto
					while($listado = $consulta -> fetch(PDO::FETCH_BOTH))
					{
						// Nombres de los campos de la tabla.
							$nombre_empleado = trim($listado['nombre_empleado']);
							$nombre = trim($listado['nombre']);
							$apellido = trim($listado['apellido']);
							//
                            $fecha_nacimiento = trim($listado['fecha_nacimiento']);
							$edad = trim($listado['edad']);
							$codigo_genero = trim($listado['codigo_genero']);
							$codigo_estado_civil = trim($listado['codigo_estado_civil']);
							$tipo_sangre = trim($listado['tipo_sangre']);
							$codigo_estudio = trim($listado['codigo_estudio']);
							$codigo_vivienda = trim($listado['codigo_vivienda']);
							$codigo_afp = trim($listado['codigo_afp']);
							$nombre_conyuge = trim($listado['nombre_conyuge']);
							//
							$codigo_municipio = trim($listado['codigo_municipio']);
                            $codigo_departamento = trim($listado['codigo_departamento']);
							$direccion = trim($listado['direccion']);
                            $telefono_fijo = trim($listado['telefono_residencia']);
                            $telefono_movil = trim($listado['telefono_celular']);
							$correo_electronico = trim($listado['correo_electronico']);
							//
							$codigo_cargo = trim($listado['codigo_cargo']);
							$fecha_ingreso = trim($listado['fecha_ingreso']);
							$fecha_retiro = trim($listado['fecha_retiro']);
							//
							$dui = trim($listado['dui']);
							$isss = trim($listado['isss']);
							$nit = trim($listado['nit']);
							$afp = trim($listado['afp']);
							
							$comentario = trim($listado['comentario']);
						//	Logo.
							$url_foto = trim($listado['foto']);
						// Rellenando la array.
							$datos[$fila_array]["nombre_empleado"] = $nombre_empleado;
							$datos[$fila_array]["nombre"] = $nombre;
							$datos[$fila_array]["apellido"] = $apellido;
							//
							$datos[$fila_array]["fecha_nacimiento"] = $fecha_nacimiento;
							$datos[$fila_array]["edad"] = $edad;							
							$datos[$fila_array]["codigo_genero"] = $codigo_genero;
                            $datos[$fila_array]["codigo_estado_civil"] = $codigo_estado_civil;
							$datos[$fila_array]["tipo_sangre"] = $tipo_sangre;
							$datos[$fila_array]["codigo_estudio"] = $codigo_estudio;
							$datos[$fila_array]["codigo_vivienda"] = $codigo_vivienda;
							$datos[$fila_array]["codigo_afp"] = $codigo_afp;
							$datos[$fila_array]["nombre_conyuge"] = $nombre_conyuge;
							//
							$datos[$fila_array]["codigo_municipio"] = $codigo_municipio;
							$datos[$fila_array]["codigo_departamento"] = $codigo_departamento;
							$datos[$fila_array]["direccion"] = $direccion;
                            $datos[$fila_array]["telefono_fijo"] = $telefono_fijo;
                            $datos[$fila_array]["telefono_movil"] = $telefono_movil;
							$datos[$fila_array]["correo_electronico"] = $correo_electronico;
							//
							$datos[$fila_array]["codigo_cargo"] = $codigo_cargo;
							$datos[$fila_array]["fecha_ingreso"] = $fecha_ingreso;
							$datos[$fila_array]["fecha_retiro"] = $fecha_retiro;
							//
							$datos[$fila_array]["dui"] = $dui;
							$datos[$fila_array]["isss"] = $isss;
							$datos[$fila_array]["nip"] = $nip;
							$datos[$fila_array]["nit"] = $nit;
							$datos[$fila_array]["afp"] = $afp;
							$datos[$fila_array]["comentario"] = $comentario;
							//
							$datos[$fila_array]["url_foto"] = $url_foto;
					}
					$mensajeError = "Si Registro";
				}
				else{
					$respuestaOK = true;
					$contenidoOK = '';
					$mensajeError =  'No Registro';
				}
			break;
			case 'AgregarNuevoPersonal':		
				// armar variables.
					// INFO 1
				//	$codigo_personal = trim($_POST['txtcodigo']);
					$codigo_estatus = trim($_POST['lstestatus']);
					
					$nombre = trim($_POST['txtnombres']);
					$apellido = trim($_POST['txtapellido']);
					$fecha_nacimiento = trim($_POST['fechanacimiento']);
					$edad = trim($_POST['txtedad']);
					$codigo_genero = trim($_POST['lstgenero']);
					$codigo_estado_civil = trim($_POST['lstEstadoCivil']);
					$tipo_sangre = htmlspecialchars(trim($_POST['txtTipoSangre']),ENT_QUOTES,'UTF-8');
					$codigo_estudios = trim($_POST['lstEstudios']);
					$codigo_tipo_vivienda = trim($_POST['lstVivienda']);
					$codigo_afp = trim($_POST['lstAfp']);
					$nombre_conyuge = htmlspecialchars(trim($_POST['txtConyuge']),ENT_QUOTES,'UTF-8');

					$codigo_departamento = trim($_POST['lstdepartamento']);
					$codigo_municipio = trim($_POST['lstmunicipio']);
					$direccion = htmlspecialchars(trim($_POST['direccion']),ENT_QUOTES,'UTF-8');

					$telefono_fijo = trim($_POST['telefono_fijo']);
					$telefono_movil = trim($_POST['telefono_movil']);
					$correo_electronico = trim($_POST['correo_electronico']);	

					$codigo_cargo = trim($_POST['lstCargo']);
					$fecha_ingreso = trim($_POST['txtFechaIngreso']);
					$fecha_retiro = trim($_POST['txtFechaRetiro']);

					
					$dui = trim($_POST['txtDui']);
					$isss = trim($_POST['txtIsss']);
					$nit = trim($_POST['txtNit']);
					
					$comentario = htmlspecialchars(trim($_POST['txtComentario']));
				// Query
					$query = "INSERT INTO personal (nombres, apellidos, fecha_nacimiento, edad, codigo_genero,
						codigo_estado_civil, tipo_sangre, codigo_estudio, 
						codigo_vivienda, codigo_afp, nombre_conyuge,
						codigo_municipio, codigo_departamento, direccion,
						telefono_residencia, telefono_celular, 
						correo_electronico, codigo_cargo, fecha_ingreso, 
						fecha_retiro, dui, nit, afp, 
						codigo_estatus)
						VALUES ('$nombre', '$apellido', '$fecha_nacimiento', '$edad', '$codigo_genero',
							'$codigo_estado_civil', '$tipo_sangre', '$codigo_estudios', 
							'$codigo_tipo_vivienda', '$codigo_afp', '$nombre_conyuge',
							'$codigo_municipio', '$codigo_departamento', '$direccion',
							'$telefono_fijo', '$telefono_movil', 
							'$correo_electronico', '$codigo_cargo', '$fecha_ingreso', 
							'$fecha_retiro',
							'$dui','$nit','$afp', 
							'$codigo_estatus')";
					// Ejecutamos el query
						$resultadoQuery = $dblink -> query($query);              
                        ///////////////////////////////////////////////////////////////////////////////////////
                        ///////////////////////////////////////////////////////////////////////////////////////
					if($resultadoQuery == true){
						$respuestaOK = true;
						$mensajeError = "Se ha agregado el registro correctamente";
						$contenidoOK = '';
					}
					else{
						$mensajeError = "No se puede guardar el registro en la base de datos ".$query;
					}
			break;
			case 'EditarRegistro':
				// INFO 1
					$codigo_personal_2 = trim($_POST['txtId']);
					$codigo_personal = trim($_POST['id_user']);
					$codigo_estatus = trim($_POST['lstestatus']);
					$nombre = trim($_POST['txtnombres']);
					$apellido = trim($_POST['txtapellido']);
					$fecha_nacimiento = trim($_POST['fechanacimiento']);
					$edad = trim($_POST['txtedad']);
					$codigo_genero = trim($_POST['lstgenero']);
					$codigo_estado_civil = trim($_POST['lstEstadoCivil']);
					$tipo_sangre = htmlspecialchars(trim($_POST['txtTipoSangre']),ENT_QUOTES,'UTF-8');
					$codigo_estudios = trim($_POST['lstEstudios']);
					$codigo_tipo_vivienda = trim($_POST['lstVivienda']);
					$codigo_afp = trim($_POST['lstAfp']);
					$nombre_conyuge = htmlspecialchars(trim($_POST['txtConyuge']),ENT_QUOTES,'UTF-8');

					$codigo_departamento = trim($_POST['lstdepartamento']);
					$codigo_municipio = trim($_POST['lstmunicipio']);
					$direccion = htmlspecialchars(trim($_POST['direccion']),ENT_QUOTES,'UTF-8');
                
                    $telefono_fijo = trim($_POST['telefono_fijo']);
                    $telefono_movil = trim($_POST['telefono_movil']);
					$correo_electronico = trim($_POST['correo_electronico']);	
					
					$codigo_cargo = trim($_POST['lstCargo']);
					$fecha_ingreso = trim($_POST['txtFechaIngreso']);
					$fecha_retiro = trim($_POST['txtFechaRetiro']);

					$dui = trim($_POST['txtDui']);
					$nip = trim($_POST['txtNip']);
					$isss = trim($_POST['txtIsss']);
					$nit = trim($_POST['txtNit']);
					
					$comentario = htmlspecialchars(trim($_POST['txtComentario']));
					//$ = htmlspecialchars(trim($_POST['']),ENT_QUOTES,'UTF-8');
					//$ = trim($_POST['']);
                // Query
					// QUERY UPDATE.
						$query_usuario = sprintf("UPDATE personal SET nombres = '%s', apellidos = '%s', fecha_nacimiento = '%s', edad = '%s', codigo_genero = '%s',
							codigo_estado_civil = '%s', tipo_sangre = '%s', codigo_estudio = '%s', codigo_vivienda = '%s', codigo_afp = '%s', nombre_conyuge = '%s',
							codigo_municipio = '%s', codigo_departamento = '%s', direccion = '%s',
							telefono_residencia = '%s', telefono_celular = '%s', correo_electronico = '%s',
							codigo_cargo = '%s', fecha_ingreso = '%s', fecha_retiro = '%s', 
							dui = '%s', nit = '%s', isss = '%s', afp = '%s', 
							comentario = '%s', codigo_estatus = '%s'
							WHERE id_personal = %d",
							$nombre, $apellido, $fecha_nacimiento, $edad, $codigo_genero, $codigo_estado_civil, $tipo_sangre, $codigo_estudios, $codigo_tipo_vivienda, $codigo_afp, $nombre_conyuge,
							$codigo_municipio, $codigo_departamento, $direccion,
							$telefono_fijo, $telefono_movil, $correo_electronico,
							$codigo_cargo, $fecha_ingreso, $fecha_retiro, 
							$dui, $nit, $isss, $afp, 
							$comentario, $codigo_estatus,
                            $codigo_personal);	

						// Ejecutamos el query guardar los datos en la tabla alumno..
						$resultadoQuery = $dblink -> query($query_usuario);
						// ACTUALIZAR SI EL DOCENTE ES DIRECTOR.
							$codigo_institucion = $_SESSION['codigo_institucion'];
							$codigo_personal = strval($codigo_personal);
							if($codigo_cargo == "01"){
								$query_actualizar_institucion = "UPDATE informacion_institucion set nombre_director = '$codigo_personal' WHERE codigo_institucion = '$codigo_institucion'";
								$resultadoQuery = $dblink -> query($query_actualizar_institucion);
							}
						
					if($resultadoQuery == true){
						$respuestaOK = true;
						$mensajeError = "Se ha Actualizado el registro correctamente";
						$contenidoOK = $query_usuario.$codigo_personal;
					}
					else{
						$mensajeError = "No se puede Actualizar el registro en la base de datos ";
						$contenidoOK = $query_usuario.$codigo_personal;
					}
			break;
			case 'BuscarCodigos':
				$codigo = trim($_POST['codigo']);
				// Armamos el query.
				$query = "SELECT id_personal, codigo FROM personal WHERE codigo = '$codigo'";
				// Ejecutamos el Query.
				$consulta = $dblink -> query($query);
				// Verificar la consulta
					if($consulta -> rowCount() != 0){
						$respuestaOK = true;
						$contenidoOK = "";
						$mensajeError =  'Si Registro';
					}else{
						$respuestaOK = false;
						$contenidoOK = "";
						$mensajeError =  'No Registro';					
						}
			break;
			case 'EliminarRegistro':
				$codigo_personal = $_POST['id_user'];
				// Buscar si existen registros en tabla PERSONAL LICENCIAS Y PERMISOS.
					$query_buscar_personal_licencias = "SELECT * FROM personal_licencias_permisos where codigo_personal = $codigo_personal";
					// Ejecutamos el Query.
					$consulta_personal_licencias_permisos = $dblink -> query($query_buscar_personal_licencias);
					// Verificar la consulta
						if($consulta_personal_licencias_permisos -> rowCount() != 0){
							$respuestaOK = true;
							$contenidoOK = "";
							$mensajeError =  'No se puede Eliminar, Tiene registros en Licencias y permisos.';
						}else{
							// Armamos el query
								$query = "DELETE FROM personal WHERE id_personal = $codigo_personal";

								// Ejecutamos el query
									$count = $dblink -> exec($query);
								
								// Validamos que se haya actualizado el registro
								if($count != 0){
									$respuestaOK = true;
									$mensajeError = 'Se ha Eliminado '.$count.' Registro(s).';

									$contenidoOK = $query;

								}else{
									$mensajeError = 'No se ha eliminado el registro';
								}
							}
			break;
            default:
				$mensajeError = 'Esta acción no se encuentra disponible';
			break;
		}
	}
	else{
		$mensajeError = 'No se puede ejecutar la aplicación';}
}
else{
	$mensajeError = 'No se puede establecer conexión con la base de datos';}
// Salida de la Array con JSON.
	if($_POST["accion"] === "" or $_POST["accion"] === "BuscarTodosCodigo"){
		echo json_encode($arreglo);	
	}elseif($_POST["accion"] === "BuscarCodigo1" or $_POST["accion"] === "GenerarCodigoNuevo" or $_POST["accion"] === "BuscarPorId"){
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