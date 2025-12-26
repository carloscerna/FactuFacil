/**
 * js/finanzas/dashboard.js
 * Módulo completo para gestión financiera
 */

const API_URL = 'admin/prestamos/crud_prestamos.php';
let dataTableFinanzas;
let listaBancosCache = {}; // Almacén temporal para los bancos

document.addEventListener('DOMContentLoaded', () => {
    // 1. Iniciar Tabla
    inicializarDataTable();
    
    // 2. Precargar lista de bancos para usarla en los pagos rápidamente
    preCargarBancos();

    // NUEVO: Cargar los saldos al entrar
    cargarResumenSaldos();

    // ------------------------------------------------------------------
    // LOGICA MODAL: REGISTRAR PRÉSTAMO
    // ------------------------------------------------------------------
    const optBanco = document.getElementById('optBancoModal');
    const optCaja = document.getElementById('optCajaModal');
    const divBanco = document.getElementById('divBancoSelectModal');
    const selectBanco = document.getElementById('modal_selectBanco');

    // Toggle Visual Caja/Banco
    if(optBanco && optCaja) {
        optBanco.addEventListener('change', () => {
            divBanco.classList.remove('d-none');
            selectBanco.setAttribute('required', 'true');
            cargarBancosEnSelect(); // Asegurar que el select tenga datos
        });

        optCaja.addEventListener('change', () => {
            divBanco.classList.add('d-none');
            selectBanco.removeAttribute('required');
            selectBanco.value = '';
        });
    }

    // Enviar Formulario Préstamo Completo
    const formPrestamo = document.getElementById('formPrestamoModal');
    if(formPrestamo) {
        formPrestamo.addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            if(!fd.has('accion')) fd.append('accion', 'guardar_prestamo');

            fetch(API_URL, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if(res.respuesta) {
                    Swal.fire('Éxito', res.mensaje, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('modalPrestamo')).hide();
                    this.reset();
                    // Resetear visualmente a Caja
                    if(optCaja) optCaja.checked = true;
                    if(divBanco) divBanco.classList.add('d-none');
                    // Recargar tabla
                    // --- AQUÍ ESTABA EL DETALLE ---
                    dataTableFinanzas.ajax.reload(); // Recarga la tabla de abajo
                    cargarResumenSaldos();           // <--- AGREGA ESTA LÍNEA (Recarga las tarjetas de arriba)
                    // -----------------------------
                } else {
                    Swal.fire('Error', res.mensaje, 'error');
                }
            })
            .catch(err => Swal.fire('Error', 'Fallo de red', 'error'));
        });
    }

    // ------------------------------------------------------------------
    // LOGICA MODAL: NUEVO PRESTAMISTA (RÁPIDO)
    // ------------------------------------------------------------------
    const formRapido = document.getElementById('formNuevoPrestamistaRapido');
    if(formRapido) {
        formRapido.addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            fd.append('accion', 'guardar_prestamista_catalogo');

            fetch(API_URL, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if(res.respuesta) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Acreedor agregado',
                        toast: true, position: 'top-end', showConfirmButton: false, timer: 2000
                    });
                    bootstrap.Modal.getInstance(document.getElementById('modalNuevoPrestamista')).hide();
                    this.reset();
                    // Recargar el select del modal principal
                    cargarPrestamistasEnSelect();
                } else {
                    Swal.fire('Error', res.mensaje, 'error');
                }
            });
        });
    }

    // ------------------------------------------------------------------
    // LOGICA MODAL: NUEVO BANCO
    // ------------------------------------------------------------------
    const formBanco = document.getElementById('formBanco');
    if(formBanco) {
        formBanco.addEventListener('submit', function(e){
            e.preventDefault();
            const fd = new FormData(this);
            fd.append('accion', 'guardar_banco');
            
            fetch(API_URL, {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
                if(res.respuesta) {
                    Swal.fire('Guardado', 'Banco registrado', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('modalBanco')).hide();
                    this.reset();
                    preCargarBancos(); // Actualizar cache
                } else {
                    Swal.fire('Error', res.mensaje, 'error');
                }
            });
        });
    }
    // EVENTO: GUARDAR SALDO CAJA INICIAL
    const formCaja = document.getElementById('formCajaInicial');
    if(formCaja){
        formCaja.addEventListener('submit', function(e){
            e.preventDefault();
            const fd = new FormData(this);
            fd.append('accion', 'guardar_saldo_caja');

            fetch(API_URL, {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
                if(res.respuesta) {
                    Swal.fire({
                        icon: 'success', title: 'Caja Actualizada', 
                        text: 'El saldo inicial ha sido establecido',
                        timer: 2000, showConfirmButton: false
                    });
                    bootstrap.Modal.getInstance(document.getElementById('modalCajaInicial')).hide();
                    this.reset();
                    cargarResumenSaldos(); // Actualizar las tarjetas
                } else {
                    Swal.fire('Error', res.mensaje, 'error');
                }
            });
        });
    }

    // Función Global para abrir el modal
    window.abrirModalCajaInicial = function() {
    new bootstrap.Modal(document.getElementById('modalCajaInicial')).show();
    }

});

// ==============================================================
// FUNCIONES GLOBALES (Window Scope) - PARA QUE FUNCIONEN LOS BOTONES
// ==============================================================

/**
 * Abre el modal de Pagos y carga las cuotas
 */
window.verPlanPagos = function(idPrestamo, nombrePrestamista, montoOriginal) {
    // 1. Mostrar Modal y poner datos de cabecera
    const modalEl = document.getElementById('modalPagos');
    const modal = new bootstrap.Modal(modalEl);
    
    document.getElementById('lblDetallePrestamo').innerText = `Acreedor: ${nombrePrestamista}`;
    document.getElementById('lblMontoTotal').innerText = '$' + parseFloat(montoOriginal).toFixed(2);
    document.getElementById('tbodyCuotas').innerHTML = '<tr><td colspan="5" class="text-center py-3"><div class="spinner-border text-primary"></div> Cargando...</td></tr>';
    
    modal.show();

    // 2. Fetch Cuotas
    const fd = new FormData(); 
    fd.append('accion', 'ver_cuotas_prestamo');
    fd.append('id_prestamo', idPrestamo);

    fetch(API_URL, {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
        let html = '';
        let totalPagado = 0;
        let totalPendiente = 0;

        if(res.data && res.data.length > 0) {
            res.data.forEach(c => {
                let btn = '';
                let estadoBadge = '';
                
                if(c.estado === 'PAGADO') {
                    totalPagado += parseFloat(c.monto_cuota);
                    estadoBadge = '<span class="badge bg-success">Pagado</span>';
                    btn = `<span class="text-muted small"><i class="fas fa-check-circle"></i> ${c.fecha_pago_real ? c.fecha_pago_real.substring(0,10) : ''}</span>`;
                } else {
                    totalPendiente += parseFloat(c.monto_cuota);
                    estadoBadge = '<span class="badge bg-warning text-dark">Pendiente</span>';
                    // Botón llama a pagarCuota global
                    btn = `<button class="btn btn-sm btn-outline-primary" 
                           onclick="pagarCuota(${c.id}, ${c.monto_cuota}, '${c.fecha_vencimiento}')">
                           <i class="fas fa-hand-holding-usd"></i> Pagar
                           </button>`;
                }

                html += `
                <tr>
                    <td>${c.numero_cuota}</td>
                    <td>${c.fecha_vencimiento}</td>
                    <td class="fw-bold">$${c.monto_cuota}</td>
                    <td>${estadoBadge}</td>
                    <td class="text-center">${btn}</td>
                </tr>`;
            });
        } else {
            html = '<tr><td colspan="5" class="text-center">No se encontraron cuotas.</td></tr>';
        }

        document.getElementById('tbodyCuotas').innerHTML = html;
        document.getElementById('lblMontoPagado').innerText = '$' + totalPagado.toFixed(2);
        document.getElementById('lblMontoPendiente').innerText = '$' + totalPendiente.toFixed(2);
    });
};

/**
 * Lógica para pagar una cuota específica
 */
window.pagarCuota = async function(idCuota, monto, fechaVenc) {
    // Construir opciones del Select (Caja + Bancos Cargados)
    const opciones = { 'CAJA': 'Caja (Efectivo)' };
    for (const [id, texto] of Object.entries(listaBancosCache)) {
        opciones[`BANCO_${id}`] = `Banco: ${texto}`;
    }

    // SweetAlert con Select
    const { value: origen } = await Swal.fire({
        title: `Pagar Cuota $${monto}`,
        text: `Vencimiento: ${fechaVenc}`,
        icon: 'question',
        input: 'select',
        inputOptions: opciones,
        inputPlaceholder: 'Seleccione origen del dinero',
        showCancelButton: true,
        confirmButtonText: 'Confirmar Pago',
        cancelButtonText: 'Cancelar',
        inputValidator: (value) => {
            return !value && 'Debe seleccionar de dónde sale el dinero';
        }
    });

    if (origen) {
        // Preparar datos para backend
        let tipoPago = 'CAJA';
        let idCuenta = null;

        if (origen.startsWith('BANCO_')) {
            tipoPago = 'BANCO';
            idCuenta = origen.split('_')[1];
        }

        const fd = new FormData();
        fd.append('accion', 'pagar_cuota');
        fd.append('id_cuota', idCuota);
        fd.append('origen_pago', tipoPago);
        if(idCuenta) fd.append('id_cuenta_origen', idCuenta);

        fetch(API_URL, {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
            if(res.respuesta) {
                Swal.fire('Pago Exitoso', res.mensaje, 'success');
                // Ocultar modal de pagos y recargar tabla principal
                bootstrap.Modal.getInstance(document.getElementById('modalPagos')).hide();
                dataTableFinanzas.ajax.reload();
            } else {
                Swal.fire('Error', res.mensaje, 'error');
            }
        });
    }
};

/**
 * Funciones de Apoyo
 */
function inicializarDataTable() {
    if($.fn.DataTable.isDataTable('#tablaFinanzas')) {
        $('#tablaFinanzas').DataTable().destroy();
    }

    dataTableFinanzas = $('#tablaFinanzas').DataTable({
        ajax: {
            url: API_URL,
            type: 'POST',
            data: { accion: 'listar_prestamos_completo' }
        },
        columns: [
            { data: 'id' },
            { data: 'nombre_prestamista', className: 'fw-bold' },
            { data: 'fecha_ingreso' },
            { data: 'destino_fondos' },
            { 
                data: 'monto_original', 
                render: $.fn.dataTable.render.number(',', '.', 2, '$') 
            },
            { 
                data: 'saldo_pendiente',
                render: function(data) {
                    let val = parseFloat(data);
                    return val > 0 
                        ? `<span class="saldo-pendiente">$${val.toFixed(2)}</span>` 
                        : `<span class="saldo-pagado">$0.00</span>`;
                }
            },
            { data: 'proximo_vencimiento' },
            { 
                data: 'saldo_pendiente',
                render: function(data) {
                    return parseFloat(data) > 0 
                        ? '<span class="badge bg-warning text-dark">Activo</span>' 
                        : '<span class="badge bg-success">Pagado</span>';
                }
            },
            {
                data: null,
                orderable: false,
                render: function(data, type, row) {
                    // Escapamos comillas para evitar errores en JS
                    const nombreSafe = row.nombre_prestamista.replace(/'/g, "\\'");
                    return `<button class="btn btn-sm btn-info text-white" 
                            onclick="verPlanPagos(${row.id}, '${nombreSafe}', '${row.monto_original}')">
                            <i class="fas fa-eye"></i> Pagos</button>`;
                }
            }
        ],
        dom: 'Bfrtip',
        buttons: ['excelHtml5', 'pdfHtml5', 'print'],
        language: { url: "//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json" },
        order: [[0, 'desc']] // Ordenar por ID descendente
    });
}

// Cargar Prestamistas en el Select del Modal
window.cargarPrestamistasEnSelect = function() { // Hacerla global por si acaso
    const select = document.getElementById('modal_selectPrestamista');
    if(!select) return;
    
    const fd = new FormData(); fd.append('accion', 'listar_prestamistas');
    fetch(API_URL, { method: 'POST', body: fd }).then(r=>r.json()).then(res=>{
        if(res.respuesta) {
            let opts = '<option value="">Seleccione...</option>';
            res.data.forEach(p => {
                opts += `<option value="${p.id}">${p.nombre_prestamista}</option>`;
            });
            select.innerHTML = opts;
        }
    });
}

// Cargar Bancos en el Select del Modal
window.cargarBancosEnSelect = function() {
    const select = document.getElementById('modal_selectBanco');
    if(!select) return;

    let opts = '<option value="">Seleccione cuenta...</option>';
    // Usamos el caché
    for (const [id, texto] of Object.entries(listaBancosCache)) {
        opts += `<option value="${id}">${texto}</option>`;
    }
    select.innerHTML = opts;
}

// Pre-cargar bancos en memoria al inicio
function preCargarBancos() {
    const fd = new FormData(); fd.append('accion', 'listar_bancos_combo');
    fetch(API_URL, {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
        if(res.respuesta) {
            listaBancosCache = {}; // Limpiar
            res.data.forEach(b => {
                // b.texto viene del backend concatenado "Banco - Cuenta ($saldo)"
                listaBancosCache[b.id] = b.texto; 
            });
            // Si el modal ya estaba abierto, refrescar su select
            cargarBancosEnSelect();
        }
    });
}

// Cargar helpers al hacer clic en botones externos
window.abrirModalPrestamo = function() {
    cargarPrestamistasEnSelect();
    cargarBancosEnSelect();
    new bootstrap.Modal(document.getElementById('modalPrestamo')).show();
}

window.abrirModalBanco = function() {
    new bootstrap.Modal(document.getElementById('modalBanco')).show();
}

window.abrirModalNuevoPrestamista = function() {
    new bootstrap.Modal(document.getElementById('modalNuevoPrestamista')).show();
}

// FUNCIÓN PARA ACTUALIZAR LAS TARJETAS DE SALDO
function cargarResumenSaldos() {
    const fd = new FormData();
    fd.append('accion', 'obtener_resumen_saldos');

    fetch(API_URL, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.respuesta) {
            // Animación simple de conteo o asignación directa
            document.getElementById('kpiSaldoCaja').innerText = '$' + res.data.caja;
            document.getElementById('kpiSaldoBanco').innerText = '$' + res.data.bancos;
            document.getElementById('kpiSaldoTotal').innerText = '$' + res.data.total_disponible;
        }
    });
}

// IMPORTANTE:
// Agrega cargarResumenSaldos() dentro del "then" de guardar_prestamo 
// y pagar_cuota para que los números se actualicen en tiempo real 
// cuando hagas un movimiento.