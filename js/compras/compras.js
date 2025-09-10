// js/compras/compras.js

$(function () {
    let productosCompra = [];

    function cargarProveedores() {
        $.ajax({
            url: 'admin/compras/crud_compras.php',
            type: 'POST',
            dataType: 'json',
            data: { accion: 'obtenerProveedores' },
            success: function(response) {
                if (response.respuesta) {
                    let select = $('#selectProveedor');
                    select.empty().append('<option value="">Seleccione un proveedor</option>');
                    $.each(response.proveedores, function(key, item) {
                        select.append(`<option value="${item.id_proveedor}">${item.nombre_empresa}</option>`);
                    });
                } else {
                    toastr.error('Error al cargar proveedores: ' + response.mensaje);
                }
            }
        });
    }

    // Funciones para buscar un producto por ID
    $('#idProducto').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            const id = $(this).val();
            if (id) {
                $.ajax({
                    url: 'admin/compras/crud_compras.php',
                    type: 'POST',
                    dataType: 'json',
                    data: { accion: 'buscarProducto', producto_id: id },
                    success: function(response) {
                        if (response.respuesta) {
                            $('#descripcionProducto').val(response.producto.descripcion);
                            $('#precioUnitarioProducto').val(response.producto.precio_costo); // Asumiendo que el precio de costo del catalogo es el precio unitario de la compra
                            $('#cantidadProducto').focus().select();
                        } else {
                            toastr.error('Producto no encontrado.');
                            $('#idProducto').focus().select();
                        }
                    }
                });
            }
        }
    });

    // Función para agregar un producto a la tabla
    $('#btnAgregarProducto').on('click', function() {
        const id = $('#idProducto').val();
        const descripcion = $('#descripcionProducto').val();
        const cantidad = parseFloat($('#cantidadProducto').val()) || 0;
        const precioUnitario = parseFloat($('#precioUnitarioProducto').val()) || 0;
        
        if (!id || !descripcion || cantidad <= 0 || precioUnitario <= 0) {
            toastr.warning('Por favor, complete todos los campos del producto.');
            return;
        }

        const subtotal = cantidad * precioUnitario;
        
        const producto = {
            producto_id: id,
            descripcion: descripcion,
            cantidad: cantidad,
            precio_unitario: precioUnitario,
            subtotal: subtotal
        };

        productosCompra.push(producto);
        renderizarTabla();
        limpiarFormularioProducto();
    });

    function renderizarTabla() {
        let tbody = $('#tablaProductosCompra tbody');
        tbody.empty();
        let total = 0;

        productosCompra.forEach((p, index) => {
            total += p.subtotal;
            const fila = `
                <tr>
                    <td>${p.producto_id}</td>
                    <td>${p.descripcion}</td>
                    <td class="text-end">${p.cantidad.toFixed(2)}</td>
                    <td class="text-end">${p.precio_unitario.toFixed(2)}</td>
                    <td class="text-end">${p.subtotal.toFixed(2)}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-danger btn-sm btnEliminarProducto" data-index="${index}"><i class="fas fa-trash-alt"></i></button>
                    </td>
                </tr>
            `;
            tbody.append(fila);
        });

        $('#totalCompra').text(total.toFixed(2));
    }

    $('#tablaProductosCompra tbody').on('click', '.btnEliminarProducto', function() {
        const index = $(this).data('index');
        productosCompra.splice(index, 1);
        renderizarTabla();
    });

    function limpiarFormularioProducto() {
        $('#idProducto').val('');
        $('#descripcionProducto').val('');
        $('#cantidadProducto').val('1');
        $('#precioUnitarioProducto').val('');
        $('#idProducto').focus();
    }

    $('#formCompra').submit(function(e) {
        e.preventDefault();

        if (productosCompra.length === 0) {
            toastr.error('Debe agregar al menos un producto a la compra.');
            return;
        }

        const totalFinal = parseFloat($('#totalCompra').text());

        const formData = {
            accion: 'guardarCompra',
            numero_documento: $('[name="numero_documento"]').val(),
            tipo_documento: $('[name="tipo_documento"]').val(),
            fecha_emision: $('[name="fecha_emision"]').val(),
            proveedor_id: $('#selectProveedor').val(),
            condicion_pago: $('#selectCondicionPago').val(),
            plazo_pago: $('#plazoPago').val() || null,
            total_compra: totalFinal.toFixed(2),
            observaciones: $('[name="observaciones"]').val(),
            productos: JSON.stringify(productosCompra)
        };

        $.ajax({
            url: 'admin/compras/crud_compras.php',
            type: 'POST',
            dataType: 'json',
            data: formData,
            success: function(response) {
                if (response.respuesta) {
                    toastr.success(response.mensaje);
                    $('#formCompra')[0].reset();
                    productosCompra = [];
                    renderizarTabla();
                } else {
                    toastr.error('Error: ' + response.mensaje);
                }
            },
            error: function() {
                toastr.error('Error al procesar la solicitud.');
            }
        });
    });

    // Lógica para el campo de Plazo de Pago
    $('#selectCondicionPago').on('change', function() {
        const condicion = $(this).val();
        if (condicion === 'Credito') {
            $('#plazoPago').prop('readonly', false);
        } else {
            $('#plazoPago').prop('readonly', true).val('');
        }
    });

    cargarProveedores();
});