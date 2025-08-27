<?php
session_name('FactuFacil');
session_start();

// Asegurarse de que el usuario está logueado
if (!isset($_SESSION['userLogin']) || $_SESSION['userLogin'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autenticado.']);
    exit();
}

// Incluir la conexión a la base de datos
// Ajusta la ruta si es diferente en tu configuración
// Usando $_SESSION['path_root'] si está disponible, o asumiendo una ruta relativa.
if (!isset($_SESSION['path_root'])) {
    // Si $_SESSION['path_root'] no está definido, intenta una ruta relativa o absoluta conocida.
    // Esto es una salvaguarda, lo ideal es que 'path_root' esté bien configurado en tu login.php o index.php
    $_SESSION['path_root'] = trim($_SERVER['DOCUMENT_ROOT']) . "/FactuFacil";
}
// ruta de los archivos con su carpeta
$path_root=trim($_SERVER['DOCUMENT_ROOT']);
    
// Incluimos el archivo de funciones y conexi�n a la base de datos

include($path_root."/FactuFacil/includes/mainFunctions_.php");

$codigo_perfil = $_SESSION['codigo_perfil'] ?? null; // Obtener el código de perfil de la sesión

$allowed_profiles = ['04', '05', '06', '99', '01']; // <<-- ¡CAMBIO AQUÍ! Añade '06'
if (!in_array($codigo_perfil, $allowed_profiles)) {
    header('Content-Type: application/json;charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Acceso denegado al menú para este perfil.']);
    exit();
}

$menuItems = []; // Array final que contendrá el menú estructurado
$rawMenuItems = []; // Array para almacenar los elementos del menú planos de la DB

if ($dblink) { // Asegurarse de que la conexión a la base de datos fue exitosa
    try {
        // Consulta SQL para obtener los elementos del menú a los que el perfil tiene acceso
        // y también los elementos padre para poder construir la estructura jerárquica.
        // Unimos menu_items con profile_menu_permissions para filtrar por perfil.
        // Y luego hacemos un LEFT JOIN para incluir también los padres de los items permitidos.

$query = "
    WITH RECURSIVE authorized_menu_items AS (
        -- Caso base: Selecciona todos los elementos directamente vinculados al perfil
        SELECT
            mi.id,
            mi.parent_id,
            mi.text,
            mi.icon,
            mi.url,
            mi.order_index,
            mi.is_active -- Asumiendo que esta columna existe
        FROM
            public.menu_items mi
        INNER JOIN
            public.profile_menu_permissions pmp ON mi.id = pmp.menu_item_id
        WHERE
            pmp.profile_id = :codigo_perfil AND mi.is_active = TRUE

        UNION

        -- Paso recursivo: Añade los elementos padre de los elementos autorizados
        SELECT
            p.id,
            p.parent_id,
            p.text,
            p.icon,
            p.url,
            p.order_index,
            p.is_active -- Asumiendo que esta columna existe
        FROM
            public.menu_items p
        INNER JOIN
            authorized_menu_items ami ON p.id = ami.parent_id
        WHERE
            p.is_active = TRUE -- Asegura que el padre también esté activo si se desea
    )
    SELECT
        id,
        parent_id,
        text,
        icon,
        url,
        order_index
    FROM
        authorized_menu_items
    ORDER BY
        parent_id ASC NULLS FIRST,
        order_index ASC;
";

        $stmt = $dblink->prepare($query);
        $stmt->bindParam(':codigo_perfil', $codigo_perfil, PDO::PARAM_STR);
        $stmt->execute();
        $rawMenuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Estructurar el menú en un formato jerárquico (padre -> hijos)
        $indexedItems = [];
        foreach ($rawMenuItems as $item) {
            $indexedItems[$item['id']] = $item;
            $indexedItems[$item['id']]['children'] = []; // Inicializar array de hijos
        }

        foreach ($indexedItems as $id => $item) {
            if ($item['parent_id'] === null) {
                // Es un elemento de menú principal
                $menuItems[] = &$indexedItems[$id]; // Usamos referencia para poder añadir hijos más tarde
            } else {
                // Es un submenú, añadirlo como hijo de su padre
                if (isset($indexedItems[$item['parent_id']])) {
                    $indexedItems[$item['parent_id']]['children'][] = &$indexedItems[$id];
                }
            }
        }

        // Limpiar referencias para evitar problemas
        unset($indexedItems);

        // Ordenar los elementos principales y sus hijos por 'order_index'
        usort($menuItems, function($a, $b) {
            return $a['order_index'] <=> $b['order_index'];
        });

        foreach ($menuItems as &$mainItem) {
            if (!empty($mainItem['children'])) {
                usort($mainItem['children'], function($a, $b) {
                    return $a['order_index'] <=> $b['order_index'];
                });
            }
        }
        unset($mainItem); // Limpiar la referencia

        header('Content-Type: application/json;charset=utf-8');
        echo json_encode(['success' => true, 'menu' => $menuItems]);

    } catch (PDOException $e) {
        // Log del error (en lugar de mostrarlo directamente en producción)
        error_log("Error al consultar menú en DB: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error en la base de datos al cargar el menú.']);
    }
} else {
    // Si la conexión a la base de datos falló (manejado en mainFunctions_.php)
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No se pudo conectar a la base de datos.']);
}

exit(); // Asegurarse de que no se envíe nada más después del JSON
?>