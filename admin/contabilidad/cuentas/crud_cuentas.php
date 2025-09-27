<?php
// contabilidad/cuentas/crud_cuentas.php

session_name('FactuFacil');
session_start();

// 1. VALIDACIÓN Y CONFIGURACIÓN INICIAL
if (empty($_SESSION['userNombre']) || empty($_SESSION['codigo_institucion'])) {
    header('Content-Type: application/json');
    echo json_encode(['respuesta' => false, 'mensaje' => 'Sesión o institución no válidas.']);
    exit();
}

$path_root = trim($_SERVER['DOCUMENT_ROOT']);
// Asegúrate de que esta ruta sea correcta para tu proyecto
include($path_root."/FactuFacil/includes/mainFunctions_.php"); 

// Usamos dblink, que es tu variable de conexión PDO
$pdo = $dblink; 
$codigo_institucion_activa = $_SESSION['codigo_institucion']; 

// 2. Definición de la Acción
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';


// =========================================================================
// FUNCIONES AUXILIARES Y PRINCIPALES
// =========================================================================

/**
 * Obtiene el nivel de jerarquía de la cuenta padre.
 */
function obtenerNivelPadre($pdo, $padre_id, $codigo_institucion) {
    if (empty($padre_id)) {
        return 0; // Si no tiene padre, es Nivel 1 (Grupo).
    }
    
    $sql = "SELECT nivel_jerarquia FROM cuentas_contables 
            WHERE id = :padre_id AND codigo_institucion = :codigo_institucion";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':padre_id' => $padre_id, ':codigo_institucion' => $codigo_institucion]);
    $nivel = $stmt->fetchColumn();

    return $nivel !== false ? (int)$nivel : 0; 
}

/**
 * Lógica para crear una nueva cuenta contable.
 */
function crearCuenta($pdo, $codigo_institucion_activa, $datos) {
    try {
        $codigo = trim($datos['codigo'] ?? '');
        $nombre = trim($datos['nombre'] ?? '');
        $tipo_cuenta = $datos['tipo_cuenta'] ?? '';
        $naturaleza = $datos['naturaleza'] ?? '';
        
        $padre_id = !empty($datos['padre_id']) ? (int)$datos['padre_id'] : null; 
        
        // Convertir checkboxes a booleano (1 o 0)
        $es_cuenta_mayor = isset($datos['es_cuenta_mayor']) ? 1 : 0;
        $requiere_centro_costo = isset($datos['requiere_centro_costo']) ? 1 : 0;
        
        if (empty($codigo) || empty($nombre) || empty($tipo_cuenta) || empty($naturaleza)) {
            return ['respuesta' => false, 'mensaje' => 'Faltan campos obligatorios.'];
        }

        // Calcular Nivel de Jerarquía
        $nivel_padre = obtenerNivelPadre($pdo, $padre_id, $codigo_institucion_activa);
        $nivel_jerarquia = $nivel_padre + 1;

        // Insertar
        $sql = "INSERT INTO cuentas_contables (
                    codigo, nombre, tipo_cuenta, nivel_jerarquia, naturaleza, 
                    requiere_centro_costo, es_cuenta_mayor, padre_id, codigo_institucion)
                VALUES (
                    :codigo, :nombre, :tipo_cuenta, :nivel_jerarquia, :naturaleza, 
                    :centro_costo, :es_cuenta_mayor, :padre_id, :codigo_institucion)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':codigo'               => $codigo,
            ':nombre'               => $nombre,
            ':tipo_cuenta'          => $tipo_cuenta,
            ':nivel_jerarquia'      => $nivel_jerarquia,
            ':naturaleza'           => $naturaleza,
            ':centro_costo'         => $requiere_centro_costo,
            ':es_cuenta_mayor'      => $es_cuenta_mayor,
            ':padre_id'             => $padre_id,
            ':codigo_institucion'   => $codigo_institucion_activa
        ]);

        return ['respuesta' => true, 'mensaje' => 'Cuenta contable creada con éxito.'];

    } catch (PDOException $e) {
        $mensaje_error = 'Error al guardar la cuenta: ' . $e->getMessage();
        if (strpos($e->getMessage(), 'unique_codigo_institucion') !== false) {
             $mensaje_error = 'Error: El código de cuenta ya existe para esta institución.';
        }
        return ['respuesta' => false, 'mensaje' => $mensaje_error];
    }
}

/**
 * Lista todas las cuentas para DataTables.
 */
function listarCuentas($pdo, $codigo_institucion) {
    try {
        $stmt = $pdo->prepare("SELECT id, codigo, nombre, tipo_cuenta, nivel_jerarquia, naturaleza, es_cuenta_mayor, requiere_centro_costo, estado 
                               FROM cuentas_contables 
                               WHERE codigo_institucion = :codigo 
                               ORDER BY codigo ASC");
        
        $stmt->execute([':codigo' => $codigo_institucion]);
        $cuentas = $stmt->fetchAll();
        
        header('Content-Type: application/json');
        echo json_encode(['data' => $cuentas]); 

    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['data' => [], 'error' => $e->getMessage()]);
    }
}

/**
 * Lista cuentas marcadas como 'es_cuenta_mayor' para el select de Cuenta Padre.
 */
function listarCuentasPadre($pdo, $codigo_institucion_activa) {
    try {
        $sql = "SELECT id, codigo, nombre, nivel_jerarquia FROM cuentas_contables 
                WHERE codigo_institucion = :codigo AND es_cuenta_mayor = TRUE 
                ORDER BY codigo ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':codigo' => $codigo_institucion_activa]);
        $padres = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode(['respuesta' => true, 'padres' => $padres]);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['respuesta' => false, 'mensaje' => 'Error al cargar cuentas padre: ' . $e->getMessage()]);
    }
}

/**
 * Lógica para clonar el catálogo base a la institución actual.
 */
function clonarCatalogoBase($pdo, $codigo_institucion_destino) {
    $codigo_institucion_modelo = 'MODEL'; // Código de la institución plantilla

    try {
        // 1. Verificar si la institución ya tiene cuentas
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cuentas_contables WHERE codigo_institucion = :codigo");
        $stmt->execute([':codigo' => $codigo_institucion_destino]);
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Esta institución ya tiene un catálogo de cuentas creado.'];
        }

        $pdo->beginTransaction();

        // 2. Obtener todas las cuentas del modelo base
        $stmt = $pdo->prepare("SELECT * FROM cuentas_contables WHERE codigo_institucion = :modelo ORDER BY codigo ASC");
        $stmt->execute([':modelo' => $codigo_institucion_modelo]);
        $cuentas_modelo = $stmt->fetchAll();

        $mapa_ids = []; 

        // 3. Iterar e insertar, mapeando IDs para mantener la jerarquía
        foreach ($cuentas_modelo as $cuenta) {
            
            $nuevo_padre_id = null;
            if ($cuenta['padre_id'] !== null && isset($mapa_ids[$cuenta['padre_id']])) {
                $nuevo_padre_id = $mapa_ids[$cuenta['padre_id']];
            }

                // --- SOLUCIÓN: Conversión explícita de valores booleanos ---
                // Aseguramos que los valores sean 1 (TRUE) o 0 (FALSE)
                // Usamos (int) para convertir el valor de la DB (que puede ser un string 't', 'f', o una representación que PHP entiende como truthy/falsy)
                $centro_costo_val = (int)($cuenta['requiere_centro_costo'] ?? 0);
                $es_mayor_val = (int)($cuenta['es_cuenta_mayor'] ?? 0);
                $estado_val = (int)($cuenta['estado'] ?? 1); // Asumimos 1 (TRUE) si es nulo


            $stmt_insert = $pdo->prepare("INSERT INTO cuentas_contables (
                codigo, nombre, tipo_cuenta, nivel_jerarquia, naturaleza, 
                requiere_centro_costo, es_cuenta_mayor, padre_id, codigo_institucion, estado) 
                VALUES (:codigo, :nombre, :tipo, :nivel, :naturaleza, 
                        :centro_costo, :es_mayor, :padre_id, :institucion, :estado)
                RETURNING id"); 

            $stmt_insert->execute([
                ':codigo'         => $cuenta['codigo'],
                ':nombre'         => $cuenta['nombre'],
                ':tipo'           => $cuenta['tipo_cuenta'],
                ':nivel'          => $cuenta['nivel_jerarquia'],
                ':naturaleza'     => $cuenta['naturaleza'],
                
                // Usar los valores convertidos
                ':centro_costo'   => $centro_costo_val,
                ':es_mayor'       => $es_mayor_val,
                ':estado'         => $estado_val, 
                
                ':padre_id'       => $nuevo_padre_id,
                ':institucion'    => $codigo_institucion_destino
                // (Asegúrate de que la lista de parámetros coincida con la lista de valores, 
                // eliminé la repetición del :estado que estaba antes en la lista)
            ]);

            $nuevo_id = $stmt_insert->fetchColumn();
            $mapa_ids[$cuenta['id']] = $nuevo_id; // Actualiza el mapa
        }

        $pdo->commit();
        return ['success' => true, 'message' => 'Catálogo base clonado con éxito.'];

    } catch (PDOException $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Error al clonar el catálogo: ' . $e->getMessage()];
    }
}

function obtenerCuentaPorId($pdo, $id, $codigo_institucion_activa) {
    try {
        $sql = "SELECT * FROM cuentas_contables 
                WHERE id = :id AND codigo_institucion = :codigo_institucion";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id, ':codigo_institucion' => $codigo_institucion_activa]);
        $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cuenta) {
            return ['respuesta' => true, 'cuenta' => $cuenta];
        } else {
            return ['respuesta' => false, 'mensaje' => 'Cuenta no encontrada.'];
        }
    } catch (PDOException $e) {
        return ['respuesta' => false, 'mensaje' => 'Error al obtener cuenta: ' . $e->getMessage()];
    }
}

/**
 * Lógica para actualizar una cuenta contable existente.
 */
function actualizarCuenta($pdo, $codigo_institucion_activa, $datos) {
    try {
        $id = (int)$datos['id'];
        $codigo = trim($datos['codigo'] ?? '');
        $nombre = trim($datos['nombre'] ?? '');
        $tipo_cuenta = $datos['tipo_cuenta'] ?? '';
        $naturaleza = $datos['naturaleza'] ?? '';
        
        $padre_id = !empty($datos['padre_id']) ? (int)$datos['padre_id'] : null; 
        $es_cuenta_mayor = isset($datos['es_cuenta_mayor']) ? 1 : 0;
        $requiere_centro_costo = isset($datos['requiere_centro_costo']) ? 1 : 0;

        if (empty($id) || empty($codigo) || empty($nombre) || empty($tipo_cuenta) || empty($naturaleza)) {
            return ['respuesta' => false, 'mensaje' => 'Faltan campos obligatorios para la actualización.'];
        }

        // Calcular Nivel de Jerarquía (si el padre_id cambió)
        $nivel_padre = obtenerNivelPadre($pdo, $padre_id, $codigo_institucion_activa);
        $nivel_jerarquia = $nivel_padre + 1;

        $sql = "UPDATE cuentas_contables SET
                    codigo = :codigo, nombre = :nombre, tipo_cuenta = :tipo_cuenta, 
                    nivel_jerarquia = :nivel_jerarquia, naturaleza = :naturaleza, 
                    requiere_centro_costo = :centro_costo, es_cuenta_mayor = :es_cuenta_mayor, 
                    padre_id = :padre_id
                WHERE id = :id AND codigo_institucion = :codigo_institucion";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id'                   => $id,
            ':codigo'               => $codigo,
            ':nombre'               => $nombre,
            ':tipo_cuenta'          => $tipo_cuenta,
            ':nivel_jerarquia'      => $nivel_jerarquia,
            ':naturaleza'           => $naturaleza,
            ':centro_costo'         => $requiere_centro_costo,
            ':es_cuenta_mayor'      => $es_cuenta_mayor,
            ':padre_id'             => $padre_id,
            ':codigo_institucion'   => $codigo_institucion_activa
        ]);

        return ['respuesta' => true, 'mensaje' => 'Cuenta contable actualizada con éxito.'];

    } catch (PDOException $e) {
        $mensaje_error = 'Error al actualizar: ' . $e->getMessage();
        if (strpos($e->getMessage(), 'unique_codigo_institucion') !== false) {
             $mensaje_error = 'Error: El código de cuenta ya existe para esta institución.';
        }
        return ['respuesta' => false, 'mensaje' => $mensaje_error];
    }
}

/**
 * Lógica para eliminar una cuenta.
 */
function eliminarCuenta($pdo, $id, $codigo_institucion_activa) {
    try {
        $id = (int)$id;
        
        // **VALIDACIÓN CRÍTICA: NO ELIMINAR CUENTAS QUE SON PADRE**
        $sql_check = "SELECT COUNT(*) FROM cuentas_contables WHERE padre_id = :id";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute([':id' => $id]);
        
        if ($stmt_check->fetchColumn() > 0) {
            return ['respuesta' => false, 'mensaje' => 'No se puede eliminar. Esta cuenta es padre de otras cuentas. Elimine las cuentas hijas primero.'];
        }

        $sql = "DELETE FROM cuentas_contables 
                WHERE id = :id AND codigo_institucion = :codigo_institucion";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id, ':codigo_institucion' => $codigo_institucion_activa]);

        if ($stmt->rowCount() > 0) {
            return ['respuesta' => true, 'mensaje' => 'Cuenta contable eliminada.'];
        } else {
            return ['respuesta' => false, 'mensaje' => 'No se encontró la cuenta para eliminar o no se permitió la operación.'];
        }
    } catch (PDOException $e) {
        // Validación de integridad referencial (ej: si hay asientos contables asociados)
        if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
            return ['respuesta' => false, 'mensaje' => 'Error: Esta cuenta tiene registros asociados (ej. asientos contables) y no puede ser eliminada.'];
        }
        return ['respuesta' => false, 'mensaje' => 'Error al eliminar la cuenta: ' . $e->getMessage()];
    }
}


/**
 * Lista todas las cuentas para el detalle de asientos.
 */
function listarTodasCuentas($pdo, $codigo_institucion) {
    try {
        $stmt = $pdo->prepare("SELECT id, codigo, nombre, nivel_jerarquia 
                               FROM cuentas_contables 
                               WHERE codigo_institucion = :codigo 
                               ORDER BY codigo ASC");
        
        $stmt->execute([':codigo' => $codigo_institucion]);
        $cuentas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Retorna el array de cuentas
        header('Content-Type: application/json');
        // Usamos 'data' para el mismo formato que usa DataTables o listados generales
        echo json_encode(['respuesta' => true, 'data' => $cuentas]); 

    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['respuesta' => false, 'mensaje' => 'Error al listar todas las cuentas: ' . $e->getMessage()]);
    }
}


// =========================================================================
// SWITCH DE ACCIONES (ACTUALIZADO)
// =========================================================================

switch ($accion) {
    case 'list':
        listarCuentas($pdo, $codigo_institucion_activa);
        break;
        
    case 'list_parents':
        listarCuentasPadre($pdo, $codigo_institucion_activa);
        break;
        
    case 'create':
        $data = $_POST; 
        $resultado = crearCuenta($pdo, $codigo_institucion_activa, $data);
        header('Content-Type: application/json');
        echo json_encode($resultado);
        break;
        
    case 'clone_base':
        // La acción clone_base siempre debe ser POST, pero la verificamos en GET para simplificar
        $resultado = clonarCatalogoBase($pdo, $codigo_institucion_activa);
        header('Content-Type: application/json');
        echo json_encode($resultado);
        break;

    case 'get':
        $id = $_GET['id'] ?? '';
        $resultado = obtenerCuentaPorId($pdo, $id, $codigo_institucion_activa);
        header('Content-Type: application/json');
        echo json_encode($resultado);
        break;
        
    case 'update':
        $data = $_POST; 
        $resultado = actualizarCuenta($pdo, $codigo_institucion_activa, $data);
        header('Content-Type: application/json');
        echo json_encode($resultado);
        break;
        
    case 'delete':
        $id = $_POST['id'] ?? '';
        $resultado = eliminarCuenta($pdo, $id, $codigo_institucion_activa);
        header('Content-Type: application/json');
        echo json_encode($resultado);
        break;
        
    case 'list_all_cuentas':
        listarTodasCuentas($pdo, $codigo_institucion_activa);
        break;
    default:
        echo json_encode(['respuesta' => false, 'mensaje' => 'Acción no válida.']);
        break;
}

?>