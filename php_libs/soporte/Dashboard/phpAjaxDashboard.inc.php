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
$path_root = trim($_SERVER['DOCUMENT_ROOT']);
include($path_root . "/FactuFacil/includes/mainFunctions_.php");

// Verificar la conexión a la base de datos
if (!isset($dblink) || !($dblink instanceof PDO)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: Conexión a la base de datos no establecida.']);
    exit();
}

$action = $_POST['action'] ?? '';
$codigo_perfil = $_SESSION['codigo_perfil'] ?? null;
$codigo_personal = $_SESSION['codigo_personal'] ?? null; // Código del docente logueado

$response = ['success' => false, 'message' => 'Acción no válida.'];

try {
    switch ($action) {
        case 'getAcademicYears':
            // Lógica para decidir qué consulta usar para ann_lectivo
            if (in_array($codigo_perfil, ['01', '04', '05'])) {
                $sql = "SELECT codigo, nombre FROM public.ann_lectivo ORDER BY codigo DESC";
            } else {
                $sql = "SELECT codigo, nombre FROM public.ann_lectivo WHERE estatus = 't' ORDER BY codigo DESC";
            }

            $stmt = $dblink->prepare($sql);
            $stmt->execute();
            $years = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response = ['success' => true, 'years' => $years];
            break;

        case 'getTeacherGradesAndSections':
            $codigo_ann_lectivo = $_POST['codigo_ann_lectivo'] ?? '';

            if (empty($codigo_personal) || empty($codigo_ann_lectivo)) {
                throw new Exception("Faltan parámetros (código personal o año lectivo) para obtener grados/secciones del docente.");
            }

            // SQL para obtener GRADOS/SECCIONES/TURNOS únicos que un docente tiene asignados en un año lectivo
            $sql = "SELECT DISTINCT
                        grd.nombre, sec.nombre, tur.nombre,
                        cd.codigo_grado || '-' || cd.codigo_seccion || '-' || cd.codigo_turno AS id_combinado,
                        TRIM(grd.nombre) || ' - ' || TRIM(sec.nombre) || ' - ' || TRIM(tur.nombre) AS nombre_combinado,
                        TRIM(grd.nombre) AS nombre_grado,
                        TRIM(sec.nombre) AS nombre_seccion,
                        TRIM(tur.nombre) AS nombre_turno
                    FROM public.carga_docente cd
                    INNER JOIN public.grado_ano grd ON grd.codigo = cd.codigo_grado
                    INNER JOIN public.turno tur ON tur.codigo = cd.codigo_turno
                    INNER JOIN public.seccion sec ON sec.codigo = cd.codigo_seccion
                    WHERE cd.codigo_docente = :codigoPersonal
                    AND cd.codigo_ann_lectivo = :codigoAnnLectivo
                    ORDER BY grd.nombre, sec.nombre, tur.nombre";
            
            $stmt = $dblink->prepare($sql);
            $stmt->bindParam(':codigoPersonal', $codigo_personal, PDO::PARAM_STR);
            $stmt->bindParam(':codigoAnnLectivo', $codigo_ann_lectivo, PDO::PARAM_STR);
            $stmt->execute();
            $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response = ['success' => true, 'grades' => $grades];
            break;

        case 'getTeacherIndicators':
            $codigo_ann_lectivo = $_POST['codigo_ann_lectivo'] ?? '';
            $codigo_grado_seccion_turno = $_POST['codigo_grado_seccion_turno'] ?? '';

            if (empty($codigo_personal) || empty($codigo_ann_lectivo) || empty($codigo_grado_seccion_turno)) {
                throw new Exception("Faltan parámetros (código personal, año o grado/sección/turno) para obtener los indicadores del docente.");
            }

            list($codigo_grado, $codigo_seccion, $codigo_turno) = explode('-', $codigo_grado_seccion_turno);

            $indicators_data = [
                'total_students' => 0,
                'assigned_grades' => 0,
                'approval_rate' => 0,
                'low_performance_students' => 0,
                'grades' => [], // Lista de grados que el docente imparte en este año
                'performance_by_grade' => [] // Datos para el gráfico por asignatura
            ];
            
            // 1. Obtener la calificación mínima de aprobación
            $sql_calif_minima = "SELECT calificacion_minima FROM public.catalogo_periodos LIMIT 1"; 
            $stmt_calif_minima = $dblink->prepare($sql_calif_minima);
            $stmt_calif_minima->execute();
            $calificacion_minima = $stmt_calif_minima->fetchColumn() ?? 60; // Valor por defecto 60 si no se encuentra
            error_log("DEBUG: calificacion_minima obtenida: " . $calificacion_minima); // DEBUG

            // 2. Calcular total_students (estudiantes NO retirados en el G/S/T seleccionado)
            $sql_total_students = "SELECT COUNT(m.codigo_alumno)
                                FROM public.alumno_matricula m
                                WHERE m.codigo_ann_lectivo = :codigoAnnLectivo
                                AND m.codigo_grado = :codigoGrado
                                AND m.codigo_seccion = :codigoSeccion
                                AND m.codigo_turno = :codigoTurno
                                AND m.retirado = 'f'"; // Estudiantes no retirados
            $stmt_total_students = $dblink->prepare($sql_total_students);
            $stmt_total_students->bindParam(':codigoAnnLectivo', $codigo_ann_lectivo, PDO::PARAM_STR);
            $stmt_total_students->bindParam(':codigoGrado', $codigo_grado, PDO::PARAM_STR);
            $stmt_total_students->bindParam(':codigoSeccion', $codigo_seccion, PDO::PARAM_STR);
            $stmt_total_students->bindParam(':codigoTurno', $codigo_turno, PDO::PARAM_STR);
            $stmt_total_students->execute();
            $indicators_data['total_students'] = $stmt_total_students->fetchColumn();

            // 3. Asignar el número de grados/secciones/turnos asignados (distintos) al docente para el año lectivo
            $sql_assigned_grades = "SELECT COUNT(DISTINCT cd.codigo_grado || cd.codigo_seccion || cd.codigo_turno) AS count_grades
                                    FROM public.carga_docente cd
                                    WHERE cd.codigo_docente = :codigoPersonal AND cd.codigo_ann_lectivo = :codigoAnnLectivo";
            $stmt_assigned = $dblink->prepare($sql_assigned_grades);
            $stmt_assigned->bindParam(':codigoPersonal', $codigo_personal, PDO::PARAM_STR);
            $stmt_assigned->bindParam(':codigoAnnLectivo', $codigo_ann_lectivo, PDO::PARAM_STR);
            $stmt_assigned->execute();
            $indicators_data['assigned_grades'] = $stmt_assigned->fetchColumn();

            // 4. Actualizar la lista de grados que imparte el docente (con conteo de estudiantes)
            $sql_grades_list_teacher = "SELECT DISTINCT
                                    TRIM(grd.nombre) AS nombre_grado,
                                    TRIM(sec.nombre) AS nombre_seccion,
                                    TRIM(tur.nombre) AS nombre_turno,
                                    (SELECT COUNT(m.codigo_alumno) FROM public.alumno_matricula m WHERE m.codigo_ann_lectivo = cd.codigo_ann_lectivo AND m.codigo_grado = cd.codigo_grado AND m.codigo_seccion = cd.codigo_seccion AND m.codigo_turno = cd.codigo_turno AND m.retirado = 'f') AS estudiantes_count
                                FROM public.carga_docente cd
                                INNER JOIN public.grado_ano grd ON grd.codigo = cd.codigo_grado
                                INNER JOIN public.seccion sec ON sec.codigo = cd.codigo_seccion
                                INNER JOIN public.turno tur ON tur.codigo = cd.codigo_turno
                                WHERE cd.codigo_docente = :codigoPersonal
                                AND cd.codigo_ann_lectivo = :codigoAnnLectivo
                                ORDER BY nombre_grado, nombre_seccion, nombre_turno";
            $stmt_grades_list_teacher = $dblink->prepare($sql_grades_list_teacher);
            $stmt_grades_list_teacher->bindParam(':codigoPersonal', $codigo_personal, PDO::PARAM_STR);
            $stmt_grades_list_teacher->bindParam(':codigoAnnLectivo', $codigo_ann_lectivo, PDO::PARAM_STR);
            $stmt_grades_list_teacher->execute();
            $indicators_data['grades'] = $stmt_grades_list_teacher->fetchAll(PDO::FETCH_ASSOC);


            // 5. Calcular Tasa de Aprobación General (approval_rate) y Estudiantes con Bajo Rendimiento (low_performance_students)
            // Esto es para la combinación Grado/Sección/Turno seleccionada por el docente.
            $sql_performance = "
                WITH TeacherRelevantGrades AS (
                    SELECT
                        n.codigo_alumno,
                        n.codigo_asignatura,
                        n.nota_final
                    FROM public.nota n
                    INNER JOIN public.alumno_matricula am ON n.codigo_matricula = am.id_alumno_matricula
                    INNER JOIN public.asignatura asi ON n.codigo_asignatura = asi.codigo
                    INNER JOIN public.carga_docente cd ON
                        am.codigo_grado = cd.codigo_grado AND am.codigo_seccion = cd.codigo_seccion AND am.codigo_turno = cd.codigo_turno AND am.codigo_ann_lectivo = cd.codigo_ann_lectivo AND asi.codigo = cd.codigo_asignatura
                    WHERE
                        cd.codigo_docente = :codigoPersonal
                        AND cd.codigo_ann_lectivo = :codigoAnnLectivo
                        AND am.codigo_grado = :codigoGrado
                        AND am.codigo_seccion = :codigoSeccion
                        AND am.codigo_turno = :codigoTurno
                        AND am.retirado = 'f' 
                        AND asi.estatus = 't' -- CORRECTO: Usando 't' para estatus activo (BOOLEAN)
                        AND n.nota_final IS NOT NULL 
                )
                SELECT
                    COUNT(CASE WHEN trg.nota_final >= :calificacionMinima THEN 1 ELSE NULL END) AS total_approved,
                    COUNT(trg.nota_final) AS total_evaluated_grades,
                    COUNT(DISTINCT CASE WHEN trg.nota_final < :calificacionMinima THEN trg.codigo_alumno ELSE NULL END) AS students_with_low_performance
                FROM TeacherRelevantGrades trg;
            ";
            $stmt_performance = $dblink->prepare($sql_performance);
            $stmt_performance->bindParam(':codigoPersonal', $codigo_personal, PDO::PARAM_STR);
            $stmt_performance->bindParam(':codigoAnnLectivo', $codigo_ann_lectivo, PDO::PARAM_STR);
            $stmt_performance->bindParam(':codigoGrado', $codigo_grado, PDO::PARAM_STR);
            $stmt_performance->bindParam(':codigoSeccion', $codigo_seccion, PDO::PARAM_STR);
            $stmt_performance->bindParam(':codigoTurno', $codigo_turno, PDO::PARAM_STR);
            $stmt_performance->bindParam(':calificacionMinima', $calificacion_minima, PDO::PARAM_INT);
            $stmt_performance->execute();
            $performance_summary = $stmt_performance->fetch(PDO::FETCH_ASSOC);

            if ($performance_summary) {
                $total_evaluated = $performance_summary['total_evaluated_grades'];
                $total_approved = $performance_summary['total_approved'];
                $indicators_data['low_performance_students'] = $performance_summary['students_with_low_performance'];
                
                $indicators_data['approval_rate'] = ($total_evaluated > 0) ? round(($total_approved / $total_evaluated) * 100, 2) : 0;
            }

            // 6. Datos para el gráfico de Rendimiento por Asignatura (performance_by_grade)
            $sql_chart_performance = "
                SELECT
                    TRIM(asi.nombre) AS asignatura_name, -- TRIM para limpiar espacios
                    COUNT(CASE WHEN n.nota_final >= :calificacionMinima THEN 1 ELSE NULL END) AS approved_count,
                    COUNT(n.nota_final) AS total_count
                FROM public.nota n
                INNER JOIN public.alumno_matricula am ON n.codigo_matricula = am.id_alumno_matricula
                INNER JOIN public.asignatura asi ON n.codigo_asignatura = asi.codigo
                INNER JOIN public.carga_docente cd ON
                    am.codigo_grado = cd.codigo_grado AND am.codigo_seccion = cd.codigo_seccion AND am.codigo_turno = cd.codigo_turno AND am.codigo_ann_lectivo = cd.codigo_ann_lectivo AND asi.codigo = cd.codigo_asignatura
                WHERE
                    cd.codigo_docente = :codigoPersonal
                    AND cd.codigo_ann_lectivo = :codigoAnnLectivo
                    AND am.codigo_grado = :codigoGrado
                    AND am.codigo_seccion = :codigoSeccion
                    AND am.codigo_turno = :codigoTurno
                    AND am.retirado = 'f' AND asi.estatus = 't' -- CORRECTO: USO 't' PARA ESTATUS ACTIVO
                    AND n.nota_final IS NOT NULL
                GROUP BY asi.nombre
                ORDER BY asi.nombre;
            ";
            $stmt_chart_performance = $dblink->prepare($sql_chart_performance);
            $stmt_chart_performance->bindParam(':codigoPersonal', $codigo_personal, PDO::PARAM_STR);
            $stmt_chart_performance->bindParam(':codigoAnnLectivo', $codigo_ann_lectivo, PDO::PARAM_STR);
            $stmt_chart_performance->bindParam(':codigoGrado', $codigo_grado, PDO::PARAM_STR);
            $stmt_chart_performance->bindParam(':codigoSeccion', $codigo_seccion, PDO::PARAM_STR);
            $stmt_chart_performance->bindParam(':codigoTurno', $codigo_turno, PDO::PARAM_STR);
            $stmt_chart_performance->bindParam(':calificacionMinima', $calificacion_minima, PDO::PARAM_INT);
            $stmt_chart_performance->execute();
            $chart_data_raw = $stmt_chart_performance->fetchAll(PDO::FETCH_ASSOC);

            $indicators_data['performance_by_grade'] = array_map(function($item) {
                $tasa = ($item['total_count'] > 0) ? round(($item['approved_count'] / $item['total_count']) * 100, 2) : 0;
                return [
                    'asignatura_name' => $item['asignatura_name'],
                    'tasa_aprobacion' => $tasa
                ];
            }, $chart_data_raw);
            
            $response = ['success' => true, 'data' => $indicators_data];
            break;
        
        case 'getStudentsByGradoSeccionTurno': // Para 'Total de Estudiantes a Cargo'
        case 'getLowPerformanceStudents':     // Para 'Estudiantes con Bajo Rendimiento'
            $codigo_ann_lectivo = $_POST['codigo_ann_lectivo'] ?? '';
            $codigo_grado = $_POST['codigo_grado'] ?? ''; // Ahora recibimos los componentes individuales
            $codigo_seccion = $_POST['codigo_seccion'] ?? '';
            $codigo_turno = $_POST['codigo_turno'] ?? '';
            $codigo_personal = $_POST['codigo_personal'] ?? null; // Asegúrate de que el docente esté logueado

            if (empty($codigo_ann_lectivo) || empty($codigo_grado) || empty($codigo_seccion) || empty($codigo_turno) || empty($codigo_personal)) {
                throw new Exception("Faltan parámetros para obtener la lista de estudiantes.");
            }

            // Consulta base de estudiantes y encargados
            $sql_base = "
                SELECT
                    a.id_alumno,
                    TRIM(btrim(a.nombre_completo || ' ' || a.apellido_paterno || ' ' || a.apellido_materno)) AS nombre_estudiante,
                    TRIM(gan.nombre) || ' - ' || TRIM(sec.nombre) || ' - ' || TRIM(tur.nombre) AS grado_seccion_turno,
                    a.telefono_celular AS telefono_estudiante, -- Teléfono del alumno
                    ae.id_alumno_encargado, -- ID del registro de encargado
                    TRIM(ae.nombres) AS nombre_encargado, -- Nombre del encargado
                    ae.telefono AS telefono_encargado_principal -- Teléfono del encargado principal
                FROM public.alumno a
                INNER JOIN public.alumno_matricula am ON a.id_alumno = am.codigo_alumno AND am.retirado = 'f'
                LEFT JOIN public.alumno_encargado ae ON a.id_alumno = ae.codigo_alumno AND ae.encargado = 't' -- SOLO ENCARGADO PRINCIPAL
                INNER JOIN public.grado_ano gan ON gan.codigo = am.codigo_grado
                INNER JOIN public.seccion sec ON sec.codigo = am.codigo_seccion
                INNER JOIN public.turno tur ON tur.codigo = am.codigo_turno
                INNER JOIN public.carga_docente cd ON
                    am.codigo_grado = cd.codigo_grado AND am.codigo_seccion = cd.codigo_seccion AND am.codigo_turno = cd.codigo_turno AND am.codigo_ann_lectivo = cd.codigo_ann_lectivo AND cd.codigo_docente = :codigoPersonal
                WHERE
                    am.codigo_ann_lectivo = :codigoAnnLectivo
                    AND am.codigo_grado = :codigoGrado
                    AND am.codigo_seccion = :codigoSeccion
                    AND am.codigo_turno = :codigoTurno
            ";

            $params = [
                ':codigoAnnLectivo' => $codigo_ann_lectivo,
                ':codigoGrado' => $codigo_grado,
                ':codigoSeccion' => $codigo_seccion,
                ':codigoTurno' => $codigo_turno,
                ':codigoPersonal' => $codigo_personal
            ];

            if ($action === 'getLowPerformanceStudents') {
                // Para bajo rendimiento, necesitamos unir con notas y calificacion_minima
                // Primero, obtenemos la calificacion_minima (ya se obtiene en getTeacherIndicators)
                $sql_calif_minima = "SELECT calificacion_minima FROM public.catalogo_periodos LIMIT 1"; 
                $stmt_calif_minima = $dblink->prepare($sql_calif_minima);
                $stmt_calif_minima->execute();
                $calificacion_minima = $stmt_calif_minima->fetchColumn() ?? 60;

                // Modificar la consulta base para incluir filtro por bajo rendimiento
                // Unimos con la tabla 'nota' y filtramos por notas < calificacion_minima
                // Aseguramos que sea DISTINCT por alumno, ya que un alumno puede reprobar varias asignaturas
                $sql_base .= "
                    AND a.id_alumno IN (
                        SELECT DISTINCT n.codigo_alumno
                        FROM public.nota n
                        INNER JOIN public.asignatura asi ON n.codigo_asignatura = asi.codigo
                        INNER JOIN public.alumno_matricula am_notes ON n.codigo_matricula = am_notes.id_alumno_matricula
                        WHERE n.nota_final < :calificacionMinima
                        AND am_notes.codigo_ann_lectivo = am.codigo_ann_lectivo
                        AND am_notes.codigo_grado = am.codigo_grado
                        AND am_notes.codigo_seccion = am.codigo_seccion
                        AND am_notes.codigo_turno = am.codigo_turno
                        AND asi.estatus = 't' -- Asignaturas activas para calificación
                        AND n.nota_final IS NOT NULL
                    )
                ";
                $params[':calificacionMinima'] = $calificacion_minima;
            }

            $sql_base .= " ORDER BY nombre_estudiante ASC";

            $stmt = $dblink->prepare($sql_base);
            foreach($params as $key => &$val) {
                $stmt->bindParam($key, $val);
            }
            $stmt->execute();
            $students_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response = ['success' => true, 'data' => $students_data];
            break;

        case 'updateContactInfo': // Para guardar los cambios de teléfono
            $id_encargado = $_POST['id_encargado'] ?? null;
            $new_encargado_phone = $_POST['new_encargado_phone'] ?? '';
            $id_estudiante = $_POST['id_estudiante'] ?? null;
            $new_estudiante_phone = $_POST['new_estudiante_phone'] ?? '';

            $dblink->beginTransaction();
            try {
                if ($id_encargado && $new_encargado_phone !== null) { // Actualizar teléfono del encargado
                    $stmt_encargado = $dblink->prepare("UPDATE public.alumno_encargado SET telefono = :telefono WHERE id_alumno_encargado = :id_encargado");
                    $stmt_encargado->bindParam(':telefono', $new_encargado_phone, PDO::PARAM_STR);
                    $stmt_encargado->bindParam(':id_encargado', $id_encargado, PDO::PARAM_INT);
                    $stmt_encargado->execute();
                }
                if ($id_estudiante && $new_estudiante_phone !== null) { // Actualizar teléfono del estudiante
                    $stmt_estudiante = $dblink->prepare("UPDATE public.alumno SET telefono_celular = :telefono_celular WHERE id_alumno = :id_estudiante");
                    $stmt_estudiante->bindParam(':telefono_celular', $new_estudiante_phone, PDO::PARAM_STR);
                    $stmt_estudiante->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
                    $stmt_estudiante->execute();
                }
                $dblink->commit();
                $response = ['success' => true, 'message' => 'Teléfonos actualizados exitosamente.'];
            } catch (PDOException | Exception $e) { // Captura PDOException y otras excepciones
                if ($dblink->inTransaction()) {
                    $dblink->rollBack();
                }
                error_log("Error al actualizar contactos: " . $e->getMessage());
                $response = ['success' => false, 'message' => 'Error de base de datos al actualizar contactos: ' . $e->getMessage()]; // Mostrar mensaje de error más detallado
            }
            break;

         case 'getGeneralIndicators':
            $codigo_ann_lectivo = $_POST['codigo_ann_lectivo'] ?? '';

            if (empty($codigo_ann_lectivo)) {
                throw new Exception("Falta el parámetro de año lectivo para obtener los indicadores generales.");
            }

            // Inicialización del array de datos
            $general_indicators_data = [
                'total_estudiantes_masculino' => 0,
                'total_estudiantes_femenino' => 0,
                'total_estudiantes' => 0,
                'total_familias' => 0,
                'total_docentes_masculino' => 0,
                'total_docentes_femenino' => 0,
                'total_docentes' => 0,
                'total_admin_masculino' => 0, // Nuevo
                'total_admin_femenino' => 0,  // Nuevo
                'total_admin_total' => 0,     // Nuevo
                'matricula_grafico' => [
                    'masculino' => 0,
                    'femenino' => 0,
                    'total' => 0
                ],
                'lista_matricula' => []
            ];

            // Total Estudiantes (sin cambios)
            $sql_estudiantes_generales = "SELECT SUM(CASE WHEN lower(e.genero) = 'm' THEN 1 ELSE 0 END) AS masculino, SUM(CASE WHEN lower(e.genero) = 'f' THEN 1 ELSE 0 END) AS femenino, COUNT(m.codigo_alumno) AS total FROM public.alumno_matricula m INNER JOIN public.alumno e ON e.id_alumno = m.codigo_alumno WHERE m.codigo_ann_lectivo = :codigoAnnLectivo AND m.retirado = 'f'";
            $stmt_estudiantes = $dblink->prepare($sql_estudiantes_generales);
            $stmt_estudiantes->bindParam(':codigoAnnLectivo', $codigo_ann_lectivo, PDO::PARAM_STR);
            $stmt_estudiantes->execute();
            $res_estudiantes = $stmt_estudiantes->fetch(PDO::FETCH_ASSOC);
            if ($res_estudiantes) {
                $general_indicators_data['total_estudiantes_masculino'] = $res_estudiantes['masculino'];
                $general_indicators_data['total_estudiantes_femenino'] = $res_estudiantes['femenino'];
                $general_indicators_data['total_estudiantes'] = $res_estudiantes['total'];
                $general_indicators_data['matricula_grafico']['masculino'] = $res_estudiantes['masculino'];
                $general_indicators_data['matricula_grafico']['femenino'] = $res_estudiantes['femenino'];
                $general_indicators_data['matricula_grafico']['total'] = $res_estudiantes['total'];
            }

           // === CONSULTA DE FAMILIAS CORREGIDA (VERSIÓN FINAL) ===
            // Contamos los DUI únicos de los encargados PRINCIPALES ('t') de los alumnos matriculados.
            $sql_familias = "SELECT COUNT(DISTINCT ae.dui)
                             FROM public.alumno_encargado ae
                             WHERE ae.dui IS NOT NULL AND ae.dui != '' AND ae.encargado = 't'
                             AND ae.codigo_alumno IN (
                                SELECT m.codigo_alumno
                                FROM public.alumno_matricula m
                                WHERE m.codigo_ann_lectivo = :codigoAnnLectivo AND m.retirado = 'f'
                             )";
            $stmt_familias = $dblink->prepare($sql_familias);
            $stmt_familias->bindParam(':codigoAnnLectivo', $codigo_ann_lectivo, PDO::PARAM_STR);
            $stmt_familias->execute();
            $general_indicators_data['total_familias'] = $stmt_familias->fetchColumn();
            // === FIN DE LA CORRECCIÓN ===

            // Total Docentes (sin cambios, ya estaba correcto)
            $sql_docentes = "SELECT SUM(CASE WHEN lower(codigo_genero) = '01' THEN 1 ELSE 0 END) AS masculino, SUM(CASE WHEN lower(codigo_genero) = '02' THEN 1 ELSE 0 END) AS femenino, COUNT(id_personal) AS total FROM public.personal WHERE codigo_cargo = '03' AND codigo_estatus = '01'";
            $stmt_docentes = $dblink->prepare($sql_docentes);
            $stmt_docentes->execute();
            $res_docentes = $stmt_docentes->fetch(PDO::FETCH_ASSOC);
            if ($res_docentes) {
                $general_indicators_data['total_docentes_masculino'] = $res_docentes['masculino'];
                $general_indicators_data['total_docentes_femenino'] = $res_docentes['femenino'];
                $general_indicators_data['total_docentes'] = $res_docentes['total'];
            }

            // NUEVO: Total Personal Administrativo
            $sql_admin = "SELECT SUM(CASE WHEN lower(codigo_genero) = '01' THEN 1 ELSE 0 END) AS masculino, SUM(CASE WHEN lower(codigo_genero) = '02' THEN 1 ELSE 0 END) AS femenino, COUNT(id_personal) AS total FROM public.personal WHERE codigo_cargo <> '03' AND codigo_estatus = '01'";
            $stmt_admin = $dblink->prepare($sql_admin);
            $stmt_admin->execute();
            $res_admin = $stmt_admin->fetch(PDO::FETCH_ASSOC);
            if ($res_admin) {
                $general_indicators_data['total_admin_masculino'] = $res_admin['masculino'];
                $general_indicators_data['total_admin_femenino'] = $res_admin['femenino'];
                $general_indicators_data['total_admin_total'] = $res_admin['total'];
            }

            // Lista de Matrícula (sin cambios)
            $sql_lista_matricula = "SELECT TRIM(modl.nombre) AS modalidad, TRIM(tur.nombre) AS turno, SUM(CASE WHEN lower(e.genero) = 'm' THEN 1 ELSE 0 END) AS masculino, SUM(CASE WHEN lower(e.genero) = 'f' THEN 1 ELSE 0 END) AS femenino, COUNT(m.codigo_alumno) AS total FROM public.alumno_matricula m INNER JOIN public.alumno e ON e.id_alumno = m.codigo_alumno INNER JOIN public.bachillerato_ciclo modl ON modl.codigo = m.codigo_bach_o_ciclo INNER JOIN public.turno tur ON tur.codigo = m.codigo_turno WHERE m.codigo_ann_lectivo = :codigoAnnLectivo AND m.retirado = 'f' GROUP BY modl.nombre, tur.nombre ORDER BY modl.nombre, tur.nombre";
            $stmt_lista_matricula = $dblink->prepare($sql_lista_matricula);
            $stmt_lista_matricula->bindParam(':codigoAnnLectivo', $codigo_ann_lectivo, PDO::PARAM_STR);
            $stmt_lista_matricula->execute();
            $general_indicators_data['lista_matricula'] = $stmt_lista_matricula->fetchAll(PDO::FETCH_ASSOC);

            $response = ['success' => true, 'data' => $general_indicators_data];
            break;

        default:
            break;
    }
} catch (Exception $e) {
    error_log("Error en phpAjaxDashboard.inc.php: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'Error interno del servidor: ' . $e->getMessage()];
}

header('Content-Type: application/json;charset=utf-8');
echo json_encode($response);
exit();
?>