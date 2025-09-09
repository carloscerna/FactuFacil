// js/categorias/Categorias.js

$(function () {
    let tablaCategorias = $('#tablaCategorias').DataTable({
        "ajax": {
            "url": "admin/categorias/crud_categorias.php",
            "type": "POST",
            "data": { accion: "listarCategorias" },
            "dataSrc": "data"
        },
        "columns": [
            { "data": "id_categoria" },
            { "data": "codigo" },
            { "data": "descripcion" },
            { "defaultContent": "<div class='text-center'><div class='btn-group'><button class='btn btn-warning btn-sm btnEditar'><i class='fas fa-edit'></i></button><button class='btn btn-danger btn-sm btnBorrar'><i class='fas fa-trash-alt'></i></button></div></div>" }
        ],
        "language": { "url": "php_libs/idioma/es_es.json" }
    });

    $('#btnNuevaCategoria').on('click', function () {
        $('#categoriaModalLabel').text('Crear Nueva Categoría');
        $('#categoriaForm')[0].reset();
        $('#id_categoria').val('');
        $('#codigo').val('Pendiente...');
        $('.form-control').removeClass('is-invalid is-valid');
        $('#categoriaModal').modal('show');
    });

    $('#tablaCategorias tbody').on('click', '.btnEditar', function () {
        let fila = $(this).closest("tr");
        let id_categoria = parseInt(fila.find('td:eq(0)').text());
        $('#categoriaModalLabel').text('Editar Categoría');
        
        $.ajax({
            url: "admin/categorias/crud_categorias.php",
            type: "POST",
            dataType: "json",
            data: { accion: 'obtenerCategoria', id_categoria: id_categoria },
            success: function(response) {
                if (response.respuesta) {
                    let categoria = response.categoria;
                    for (const key in categoria) {
                        if (categoria.hasOwnProperty(key) && typeof categoria[key] === 'string') {
                            categoria[key] = categoria[key].trim();
                        }
                    }

                    $('#id_categoria').val(categoria.id_categoria);
                    $('#codigo').val(categoria.codigo);
                    $('#descripcion').val(categoria.descripcion);
                    $('#comentario').val(categoria.comentario);
                    
                    $('#categoriaModal').modal('show');
                }
            }
        });
    });

    $('#categoriaForm').submit(function (e) {
        e.preventDefault();
        let formData = $(this).serialize();
        
        $.ajax({
            url: "admin/categorias/crud_categorias.php",
            type: "POST",
            dataType: "json",
            data: formData,
            success: function (response) {
                if (response.respuesta) {
                    toastr.success(response.mensaje);
                    $('#categoriaModal').modal('hide');
                    tablaCategorias.ajax.reload();
                } else {
                    toastr.error("Error: " + response.mensaje);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                toastr.error("Error al enviar el formulario: " + textStatus + ", " + errorThrown);
            }
        });
    });

    $('#tablaCategorias tbody').on('click', '.btnBorrar', function () {
        let fila = $(this).closest("tr");
        let id_categoria = parseInt(fila.find('td:eq(0)').text());

        if (confirm("¿Estás seguro de que quieres eliminar este registro?")) {
            $.ajax({
                url: "admin/categorias/crud_categorias.php",
                type: "POST",
                dataType: "json",
                data: { accion: 'eliminar', id_categoria: id_categoria },
                success: function (response) {
                    if (response.respuesta) {
                        toastr.success(response.mensaje);
                        tablaCategorias.ajax.reload();
                    } else {
                        toastr.error(response.mensaje);
                    }
                }
            });
        }
    });
});