// js/personal/Personal.js
$(function () {
    let tablaPersonal = $('#tablaPersonal').DataTable({
        "ajax": {
            "url": "admin/personal/crud_personal.php",
            "type": "POST",
            "data": { accion: "listarPersonal" }
        },
        "columns": [
            { "data": "id_personal" },
            { "data": "nombres" },
            { "data": "apellidos" },
            { "data": "dui" },
            { "data": "telefono_celular" },
            { "data": "cargo" },
            { "data": "estatus" },
            { "data": "acciones" }
        ],
        "language": { "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/es_es.json" }
    });
    
    // Función para poblar todos los catálogos
    function cargarCatalogos() {
        $.ajax({
            url: "admin/personal/crud_personal.php",
            type: "POST",
            dataType: "json",
            data: { accion: "obtenerCatalogos" },
            success: function (response) {
                if (response.respuesta) {
                    $.each(response.catalogos, function (nombreCatalogo, datos) {
                        let select = $(`#select${nombreCatalogo}`); // Usa el ID correspondiente
                        select.empty().append('<option value="">Seleccione...</option>');
                        $.each(datos, function (key, item) {
                            select.append(`<option value="${item.codigo}">${item.descripcion}</option>`);
                        });
                    });
                }
            }
        });
    }

    // Evento para el botón "Nuevo Registro"
    $('#btnNuevoPersonal').on('click', function () {
        $('#personalModalLabel').text('Crear Nuevo Registro');
        $('#personalForm')[0].reset();
        $('#id_personal').val('');
        cargarCatalogos();
    });

    // Evento para el formulario de creación/edición
    $('#personalForm').submit(function (e) {
        e.preventDefault();
        let formData = $(this).serialize();
        $.ajax({
            url: "admin/personal/crud_personal.php",
            type: "POST",
            dataType: "json",
            data: formData,
            success: function (response) {
                if (response.respuesta) {
                    toastr.success(response.mensaje);
                    $('#personalModal').modal('hide');
                    tablaPersonal.ajax.reload();
                } else {
                    toastr.error("Error: " + response.mensaje);
                }
            }
        });
    });
    
    // Aquí puedes agregar la lógica para los eventos de editar y eliminar
    // ...
});