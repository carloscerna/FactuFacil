// js/usuarios/Usuarios.js
$(function () {
    let usersTable;
    let userModal = new bootstrap.Modal(document.getElementById('userModal'));

    // Configuración global para Toastr
    toastr.options = {
        "closeButton": true, "debug": false, "newestOnTop": true, "progressBar": true, "positionClass": "toast-top-right",
        "preventDuplicates": false, "onclick": null, "showDuration": "300", "hideDuration": "1000", "timeOut": "5000",
        "extendedTimeOut": "1000", "showEasing": "swing", "hideEasing": "linear", "showMethod": "fadeIn", "hideMethod": "fadeOut"
    };

    function initializeDataTable() {
        if ($.fn.DataTable.isDataTable('#usersTable')) {
            $('#usersTable').DataTable().destroy();
        }
        usersTable = $('#usersTable').DataTable({
            "processing": true,
            "serverSide": false,
            "ajax": {
                "url": "admin/usuarios/Usuarios.php",
                "type": "POST",
                "data": { accion: "ReadUsers" },
                "dataSrc": "contenido"
            },
            "columns": [
                { "data": "id_usuario" },
                { "data": "username" },
                { "data": "nombre_personal" },
                { "data": "nombre_perfil" },
                { "data": "nombre_institucion_usuario" },
                { "data": "estado" }, // Nueva columna para el estado
                { "data": "acciones", "orderable": false, "searchable": false }
            ],
            "language": { "url": "php_libs/idioma/es_es.json" },
            "responsive": true
        });
    }

    // Función para cargar los perfiles
    function loadProfiles() {
        return $.ajax({
            url: "admin/usuarios/Usuarios.php",
            type: "POST",
            dataType: "json",
            data: { accion: "GetProfiles" }
        }).done(function(response) {
            if (response.respuesta) {
                let select = $('#profileCode');
                select.empty().append('<option value="">Seleccione un perfil</option>');
                response.contenido.forEach(profile => {
                    select.append(`<option value="${profile.codigo}">${profile.descripcion}</option>`);
                });
            } else {
                toastr.error("Error al cargar perfiles: " + response.mensaje);
            }
        }).fail(() => toastr.error("Error de conexión al cargar perfiles."));
    }

    // Función para cargar el personal
    function loadPersonal() {
        return $.ajax({
            url: "admin/usuarios/Usuarios.php",
            type: "POST",
            dataType: "json",
            data: { accion: "GetPersonal" }
        }).done(function(response) {
            if (response.respuesta) {
                let select = $('#personalId');
                select.empty().append('<option value="">Seleccione personal</option>');
                response.contenido.forEach(person => {
                    select.append(`<option value="${person.id_personal}">${person.nombres} ${person.apellidos}</option>`);
                });
            } else {
                toastr.error("Error al cargar personal: " + response.mensaje);
            }
        }).fail(() => toastr.error("Error de conexión al cargar personal."));
    }

    // Evento click para "Nuevo Usuario"
    $('#btnAddNewUser').on('click', function () {
        $('#userModalLabel').text('Crear Nuevo Usuario');
        $('#accion').val('CreateUser');
        $('#userId').val('');
        $('#userForm')[0].reset();
        $('#password').attr('required', true).val('');
        $('#userForm').validate().resetForm();
        $('.form-control').removeClass('is-invalid is-valid');

        loadProfiles();
        loadPersonal();
        userModal.show();
    });

    // Evento click para "Editar"
    $('#usersTable tbody').on('click', '.edit-user', function () {
        let userId = $(this).data('id');
        $('#userModalLabel').text('Editar Usuario');
        $('#accion').val('UpdateUser');
        $('#userId').val(userId);
        $('#password').attr('required', false).val('');
        $('#userForm').validate().resetForm();
        $('.form-control').removeClass('is-invalid is-valid');

        $.when(loadProfiles(), loadPersonal()).done(function() {
            $.ajax({
                url: "admin/usuarios/Usuarios.php",
                type: "POST",
                dataType: "json",
                data: { accion: "GetUserById", userId: userId },
                success: function (response) {
                    if (response.respuesta) {
                        let user = response.contenido;
                        $('#username').val(user.username);
                        $('#personalId').val(user.codigo_personal);
                        $('#profileCode').val(user.codigo_perfil);
                        $('#estado').val(user.estado === true ? 'true' : 'false'); // Establecer el estado
                        userModal.show();
                    } else {
                        toastr.error("Error al obtener datos del usuario: " + response.mensaje);
                    }
                },
                error: function () {
                    toastr.error("Error de conexión al obtener datos del usuario.");
                }
            });
        });
    });

    // Evento click para "Eliminar"
    $('#usersTable tbody').on('click', '.delete-user', function () {
        let userId = $(this).data('id');
        if (confirm("¿Estás seguro de que quieres eliminar este usuario?")) {
            $.ajax({
                url: "admin/usuarios/Usuarios.php",
                type: "POST",
                dataType: "json",
                data: { accion: "DeleteUser", userId: userId },
                success: function (response) {
                    if (response.respuesta) {
                        toastr.success(response.mensaje);
                        usersTable.ajax.reload();
                    } else {
                        toastr.error("Error al eliminar usuario: " + response.mensaje);
                    }
                },
                error: function () {
                    toastr.error("Error de conexión al eliminar usuario.");
                }
            });
        }
    });

    // Configuración de validación del formulario
    $('#userForm').validate({
        rules: { username: { required: true, minlength: 4, maxlength: 100 }, password: { required: () => $('#accion').val() === 'CreateUser', minlength: 4, maxlength: 20 }, personalId: { required: true }, profileCode: { required: true } },
        messages: { username: { required: "Ingrese el nombre de usuario.", minlength: "Mínimo {0} caracteres.", maxlength: "Máximo {0} caracteres." }, password: { required: "Ingrese la contraseña.", minlength: "Mínimo {0} caracteres.", maxlength: "Máximo {0} caracteres." }, personalId: { required: "Seleccione personal." }, profileCode: { required: "Seleccione un perfil." } },
        submitHandler: function (form) {
            let formData = $(form).serialize();
            $.ajax({
                url: "admin/usuarios/Usuarios.php",
                type: "POST",
                dataType: "json",
                data: formData,
                success: function (response) {
                    if (response.respuesta) {
                        toastr.success(response.mensaje);
                        userModal.hide();
                        usersTable.ajax.reload();
                    } else {
                        toastr.error("Error: " + response.mensaje);
                    }
                },
                error: function () {
                    toastr.error("Error de conexión al guardar usuario.");
                }
            });
            return false;
        }
    });

    initializeDataTable();
});