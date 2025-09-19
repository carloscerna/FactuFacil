$(function () {
    let productosCompra = [];
    let tablaBusquedaProductosDT = null;
    let modoDeGuardado = 'manual';
    let compraEncabezadoDte = {}; // New global variable to store DTE header data

    // Controlar la visibilidad de los formularios
    $('#btnManual').on('click', function() {
        $('#seccionManual').show();
        $('#seccionDte').hide();
        modoDeGuardado = 'manual';
        // Limpiar el formulario y el estado del DTE al cambiar de modo
        $('#formCompra')[0].reset();
        productosCompra = [];
        renderizarTabla();
    });

    $('#btnDte').on('click', function() {
        $('#seccionManual').hide();
        $('#seccionDte').show();
        modoDeGuardado = 'dte';
        console.log(modoDeGuardado);
        // Limpiar el formulario al cambiar de modo
        $('#formCompra')[0].reset();
        productosCompra = [];
        renderizarTabla();
    });

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

    async function calcularPrecioConGanancia(precioConImpuesto, codigoGanancia) {
        if (!codigoGanancia || precioConImpuesto === undefined) {
            return parseFloat(precioConImpuesto) || 0;
        }

        try {
            const response = await $.ajax({
                url: 'admin/compras/crud_compras.php',
                type: 'POST',
                dataType: 'json',
                data: { accion: 'obtenerDetalleGanancia', codigo_ganancia: codigoGanancia }
            });
            
            if (response.respuesta && response.ganancia) {
                const porcentaje = parseFloat(response.ganancia.porcentaje) || 0;
                return precioConImpuesto * (1 + (porcentaje / 100));
            } else {
                console.error('No se pudo obtener el detalle de la ganancia:', response.mensaje);
                return parseFloat(precioConImpuesto);
            }
        } catch (error) {
            console.error('Error al obtener el detalle de ganancia:', error);
            return parseFloat(precioConImpuesto);
        }
    }

    function cargarCatalogos() {
        return $.ajax({
            url: 'admin/compras/crud_compras.php',
            type: 'POST',
            dataType: 'json',
            data: { accion: 'obtenerCatalogosCompra' }
        });
    }

    function cargarProveedores() {
        return $.ajax({
            url: 'admin/compras/crud_compras.php',
            type: 'POST',
            dataType: 'json',
            data: { accion: 'obtenerProveedores' }
        });
    }

    function renderizarSelects(catalogoData, proveedoresData) {
        if (catalogoData[0].respuesta) {
            let selectTipoDte = $('#selectTipoDte');
            selectTipoDte.empty().append('<option value="">Seleccione...</option>');
            $.each(catalogoData[0].catalogos.tipos_documento, function(key, item) {
                selectTipoDte.append(`<option value="${item.codigo}">${item.descripcion}</option>`);
            });

            let selectCondicionPago = $('#selectCondicionPago');
            selectCondicionPago.empty().append('<option value="">Seleccione...</option>');
            $.each(catalogoData[0].catalogos.condiciones_pago, function(key, item) {
                selectCondicionPago.append(`<option value="${item.codigo}">${item.descripcion}</option>`);
            });

            let selectPlazoPagoDTE = $('#selectPlazoPagoDTE');
            selectPlazoPagoDTE.empty().append('<option value="">Seleccione...</option>');
            $.each(catalogoData[0].catalogos.plazos_pago, function(key, item) {
                selectPlazoPagoDTE.append(`<option value="${item.codigo}">${item.descripcion}</option>`);
            });
        } else {
            toastr.error('Error al cargar catálogos: ' + catalogoData[0].mensaje);
        }

        if (proveedoresData[0].respuesta) {
            let select = $('#selectProveedor');
            select.empty().append('<option value="">Seleccione un proveedor</option>');
            $.each(proveedoresData[0].proveedores, function(key, item) {
                select.append(`<option value="${item.id_proveedores}">${item.nombre_empresa}</option>`);
            });
        } else {
            toastr.error('Error al cargar proveedores: ' + proveedoresData[0].mensaje);
        }
    }

   function renderizarTabla() {
    let tbody = $('#tablaProductosCompra tbody');
    tbody.empty();
    let total = 0;

    productosCompra.forEach((p, index) => {
        const subtotal = (p.cantidad * p.precio_unitario) - (p.descuento || 0);
        total += subtotal;

        const fila = `
            <tr data-index="${index}">
                <td>${p.codigo_interno || '<span class="text-muted">Auto</span>'}</td>
                <td>${p.codigo_proveedor || ''}</td>
                <td class="text-end">
                    <input type="number" class="form-control form-control-sm text-end input-cantidad" 
                           value="${p.cantidad}" min="0.01" step="0.01" data-index="${index}">
                </td>
                <td>${p.unidad_medida || ''}</td>
                <td>${p.descripcion}</td>
                <td class="text-end">
                    <input type="number" class="form-control form-control-sm text-end input-precio" 
                           value="${(p.precio_unitario || 0).toFixed(2)}" min="0.01" step="0.01" data-index="${index}">
                </td>
                <td class="text-end">${(p.descuento || 0).toFixed(2)}</td>
                <td class="text-end">${(p.venta_no_suj || 0).toFixed(2)}</td>
                <td class="text-end">${(p.venta_exenta || 0).toFixed(2)}</td>
                <td class="text-end">${(p.venta_gravada || 0).toFixed(2)}</td>
                <td class="text-end subtotal-row">${subtotal.toFixed(2)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-danger btn-sm btnEliminarProducto" data-index="${index}">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            </tr>
        `;
        tbody.append(fila);
    });

    $('#totalCompra').text(total.toFixed(2));
}


    // Evento para detectar cambios en la cantidad o el precio
    $('#tablaProductosCompra tbody').on('change', '.input-cantidad, .input-precio', function() {
        const index = $(this).data('index');
        const campo = $(this).hasClass('input-cantidad') ? 'cantidad' : 'precio_unitario';
        const valor = $(this).val();

        productosCompra[index][campo] = parseFloat(valor) || 0;
//        productosCompra[index].subtotal = (productosCompra[index].cantidad * productosCompra[index].precio_unitario);
        productosCompra[index].subtotal = 
            (productosCompra[index].cantidad * productosCompra[index].precio_unitario) 
            - (productosCompra[index].descuento || 0);

        renderizarTabla();
    });

    // Evento para el formulario de registro de compras (Registro Manual)
    $('#formCompra').submit(function(e) {
        e.preventDefault();

        if (productosCompra.length === 0) {
            toastr.error('Debe agregar al menos un producto a la compra.');
            return;
        }

        const totalFinal = parseFloat($('#totalCompra').text());

        let accionDeGuardado = (modoDeGuardado === 'dte') ? 'guardarCompraProcesada' : 'guardarCompra';
        let datosDeEnvio = {};
        
        if (accionDeGuardado === 'guardarCompraProcesada') {
            datosDeEnvio = {
                accion: accionDeGuardado,
                compra_data: JSON.stringify(compraEncabezadoDte),
                productos_data: JSON.stringify(productosCompra)
            };
        } else {
            datosDeEnvio = {
                accion: accionDeGuardado,
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
        }

        $.ajax({
            url: 'admin/compras/crud_compras.php',
            type: 'POST',
            dataType: 'json',
            data: datosDeEnvio,
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

    // Lógica para buscar producto por ID o código de barra
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

    // Eventos para el formulario de subida de DTE
    $('#formSubirDte').submit(function(e) {
        e.preventDefault();
        const fileInput = $('#json_file')[0];
        if (fileInput.files.length === 0) {
            toastr.error('Por favor, seleccione un archivo JSON.');
            return;
        }

        const formData = new FormData(this);
        formData.append('accion', 'procesarJsonDte');

        $.ajax({
            url: 'admin/compras/crud_compras.php',
            type: 'POST',
            dataType: 'json',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                if (response.respuesta) {
                    toastr.success('DTE procesado. Verifique los datos para guardar la compra.');
                    mostrarVistaPrevia(response.compra, response.productos);
                } else {
                    toastr.error('Error: ' + response.mensaje);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                toastr.error('Error al procesar el DTE: ' + textStatus + ', ' + errorThrown);
            }
        });
    });

    // Muestra la vista previa del DTE en el formulario de registro manual
    function mostrarVistaPrevia(compra, productos) {
        // Almacenar los datos de la compra del DTE
        compraEncabezadoDte = compra;
        
        // Llenar los campos de encabezado del formulario manual
        $('[name="numero_documento"]').val(compra.numero_control);
        $('[name="tipo_documento"]').val(compra.tipo_dte);
        $('[name="fecha_emision"]').val(compra.fecha_emision);
        $('[name="id_proveedores"]').val(compra.id_proveedores); // Asume que el ID del proveedor está en el DTE
        $('[name="condicion_pago"]').val(compra.tipo_operacion);
        $('[name="plazo_pago"]').val(compra.plazo_pago);
        $('[name="observaciones"]').val(compra.observaciones);

        // Llenar el arreglo de productos y renderizar la tabla
        productosCompra = productos;
        renderizarTabla();
        
        // Ocultar el formulario DTE y mostrar el formulario manual
        $('#seccionDte').hide();
        $('#seccionManual').show();
        modoDeGuardado = 'dte'; // Establecer el modo a DTE
    }
    
    // ... El resto del código de la lógica del modal, etc.
    
    // Llamadas iniciales
    $.when(cargarCatalogos(), cargarProveedores()).done(function(catalogoData, proveedoresData) {
        renderizarSelects(catalogoData, proveedoresData);
    }).fail(function() {
        toastr.error('Error al cargar catálogos y proveedores.');
    });
});