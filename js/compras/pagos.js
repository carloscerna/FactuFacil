// js/compras/pagos.js
let tablaPagos;
let tablaHistorialDT;

$(document).ready(function() {
    cargarTablaPagos();
    cargarCuentasTesoreria(); // <--- Esta es la función clave actualizada

    // Evento Guardar Pago
    $('#btnConfirmarPago').on('click', function() {
        if($('#formPagar')[0].checkValidity()){
            guardarAbono();
        } else {
            $('#formPagar')[0].reportValidity();
        }
    });

    // =======================================================
    // 1. EXTENSIÓN DE FILTRO PERSONALIZADO DATATABLES
    // =======================================================
    $.fn.dataTable.ext.search.push(
        function(settings, data, dataIndex) {
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

    // =======================================================
    // 2. INICIALIZACIÓN DE LA TABLA HISTORIAL
    // =======================================================
    $('#modalHistorial').on('shown.bs.modal', function () {
        if (tablaHistorialDT) {
            tablaHistorialDT.ajax.reload();
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
                    { "data": "banco" }, // Muestra 'Sin cuenta' si no se cruza, o nombre banco
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
                "dom": 'Bfrtip',
                "buttons": [
                    { extend: 'excelHtml5', className: 'btn btn-success btn-sm', title: 'Historial_Pagos' },
                    { extend: 'pdfHtml5', className: 'btn btn-danger btn-sm' }
                ],
                "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json" },
                "footerCallback": function (row, data, start, end, display) {
                    var api = this.api();
                    var intVal = function (i) {
                        return typeof i === 'string' ? i.replace(/[\$,]/g, '') * 1 : typeof i === 'number' ? i : 0;
                    };
                    var total = api.column(5, { page: 'current' }).data().reduce((a, b) => intVal(a) + intVal(b), 0);
                    $('#totalHistorial').html('$ ' + total.toFixed(2));
                }
            });
        }
    });

    $('#btnFiltrarFechas').on('click', function() { tablaHistorialDT.draw(); });
    $('#btnLimpiarFiltros').on('click', function() {
        $('#filtroFechaInicio').val('');
        $('#filtroFechaFin').val('');
        tablaHistorialDT.draw();
    });
});

// =======================================================
// CARGAR TABLA DE CUENTAS POR PAGAR (PENDIENTES)
// =======================================================
function cargarTablaPagos() {
    tablaPagos = $('#tablaPagos').DataTable({
        ajax: {
            url: 'admin/compras/crud_pagos.php',
            type: 'POST',
            data: { accion: 'listarCuentasPorPagar' },
            dataSrc: function(json) {
                actualizarKPIs(json.data);
                return json.data;
            }
        },
        columns: [
            { data: 'fecha_emision' },
            { 
                data: 'numero_documento',
                render: function(data) { return `<span class="fw-bold text-primary">${data}</span>`; }
            },
            { data: 'nombre_proveedor' },
            { 
                data: 'fecha_vencimiento',
                render: function(data) {
                    if(!data) return '<span class="text-muted">-</span>';
                    let hoy = new Date(); let ven = new Date(data);
                    let color = ven < hoy ? 'text-danger fw-bold' : 'text-success';
                    return `<span class="${color}">${data}</span>`;
                }
            },
            { data: 'total_compra', className: 'text-end', render: $.fn.dataTable.render.number(',', '.', 2, '$ ') },
            { 
                data: 'saldo_pendiente',
                className: 'text-end',
                render: function(data) { return `<span class="badge bg-danger fs-6">$ ${parseFloat(data).toFixed(2)}</span>`; }
            },
            {
                data: null, className: 'text-center',
                render: function(data, type, row) {
                    return `<button class="btn btn-sm btn-info text-white rounded-pill px-3 shadow-sm btnPagar" 
                            data-id="${row.id_compra}" data-prov="${row.nombre_proveedor}" data-saldo="${row.saldo_pendiente}">
                                <i class="fas fa-hand-holding-usd me-1"></i> Pagar
                            </button>`;
                }
            }
        ],
        language: { url: "//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json" },
        dom: 'rtip', pageLength: 10
    });

    $('#tablaPagos tbody').on('click', '.btnPagar', function() {
        let id = $(this).data('id');
        let prov = $(this).data('prov');
        let saldo = $(this).data('saldo');

        $('#pagoIdCompra').val(id);
        $('#lblProveedor').text(prov);
        $('#lblSaldo').text(parseFloat(saldo).toFixed(2));
        $('#pagoMonto').val(parseFloat(saldo).toFixed(2)); 
        $('#pagoMonto').attr('max', saldo);
        $('#modalPagar').modal('show');
    });
}

// =======================================================
// CARGAR SELECT DE CUENTAS (CORREGIDO PARA NUEVO PHP)
// =======================================================
function cargarCuentasTesoreria() {
    $.ajax({
        url: 'admin/compras/crud_pagos.php',
        type: 'POST',
        dataType: 'json', // Forzar JSON
        data: { accion: 'obtenerCuentasTesoreria' },
        success: function(response) {
            let options = '<option value="">Seleccione Cuenta...</option>';
            
            // El PHP nuevo devuelve un array directo [{id_compuesto: '...', texto: '...'}, ...]
            if (Array.isArray(response) && response.length > 0) {
                response.forEach(c => {
                    // Usamos id_compuesto (ej: BANCO_1) y texto pre-formateado
                    options += `<option value="${c.id_compuesto}">${c.texto}</option>`;
                });
            } else {
                options += '<option value="" disabled>No hay cuentas configuradas</option>';
            }
            $('#pagoCuenta').html(options);
        },
        error: function(e) {
            console.error("Error al cargar cuentas:", e);
        }
    });
}

// =======================================================
// GUARDAR ABONO (PROCESA SALIDA DE DINERO)
// =======================================================
function guardarAbono() {
    if($('#pagoCuenta').val() === '' || $('#pagoMonto').val() === '') {
        Swal.fire('Atención', 'Seleccione una cuenta y un monto', 'warning');
        return;
    }

    // Aseguramos enviar el nombre correcto 'id_cuenta_tesoreria'
    // aunque el select tenga otro ID, lo forzamos en el data
    let datos = $('#formPagar').serialize();
    // Agregamos explícitamente el ID compuesto por si el name del select no coincide
    datos += '&id_cuenta_tesoreria=' + $('#pagoCuenta').val();
    datos += '&accion=guardarAbono';

    $.ajax({
        url: 'admin/compras/crud_pagos.php',
        type: 'POST',
        data: datos,
        dataType: 'json', // jQuery parsea automáticamente
        success: function(resp) {
            if(resp.respuesta) {
                $('#modalPagar').modal('hide');
                Swal.fire({
                    icon: 'success',
                    title: '¡Pago Exitoso!',
                    text: resp.mensaje,
                    timer: 2000,
                    showConfirmButton: false
                });
                tablaPagos.ajax.reload();
                $('#formPagar')[0].reset();
            } else {
                Swal.fire('Error', resp.mensaje, 'error');
            }
        },
        error: function(xhr) {
            console.error(xhr.responseText);
            Swal.fire('Error', 'No se pudo procesar el pago. Revise la consola.', 'error');
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