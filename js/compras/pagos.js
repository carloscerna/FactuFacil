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
    $.ajax({
        url: 'admin/compras/crud_pagos.php',
        type: 'POST',
        data: $('#formPagar').serialize() + '&accion=guardarAbono',
        success: function(response) {
            let resp = JSON.parse(response);
            if(resp.respuesta) {
                $('#modalPagar').modal('hide');
                Swal.fire({
                    icon: 'success',
                    title: '¡Pago Exitoso!',
                    text: resp.mensaje,
                    timer: 2000,
                    showConfirmButton: false
                });
                tablaPagos.ajax.reload(); // Recargar tabla para ver el saldo bajar
                $('#formPagar')[0].reset();
            } else {
                Swal.fire('Error', resp.mensaje, 'error');
            }
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