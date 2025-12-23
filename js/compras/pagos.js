// js/compras/pagos.js
let tablaPagos;

$(document).ready(function() {
    cargarTablaPagos();
    cargarCuentasTesoreria();

    // Evento Guardar Pago
    $('#btnConfirmarPago').on('click', function() {
        if($('#formPagar')[0].checkValidity()){
            guardarAbono();
        } else {
            $('#formPagar')[0].reportValidity();
        }
    });

let tablaHistorialDT;

// 1. EXTENSIÓN DE FILTRO PERSONALIZADO DATATABLES
$.fn.dataTable.ext.search.push(
    function(settings, data, dataIndex) {
        // Solo aplicar filtro a la tabla historial
        if (settings.nTable.id !== 'tablaHistorial') return true;

        var min = $('#filtroFechaInicio').val();
        var max = $('#filtroFechaFin').val();
        var date = data[0]; // La fecha está en la columna 0

        if (
            (min === "" && max === "") ||
            (min === "" && date <= max) ||
            (min <= date && max === "") ||
            (min <= date && date <= max)
        ) {
            return true;
        }
        return false;
    }
);

// 2. INICIALIZACIÓN DE LA TABLA
$('#modalHistorial').on('shown.bs.modal', function () {
    if (tablaHistorialDT) {
        tablaHistorialDT.ajax.reload();
        // Resetear filtros visuales al abrir
        $('#filtroFechaInicio').val('');
        $('#filtroFechaFin').val('');
        tablaHistorialDT.draw();
    } else {
        tablaHistorialDT = $('#tablaHistorial').DataTable({
            "ajax": {
                "url": "admin/compras/crud_pagos.php",
                "type": "POST",
                "data": { accion: 'listarHistorialPagos' }
            },
            "columns": [
                { "data": "fecha_pago" },
                { "data": "nombre_empresa" },
                { "data": "numero_documento" },
                { "data": "banco" },
                { "data": "referencia_pago" },
                { 
                    "data": "monto_abonado",
                    "className": "text-end fw-bold text-success",
                    "render": function(data) {
                        return '$ ' + parseFloat(data).toFixed(2);
                    }
                }
            ],
            "order": [[0, "desc"]],
            "dom": 'Bfrtip', // Habilitar botones
            "buttons": [
                {
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel"></i> Exportar Excel',
                    className: 'btn btn-success btn-sm',
                    title: 'Historial_Pagos_Proveedores'
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="fas fa-file-pdf"></i> PDF',
                    className: 'btn btn-danger btn-sm'
                }
            ],
            "language": {
                "url": "https://cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json"
            },
            // Calcular Total en el Footer cada vez que se dibuje la tabla
            "footerCallback": function (row, data, start, end, display) {
                var api = this.api();

                // Función auxiliar para convertir a número
                var intVal = function (i) {
                    return typeof i === 'string' ?
                        i.replace(/[\$,]/g, '') * 1 :
                        typeof i === 'number' ? i : 0;
                };

                // Calcular total de la página actual (o de todo el filtro)
                var total = api
                    .column(5, { page: 'current' }) // Columna 5 es el Monto
                    .data()
                    .reduce(function (a, b) {
                        return intVal(a) + intVal(b);
                    }, 0);

                // Actualizar el footer
                $('#totalHistorial').html('$ ' + total.toFixed(2));
            }
        });
    }
});

// 3. EVENTOS DE LOS BOTONES DE FILTRO
$('#btnFiltrarFechas').on('click', function() {
    tablaHistorialDT.draw();
});

$('#btnLimpiarFiltros').on('click', function() {
    $('#filtroFechaInicio').val('');
    $('#filtroFechaFin').val('');
    tablaHistorialDT.draw();
});


});

function cargarTablaPagos() {
    tablaPagos = $('#tablaPagos').DataTable({
        ajax: {
            url: 'admin/compras/crud_pagos.php',
            type: 'POST',
            data: { accion: 'listarCuentasPorPagar' },
            dataSrc: function(json) {
                // Actualizar KPIs al cargar datos
                actualizarKPIs(json.data);
                return json.data;
            }
        },
        columns: [
            { data: 'fecha_emision' },
            { 
                data: 'numero_documento',
                render: function(data) {
                    return `<span class="fw-bold text-primary">${data}</span>`;
                }
            },
            { data: 'nombre_proveedor' },
            { 
                data: 'fecha_vencimiento',
                render: function(data) {
                    if(!data) return '<span class="text-muted">-</span>';
                    // Lógica visual de vencimiento
                    let hoy = new Date();
                    let ven = new Date(data);
                    let color = ven < hoy ? 'text-danger fw-bold' : 'text-success';
                    let icono = ven < hoy ? '<i class="fas fa-exclamation-circle me-1"></i>' : '';
                    return `<span class="${color}">${icono}${data}</span>`;
                }
            },
            { 
                data: 'total_compra',
                className: 'text-end',
                render: $.fn.dataTable.render.number(',', '.', 2, '$ ')
            },
            { 
                data: 'saldo_pendiente',
                className: 'text-end',
                render: function(data) {
                    return `<span class="badge bg-danger fs-6">$ ${parseFloat(data).toFixed(2)}</span>`;
                }
            },
            {
                data: null,
                className: 'text-center',
                render: function(data, type, row) {
                    // Botón Pagar: Pasa todos los datos necesarios
                    return `<button class="btn btn-sm btn-info text-white rounded-pill px-3 shadow-sm btnPagar" 
                            data-id="${row.id_compra}" 
                            data-prov="${row.nombre_proveedor}" 
                            data-saldo="${row.saldo_pendiente}">
                                <i class="fas fa-hand-holding-usd me-1"></i> Pagar
                            </button>`;
                }
            }
        ],
        language: { url: "//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json" },
        dom: 'rtip', // Tabla limpia sin buscador gigante arriba (opcional)
        pageLength: 10
    });

    // Evento Click en Botón Pagar de la tabla
    $('#tablaPagos tbody').on('click', '.btnPagar', function() {
        let id = $(this).data('id');
        let prov = $(this).data('prov');
        let saldo = $(this).data('saldo');

        // Llenar Modal
        $('#pagoIdCompra').val(id);
        $('#lblProveedor').text(prov);
        $('#lblSaldo').text(parseFloat(saldo).toFixed(2));
        $('#pagoMonto').val(parseFloat(saldo).toFixed(2)); // Sugerir pago total
        $('#pagoMonto').attr('max', saldo); // No pagar más de lo que se debe

        $('#modalPagar').modal('show');
    });
}

function cargarCuentasTesoreria() {
    $.ajax({
        url: 'admin/compras/crud_pagos.php',
        type: 'POST',
        data: { accion: 'obtenerCuentasTesoreria' },
        success: function(response) {
            // 1. DETECCIÓN INTELIGENTE DE RESPUESTA
                let resp;
                
                // Si jQuery ya lo convirtió en objeto, úsalo directamente.
                if (typeof response === 'object') {
                    resp = response; 
                }
            let cuentas = JSON.parse(response);
            let options = '<option value="">Seleccione Cuenta...</option>';
            cuentas.forEach(c => {
                let icono = c.tipo_cuenta === 'BANCO' ? '🏦' : '💵';
                options += `<option value="${c.id}">${icono} ${c.nombre_cuenta} (Saldo: $${c.saldo_actual})</option>`;
            });
            $('#pagoCuenta').html(options);
        }
    });
}

function guardarAbono() {
    // 1. Validaciones básicas
    if($('#pagoCuenta').val() === '' || $('#pagoMonto').val() === '') {
        Swal.fire('Atención', 'Seleccione una cuenta y un monto', 'warning');
        return;
    }

    // 2. Enviar petición
    $.ajax({
        url: 'admin/compras/crud_pagos.php',
        type: 'POST',
        data: $('#formPagar').serialize() + '&accion=guardarAbono',
        // dataType: 'json', // <--- Opcional: Si lo pones, jQuery SIEMPRE parsea
        success: function(response) {
            console.log("Respuesta Servidor:", response); // Para depurar

            let resp;
            
            // --- CORRECCIÓN DEL ERROR DE JSON ---
            // Verificamos si jQuery ya lo convirtió en Objeto o si sigue siendo Texto
            if (typeof response === 'object') {
                resp = response; // Ya es objeto, no hacemos nada
            } else {
                try {
                    resp = JSON.parse(response); // Es texto, lo parseamos
                } catch (e) {
                    console.error("Error parseando:", e);
                    Swal.fire('Error Fatal', 'Respuesta del servidor no válida', 'error');
                    return;
                }
            }
            // ------------------------------------

            if(resp.respuesta) {
                $('#modalPagar').modal('hide');
                Swal.fire({
                    icon: 'success',
                    title: '¡Pago Exitoso!',
                    text: resp.mensaje,
                    timer: 2000,
                    showConfirmButton: false
                });
                
                tablaPagos.ajax.reload(); // Recargar tabla
                $('#formPagar')[0].reset(); // Limpiar formulario
            } else {
                Swal.fire('Error', resp.mensaje || 'Error desconocido', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error("Error AJAX:", xhr.responseText);
            Swal.fire('Error de Conexión', 'No se pudo procesar el pago.', 'error');
        }
    });
}

function actualizarKPIs(data) {
    let totalDocs = data.length;
    let totalDeuda = 0;
    let porVencer = 0;
    let hoy = new Date();

    data.forEach(item => {
        totalDeuda += parseFloat(item.saldo_pendiente);
        if(item.fecha_vencimiento) {
            let ven = new Date(item.fecha_vencimiento);
            let diff = (ven - hoy) / (1000 * 60 * 60 * 24);
            if(diff <= 7) porVencer++;
        }
    });

    $('#kpiFacturas').text(totalDocs);
    $('#kpiTotalDeuda').text(totalDeuda.toFixed(2));
    $('#kpiVencimiento').text(porVencer);
}