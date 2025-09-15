// js/compras/compras.js

$(function () {
    let productosCompra = [];
    let tablaBusquedaProductosDT = null; // Variable para la instancia de DataTable

    // Obtener el id_compra de la plantilla (se pasa desde el PHP)
    const id_compra = $('input#idCompra').val();

    function cargarCatalogos() {
        $.ajax({
            url: 'admin/compras/crud_compras.php',
            type: 'POST',
            dataType: 'json',
            data: { accion: 'obtenerCatalogosCompra' },
            success: function(response) {
                if (response.respuesta) {
                    let selectTipoDte = $('#selectTipoDte');
                    selectTipoDte.empty().append('<option value="">Seleccione...</option>');
                    $.each(response.catalogos.tipos_documento, function(key, item) {
                        selectTipoDte.append(`<option value="${item.codigo}">${item.descripcion}</option>`);
                    });

                    let selectCondicionPago = $('#selectCondicionPago');
                    selectCondicionPago.empty().append('<option value="">Seleccione...</option>');
                    $.each(response.catalogos.condiciones_pago, function(key, item) {
                        selectCondicionPago.append(`<option value="${item.codigo}">${item.descripcion}</option>`);
                    });

                    let selectPlazoPagoDTE = $('#selectPlazoPagoDTE');
                    selectPlazoPagoDTE.empty().append('<option value="">Seleccione...</option>');
                    $.each(response.catalogos.plazos_pago, function(key, item) {
                        selectPlazoPagoDTE.append(`<option value="${item.codigo}">${item.descripcion}</option>`);
                    });
                } else {
                    toastr.error('Error al cargar catálogos: ' + response.mensaje);
                }
            }
        });
    }

  async function obtenerDetalleImpuesto(codigo_impuesto) {
        if (!codigo_impuesto || codigo_impuesto === '00') {
            return { descripcion_completa: 'N/A', tipo_impuesto: null, porcentaje: 0, monto_fijo: 0, codigo: '00' };
        }
        try {
            const response = await $.ajax({
                url: 'admin/compras/crud_compras.php',
                type: 'POST',
                dataType: 'json',
                data: { accion: 'obtenerDetalleImpuesto', codigo_impuesto: codigo_impuesto }
            });

            if (response.respuesta && response.impuesto) {
                const impuesto = response.impuesto;
                let descripcion_completa = impuesto.descripcion || '';
                
                if (impuesto.tipo_impuesto === 'PORCENTAJE') {
                    descripcion_completa += ` (${parseFloat(impuesto.porcentaje).toFixed(2)}%)`;
                } else if (impuesto.tipo_impuesto === 'MONETARIO') {
                    descripcion_completa += ` ($${parseFloat(impuesto.monto_fijo).toFixed(2)})`;
                }

                return {
                    descripcion_completa: descripcion_completa.trim(),
                    tipo_impuesto: impuesto.tipo_impuesto,
                    porcentaje: parseFloat(impuesto.porcentaje) || 0,
                    monto_fijo: parseFloat(impuesto.monto_fijo) || 0,
                    codigo: codigo_impuesto
                };
            } else {
                return { descripcion_completa: 'N/A', tipo_impuesto: null, porcentaje: 0, monto_fijo: 0, codigo: '00' };
            }
        } catch (error) {
            console.error('Error al obtener el detalle del impuesto:', error);
            return { descripcion_completa: 'N/A', tipo_impuesto: null, porcentaje: 0, monto_fijo: 0, codigo: '00' };
        }
    }

   async function calcularPrecioConImpuesto(precioCosto, impuesto) {
        let precioFinal = parseFloat(precioCosto) || 0;
        
        if (impuesto && impuesto.tipo_impuesto === 'PORCENTAJE') {
            precioFinal = precioFinal * (1 + (impuesto.porcentaje / 100));
        } else if (impuesto && impuesto.tipo_impuesto === 'MONETARIO') {
            precioFinal = precioFinal + impuesto.monto_fijo;
        }

        return precioFinal;
    }

    async function calcularPrecioConGanancia(precioCosto, codigoGanancia) {
        if (!codigoGanancia || precioCosto === undefined) {
            return parseFloat(precioCosto);
        }

        try {
            const response = await $.ajax({
                url: 'admin/compras/crud_compras.php',
                type: 'POST',
                dataType: 'json',
                data: { accion: 'obtenerDetalleGanancia', codigo_ganancia: codigoGanancia }
            });
            
            if (response.respuesta && response.ganancia) {
                const porcentaje = parseFloat(response.ganancia.porcentaje);
                return precioCosto * (1 + (porcentaje / 100));
            } else {
                console.error('No se pudo obtener el detalle de la ganancia:', response.mensaje);
                return parseFloat(precioCosto);
            }
        } catch (error) {
            console.error('Error al obtener el detalle de ganancia:', error);
            return parseFloat(precioCosto);
        }
    }

    $('#selectCondicionPago').on('change', function() {
        const codigo_condicion = $(this).val();
        if (codigo_condicion === '02') {
            $('#selectPlazoPagoDTE').prop('disabled', false).val('');
        } else {
            $('#selectPlazoPagoDTE').prop('disabled', true).val('');
        }
    });

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
                        select.append(`<option value="${item.id_proveedores}">${item.nombre_empresa}</option>`);
                    });
                } else {
                    toastr.error('Error al cargar proveedores: ' + response.mensaje);
                }
            }
        });
    }


       $('#codigoProducto').on('keypress', async function(e) {
        if (e.which === 13) {
            e.preventDefault();
            const termino = $(this).val();
            if (termino) {
                $.ajax({
                    url: 'admin/compras/crud_compras.php',
                    type: 'POST',
                    dataType: 'json',
                    data: { accion: 'buscarProducto', termino: termino },
                    success: async function(response) {
                        if (response.respuesta) {
                            const producto = response.producto;
                            $('#descripcionProducto').val(producto.descripcion);
                            $('#impuestoAplicableProducto').val(producto.impuesto_aplicable);
                            $('#codigoGananciaProducto').val(producto.codigo_ganancia);

                            const impuestoInfo = await obtenerDetalleImpuesto(producto.impuesto_aplicable);
                            const precioConImpuesto = await calcularPrecioConImpuesto(producto.precio_costo, impuestoInfo);
                            const precioUnitarioFinal = await calcularPrecioConGanancia(precioConImpuesto, producto.codigo_ganancia);
                            
                            $('#precioUnitarioProducto').val(precioUnitarioFinal.toFixed(2));
                            $('#cantidadProducto').focus().select();
                        } else {
                            toastr.warning('Producto no encontrado. Utilice la búsqueda por descripción.');
                            $('#codigoProducto').focus().select();
                        }
                    }
                });
            }
        }
    });


 $('#tablaBusquedaProductos tbody').on('click', '.btnSeleccionarProductoModal', async function() {
        const data = tablaBusquedaProductosDT.row($(this).parents('tr')).data();
        
        $('#codigoProducto').val(data.id_productos); 
        $('#descripcionProducto').val(data.descripcion);
        $('#impuestoAplicableProducto').val(data.impuesto_aplicable);
        $('#codigoGananciaProducto').val(data.codigo_ganancia);
        
        const impuestoInfo = await obtenerDetalleImpuesto(data.impuesto_aplicable);
        const precioConImpuesto = await calcularPrecioConImpuesto(data.precio_costo, impuestoInfo);
        const precioUnitarioFinal = await calcularPrecioConGanancia(precioConImpuesto, data.codigo_ganancia);
        
        $('#precioUnitarioProducto').val(precioUnitarioFinal.toFixed(2));
        
        $('#buscarProductoModal').modal('hide');
        $('#cantidadProducto').focus().select();
    });

    // He modificado el DataTables para que muestre el impuesto aplicable
      $('#buscarProductoModal').on('shown.bs.modal', function() {
        if (tablaBusquedaProductosDT) {
            tablaBusquedaProductosDT.ajax.reload();
        } else {
            tablaBusquedaProductosDT = $('#tablaBusquedaProductos').DataTable({
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "admin/compras/crud_compras.php",
                    "type": "POST",
                    "data": function (d) {
                        d.accion = 'buscarProductoDescripcion';
                    },
                    "dataSrc": "data"
                },
                "columns": [
                    { "data": "id_productos" },
                    { "data": "codigo_interno" },
                    { "data": "descripcion" },
                    { "data": "precio_costo", "render": $.fn.dataTable.render.number(',', '.', 2, '$') },
                    { "data": "impuesto_aplicable" },
                    { "defaultContent": "<div class='text-center'><button class='btn btn-primary btn-sm btnSeleccionarProductoModal'><i class='fas fa-check me-1'></i>Seleccionar</button></div>" }
                ],
                "language": { "url": "php_libs/idioma/es_es.json" }
            });
        }
        $('#tablaBusquedaProductos_filter input').focus();
    });

 function renderizarTabla() {
        let tbody = $('#tablaProductosCompra tbody');
        tbody.empty();
        let total = 0;

        productosCompra.forEach((p, index) => {
            total += p.subtotal;
            const fila = `
                <tr>
                    <td>${p.id_productos}</td>
                    <td>${p.descripcion}</td>
                    <td class="text-end">${p.cantidad.toFixed(2)}</td>
                    <td class="text-end">${p.precio_unitario.toFixed(2)}</td>
                    <td>${p.impuesto_descripcion || 'N/A'}</td>
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

    $('#btnAgregarProducto').on('click', async function() {
        const id = $('#codigoProducto').val();
        const descripcion = $('#descripcionProducto').val();
        const impuestoAplicable = $('#impuestoAplicableProducto').val();
        const codigoGanancia = $('#codigoGananciaProducto').val();
        const cantidad = parseFloat($('#cantidadProducto').val()) || 0;
        const precioUnitario = parseFloat($('#precioUnitarioProducto').val()) || 0;
        
        if (!id || !descripcion || cantidad <= 0 || precioUnitario <= 0) {
            toastr.warning('Por favor, complete todos los campos del producto.');
            return;
        }

        const subtotal = cantidad * precioUnitario;
        const impuestoInfo = await obtenerDetalleImpuesto(impuestoAplicable);
        
        const producto = {
            id_productos: id,
            descripcion: descripcion,
            cantidad: cantidad,
            precio_unitario: precioUnitario,
            impuesto_aplicable: impuestoAplicable,
            impuesto_descripcion: impuestoInfo.descripcion_completa,
            subtotal: subtotal
        };

        productosCompra.push(producto);
        renderizarTabla();
        limpiarFormularioProducto();
    });
    
    function limpiarFormularioProducto() {
        $('#codigoProducto').val('');
        $('#descripcionProducto').val('');
        $('#impuestoAplicableProducto').val('');
        $('#codigoGananciaProducto').val('');
        $('#cantidadProducto').val('1');
        $('#precioUnitarioProducto').val('');
        $('#codigoProducto').focus();
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
            id_proveedores: $('#selectProveedor').val(),
            condicion_pago: $('#selectCondicionPago').val(),
            plazo_pago: $('#selectPlazoPagoDTE').val(),
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

// Nueva función para cargar los datos de la compra a editar
    function cargarDatosCompra(id) {
        $.when(cargarCatalogos(), cargarProveedores()).done(function() {
            $.ajax({
                url: 'admin/compras/crud_compras.php',
                type: 'POST',
                dataType: 'json',
                data: { accion: 'obtenerCompra', id_compra: id },
                success: async function(response) {
                    if (response.respuesta) {
                        const compra = response.compra;
                        const detalle = response.detalle;
                        
                        // Poblar el formulario de cabecera
                        $('#idCompra').val(compra.id_compra);
                        $('[name="numero_documento"]').val(compra.numero_documento);
                        $('#selectTipoDte').val(compra.tipo_documento);
                        $('[name="fecha_emision"]').val(compra.fecha_emision);
                        $('#selectProveedor').val(compra.id_proveedores);
                        $('#selectCondicionPago').val(compra.condicion_pago).trigger('change');
                        $('#selectPlazoPagoDTE').val(compra.plazo_pago);
                        $('[name="observaciones"]').val(compra.observaciones);

                        // Poblar la tabla de productos de la compra
                        productosCompra = [];
                        for (const item of detalle) {
                            productosCompra.push({
                                id_productos: item.id_productos,
                                descripcion: item.descripcion,
                                cantidad: parseFloat(item.cantidad),
                                precio_unitario: parseFloat(item.precio_unitario),
                                impuesto_aplicable: item.impuesto_aplicable,
                                impuesto_descripcion: (await obtenerDetalleImpuesto(item.impuesto_aplicable)).descripcion_completa,
                                subtotal: parseFloat(item.subtotal)
                            });
                        }
                        renderizarTabla();

                    } else {
                        toastr.error(response.mensaje);
                    }
                }
            });
        });
    }

    // Al inicio de la carga de la página
    if (id_compra) {
        cargarDatosCompra(id_compra);
    } else {
        toastr.error('ID de compra no especificado para la edición.');
    }

    // Evento para el envío del formulario de actualización
    $('#formEditarCompra').submit(function(e) {
        e.preventDefault();

        if (productosCompra.length === 0) {
            toastr.error('Debe agregar al menos un producto a la compra.');
            return;
        }

        const totalFinal = parseFloat($('#totalCompra').text());

        const formData = {
            accion: 'actualizarCompra',
            id_compra: $('#idCompra').val(),
            numero_documento: $('[name="numero_documento"]').val(),
            tipo_documento: $('[name="tipo_documento"]').val(),
            fecha_emision: $('[name="fecha_emision"]').val(),
            id_proveedores: $('#selectProveedor').val(),
            condicion_pago: $('#selectCondicionPago').val(),
            plazo_pago: $('#selectPlazoPagoDTE').val(),
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
                    // Opcional: Redirigir a una página de listado de compras
                    // window.location.href = 'listado_compras.html';
                } else {
                    toastr.error('Error: ' + response.mensaje);
                }
            },
            error: function() {
                toastr.error('Error al procesar la solicitud.');
            }
        });
    });

    // Cargar los datos de la compra al iniciar la página
    if (id_compra) {
        cargarDatosCompra(id_compra);
    } else {
        toastr.error('ID de compra no especificado para la edición.');
    }

    cargarProveedores();
    cargarCatalogos();
});