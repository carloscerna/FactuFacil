// js/productos/Productos.js

$(function () {
    let tablaProductos = $('#tablaProductos').DataTable({
        "ajax": {
            "url": "admin/productos/crud_productos.php",
            "type": "POST",
            "data": { accion: "listarProductos" },
            "dataSrc": "data"
        },
        "columns": [
            { "data": "id_productos" },
            { "data": "codigo_interno" },
            { "data": "descripcion" },
            { "data": "categoria_descripcion" },
            { "data": "precio_unitario" },
            { "data": "stock_actual" },
            { "defaultContent": "<div class='text-center'><div class='btn-group'><button class='btn btn-warning btn-sm btnEditar'><i class='fas fa-edit'></i></button><button class='btn btn-danger btn-sm btnBorrar'><i class='fas fa-trash-alt'></i></button></div></div>" }
        ],
        "language": { "url": "php_libs/idioma/es_es.json" }
    });

 // Función de cálculo
    function calcularPrecioUnitario() {
        const precioCosto = parseFloat($('#precio_costo').val()) || 0;
        const porcentajeGanancia = parseFloat($('#selectGanancia option:selected').data('porcentaje')) || 0;
        const tipoImpuesto = $('#tipo_impuesto').val();
        let precioConImpuesto = precioCosto;

        if (tipoImpuesto === 'PORCENTAJE') {
            const porcentajeImpuesto = parseFloat($('#porcentaje_impuesto').val()) || 0;
            precioConImpuesto = precioCosto * (1 + (porcentajeImpuesto / 100));
        } else if (tipoImpuesto === 'MONETARIO') {
            const montoImpuesto = parseFloat($('#monto_impuesto').val()) || 0;
            precioConImpuesto = precioCosto + montoImpuesto;
        }

        const precioFinal = precioConImpuesto * (1 + (porcentajeGanancia / 100));
        $('#precio_unitario').val(precioFinal.toFixed(2));
    }

    // Eventos para recalcular el precio al cambiar los campos
    $('#precio_costo').on('input', calcularPrecioUnitario);
    $('#selectGanancia').on('change', calcularPrecioUnitario);

    // Evento para el cambio en el select de impuestos
    $('#selectImpuesto').on('change', function() {
        const codigo_impuesto = $(this).val();
        if (codigo_impuesto) {
            $.ajax({
                url: "admin/productos/crud_productos.php",
                type: "POST",
                dataType: "json",
                data: { accion: 'obtenerPorcentajeImpuesto', codigo_impuesto: codigo_impuesto },
                success: function(response) {
                    if (response.respuesta) {
                        const impuesto = response.impuesto;
                        $('#tipo_impuesto').val(impuesto.tipo_impuesto);
                        $('#porcentaje_impuesto').val(impuesto.porcentaje);
                        $('#monto_impuesto').val(impuesto.monto_fijo);
                        calcularPrecioUnitario(); // Recalcular al obtener el impuesto
                    }
                }
            });
        }
    });
    // Función para cargar todos los catálogos
    function cargarCatalogos() {
        return $.ajax({
            url: "admin/productos/crud_productos.php",
            type: "POST",
            dataType: "json",
            data: { accion: "obtenerCatalogos" },
            success: function (response) {
                if (response.respuesta) {
                    let selectCategoria = $('#selectCategoria');
                    let selectUnidadMedida = $('#selectUnidadMedida');
                    let selectTipoItem = $('#selectTipoItem');
                    let selectImpuesto = $('#selectImpuesto');
                    let selectGanancia = $('#selectGanancia');
                    
                    selectCategoria.empty().append('<option value="">Seleccione...</option>');
                    $.each(response.catalogos.categorias, function (key, item) {
                        selectCategoria.append(`<option value="${item.codigo}">${item.descripcion}</option>`);
                    });

                    selectUnidadMedida.empty().append('<option value="">Seleccione...</option>');
                    $.each(response.catalogos.unidades_medida, function (key, item) {
                        selectUnidadMedida.append(`<option value="${item.codigo}">${item.descripcion}</option>`);
                    });

                    selectTipoItem.empty().append('<option value="">Seleccione...</option>');
                    $.each(response.catalogos.tipos_item, function (key, item) {
                        selectTipoItem.append(`<option value="${item.codigo}">${item.descripcion}</option>`);
                    });

                    selectImpuesto.empty().append('<option value="">Seleccione...</option>');
                    $.each(response.catalogos.impuestos, function (key, item) {
                        selectImpuesto.append(`<option value="${item.codigo}">${item.descripcion}</option>`);
                    });
                    
                    selectGanancia.empty().append('<option value="">Seleccione...</option>');
                    $.each(response.catalogos.ganancias, function (key, item) {
                        selectGanancia.append(`<option value="${item.codigo}" data-porcentaje="${item.porcentaje}">${item.descripcion} (${item.porcentaje}%)</option>`);
                    });
                }
            }
        });
    }

    // Evento para el botón "Nuevo Producto"
    $('#btnNuevoProducto').on('click', function () {
        $('#productoModalLabel').text('Crear Nuevo Producto');
        $('#productoForm')[0].reset();
        $('#id_productos').val('');
        $('#codigo_interno').val('Pendiente...');
        $('.form-control').removeClass('is-invalid is-valid');
        cargarCatalogos();
        $('#productoModal').modal('show');
    });

    // Evento para el botón "Editar"
    $('#tablaProductos tbody').on('click', '.btnEditar', function () {
        let fila = $(this).closest("tr");
        let id_productos = parseInt(fila.find('td:eq(0)').text());
        $('#productoModalLabel').text('Editar Producto');
        
        $.when(cargarCatalogos()).done(function() {
            $.ajax({
                url: "admin/productos/crud_productos.php",
                type: "POST",
                dataType: "json",
                data: { accion: 'obtenerProducto', id_productos: id_productos },
                success: function(response) {
                    if (response.respuesta) {
                        let producto = response.producto;
                        for (const key in producto) {
                            if (producto.hasOwnProperty(key) && typeof producto[key] === 'string') {
                                producto[key] = producto[key].trim();
                            }
                        }
                        
                        $('#id_productos').val(producto.id_productos);
                        $('#codigo_interno').val(producto.codigo_interno);
                        $('#descripcion').val(producto.descripcion);
                        $('#precio_unitario').val(producto.precio_unitario);
                        $('#precio_costo').val(producto.precio_costo);
                        $('#stock_actual').val(producto.stock_actual);
                        $('#stock_minimo').val(producto.stock_minimo);
                        $('#codigo_barra').val(producto.codigo_barra);
                        $('#comentario').val(producto.comentario);

                        $('#selectCategoria').val(producto.codigo_categoria);
                        $('#selectUnidadMedida').val(producto.unidad_medida);
                        $('#selectTipoItem').val(producto.tipo_item);
                        $('#selectImpuesto').val(producto.impuesto_aplicable);

                        // Aquí debes cargar los porcentajes para el cálculo en el formulario
                        // Esto requiere una lógica adicional en el backend o en el frontend.
                        // La mejor forma es que el backend devuelva también los porcentajes al editar.
                        
                        $('#productoModal').modal('show');
                    }
                }
            });
        });
    });

    // Evento para el formulario de creación/edición
    $('#productoForm').submit(function (e) {
        e.preventDefault();
        let formData = $(this).serialize();
        
        $.ajax({
            url: "admin/productos/crud_productos.php",
            type: "POST",
            dataType: "json",
            data: formData,
            success: function (response) {
                if (response.respuesta) {
                    toastr.success(response.mensaje);
                    $('#productoModal').modal('hide');
                    tablaProductos.ajax.reload();
                } else {
                    toastr.error("Error: " + response.mensaje);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                toastr.error("Error al enviar el formulario: " + textStatus + ", " + errorThrown);
            }
        });
    });

    // Evento para el botón "Eliminar"
    $('#tablaProductos tbody').on('click', '.btnBorrar', function () {
        let fila = $(this).closest("tr");
        let id_productos = parseInt(fila.find('td:eq(0)').text());

        if (confirm("¿Estás seguro de que quieres eliminar este registro?")) {
            $.ajax({
                url: "admin/productos/crud_productos.php",
                type: "POST",
                dataType: "json",
                data: { accion: 'eliminar', id_productos: id_productos },
                success: function (response) {
                    if (response.respuesta) {
                        toastr.success(response.mensaje);
                        tablaProductos.ajax.reload();
                    } else {
                        toastr.error(response.mensaje);
                    }
                }
            });
        }
    });

    cargarCatalogos();
});