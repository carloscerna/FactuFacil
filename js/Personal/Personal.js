// js/personal/Personal.js
$(function () {
    let tablaPersonal = $('#tablaPersonal').DataTable({
        "ajax": {
            "url": "admin/personal/crud_personal.php",
            "type": "POST",
            "data": { accion: "listarPersonal" },
            "dataSrc": "data"
        },
        "columns": [
            { "data": "id_personal" },
            { "data": "nombres" },
            { "data": "apellidos" },
            { "data": "dui" },
            { "data": "telefono_celular" },
            { "data": "cargo" },
            { "data": "estatus" },
            {
                "defaultContent": "<div class='text-center'><div class='btn-group'><button class='btn btn-warning btn-sm btnEditar'><i class='fas fa-edit'></i></button><button class='btn btn-danger btn-sm btnBorrar'><i class='fas fa-trash-alt'></i></button></div></div>"
            }
        ],
        "language": { "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/es_es.json" }
    });

    // Función para poblar todos los catálogos en el formulario
    function cargarCatalogos() {
        $.ajax({
            url: "admin/personal/crud_personal.php",
            type: "POST",
            dataType: "json",
            data: { accion: "obtenerCatalogos" },
            success: function (response) {
                if (response.respuesta) {
                    $.each(response.catalogos, function (nombreCatalogo, datos) {
                        let select = $(`#select${nombreCatalogo.charAt(0).toUpperCase() + nombreCatalogo.slice(1)}`);
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
        $('.form-control').removeClass('is-invalid is-valid');
    });

    // Evento para el botón "Editar"
    $('#tablaPersonal tbody').on('click', '.btnEditar', function () {
        let fila = $(this).closest("tr");
        let id_personal = parseInt(fila.find('td:eq(0)').text());
        $('#personalModalLabel').text('Editar Registro');
        $('#id_personal').val(id_personal);

        $.ajax({
            url: "admin/personal/crud_personal.php",
            type: "POST",
            dataType: "json",
            data: { accion: 'obtenerPersonal', id_personal: id_personal },
            success: function(response) {
                if (response.respuesta) {
                    let personal = response.personal;
                    $('#nombres').val(personal.nombres);
                    $('#apellidos').val(personal.apellidos);
                    $('#dui').val(personal.dui);
                    $('#nit').val(personal.nit);
                    $('#isss').val(personal.isss);
                    // ... (Poblar el resto de los campos y select) ...
                    cargarCatalogos();
                    $('#personalModal').modal('show');
                } else {
                    toastr.error(response.mensaje);
                }
            }
        });
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
    
    // Evento para el botón "Eliminar"
    $('#tablaPersonal tbody').on('click', '.btnBorrar', function () {
        let fila = $(this).closest("tr");
        let id_personal = parseInt(fila.find('td:eq(0)').text());

        if (confirm("¿Estás seguro de que quieres eliminar este registro?")) {
            $.ajax({
                url: "admin/personal/crud_personal.php",
                type: "POST",
                dataType: "json",
                data: { accion: 'eliminar', id_personal: id_personal },
                success: function (response) {
                    if (response.respuesta) {
                        toastr.success(response.mensaje);
                        tablaPersonal.ajax.reload();
                    } else {
                        toastr.error(response.mensaje);
                    }
                }
            });
        }
    });

    cargarCatalogos();
});