// js/mantenimiento.js

$(function () {
    let tablaRegistros = null;

    function cargarSelectCatalogo() {
        $.ajax({
            url: "admin/cat_dte/crud_cat.php",
            type: "POST",
            dataType: "json",
            data: { accion: "listarCatalogoTablas" },
            success: function (response) {
                if (response.respuesta) {
                    let select = $('#selectCatalogo');
                    select.empty().append('<option value="">-- Seleccione un catálogo --</option>');
                    $.each(response.tablas, function (key, item) {
                        select.append(`<option value="${item.codigo}" data-titulo="${item.descripcion}">${item.descripcion}</option>`);
                    });
                } else {
                    toastr.error('Error al cargar la lista de catálogos.');
                }
            }
        });
    }

    $('#selectCatalogo').on('change', function() {
        const tabla = $(this).val();
        const titulo = $(this).find('option:selected').data('titulo');
        
        if (tabla) {
            $('#tabla_nombre').val(tabla);
            $('#tituloCatalogo').text(titulo);
            $('#seccionTabla').show();
            
            if (tablaRegistros) {
                tablaRegistros.destroy();
            }

            tablaRegistros = $('#tablaRegistros').DataTable({
                "ajax": {
                    "url": "admin/cat_dte/crud_cat.php",
                    "type": "POST",
                    "data": { accion: "listar", tabla: tabla },
                    "dataSrc": "data"
                },
                "columns": [
                    { "data": "id" },
                    { "data": "codigo" },
                    { "data": "descripcion" },
                    { "defaultContent": "<div class='text-center'><div class='btn-group'><button class='btn btn-warning btn-sm btnEditar'><i class='fas fa-edit'></i></button><button class='btn btn-danger btn-sm btnBorrar'><i class='fas fa-trash-alt'></i></button></div></div>" }
                ],
                "language": { "url": "php_libs/idioma/es_es.json" }
            });
        } else {
            $('#seccionTabla').hide();
        }
    });

    // Eventos para el modal de creación y edición
    $('#btnNuevoRegistro').on('click', function () {
        $('#registroModalLabel').text('Crear Nuevo Registro');
        $('#registroForm')[0].reset();
        $('#id_registro').val('');
        $('.form-control').removeClass('is-invalid is-valid');
        $('#registroModal').modal('show');
    });

    $('#tablaRegistros').on('click', '.btnEditar', function () {
        let fila = $(this).closest("tr");
        let id_registro = parseInt(fila.find('td:eq(0)').text());
        const tabla = $('#tabla_nombre').val();
        $('#registroModalLabel').text('Editar Registro');
        
        $.ajax({
            url: "admin/cat_dte/crud_cat.php",
            type: "POST",
            dataType: "json",
            data: { accion: 'obtener', tabla: tabla, id: id_registro },
            success: function(response) {
                if (response.respuesta) {
                    let item = response.item;
                    $('#id_registro').val(item.id);
                    $('#codigo').val(item.codigo);
                    $('#descripcion').val(item.descripcion);
                    $('#registroModal').modal('show');
                }
            }
        });
    });

    $('#registroForm').submit(function (e) {
        e.preventDefault();
        let formData = $(this).serialize();
        
        $.ajax({
            url: "admin/cat_dte/crud_cat.php",
            type: "POST",
            dataType: "json",
            data: formData,
            success: function (response) {
                if (response.respuesta) {
                    toastr.success(response.mensaje);
                    $('#registroModal').modal('hide');
                    tablaRegistros.ajax.reload();
                } else {
                    toastr.error("Error: " + response.mensaje);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                toastr.error("Error al enviar el formulario: " + textStatus + ", " + errorThrown);
            }
        });
    });

    $('#tablaRegistros').on('click', '.btnBorrar', function () {
        let fila = $(this).closest("tr");
        let id_registro = parseInt(fila.find('td:eq(0)').text());
        const tabla = $('#tabla_nombre').val();

        if (confirm("¿Estás seguro de que quieres eliminar este registro?")) {
            $.ajax({
                url: "admin/cat_dte/crud_cat.php",
                type: "POST",
                dataType: "json",
                data: { accion: 'eliminar', tabla: tabla, id: id_registro },
                success: function (response) {
                    if (response.respuesta) {
                        toastr.success(response.mensaje);
                        tablaRegistros.ajax.reload();
                    } else {
                        toastr.error(response.mensaje);
                    }
                }
            });
        }
    });

    cargarSelectCatalogo();
});