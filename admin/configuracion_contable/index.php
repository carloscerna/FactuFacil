<?php
session_start();
// Define la ruta raíz del proyecto para los includes
$path_root = trim($_SERVER['DOCUMENT_ROOT']);
// Incluye tu archivo de conexión y funciones principales
include_once($path_root . "/FactuFacil/includes/mainFunctions_.php");

// Validación básica de sesión
if (empty($_SESSION['userNombre']) || empty($_SESSION['codigo_institucion'])) {
    // Redirigir al login si no hay sesión activa
    header('Location: /FactuFacil/login.php'); 
    exit();
}

// Obtener el código de la institución activa
$codigo_institucion_activa = $_SESSION['codigo_institucion'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración Contable (Mapeo)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/FactuFacil/css/main.css"> 
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="fas fa-cogs me-2"></i>Mapeo de Cuentas Contables</h4>
                        <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#modalConfig" id="btnNuevo">
                            <i class="fas fa-plus"></i> Nuevo Mapeo
                        </button>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">Asocia claves internas del sistema con tus cuentas contables reales para automatizar los asientos.</p>
                        <div class="table-responsive">
                            <table id="tablaConfiguracion" class="table table-striped table-hover table-bordered w-100">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Clave de Sistema (Mapeo)</th>
                                        <th>Cuenta Contable Asociada</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalConfig" tabindex="-1" aria-labelledby="modalConfigLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalConfigLabel">Configurar Mapeo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formConfig">
                        <input type="hidden" id="config_id" name="config_id" value="">
                        
                        <div class="mb-3">
                            <label for="clave_mapeo" class="form-label">Clave del Sistema <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="clave_mapeo" name="clave_mapeo" required placeholder="Ej: INVENTARIO_MERCADERIA">
                            <div class="form-text text-muted">Escribe la clave exacta solicitada por el sistema (en mayúsculas).</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="cuenta_id" class="form-label">Seleccionar Cuenta Contable <span class="text-danger">*</span></label>
                            <select class="form-select" id="cuenta_id" name="cuenta_id" required>
                                <option value="">Cargando cuentas...</option>
                                </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnGuardar">Guardar Configuración</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            let tabla;

            // --- 1. Inicializar DataTables ---
            tabla = $('#tablaConfiguracion').DataTable({
                "ajax": {
                    "url": "crud_config.php", // Archivo PHP que provee los datos
                    "type": "POST",
                    "data": { "accion": "listar" }, // Indica la acción a realizar
                    "dataSrc": "" // Los datos vienen como un array directo
                },
                "columns": [
                    { "data": "id" },
                    { 
                        "data": "clave_mapeo",
                        "render": function(data) {
                            return `<code>${data}</code>`; // Resaltar la clave
                        }
                    },
                    { 
                        "data": null,
                        "render": function(data, type, row) {
                            // Combina código y nombre de la cuenta
                            return (row.cuenta_codigo && row.cuenta_nombre) 
                                ? `<strong>${row.cuenta_codigo}</strong> - ${row.cuenta_nombre}` 
                                : '<span class="badge bg-danger">Cuenta no encontrada</span>';
                        }
                    },
                    {
                        "data": null,
                        "orderable": false,
                        "render": function(data, type, row) {
                            return `
                                <button class="btn btn-sm btn-warning btnEditar" data-id="${row.id}"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-danger btnEliminar" data-id="${row.id}"><i class="fas fa-trash"></i></button>
                            `;
                        }
                    }
                ],
                "language": { // Configuración de idioma al español
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                }
            });

            // --- 2. Función para cargar el Select de Cuentas ---
            function cargarSelectCuentas() {
                $.ajax({
                    url: 'get_cuentas.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        let options = '<option value="">Seleccione una cuenta...</option>';
                        if(response && response.length > 0){
                            response.forEach(cuenta => {
                                options += `<option value="${cuenta.id}">${cuenta.codigo} - ${cuenta.nombre}</option>`;
                            });
                        } else {
                            options = '<option value="">No hay cuentas disponibles</option>';
                        }
                        $('#cuenta_id').html(options);
                    },
                    error: function() {
                         $('#cuenta_id').html('<option value="">Error al cargar cuentas</option>');
                         Swal.fire('Error', 'No se pudieron cargar las cuentas contables.', 'error');
                    }
                });
            }

            // Cargar cuentas al iniciar la página
            cargarSelectCuentas();


            // --- 3. Evento: Botón "Nuevo Mapeo" ---
            $('#btnNuevo').click(function() {
                $('#formConfig')[0].reset(); // Limpiar formulario
                $('#config_id').val('');      // Limpiar ID oculto (indica que es nuevo)
                $('#modalConfigLabel').text('Nuevo Mapeo de Cuenta');
                $('#clave_mapeo').prop('readonly', false); // Permitir editar clave
                // Asegurarse de que las cuentas estén cargadas
                if($('#cuenta_id option').length <= 1) { cargarSelectCuentas(); }
            });


            // --- 4. Evento: Botón "Guardar" (en el modal) ---
            $('#btnGuardar').click(function() {
                // Validación simple
                if($('#clave_mapeo').val() === '' || $('#cuenta_id').val() === '') {
                    Swal.fire('Atención', 'Por favor complete todos los campos obligatorios.', 'warning');
                    return;
                }

                let formData = new FormData($('#formConfig')[0]);
                formData.append('accion', 'guardar'); // Indicar la acción

                $.ajax({
                    url: 'crud_config.php',
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('¡Éxito!', response.message, 'success');
                            $('#modalConfig').modal('hide'); // Cerrar modal
                            tabla.ajax.reload(); // Recargar tabla
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Hubo un problema al procesar la solicitud.', 'error');
                    }
                });
            });


            // --- 5. Evento: Botón "Editar" (en la tabla) ---
            $('#tablaConfiguracion tbody').on('click', '.btnEditar', function() {
                // Obtener los datos de la fila actual de DataTables
                let dataRow = tabla.row($(this).parents('tr')).data();
                
                $('#config_id').val(dataRow.id); // Establecer el ID oculto
                $('#clave_mapeo').val(dataRow.clave_mapeo).prop('readonly', true); // La clave no se debe editar
                
                // Seleccionar la cuenta correcta en el dropdown
                $('#cuenta_id').val(dataRow.cuenta_id);

                $('#modalConfigLabel').text('Editar Mapeo Existente');
                $('#modalConfig').modal('show'); // Mostrar el modal
            });


            // --- 6. Evento: Botón "Eliminar" (en la tabla) ---
            $('#tablaConfiguracion tbody').on('click', '.btnEliminar', function() {
                 let dataRow = tabla.row($(this).parents('tr')).data();
                 let idEliminar = dataRow.id;

                 Swal.fire({
                    title: '¿Estás seguro?',
                    text: `Se eliminará el mapeo para la clave: ${dataRow.clave_mapeo}. Esto podría causar errores en los procesos automáticos.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                         $.ajax({
                            url: 'crud_config.php',
                            type: 'POST',
                            data: { 'accion': 'eliminar', 'id': idEliminar },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire('Eliminado', response.message, 'success');
                                    tabla.ajax.reload();
                                } else {
                                    Swal.fire('Error', response.message, 'error');
                                }
                            },
                            error: function() { Swal.fire('Error', 'Error de conexión al intentar eliminar.', 'error'); }
                        });
                    }
                });
            });

        }); // Fin document.ready
    </script>
</body>
</html>