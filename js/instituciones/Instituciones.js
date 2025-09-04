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

    // Función para mostrar la previsualización del logo
    function showImagePreview(fileInput, previewDivId, currentLogoName, institutionCode) {
        const previewDiv = $(`#${previewDivId}`);
        previewDiv.empty(); // Limpiar previsualizaciones anteriores

        if (fileInput.files && fileInput.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                previewDiv.append(`<img src="${e.target.result}" class="img-thumbnail" style="max-width: 100px; max-height: 100px;">`);
            };
            reader.readAsDataURL(fileInput.files[0]);
        } else if (currentLogoName && institutionCode) {
            // Si hay un logo actual y no se ha subido uno nuevo, mostrar el existente
            const logoPath = `img/${institutionCode}/${currentLogoName}`;
            previewDiv.append(`<img src="${logoPath}" class="img-thumbnail" style="max-width: 100px; max-height: 100px;">`);
        }
    }

    // Evento para el botón "Nueva Institución"
    $('#btnNuevaInstitucion').on('click', function () {
        $('#institucionModalLabel').text('Crear Nueva Institución');
        $('#institucionForm')[0].reset();
        $('#codigo_institucion').val('');
        $('.form-control').removeClass('is-invalid is-valid');

        $('#preview_logo_uno').empty(); // Limpiar previsualización del logo 1
        $('#preview_logo_dos').empty(); // Limpiar previsualización del logo 2
        $('#logo_uno_actual').val(''); // Limpiar hidden para el logo actual
        $('#logo_dos_actual').val(''); // Limpiar hidden para el logo actual
    });

    // Evento para el botón "Editar"
    $('#tablaInstituciones tbody').on('click', '.btnEditar', function () {
        let fila = $(this).closest("tr");
        let codigo_institucion = fila.find('td:eq(0)').text();
        $('#institucionModalLabel').text('Editar Institución');
        
        // Limpiar previsualizaciones y campos de archivo al abrir en edición
        $('#logo_uno_file').val('');
        $('#logo_dos_file').val('');
        $('#preview_logo_uno').empty();
        $('#preview_logo_dos').empty();

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

                    // Guardar los nombres de logos actuales en campos ocultos
                    $('#logo_uno_actual').val(institucion.logo_uno || '');
                    $('#logo_dos_actual').val(institucion.logo_dos || '');

                    // Mostrar previsualizaciones de logos existentes
                    showImagePreview(document.getElementById('logo_uno_file'), 'preview_logo_uno', institucion.logo_uno, institucion.codigo_institucion);
                    showImagePreview(document.getElementById('logo_dos_file'), 'preview_logo_dos', institucion.logo_dos, institucion.codigo_institucion);
                  

                    $('#institucionModal').modal('show');
                } else {
                    toastr.error(response.mensaje);
                }
            }
        });
    });


    // Eventos para mostrar previsualización cuando se selecciona un archivo nuevo
    $('#logo_uno_file').on('change', function() {
        showImagePreview(this, 'preview_logo_uno', null, null);
    });
    $('#logo_dos_file').on('change', function() {
        showImagePreview(this, 'preview_logo_dos', null, null);
    });

    // Evento para el formulario de creación/edición
    $('#institucionForm').submit(function (e) {
        e.preventDefault();
            // Usar FormData para enviar archivos y otros datos del formulario
            let formData = new FormData(this);
        // Si es una nueva institución, el codigo_institucion está vacío,
        // el backend lo generará y lo devolverá.
        // Si es edición, el codigo_institucion ya estará en el formData.

            $.ajax({
                url: "admin/instituciones/crud_instituciones.php",
                type: "POST",
                dataType: "json",
                data: formData,
                processData: false, // Importante: No procesar los datos
                contentType: false, // Importante: No establecer el tipo de contenido
                success: function (response) {
                    if (response.respuesta) {
                        toastr.success(response.mensaje);
                        
                        // Si se acaba de crear, actualizar el campo de lectura con el nuevo código
                        if (response.nuevo_codigo) {
                            $('#codigo_institucion').val(response.nuevo_codigo);
                            $('#codigo_institucion_lectura').val(response.nuevo_codigo);
                            $('#codigo_institucion_display').hide();
                        }
                        
                        $('#institucionModal').modal('hide');
                        tablaInstituciones.ajax.reload();
                    } else {
                        toastr.error("Error: " + response.mensaje);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    toastr.error("Error al enviar el formulario: " + textStatus + ", " + errorThrown);
                    console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
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