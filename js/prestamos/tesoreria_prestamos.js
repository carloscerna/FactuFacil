document.addEventListener('DOMContentLoaded', () => {
    const API_URL = 'admin/prestamos/crud_prestamos.php'; 
    const form = document.getElementById('formPrestamo');
    const selectPrestamista = document.getElementById('selectPrestamista');
    
    // Elementos de Banco
    const optBanco = document.getElementById('optBanco');
    const optCaja = document.getElementById('optCaja');
    const bloqueBanco = document.getElementById('bloqueBanco');
    const selectBanco = document.getElementById('selectBanco');

    // Cargas iniciales
    cargarPrestamistas();

    // Logica visual Banco/Caja
    optBanco.addEventListener('change', () => {
        bloqueBanco.classList.remove('d-none');
        selectBanco.required = true;
        cargarBancos(); // Función para cargar cuentas
    });
    
    optCaja.addEventListener('change', () => {
        bloqueBanco.classList.add('d-none');
        selectBanco.required = false;
    });

    // Envío del Formulario
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const formData = new FormData(form);

        fetch(API_URL, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                if (res.respuesta) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: res.mensaje,
                        confirmButtonColor: '#3085d6'
                    }).then(() => {
                        window.location.href = 'prestamos/estado_deuda'; // Redirigir a ver deudas
                    });
                    form.reset();
                } else {
                    Swal.fire('Error', res.mensaje, 'error');
                }
            })
            .catch(err => Swal.fire('Error', 'Fallo en la petición', 'error'));
    });

    function cargarPrestamistas() {
        const d = new FormData(); d.append('accion', 'listar_prestamistas');
        fetch(API_URL, { method: 'POST', body: d }).then(r=>r.json()).then(res=>{
            if(res.respuesta){
                let opts = '<option value="">Seleccione...</option>';
                res.data.forEach(p => opts += `<option value="${p.id}">${p.nombre_prestamista}</option>`);
                selectPrestamista.innerHTML = opts;
            }
        });
    }

    function cargarBancos() {
        // Implementar similar a cargarPrestamistas pero con accion 'listar_cuentas'
        // (Ya tienes el case en PHP para listar_cuentas si lo agregaste)
    }
});