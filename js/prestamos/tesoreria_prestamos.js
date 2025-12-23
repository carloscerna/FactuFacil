// js/prestamos/form_nuevo.js

document.addEventListener('DOMContentLoaded', () => {
    // Apuntamos al controlador específico de este módulo
    const API_URL = 'admin/prestamos/crud_prestamos.php'; 

    const form = document.getElementById('formPrestamo');
    const selectPrestamista = document.getElementById('selectPrestamista');

    // Inicializar
    cargarPrestamistas();

    function cargarPrestamistas() {
        const data = new FormData();
        data.append('accion', 'listar_prestamistas');

        fetch(API_URL, { method: 'POST', body: data })
            .then(r => r.json())
            .then(res => {
                if (res.respuesta) {
                    let opts = '<option value="">Seleccione...</option>';
                    res.data.forEach(p => {
                        opts += `<option value="${p.id}">${p.nombre_prestamista}</option>`;
                    });
                    selectPrestamista.innerHTML = opts;
                }
            });
    }

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const formData = new FormData(form);
        formData.append('accion', 'guardar_prestamo');

        fetch(API_URL, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                if (res.respuesta) {
                    // Usar tu librería de notificaciones (Toast, Swal, etc)
                    alert('Guardado con éxito'); 
                    form.reset();
                } else {
                    alert('Error: ' + res.mensaje);
                }
            });
    });
});