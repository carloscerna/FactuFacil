// js/ventas/Ventas.js
$(document).ready(function() {
    
    let productosCarrito = [];

    // INICIALIZACIÓN
    cargarCuentasIngreso(); // Cargar Bancos/Cajas al inicio

    // =======================================================
    // 1. SELECT2 CLIENTES
    // =======================================================
    $('#id_cliente').select2({
        theme: 'bootstrap-5',
        placeholder: 'Buscar Cliente (Nombre/NIT)...',
        ajax: {
            url: 'admin/clientes/crud_clientes.php',
            type: 'POST',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { accion: 'buscar_cliente_venta', termino: params.term };
            },
            processResults: function (data) {
                return {
                    results: $.map(data.clientes, function (item) {
                        return {
                            id: item.id_clientes,
                            text: item.nombre_completo,
                            nit: item.nit, nrc: item.nrc, es_contribuyente: item.es_contribuyente
                        }
                    })
                };
            }
        }
    });

    $('#id_cliente').on('select2:select', function (e) {
        var data = e.params.data;
        $('#lbl_nit_nrc').text(`${data.nit || 'N/A'} / ${data.nrc || 'N/A'}`);
        $('#lbl_es_contri').text(data.es_contribuyente ? 'SI' : 'NO');
        $('#info_cliente_row').removeClass('d-none');

        // Sugerencia Documento
        if(data.es_contribuyente === 'true' || data.es_contribuyente === true) {
            $('#tipo_documento').val('03'); // CCF
        } else {
            $('#tipo_documento').val('01'); // Factura
        }
    });

    // =======================================================
    // 2. SELECT2 PRODUCTOS
    // =======================================================
    $('#buscar_producto').select2({
        theme: 'bootstrap-5',
        placeholder: 'Escriba nombre o código...',
        ajax: {
            url: 'admin/ventas/crud_ventas.php',
            type: 'POST',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { accion: 'buscar_producto_venta', termino: params.term };
            },
            processResults: function (data) {
                return {
                    results: $.map(data.productos, function (item) {
                        return {
                            id: item.codigo_interno,
                            text: `${item.codigo_interno} - ${item.descripcion} ($${parseFloat(item.precio_venta).toFixed(2)})`,
                            stock: item.stock_actual,
                            precio: item.precio_venta,
                            descripcion: item.descripcion
                        }
                    })
                };
            }
        }
    });

    $('#buscar_producto').on('select2:select', function (e) {
        var data = e.params.data;
        $('#stock_actual').val(data.stock);
        $('#precio_venta').val(data.precio); // Precio sugerido
        $('#cantidad_venta').val(1).focus();
    });

    // =======================================================
    // 3. TESORERÍA (CUENTAS DE INGRESO)
    // =======================================================
    function cargarCuentasIngreso() {
        $.ajax({
            url: 'admin/ventas/crud_ventas.php',
            type: 'POST',
            dataType: 'json',
            data: { accion: 'listar_cuentas_ingreso' },
            success: function(res) {
                let select = $('#id_cuenta_destino');
                select.empty();
                if(res.respuesta && res.datos) {
                    if(res.datos.cajas.length > 0) {
                        let g = $('<optgroup label="Cajas">');
                        res.datos.cajas.forEach(c => g.append(`<option value="CAJA_${c.id}" data-tipo="CAJA">${c.nombre_caja}</option>`));
                        select.append(g);
                    }
                    if(res.datos.bancos.length > 0) {
                        let g = $('<optgroup label="Bancos">');
                        res.datos.bancos.forEach(b => g.append(`<option value="BANCO_${b.id}" data-tipo="BANCO">${b.nombre_banco}</option>`));
                        select.append(g);
                    }
                }
            }
        });
    }

    // Toggle Contado/Crédito
    $('#condicion_pago').on('change', function() {
        if($(this).val() === 'CONTADO') {
            $('#divCuentaIngreso').removeClass('d-none');
            $('#divMsgCredito').addClass('d-none');
        } else {
            $('#divCuentaIngreso').addClass('d-none');
            $('#divMsgCredito').removeClass('d-none');
        }
    });

    // =======================================================
    // 4. CARRITO DE COMPRAS
    // =======================================================
    $('#btnAgregarProducto').click(function() {
        let data = $('#buscar_producto').select2('data')[0];
        if(!data) { toastr.warning("Seleccione un producto"); return; }

        let cantidad = parseFloat($('#cantidad_venta').val());
        let precioDigitado = parseFloat($('#precio_venta').val());
        let stock = parseFloat(data.stock);
        let tipoDoc = $('#tipo_documento').val(); // 01 o 03

        if(isNaN(cantidad) || cantidad <= 0) { toastr.warning("Cantidad inválida"); return; }
        if(cantidad > stock) { toastr.error("Stock insuficiente"); return; }

        // --- CÁLCULO DE IMPUESTOS ---
        // Asumimos que el precio digitado es el PRECIO FINAL (con IVA) para Factura
        // O Precio Neto + IVA para CCF. 
        // Para simplificar UX: El precio que pone el usuario es el Unitario Final.
        
        let precio_unitario = precioDigitado;
        let precio_gravado = 0;
        let iva_unitario = 0;

        if (tipoDoc === '01') { // Factura (Precio Incluye IVA)
            precio_gravado = precio_unitario / 1.13;
            iva_unitario = precio_unitario - precio_gravado;
        } else { // CCF (Precio + IVA) -> Aquí hay debate, pero usualmente en sistema POS se digita precio final.
            // Si quieres que sea Precio Neto en CCF, cambia la lógica aquí.
            // Asumiremos Precio Final para consistencia visual.
            precio_gravado = precio_unitario / 1.13;
            iva_unitario = precio_unitario - precio_gravado;
        }

        let total_linea = precio_unitario * cantidad;
        let total_gravado = precio_gravado * cantidad;
        let total_iva = iva_unitario * cantidad;

        productosCarrito.push({
            codigo: data.id,
            descripcion: data.descripcion,
            cantidad: cantidad,
            precio_venta: precio_unitario,
            subtotal: total_linea,
            gravado: total_gravado,
            iva: total_iva
        });

        renderizarTabla();
        
        // Reset Inputs
        $('#buscar_producto').val(null).trigger('change');
        $('#stock_actual').val('');
        $('#precio_venta').val('');
        $('#cantidad_venta').val(1);
    });

    function renderizarTabla() {
        let tbody = $('#tablaDetalleVenta tbody');
        tbody.empty();
        
        let sumGrav = 0, sumIva = 0, sumTotal = 0;

        productosCarrito.forEach((p, i) => {
            sumGrav += p.gravado;
            sumIva += p.iva;
            sumTotal += p.subtotal;

            tbody.append(`
                <tr>
                    <td>${p.codigo}</td>
                    <td>${p.descripcion}</td>
                    <td class="text-center">${p.cantidad}</td>
                    <td class="text-end">$${p.precio_venta.toFixed(2)}</td>
                    <td class="text-end">$${p.gravado.toFixed(2)}</td>
                    <td class="text-end">$${p.iva.toFixed(2)}</td>
                    <td class="text-end fw-bold">$${p.subtotal.toFixed(2)}</td>
                    <td class="text-center"><button class="btn btn-danger btn-sm btnQuitar" data-index="${i}"><i class="fas fa-trash"></i></button></td>
                </tr>
            `);
        });

        $('#sum_gravado').text('$' + sumGrav.toFixed(2));
        $('#sum_iva').text('$' + sumIva.toFixed(2));
        $('#sum_total').text('$' + sumTotal.toFixed(2));
    }

    $(document).on('click', '.btnQuitar', function() {
        productosCarrito.splice($(this).data('index'), 1);
        renderizarTabla();
    });

    // =======================================================
    // 5. GUARDAR VENTA
    // =======================================================
    $('#btnGuardarVenta').click(function() {
        if(productosCarrito.length === 0) { toastr.warning("Carrito vacío"); return; }
        if(!$('#id_cliente').val()) { toastr.warning("Seleccione Cliente"); return; }

        let datosPago = {};
        if($('#condicion_pago').val() === 'CONTADO') {
            let sel = $('#id_cuenta_destino option:selected');
            let val = $('#id_cuenta_destino').val(); // Ej: CAJA_1
            if(!val) { toastr.warning("Seleccione cuenta de ingreso"); return; }
            
            datosPago.destino_pago = sel.data('tipo'); // CAJA o BANCO
            datosPago.id_cuenta_destino = val.split('_')[1]; // ID numérico
        }

        let cabecera = {
            fecha_emision: $('#fecha_emision').val(),
            tipo_documento: $('#tipo_documento').val(),
            numero_documento: $('#numero_documento').val() || 'AUTO',
            id_cliente: $('#id_cliente').val(),
            condicion_pago: $('#condicion_pago').val(),
            ...datosPago, // Spread operator para unir objetos
            total_gravado: parseFloat($('#sum_gravado').text().replace('$','')),
            total_iva: parseFloat($('#sum_iva').text().replace('$','')),
            total_pagar: parseFloat($('#sum_total').text().replace('$',''))
        };

        if(confirm("¿Procesar Venta?")) {
            $.ajax({
                url: 'admin/ventas/crud_ventas.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    accion: 'guardar_venta',
                    venta_cabecera: JSON.stringify(cabecera),
                    venta_detalle: JSON.stringify(productosCarrito)
                },
                success: function(res) {
                    if(res.respuesta) {
                        toastr.success(res.mensaje);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        toastr.error(res.mensaje);
                    }
                },
                error: function() { toastr.error("Error al procesar venta"); }
            });
        }
    });
});