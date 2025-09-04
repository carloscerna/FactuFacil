// js/instituciones/Instituciones.js
$(function () {
    let tablaInstituciones = $('#tablaInstituciones').DataTable({
        "ajax": {
            "url": "admin/instituciones/crud_instituciones.php",
            "type": "POST",
            "data": { accion: "listarInstituciones" },
            "dataSrc": "data"
        },
        "columns": [
            { "data": "codigo_institucion" },
            { "data": "nombre_institucion" },
            { "data": "nit" },
            { "data": "nrc" },
            { "data": "estado_actividad" },
            { "defaultContent": "<div class='text-center'><div class='btn-group'><button class='btn btn-warning btn-sm btnEditar'><i class='fas fa-edit'></i></button><button class='btn btn-danger btn-sm btnBorrar'><i class='fas fa-trash-alt'></i></button></div></div>" }
        ],
        "language": { "url": "php_libs/idioma/es_es.json" }
    });

    // Aplicar máscaras de entrada
    $('#nit').mask('0000-000000-000-0');
    $('#nrc').mask('000000-0');
    $('#telefono').mask('0000-0000');
    
    // Evento para el botón "Nueva Institución"
    $('#btnNuevaInstitucion').on('click', function () {
        $('#institucionModalLabel').text('Crear Nueva Institución');
        $('#institucionForm')[0].reset();
        $('#codigo_institucion').val('');
        $('.form-control').removeClass('is-invalid is-valid');
    });

    // Evento para el botón "Editar"
    $('#tablaInstituciones tbody').on('click', '.btnEditar', function () {
        let fila = $(this).closest("tr");
        let codigo_institucion = fila.find('td:eq(0)').text();
        $('#institucionModalLabel').text('Editar Institución');
        
        $.ajax({
            url: "admin/instituciones/crud_instituciones.php",
            type: "POST",
            dataType: "json",
            data: { accion: 'obtenerInstitucion', codigo_institucion: codigo_institucion },
            success: function(response) {
                if (response.respuesta) {
                    let institucion = response.institucion;
                    for (const key in institucion) {
                        if (institucion.hasOwnProperty(key) && typeof institucion[key] === 'string') {
                            institucion[key] = institucion[key].trim();
                        }
                    }

                    $('#codigo_institucion').val(institucion.codigo_institucion);
                    $('#nombre_institucion').val(institucion.nombre_institucion);
                    $('#nombre_legal').val(institucion.nombre_legal);
                    $('#nombre_corto').val(institucion.nombre_corto);
                    $('#nit').val(institucion.nit);
                    $('#nrc').val(institucion.nrc);
                    $('#nrc_vigente').val(institucion.nrc_vigente === 't' ? 'true' : 'false');
                    $('#telefono').val(institucion.telefono);
                    $('#direccion').val(institucion.direccion);
                    $('#representante_legal').val(institucion.representante_legal);
                    $('#correo_electronico').val(institucion.correo_electronico);
                    $('#estado_actividad').val(institucion.estado_actividad === 't' ? 'true' : 'false');

                    $('#institucionModal').modal('show');
                } else {
                    toastr.error(response.mensaje);
                }
            }
        });
    });

    // Evento para el formulario de creación/edición
    $('#institucionForm').submit(function (e) {
        e.preventDefault();
        let formData = $(this).serialize();
        
        $.ajax({
            url: "admin/instituciones/crud_instituciones.php",
            type: "POST",
            dataType: "json",
            data: formData,
            success: function (response) {
                if (response.respuesta) {
                    toastr.success(response.mensaje);
                    $('#institucionModal').modal('hide');
                    tablaInstituciones.ajax.reload();
                } else {
                    toastr.error("Error: " + response.mensaje);
                }
            }
        });
    });
    
    // Evento para el botón "Eliminar"
    $('#tablaInstituciones tbody').on('click', '.btnBorrar', function () {
        let fila = $(this).closest("tr");
        let codigo_institucion = fila.find('td:eq(0)').text().trim();

        if (confirm("¿Estás seguro de que quieres eliminar este registro?")) {
            $.ajax({
                url: "admin/instituciones/crud_instituciones.php",
                type: "POST",
                dataType: "json",
                data: { accion: 'eliminar', codigo_institucion: codigo_institucion },
                success: function (response) {
                    if (response.respuesta) {
                        toastr.success(response.mensaje);
                        tablaInstituciones.ajax.reload();
                    } else {
                        toastr.error(response.mensaje);
                    }
                }
            });
        }
    });
});