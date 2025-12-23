// js/prestamos/catalogo_prestamistas.js
const API_URL = 'admin/prestamos/crud_prestamos.php';
let myModal;

document.addEventListener('DOMContentLoaded', () => {
    myModal = new bootstrap.Modal(document.getElementById('modalPrestamista'));
    listarPrestamistas();
});

function listarPrestamistas() {
    const formData = new FormData();
    formData.append('accion', 'tabla_prestamistas');

    fetch(API_URL, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if(res.respuesta) {
            let html = '';
            res.data.forEach(item => {
                html += `
                <tr>
                    <td>${item.nombre_prestamista}</td>
                    <td>${item.telefono || '-'}</td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-warning me-1" onclick="editar(${item.id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="eliminar(${item.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>`;
            });
            document.getElementById('tbodyPrestamistas').innerHTML = html;
        }
    });
}

function abrirModal() {
    document.getElementById('formPrestamista').reset();
    document.getElementById('inputId').value = '';
    document.getElementById('tituloModal').innerText = 'Nuevo Prestamista';
    myModal.show();
}

function guardarPrestamista() {
    const form = document.getElementById('formPrestamista');
    if(!form.checkValidity()){
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);
    formData.append('accion', 'guardar_prestamista_catalogo');

    fetch(API_URL, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if(res.respuesta) {
            alert('Guardado correctamente'); // O tu notificación toast
            myModal.hide();
            listarPrestamistas();
        } else {
            alert('Error: ' + res.mensaje);
        }
    });
}

function editar(id) {
    const formData = new FormData();
    formData.append('accion', 'obtener_prestamista');
    formData.append('id', id);

    fetch(API_URL, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if(res.respuesta) {
            const d = res.data;
            document.getElementById('inputId').value = d.id;
            document.getElementById('inputNombre').value = d.nombre_prestamista;
            document.getElementById('inputTelefono').value = d.telefono;
            
            document.getElementById('tituloModal').innerText = 'Editar Prestamista';
            myModal.show();
        }
    });
}

function eliminar(id) {
    if(!confirm('¿Seguro de eliminar este acreedor?')) return;

    const formData = new FormData();
    formData.append('accion', 'eliminar_prestamista');
    formData.append('id', id);

    fetch(API_URL, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if(res.respuesta) {
            listarPrestamistas();
        } else {
            alert('Error: ' + res.mensaje);
        }
    });
}