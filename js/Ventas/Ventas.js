// js/ventas/Ventas.js
$(document).ready(function() {
    
    let productosCarrito = [];

    // INICIALIZACIÓN
    cargarCuentasIngreso();

    // =======================================================
    // 1. INICIALIZAR SELECT2 CLIENTES
    // =======================================================
    $('#listado_clientes').select2({
        theme: 'bootstrap-5',
        placeholder: 'Escriba nombre o NIT del cliente...',
        allowClear: true,
        minimumInputLength: 1,
        ajax: {
            url: 'admin/ventas/crud_ventas.php',
            type: 'POST',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { accion: 'buscar_clientes_select2', q: params.term };
            },
            processResults: function (data) { return { results: data.results }; }
        }
    });

    // =======================================================
    // 2. INICIALIZAR SELECT2 PRODUCTOS
    // =======================================================
    $('#listado_productos').select2({
        theme: 'bootstrap-5',
        placeholder: 'Buscar por código o nombre...',
        allowClear: true,
        minimumInputLength: 1,
        ajax: {
            url: 'admin/ventas/crud_ventas.php',
            type: 'POST',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { accion: 'buscar_productos_select2', q: params.term };
            },
            processResults: function (data) { return { results: data.results }; }
        }
    });

    // =======================================================
    // 3. EVENTOS DE SELECCIÓN Y LLENADO DE DATOS
    // =======================================================
    
    // Al seleccionar producto
    $('#listado_productos').on('select2:select', function (e) {
        var data = e.params.data;
        llenarCamposProducto(data);
    });

    // Función auxiliar para llenar inputs
    function llenarCamposProducto(data) {
        $('#id_producto_seleccionado').val(data.id);
        $('#codigo_item').val(data.codigo_interno);
        $('#descripcion_item').val(data.descripcion);
        $('#precio_unitario').val(data.precio_venta); // Precio Sugerido
        $('#stock_disponible').val(data.stock_actual);
        // Guardamos si es gravado para la lógica del carrito
        $('#es_gravado_item').val(data.es_gravado ? '1' : '0'); 
        
        $('#cantidad_item').val(1).focus();
    }

    // =======================================================
    // 4. CARRITO DE COMPRAS (LÓGICA ACTUALIZADA)
    // =======================================================
    $('#btnAgregarAlDetalle').click(function() {
        let id = $('#id_producto_seleccionado').val();
        let codigo = $('#codigo_item').val();
        let desc = $('#descripcion_item').val();
        let esGravado = $('#es_gravado_item').val() === '1';
        let cantidad = parseFloat($('#cantidad_item').val());
        let precio = parseFloat($('#precio_unitario').val());
        let stock = parseFloat($('#stock_disponible').val());

        // Validaciones
        if(!id) { toastr.warning("Seleccione un producto"); return; }
        if(isNaN(cantidad) || cantidad <= 0) { toastr.warning("Cantidad inválida"); return; }
        if(cantidad > stock) { toastr.error("Stock insuficiente"); return; }
        
        // Cálculos
        let subtotal = cantidad * precio;
        let gravado = 0, exento = 0, nosujeto = 0;
        let iva = 0;

        if (esGravado) {
            gravado = subtotal;
            // Cálculo inverso del IVA para reporte (Asumiendo precio tiene IVA incluido)
            // Gravado Neto = Precio / 1.13
            let neto = subtotal / 1.13;
            iva = subtotal - neto;
        } else {
            // Aquí podrías validar si es Exento o No Sujeto según impuesto_codigo
            // Por simplicidad, si no es gravado, lo ponemos como Exento por ahora
            exento = subtotal;
        }

        // Agregar al Array
        productosCarrito.push({
            codigo: codigo,
            descripcion: desc,
            cantidad: cantidad,
            precio_venta: precio,
            gravado: gravado,
            exento: exento,
            nosujeto: nosujeto,
            subtotal: subtotal,
            iva: iva
        });

        renderizarTabla();
        
        // Limpiar inputs
        $('#listado_productos').val(null).trigger('change');
        $('#stock_disponible').val('');
        $('#precio_unitario').val('');
        $('#cantidad_item').val(1);
    });

    function renderizarTabla() {
        let tbody = $('#tablaDetalleVenta tbody');
        tbody.empty();
        
        let sumGrav = 0, sumEx = 0, sumNoSuj = 0, sumIva = 0, sumTotal = 0;

        productosCarrito.forEach((p, i) => {
            sumGrav += p.gravado;
            sumEx += p.exento;
            sumNoSuj += p.nosujeto;
            sumIva += p.iva;
            sumTotal += p.subtotal;

            tbody.append(`
                <tr>
                    <td>${p.codigo}</td>
                    <td>${p.descripcion}</td>
                    <td class="text-center">${p.cantidad}</td>
                    <td class="text-end">$${p.precio_venta.toFixed(2)}</td>
                    <td class="text-end text-muted small">$${p.gravado.toFixed(2)}</td>
                    <td class="text-end text-muted small">$${p.exento.toFixed(2)}</td>
                    <td class="text-end text-muted small">$${p.nosujeto.toFixed(2)}</td>
                    <td class="text-end fw-bold">$${p.subtotal.toFixed(2)}</td>
                    <td class="text-center">
                        <button class="btn btn-danger btn-sm btnQuitar" data-index="${i}"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `);
        });

        // Totales al pie
        $('#sum_gravado').text('$' + sumGrav.toFixed(2));
        $('#sum_exento').text('$' + sumEx.toFixed(2));
        $('#sum_nosujeto').text('$' + sumNoSuj.toFixed(2));
        $('#sum_iva').text('$' + sumIva.toFixed(2));
        $('#sum_total').text('$' + sumTotal.toFixed(2));
    }

    $(document).on('click', '.btnQuitar', function() {
        productosCarrito.splice($(this).data('index'), 1);
        renderizarTabla();
    });

    // =======================================================
    // 5. MODALES Y FUNCIONALIDAD EXTRA
    // =======================================================
    
    // A. CLIENTE RÁPIDO
    $('#btnGuardarClienteRapido').click(function() {
        let nombre = $('#new_nombre').val();
        let nit = $('#new_nit').val();
        let nrc = $('#new_nrc').val();
        if(!nombre || !nit) { toastr.warning('Datos obligatorios faltantes'); return; }

        $.post('admin/ventas/crud_ventas.php', {
            accion: 'guardar_cliente_rapido',
            nombre_cliente: nombre, nit_cliente: nit, nrc_cliente: nrc
        }, function(res) {
            if(res.respuesta) {
                $('#modalClienteRapido').modal('hide');
                var newOption = new Option(res.text, res.id, true, true);
                $('#listado_clientes').append(newOption).trigger('change');
                toastr.success('Cliente creado');
            } else { toastr.error(res.mensaje); }
        }, 'json');
    });

    // B. BUSCADOR PRODUCTOS (MODAL)
    $('#txtBuscarEnModal').on('keyup', function() {
        let q = $(this).val();
        if(q.length < 2) return;
        $.post('admin/ventas/crud_ventas.php', { accion: 'buscar_productos_select2', q: q }, function(res) {
            let filas = '';
            res.results.forEach(p => {
                filas += `
                    <tr>
                        <td>${p.codigo_interno}</td>
                        <td>${p.descripcion}</td>
                        <td class="${p.stock_actual<=0?'text-danger':'text-success'} fw-bold">${p.stock_actual}</td>
                        <td>$${p.precio_venta.toFixed(2)}</td>
                        <td>
                            <button class="btn btn-primary btn-sm btnAddDesdeModal" 
                                data-id="${p.id}" data-codigo="${p.codigo_interno}" 
                                data-desc="${p.descripcion}" data-stock="${p.stock_actual}" 
                                data-precio="${p.precio_venta}" data-gravado="${p.es_gravado}">
                                <i class="fas fa-plus"></i>
                            </button>
                        </td>
                    </tr>`;
            });
            $('#tablaResultadosModal tbody').html(filas);
        }, 'json');
    });

    $(document).on('click', '.btnAddDesdeModal', function() {
        let btn = $(this);
        let data = {
            id: btn.data('id'),
            codigo_interno: btn.data('codigo'),
            descripcion: btn.data('desc'),
            stock_actual: btn.data('stock'),
            precio_venta: btn.data('precio'),
            es_gravado: btn.data('gravado')
        };
        llenarCamposProducto(data);
        $('#modalBuscadorProductos').modal('hide');
    });

    // =======================================================
    // 6. COBRO Y VUELTO (CALCULADORA)
    // =======================================================
    
    // Botón principal "COBRAR"
    $('#btnAbrirCobro').click(function() {
        if(productosCarrito.length === 0) { toastr.warning("Carrito vacío"); return; }
        if(!$('#listado_clientes').val()) { toastr.warning("Seleccione Cliente"); return; }
        
        let total = parseFloat($('#sum_total').text().replace('$',''));
        $('#lblTotalCobro').text('$' + total.toFixed(2));
        $('#txtEfectivoRecibido').val('');
        $('#lblVuelto').text('$0.00');
        $('#btnConfirmarVentaFinal').prop('disabled', true);
        
        $('#modalCobro').modal('show');
        setTimeout(() => $('#txtEfectivoRecibido').focus(), 500);
    });

    // Lógica Calculadora
    $('#txtEfectivoRecibido').on('keyup input', function() {
        let total = parseFloat($('#lblTotalCobro').text().replace('$',''));
        let recibido = parseFloat($(this).val()) || 0;
        let vuelto = recibido - total;

        if(vuelto >= 0) {
            $('#lblVuelto').text('$' + vuelto.toFixed(2)).removeClass('text-danger').addClass('text-success');
            $('#btnConfirmarVentaFinal').prop('disabled', false);
        } else {
            $('#lblVuelto').text('Falta: $' + Math.abs(vuelto).toFixed(2)).removeClass('text-success').addClass('text-danger');
            $('#btnConfirmarVentaFinal').prop('disabled', true);
        }
    });

    // Botones de Billetes Rápidos
    $('.btnBillete').click(function() {
        let val = $(this).data('val');
        if(val === 'exacto') {
             let total = parseFloat($('#lblTotalCobro').text().replace('$',''));
             $('#txtEfectivoRecibido').val(total).trigger('input');
        } else {
             $('#txtEfectivoRecibido').val(val).trigger('input');
        }
    });

    // =======================================================
    // 7. GUARDAR VENTA FINAL
    // =======================================================
    $('#btnConfirmarVentaFinal').click(function() {
        let datosPago = {};
        if($('#condicion_pago').val() === 'CONTADO') {
            let sel = $('#id_cuenta_destino option:selected');
            let val = $('#id_cuenta_destino').val();
            if(val) {
                datosPago.destino_pago = sel.data('tipo'); 
                datosPago.id_cuenta_destino = val.split('_')[1];
            }
        }

        let cabecera = {
            fecha_emision: $('#fecha_emision').val(),
            tipo_documento: $('#tipo_documento').val(),
            numero_documento: $('#numero_documento').val() || 'AUTO',
            id_cliente: $('#listado_clientes').val(),
            condicion_pago: $('#condicion_pago').val(),
            ...datosPago,
            total_gravado: parseFloat($('#sum_gravado').text().replace('$','')),
            total_exenta: parseFloat($('#sum_exento').text().replace('$','')),
            total_nosujeta: parseFloat($('#sum_nosujeto').text().replace('$','')),
            total_iva: parseFloat($('#sum_iva').text().replace('$','')),
            total_pagar: parseFloat($('#sum_total').text().replace('$',''))
        };

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
                    $('#modalCobro').modal('hide');
                    toastr.success(res.mensaje);
                    
                    // Preguntar Transmisión DTE
                    Swal.fire({
                        title: 'Venta Guardada',
                        text: "¿Transmitir a Hacienda?",
                        icon: 'success',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, Transmitir',
                        cancelButtonText: 'Más tarde'
                    }).then((result) => {
                        if (result.isConfirmed) { transmitirDTE(res.id_venta); } 
                        else { setTimeout(() => location.reload(), 1000); }
                    });
                } else {
                    toastr.error(res.mensaje);
                }
            },
            error: function() { toastr.error("Error de conexión"); }
        });
    });

    // AUX: Cargar Tesorería
    function cargarCuentasIngreso() {
        $.post('admin/ventas/crud_ventas.php', { accion: 'listar_cuentas_ingreso' }, function(res) {
            let sel = $('#id_cuenta_destino').empty();
            if(res.datos.cajas.length) {
                let g = $('<optgroup label="Cajas">');
                res.datos.cajas.forEach(c => g.append(`<option value="CAJA_${c.id}" data-tipo="CAJA">${c.nombre_caja}</option>`));
                sel.append(g);
            }
            if(res.datos.bancos.length) {
                let g = $('<optgroup label="Bancos">');
                res.datos.bancos.forEach(b => g.append(`<option value="BANCO_${b.id}" data-tipo="BANCO">${b.nombre_banco}</option>`));
                sel.append(g);
            }
        }, 'json');
    }

    // Funciones del Historial (Se mantienen igual que antes, solo asegúrate de pegarlas si las necesitas)
    // ... (copia aquí la función transmitirDTE y cargarHistorialVentas de tu versión anterior si es necesario)
});