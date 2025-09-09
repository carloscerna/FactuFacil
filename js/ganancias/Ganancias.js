// js/ganancias/Ganancias.js

$(function () {
    let tablaGanancias = $('#tablaGanancias').DataTable({
        "ajax": {
            "url": "admin/ganancias/crud_ganancias.php",
            "type": "POST",
            "data": { accion: "listarGanancias" },
            "dataSrc": "data"
        },
        "columns": [
            { "data": "id_ganancia" },
            { "data": "codigo" },
            { "data": "descripcion" },
            { "data": "porcentaje" },
            { "defaultContent": "<div class='text-center'><div class='btn-group'><button class='btn btn-warning btn-sm btnEditar'><i class='fas fa-edit'></i></button><button class='btn btn-danger btn-sm btnBorrar'><i class='fas fa-trash-alt'></i></button></div></div>" }
        ],
        "language": { "url": "php_libs/idioma/es_es.json" }
    });

    $('#btnNuevaGanancia').on('click', function () {
        $('#gananciaModalLabel').text('Crear Nuevo Porcentaje');
        $('#gananciaForm')[0].reset();
        $('#id_ganancia').val('');
        $('#codigo').val('Pendiente...');
        $('.form-control').removeClass('is-invalid is-valid');
        $('#gananciaModal').modal('show');
    });

    $('#tablaGanancias tbody').on('click', '.btnEditar', function () {
        let fila = $(this).closest("tr");
        let id_ganancia = parseInt(fila.find('td:eq(0)').text());
        $('#gananciaModalLabel').text('Editar Porcentaje de Ganancia');
        
        $.ajax({
            url: "admin/ganancias/crud_ganancias.php",
            type: "POST",
            dataType: "json",
            data: { accion: 'obtenerGanancia', id_ganancia: id_ganancia },
            success: function(response) {
                if (response.respuesta) {
                    let ganancia = response.ganancia;
                    for (const key in ganancia) {
                        if (ganancia.hasOwnProperty(key) && typeof ganancia[key] === 'string') {
                            ganancia[key] = ganancia[key].trim();
                        }
                    }

                    $('#id_ganancia').val(ganancia.id_ganancia);
                    $('#codigo').val(ganancia.codigo);
                    $('#descripcion').val(ganancia.descripcion);
                    $('#porcentaje').val(ganancia.porcentaje);
                    
                    $('#gananciaModal').modal('show');
                }
            }
        });
    });

    $('#gananciaForm').submit(function (e) {
        e.preventDefault();
        let formData = $(this).serialize();
        
        $.ajax({
            url: "admin/ganancias/crud_ganancias.php",
            type: "POST",
            dataType: "json",
            data: formData,
            success: function (response) {
                if (response.respuesta) {
                    toastr.success(response.mensaje);
                    $('#gananciaModal').modal('hide');
                    tablaGanancias.ajax.reload();
                } else {
                    toastr.error("Error: " + response.mensaje);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                toastr.error("Error al enviar el formulario: " + textStatus + ", " + errorThrown);
            }
        });
    });

    $('#tablaGanancias tbody').on('click', '.btnBorrar', function () {
        let fila = $(this).closest("tr");
        let id_ganancia = parseInt(fila.find('td:eq(0)').text());

        if (confirm("¿Estás seguro de que quieres eliminar este registro?")) {
            $.ajax({
                url: "admin/ganancias/crud_ganancias.php",
                type: "POST",
                dataType: "json",
                data: { accion: 'eliminar', id_ganancia: id_ganancia },
                success: function (response) {
                    if (response.respuesta) {
                        toastr.success(response.mensaje);
                        tablaGanancias.ajax.reload();
                    } else {
                        toastr.error(response.mensaje);
                    }
                }
            });
        }
    });
});