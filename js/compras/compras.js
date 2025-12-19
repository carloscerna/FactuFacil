$(function () {
    let productosCompra = [];
    let tablaBusquedaProductosDT = null;
    let modoDeGuardado = 'manual';
    let compraEncabezadoDte = {};

// Función para limpiar todo
function limpiarPantalla() {
    $('#formCompra')[0].reset();
    productosCompra = [];
    renderizarTabla();
    $("#totalNoSuj, #totalExenta, #totalGravada, #totalIva, #totalDescuento, #totalFinal").text("0.0000");
}

// Mostrar secciones con confirmación
function cambiarModo(modo) {
    if (productosCompra.length > 0) {
        Swal.fire({
            title: "¿Estás seguro?",
            text: "Se perderán los productos cargados si cambias de modo.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: "Sí, cambiar",
            cancelButtonText: "Cancelar"
        }).then((result) => {
            if (result.isConfirmed) {
                aplicarCambio(modo);
            }
        });
    } else {
        aplicarCambio(modo);
    }
}

// Aplica el cambio de modo
function aplicarCambio(modo) {
    limpiarPantalla();

    if (modo === "manual") {
        $('#seccionManual').show();
        $('#seccionDte').hide();
    } else {
        $('#seccionManual').hide();
        $('#seccionDte').show();
    }

    modoDeGuardado = modo;
    console.log("Modo cambiado a:", modoDeGuardado);
}

// Botones
$('#btnManual').on('click', function() {
    cambiarModo('manual');
});

$('#btnDte').on('click', function() {
    cambiarModo('dte');
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

  // ✅ Renderiza tabla y actualiza totales
        function renderizarTabla() {
            const tbody = $("#tablaProductosCompra tbody");
            tbody.empty();

            productosCompra.forEach((prod, index) => {
                const subtotal = (prod.cantidad * parseFloat(prod.precio_unitario)).toFixed(4);

                const row = `
                    <tr>
                        <td>${prod.codigo_interno}</td>
                        <td>${prod.codigo_proveedor}</td>
                        <td>
                            <input type="number" step="0.01" min="0" 
                                class="form-control form-control-sm inputCantidad" 
                                data-index="${index}" 
                                value="${parseFloat(prod.cantidad).toFixed(2)}">
                        </td>
                        <td>${prod.unidad_medida ?? ''}</td>
                        <td>${prod.descripcion}</td>
                        <td>
                            <input type="number" step="0.0001" min="0" 
                                class="form-control form-control-sm inputPrecio" 
                                data-index="${index}" 
                                value="${parseFloat(prod.precio_unitario).toFixed(4)}">
                        </td>
                        <td class="text-end">${parseFloat(prod.descuento ?? 0).toFixed(4)}</td>
                        <td class="text-end">${parseFloat(prod.ventas_no_sujetas ?? 0).toFixed(4)}</td>
                        <td class="text-end">${parseFloat(prod.ventas_exentas ?? 0).toFixed(4)}</td>
                        <td class="text-end">${parseFloat(prod.ventas_gravadas ?? 0).toFixed(4)}</td>
                        <td class="text-end">${subtotal}</td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm btnEliminarProducto" data-index="${index}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
                tbody.append(row);

                prod.subtotal = parseFloat(subtotal).toFixed(4); // ✅ mantenemos redondeado
            });

            actualizarTotales();
        }

    // =======================================================
    //  SOLUCIÓN AL PROBLEMA DE EDICIÓN Y RETROCESO
    // =======================================================

    // Editar cantidad (Sin redibujar toda la tabla)
    $(document).on('input', '.inputCantidad', function() {
        const index = $(this).data('index');
        let val = $(this).val();
        
        // Permitir que el usuario borre todo para escribir de nuevo
        if (val === '') {
            productosCompra[index].cantidad = 0;
            return; 
        }

        const nuevaCantidad = parseFloat(val) || 0;
        productosCompra[index].cantidad = nuevaCantidad;
        
        // Recalcular subtotal de la fila visualmente
        recalcularFila(index);
    });

    // Editar precio unitario (Sin redibujar toda la tabla)
    $(document).on('input', '.inputPrecio', function() {
        const index = $(this).data('index');
        let val = $(this).val();

        if (val === '') {
            productosCompra[index].precio_unitario = 0;
            return;
        }

        const nuevoPrecio = parseFloat(val) || 0;
        productosCompra[index].precio_unitario = nuevoPrecio;
        
        // Recalcular subtotal de la fila visualmente
        recalcularFila(index);
    });

// Función auxiliar para actualizar la fila, redistribuir montos y actualizar totales
    function recalcularFila(index) {
        let prod = productosCompra[index];
        
        // 1. Calcular el nuevo subtotal matemático
        let nuevoTotalLinea = (prod.cantidad * parseFloat(prod.precio_unitario));
        
        // Aplica descuento si existe
        let subtotalFinal = nuevoTotalLinea - (parseFloat(prod.descuento) || 0);

        // 2. Redistribuir el monto en la categoría correcta (Gravado, Exento o No Sujeto)
        // Verificamos dónde estaba el valor originalmente para actualizar esa misma columna
        
        // Convertimos a float para comparar
        let esNoSujeta = parseFloat(prod.ventas_no_sujetas || 0) > 0;
        let esExenta = parseFloat(prod.ventas_exentas || 0) > 0;
        
        // Reseteamos todos a 0 para asignar el nuevo valor limpio
        prod.ventas_no_sujetas = 0;
        prod.ventas_exentas = 0;
        prod.ventas_gravadas = 0;

        if (esNoSujeta) {
            prod.ventas_no_sujetas = subtotalFinal.toFixed(4);
        } else if (esExenta) {
            prod.ventas_exentas = subtotalFinal.toFixed(4);
        } else {
            // Por defecto, si no es ni exenta ni no sujeta, cae en GRAVADA
            prod.ventas_gravadas = subtotalFinal.toFixed(4);
        }

        // Guardamos el subtotal general visual
        prod.subtotal = subtotalFinal.toFixed(4);

        // 3. ACTUALIZAR EL DOM (TABLA VISUAL)
        // Buscamos la fila correspondiente
        let fila = $(`#tablaProductosCompra tbody tr`).eq(index);

        // Actualizamos las celdas específicas según el orden de tu HTML:
        // Col 7: No Sujetas | Col 8: Exentas | Col 9: Gravadas | Col 10: Subtotal
        // Nota: eq(index) empieza en 0. Revisa si tus indices coinciden. 
        // Según tu HTML anterior:
        // 0:CodInterno, 1:CodProv, 2:Cant, 3:Unidad, 4:Descrip, 5:Precio, 6:Desc, 7:NoSuj, 8:Exenta, 9:Grav, 10:Subt
        
        fila.find('td').eq(7).text(parseFloat(prod.ventas_no_sujetas).toFixed(4));
        fila.find('td').eq(8).text(parseFloat(prod.ventas_exentas).toFixed(4));
        fila.find('td').eq(9).text(parseFloat(prod.ventas_gravadas).toFixed(4));
        fila.find('td').eq(10).text(parseFloat(prod.subtotal).toFixed(4));

        // 4. Recalcular los totales del pie de página
        actualizarTotales();
    }
// ✅ Totales Actualizados (Versión Final corregida)
    function actualizarTotales() {
        console.clear(); 
        console.log("--- ACTUALIZANDO TOTALES ---");

        let totalNoSuj = 0;
        let totalExenta = 0;
        let totalGravada = 0;
        let totalIva = 0;
        let totalDescuento = 0;
        let totalFinal = 0;

        let tipoDoc = $('[name="tipo_documento"]').val(); 
        if (!tipoDoc) tipoDoc = '03'; // Default CCF

        productosCompra.forEach((p, index) => {
            let gravadaLinea = parseFloat(p.ventas_gravadas || p.venta_gravada || 0);
            let exentaLinea = parseFloat(p.ventas_exentas || p.venta_exenta || 0);
            let noSujetaLinea = parseFloat(p.ventas_no_sujetas || p.venta_no_suj || 0);
            let descuentoLinea = parseFloat(p.descuento || 0);

            // Sumar a acumuladores base
            totalGravada += gravadaLinea;
            totalExenta += exentaLinea;
            totalNoSuj += noSujetaLinea;
            totalDescuento += descuentoLinea;

            // --- CORRECCIÓN: Detectar impuesto o usar Default ---
            // Si p.impuesto_aplicable es null, undefined o "null" (texto), usamos '20'
            let codImpuesto = p.impuesto_aplicable;
            if (!codImpuesto || codImpuesto === 'null') {
                codImpuesto = '20'; 
                // Actualizamos el objeto en memoria para futuras ediciones
                productosCompra[index].impuesto_aplicable = '20'; 
            }

            // --- CÁLCULO DE IVA ---
            if (gravadaLinea > 0 && String(codImpuesto) === '20') {
                
                if (tipoDoc === '01') { // FACTURA
                    // Desglosamos IVA (El total ya lo incluye)
                    let base = gravadaLinea / 1.13;
                    let ivaItem = gravadaLinea - base;
                    totalIva += ivaItem;
                    totalFinal += gravadaLinea; 
                } else { // CCF
                    // Sumamos IVA (El total es Neto + IVA)
                    let ivaItem = gravadaLinea * 0.13;
                    totalIva += ivaItem;
                    totalFinal += (gravadaLinea + ivaItem);
                }
            } else {
                // Si es Exento o No Sujeto
                totalFinal += gravadaLinea;
            }
            
            totalFinal += (exentaLinea + noSujetaLinea);
        });

        // Actualizar HTML
        $('#totalNoSuj').text(totalNoSuj.toFixed(2));
        $('#totalExenta').text(totalExenta.toFixed(2));
        $('#totalGravada').text(totalGravada.toFixed(2));
        $('#totalIva').text(totalIva.toFixed(2));
        $('#totalDescuento').text(totalDescuento.toFixed(2));
        $('#totalFinal').text(totalFinal.toFixed(2));
        
        console.log("Calculo finalizado. IVA:", totalIva.toFixed(2));
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

        let accionDeGuardado = (modoDeGuardado === 'dte') ? 'guardarCompraProcesada' : 'guardarCompra';
        let datosDeEnvio = {};

        // ---------------------------------------------------------
        // CASO 1: IMPORTACIÓN DE DTE (Automático)
        // ---------------------------------------------------------
        if (accionDeGuardado === 'guardarCompraProcesada') {
            datosDeEnvio = {
                accion: accionDeGuardado,
                compra_data: JSON.stringify(compraEncabezadoDte),
                productos_data: JSON.stringify(productosCompra)
            };
        } 
        // ---------------------------------------------------------
        // CASO 2: REGISTRO MANUAL (Aquí estaba el error)
        // ---------------------------------------------------------
        else {
            
           // 1. Calculamos los totales recorriendo el array
        let sumGravado = 0;
        let sumIva = 0;
        let sumTotal = 0;
        let tipoDoc = $('[name="tipo_documento"]').val(); // '01', '03'

            productosCompra.forEach(p => {
                let precio = parseFloat(p.precio_costo || p.precio_unitario || 0); // Precio ingresado
                let cantidad = parseFloat(p.cantidad || 0);
                let totalLinea = precio * cantidad;

                // CÁLCULO DE IVA SEGÚN TIPO DE DOCUMENTO
                if (tipoDoc === '01') { 
                    // FACTURA: El precio tiene IVA incluido. Hay que extraerlo.
                    // Ejemplo: Precio $1.13 -> Neto $1.00 -> IVA $0.13
                    let base = totalLinea / 1.13;
                    let iva = totalLinea - base;
                    
                    sumGravado += base;
                    sumIva += iva;
                    sumTotal += totalLinea; // El total a pagar es el mismo
                } else {
                    // CCF (03): El precio es Neto. El IVA se suma aparte.
                    // Ejemplo: Precio $1.00 -> Neto $1.00 -> IVA $0.13 -> Total $1.13
                    let base = totalLinea;
                    let iva = totalLinea * 0.13;
                    
                    sumGravado += base;
                    sumIva += iva;
                    sumTotal += (base + iva);
                }
            });

            // 2. Construimos el Objeto Cabecera
            let cabeceraManual = {
                numero_documento: $('[name="numero_documento"]').val(),
                tipo_documento: $('[name="tipo_documento"]').val(),
                fecha_emision: $('[name="fecha_emision"]').val(),
                
                // Datos Proveedor
                id_proveedores: $('#selectProveedor').val(),
                nombre_proveedor: $('#selectProveedor option:selected').text(), // Obtenemos el texto, no el ID
                nrc_proveedor: $('#nrc_proveedor').val() || '', // Asegúrate de tener este input, si no, mándalo vacío
                
                condicion_pago: $('#selectCondicionPago').val(),
                plazo_pago: $('#selectPlazoPagoDTE').val(),
                observaciones: $('[name="observaciones"]').val(),
                
                // Totales Calculados
                total_gravado: sumGravado.toFixed(2),
                total_iva: sumIva.toFixed(2),
                total_pagar: sumTotal.toFixed(2)
            };

            // 3. Empaquetamos para enviar al PHP
            datosDeEnvio = {
                accion: 'guardarCompra',
                // El PHP espera estos dos nombres exactos:
                compra_cabecera: JSON.stringify(cabeceraManual),
                compra_detalle: JSON.stringify(productosCompra)
            };
        }

        // Envío AJAX
        $.ajax({
            url: 'admin/compras/crud_compras.php',
            type: 'POST',
            dataType: 'json',
            data: datosDeEnvio,
            success: function(response) {
                if (response.respuesta) {
                    toastr.success(response.mensaje);
                    
                    // Limpieza
                    $('#formCompra')[0].reset();
                    $('#selectProveedor').val(null).trigger('change'); // Si usas Select2
                    productosCompra = [];
                    renderizarTabla(); // Limpia la tabla visual
                    
                    // Opcional: Recargar para ver los cambios en inventario
                    setTimeout(() => { location.reload(); }, 1500);

                } else {
                    toastr.error('Error: ' + response.mensaje);
                }
            },
            error: function(xhr, status, error) {
                console.error(xhr.responseText); // Útil para ver errores de PHP en consola
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
                     { "data": "codigo_proveedor" }, // 👈 Nueva columna
                    { "data": "descripcion" },
                    { "data": "precio_costo", "render": $.fn.dataTable.render.number(',', '.', 2, '$') },
                    { "data": "impuesto_aplicable" },
                    { "defaultContent": "<div class='text-center'><button class='btn btn-primary btn-sm btnSeleccionarProductoModal'><i class='fas fa-check me-1'></i>Seleccionar</button></div>" }
                ],
                columnDefs: [
                        {
                            targets: [4], // columna precio_costo o precio_unitario
                            render: function(data, type, row) {
                                return parseFloat(data).toFixed(4); // ✅ 4 decimales
                            }
                        },
                        {
                            targets: [5], // columna subtotal
                            render: function(data, type, row) {
                                return parseFloat(data).toFixed(4); // ✅ 4 decimales
                            }
                        }
                    ],
                "language": { "url": "php_libs/idioma/es_es.json" }
            });
        }
        $('#tablaBusquedaProductos_filter input').focus();
    });

    // =======================================================
    //  CORRECCIÓN: EVENTO SELECCIONAR DESDE EL MODAL
    // =======================================================
    $('#tablaBusquedaProductos tbody').on('click', '.btnSeleccionarProductoModal', function() {
        const data = tablaBusquedaProductosDT.row($(this).parents('tr')).data();

        // 1. Preparar valores iniciales (Cálculo a 4 decimales)
        const cantidad = 1; // Por defecto agrega 1 unidad al seleccionar
        const precioUnitario = parseFloat(data.precio_unitario_final) || 0; // Este precio ya trae cálculos del backend
        const subtotal = cantidad * precioUnitario;
        
        // Aseguramos que venga un código de impuesto, si no, default '20'
        const impuestoAplicable = data.impuesto_aplicable || '20'; 

        // 2. Construir el objeto del producto
        const producto = {
            id_productos: data.id_productos,
            codigo_interno: data.codigo_interno,
            codigo_proveedor: data.codigo_proveedor,
            descripcion: data.descripcion,
            cantidad: cantidad,
            precio_unitario: precioUnitario.toFixed(4),
            impuesto_aplicable: impuestoAplicable,
            impuesto_descripcion: data.impuesto_descripcion,
            codigo_ganancia: data.codigo_ganancia,
            subtotal: subtotal.toFixed(4),
            descuento: 0,
            
            // Inicializamos en 0 para evitar undefined
            ventas_no_sujetas: 0,
            ventas_exentas: 0,
            ventas_gravadas: 0
        };

        // 3. LÓGICA DE DISTRIBUCIÓN FISCAL (Igual que en el botón manual)
        // Decidimos en qué columna poner el dinero según el impuesto
        if (impuestoAplicable === '20') {
            producto.ventas_gravadas = subtotal.toFixed(4);
        } else if (impuestoAplicable === '00' || impuestoAplicable === '04') { 
            // Agrega aquí los códigos que uses para Exento (ej: '04')
            producto.ventas_exentas = subtotal.toFixed(4);
        } else {
            // Fallback: Si es otro código, asumimos Gravado por seguridad
            producto.ventas_gravadas = subtotal.toFixed(4);
        }

        // 4. Agregar al array y actualizar vista
        productosCompra.push(producto);
        renderizarTabla(); // Esto actualiza los totales y el HTML
        
        // Cerrar modal y notificar
        $('#buscarProductoModal').modal('hide');
        toastr.success('Producto agregado correctamente.');
    });



$('#tablaProductosCompra tbody').on('click', '.btnEliminarProducto', function() {
    const index = $(this).data('index');

    Swal.fire({
        title: '¿Eliminar producto?',
        text: "Esta acción no se puede deshacer.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            productosCompra.splice(index, 1);
            renderizarTabla();
            Swal.fire('Eliminado', 'El producto ha sido eliminado.', 'success');
        }
    });
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

                // Recargar proveedores si el backend devolvió un proveedor
                if (response.compra.proveedor_id) {
                    recargarProveedores(response.compra.proveedor_id);
                }

                // Mostrar resumen en el footer
                if (response.compra.resumen) {
                    $('#totalNoSuj').text(parseFloat(response.compra.resumen.total_no_suj).toFixed(2));
                    $('#totalExenta').text(parseFloat(response.compra.resumen.total_exenta).toFixed(2));
                    $('#totalGravada').text(parseFloat(response.compra.resumen.total_gravada).toFixed(2));
                    $('#totalIva').text(parseFloat(response.compra.resumen.total_iva).toFixed(2));
                    $('#totalDescuento').text(parseFloat(response.compra.resumen.total_descuento).toFixed(2));
                    $('#totalFinal').text(parseFloat(response.compra.resumen.total_pagar).toFixed(2));
                }

                // 🔍 Validar número de documento después de procesar el JSON
                if (response.compra.numero_control) {
                    $.ajax({
                        url: 'admin/compras/crud_compras.php',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            accion: 'validarNumeroDocumento',
                            numero_documento: response.compra.numero_control
                        },
                        success: function(resp) {
                            if (resp.existe) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Documento duplicado',
                                    text: 'El número de documento ya está registrado en otra compra.',
                                    confirmButtonText: 'Entendido'
                                }).then(() => {
                                    // limpiar vista previa y formulario
                                    $('#numero_documento').val('');
                                    productosCompra = [];
                                    renderizarTabla();
                                });
                            }
                        }
                    });
                }

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

    // Función para recargar el select de proveedores
    function recargarProveedores(seleccionadoId = null) {
    $.ajax({
        url: 'admin/compras/crud_compras.php',
        type: 'POST',
        data: { accion: 'obtenerProveedores' },
        dataType: 'json',
        success: function(data) {
            let select = $('#selectProveedor');
            select.empty();

            // Si PHP devuelve un objeto con clave "proveedores"
            let proveedores = data.proveedores || data; // fallback

            proveedores.forEach(p => {
                let selected = (p.id_proveedores == seleccionadoId) ? 'selected' : '';
                select.append(`<option value="${p.id_proveedores}" ${selected}>${p.nombre_empresa}</option>`);
            });
        }
    });
}
// ✅ CORRECCIÓN: Botón Agregar Producto Manual
    // Ahora distribuye el monto correctamente al instante (Gravado/Exento)
    $('#btnAgregarProducto').on('click', async function() {
        const id = $('#codigoProducto').val();
        const descripcion = $('#descripcionProducto').val();
        const impuestoAplicable = $('#impuestoAplicableProducto').val() || '20'; // Default '20'
        const codigoGanancia = $('#codigoGananciaProducto').val();
        const cantidad = parseFloat($('#cantidadProducto').val()) || 0;
        const precioUnitario = parseFloat($('#precioUnitarioProducto').val()) || 0;
        
        if (!id || !descripcion || cantidad <= 0 || precioUnitario <= 0) {
            toastr.warning('Por favor, complete todos los campos del producto.');
            return;
        }

        // 1. Cálculos iniciales (4 decimales)
        const subtotal = (cantidad * precioUnitario);
        const impuestoInfo = await obtenerDetalleImpuesto(impuestoAplicable);
        
        // 2. Construir objeto base
        const producto = {
            id_productos: id,
            codigo_interno: id, // Asumimos mismo código para manual
            codigo_proveedor: '', // Manual no suele tener este dato al vuelo
            descripcion: descripcion,
            cantidad: cantidad,
            precio_unitario: precioUnitario.toFixed(4),
            impuesto_aplicable: impuestoAplicable,
            impuesto_descripcion: impuestoInfo.descripcion_completa,
            codigo_ganancia: codigoGanancia,
            subtotal: subtotal.toFixed(4),
            descuento: 0,
            
            // Inicializamos las columnas de venta en 0
            ventas_no_sujetas: 0,
            ventas_exentas: 0,
            ventas_gravadas: 0
        };

        // 3. DISTRIBUCIÓN AUTOMÁTICA (La corrección clave)
        // Determinamos dónde poner el dinero según el impuesto seleccionado
        if (impuestoAplicable === '20') {
            producto.ventas_gravadas = subtotal.toFixed(4);
        } else if (impuestoAplicable === '00' || impuestoInfo.porcentaje === 0) {
            // Si el código es 00 o el porcentaje es 0, asumimos Exento
            // (Si usas No Sujeto, tendrías que tener un código específico o un checkbox en el UI)
            producto.ventas_exentas = subtotal.toFixed(4);
        } else {
            // Fallback por defecto a Gravada
            producto.ventas_gravadas = subtotal.toFixed(4);
        }

        // 4. Agregar y Renderizar
        productosCompra.push(producto);
        renderizarTabla(); // Esto ahora llamará a actualizarTotales() con datos reales
        
        // Limpiar campos de entrada (Visual)
        $('#codigoProducto').val('').focus();
        $('#descripcionProducto').val('');
        $('#cantidadProducto').val('1');
        $('#precioUnitarioProducto').val('');
    });
    
    // Llamadas iniciales
    $.when(cargarCatalogos(), cargarProveedores()).done(function(catalogoData, proveedoresData) {
        renderizarSelects(catalogoData, proveedoresData);
    }).fail(function() {
        toastr.error('Error al cargar catálogos y proveedores.');
    });

    $(document).on('blur', '#numero_documento', function() {
        const numeroDocumento = $(this).val().trim();

        if (numeroDocumento !== '') {
            $.ajax({
                url: 'admin/compras/crud_compras.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    accion: 'validarNumeroDocumento',
                    numero_documento: numeroDocumento
                },
                success: function(response) {
                    if (response.existe) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Documento duplicado',
                            text: 'El número de documento ya está registrado en otra compra.',
                            confirmButtonText: 'Entendido'
                        });

                        // limpiar campo para forzar que ingrese otro número
                        $('#numero_documento').val('').focus();
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error en validación de número de documento:", error);
                }
            });
        }
    });

});