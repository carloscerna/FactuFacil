// js/ventas/Ventas.js
$(document).ready(function() {
    
    let productosCarrito = [];

    // 1. INICIALIZAR SELECT2 DE CLIENTES
    // Usa la función 'buscar_cliente_venta' que agregamos a crud_clientes.php
    $('#id_cliente').select2({
        theme: 'bootstrap-5',
        placeholder: 'Busque por Nombre, NIT o Razón Social',
        ajax: {
            url: 'admin/clientes/crud_clientes.php',
            type: 'POST',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    accion: 'buscar_cliente_venta',
                    termino: params.term
                };
            },
            processResults: function (data) {
                return {
                    results: $.map(data.clientes, function (item) {
                        return {
                            id: item.id_clientes,
                            text: item.nombre_completo + (item.nombre_comercial ? ' (' + item.nombre_comercial + ')' : ''),
                            // Guardamos datos extra en el objeto para usarlos al seleccionar
                            nit: item.nit,
                            nrc: item.nrc,
                            es_contribuyente: item.es_contribuyente
                        }
                    })
                };
            },
            cache: true
        }
    });

    // Al seleccionar cliente, mostramos su info
    $('#id_cliente').on('select2:select', function (e) {
        var data = e.params.data;
        let info = `NIT: ${data.nit || 'N/A'} / NRC: ${data.nrc || 'N/A'}`;
        let esContri = (data.es_contribuyente === true || data.es_contribuyente === 't' || data.es_contribuyente === 'true') ? 'SI' : 'NO';
        
        $('#lbl_nit_nrc').text(info);
        $('#lbl_es_contri').text(esContri);
        $('#info_cliente').removeClass('d-none');

        // Sugerencia automática: Si es contribuyente, cambiar a CCF
        if(esContri === 'SI') {
            $('#tipo_documento').val('03'); // CCF
        } else {
            $('#tipo_documento').val('01'); // Factura
        }
    });

    // 2. INICIALIZAR SELECT2 DE PRODUCTOS
    // Usa 'buscar_producto_venta' de crud_ventas.php
    $('#buscar_producto').select2({
        theme: 'bootstrap-5',
        placeholder: 'Buscar producto...',
        ajax: {
            url: 'admin/ventas/crud_ventas.php',
            type: 'POST',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    accion: 'buscar_producto_venta',
                    termino: params.term
                };
            },
            processResults: function (data) {
                return {
                    results: $.map(data.productos, function (item) {
                        return {
                            id: item.codigo_interno,
                            text: `${item.codigo_interno} - ${item.descripcion}`,
                            
                            // --- CORRECCIÓN AQUÍ ---
                            // 1. Mapeamos 'stock_actual' que es como viene en tu JSON
                            stock: item.stock_actual, 
                            
                            // 2. Si el precio es null, ponemos 0.00 para que no de error
                            precio: item.precio_venta !== null ? item.precio_venta : 0, 
                            
                            descripcion: item.descripcion
                        }
                    })
                };
            }
        }
    });

    // Al seleccionar producto
    $('#buscar_producto').on('select2:select', function (e) {
        var data = e.params.data;
        $('#stock_actual').val(data.stock);
        $('#precio_venta').val(data.precio);
        $('#cantidad_venta').val(1).focus();
    });

    // 3. AGREGAR PRODUCTO AL CARRITO
    $('#btnAgregarProducto').click(function() {
        let data = $('#buscar_producto').select2('data')[0];
        if(!data) { toastr.warning("Seleccione un producto"); return; }

        let cantidad = parseFloat($('#cantidad_venta').val());
        let precio = parseFloat($('#precio_venta').val());
        let stock = parseFloat(data.stock);

        if(isNaN(cantidad) || cantidad <= 0) { toastr.warning("Cantidad inválida"); return; }
        if(cantidad > stock) { toastr.error("No hay suficiente stock. Disponible: " + stock); return; }

        // --- CÁLCULOS (Simplificado para El Salvador) ---
        // Asumimos que el precio ingresado YA incluye IVA si es FCF, o es +IVA si es CCF?
        // PARA SIMPLIFICAR: El sistema calculará desglosando del precio ingresado.
        
        let precio_sin_iva = (precio / 1.13);
        let iva_unitario = precio - precio_sin_iva;
        
        // Si es CCF, a veces se prefiere sumar el IVA al precio base. 
        // Por ahora manejaremos "Precio incluye IVA" para que cuadre con el catálogo.
        
        let subtotal_linea = precio * cantidad; // Total a pagar por esa línea
        let gravado_linea = precio_sin_iva * cantidad;
        let iva_linea = iva_unitario * cantidad;

        // Agregar al array
        productosCarrito.push({
            codigo: data.id,
            descripcion: data.descripcion,
            cantidad: cantidad,
            precio_venta: precio, // Guardamos el precio unitario final
            subtotal: subtotal_linea,
            gravado: gravado_linea,
            iva: iva_linea
        });

        actualizarTabla();
        
        // Limpiar inputs
        $('#buscar_producto').val(null).trigger('change');
        $('#stock_actual').val('');
        $('#precio_venta').val('');
        $('#cantidad_venta').val(1);
    });

    // 4. ACTUALIZAR TABLA VISUAL
    function actualizarTabla() {
        let tbody = $('#tablaDetalleVenta tbody');
        tbody.empty();
        
        let total_gravado = 0;
        let total_iva = 0;
        let total_pagar = 0;

        productosCarrito.forEach((prod, index) => {
            total_gravado += prod.gravado;
            total_iva += prod.iva;
            total_pagar += prod.subtotal;

            let fila = `
                <tr>
                    <td>${prod.codigo}</td>
                    <td>${prod.descripcion}</td>
                    <td class="text-center">${prod.cantidad}</td>
                    <td class="text-end">$${prod.precio_venta.toFixed(2)}</td>
                    <td class="text-end">$${prod.gravado.toFixed(2)}</td>
                    <td class="text-end">$${prod.iva.toFixed(2)}</td>
                    <td class="text-end fw-bold">$${prod.subtotal.toFixed(2)}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-danger btn-sm btnQuitar" data-index="${index}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            tbody.append(fila);
        });

        // Actualizar Footer
        $('#sum_gravado').text('$' + total_gravado.toFixed(2));
        $('#sum_iva').text('$' + total_iva.toFixed(2));
        $('#sum_total').text('$' + total_pagar.toFixed(2));
    }

    // Quitar producto
    $(document).on('click', '.btnQuitar', function() {
        let index = $(this).data('index');
        productosCarrito.splice(index, 1);
        actualizarTabla();
    });

    // 5. GUARDAR VENTA
    $('#btnGuardarVenta').click(function() {
        if(productosCarrito.length === 0) { toastr.warning("Agregue productos a la venta"); return; }
        
        let id_cliente = $('#id_cliente').val();
        if(!id_cliente) { toastr.warning("Seleccione un cliente"); return; }

        let cabecera = {
            fecha_emision: $('#fecha_emision').val(),
            tipo_documento: $('#tipo_documento').val(),
            numero_documento: $('#numero_documento').val() || 'AUTO',
            id_cliente: id_cliente,
            condicion_pago: $('#condicion_pago').val(),
            // Totales limpios (sin signo $)
            total_gravado: parseFloat($('#sum_gravado').text().replace('$','')),
            total_iva: parseFloat($('#sum_iva').text().replace('$','')),
            total_pagar: parseFloat($('#sum_total').text().replace('$',''))
        };

        if(confirm("¿Confirmar y procesar venta?")) {
            $.ajax({
                url: 'admin/ventas/crud_ventas.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    accion: 'guardar_venta',
                    venta_cabecera: JSON.stringify(cabecera),
                    venta_detalle: JSON.stringify(productosCarrito)
                },
                success: function(response) {
                    if(response.respuesta) {
                        toastr.success(response.mensaje);
                        // Recargar o limpiar
                        setTimeout(() => { location.reload(); }, 2000);
                    } else {
                        toastr.error(response.mensaje);
                    }
                },
                error: function(err) {
                    toastr.error("Error de servidor");
                    console.log(err);
                }
            });
        }
    });
});