$(function () {
    let usersTable; // Variable para la instancia de DataTables
    let userModal = new bootstrap.Modal(document.getElementById('userModal')); // Instancia del modal de Bootstrap

    // Configuración global para Toastr
    toastr.options = {
        "closeButton": true,
        "debug": false,
        "newestOnTop": true,
        "progressBar": true,
        "positionClass": "toast-top-right",
        "preventDuplicates": false,
        "onclick": null,
        "showDuration": "300",
        "hideDuration": "1000",
        "timeOut": "5000",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut"
    };

    // Función para inicializar DataTables
    function initializeDataTable() {
        // Destruir la instancia existente de DataTables si ya existe
        if ($.fn.DataTable.isDataTable('#usersTable')) {
            $('#usersTable').DataTable().destroy();
        }
        usersTable = $('#usersTable').DataTable({
            "processing": true, // Mostrar indicador de procesamiento
            "serverSide": false, // False para procesamiento del lado del cliente (para datasets pequeños/medianos)
            "ajax": {
                "url": "php_libs/soporte/Usuarios/Usuarios.php", // Script PHP para obtener usuarios
                "type": "POST",
                "data": { accion: "ReadUsers" }, // Acción para leer usuarios
                "dataSrc": "contenido" // Propiedad del JSON que contiene los datos de la tabla
            },
            "columns": [
                { "data": "id_usuario" },
                { "data": "username" },
                { "data": "nombre_personal" },
                { "data": "nombre_perfil" },
                { "data": "nombre_institucion_usuario" }, // Nueva columna para la institución
                { "data": "acciones", "orderable": false, "searchable": false } // Columna de acciones no ordenable ni buscable
            ],
            "language": {
                "url": "php_libs/idioma/es_es.json" // Traducción al español
            },
            "responsive": true // Habilitar responsividad
        });
    }

    // Llamar a la función de inicialización de DataTables al cargar la página
    initializeDataTable();

    // Función para cargar los perfiles en el dropdown
    function loadProfiles() {
        $.ajax({
            url: "php_libs/soporte/Usuarios/Usuarios.php",
            type: "POST",
            dataType: "json",
            data: { accion: "GetProfiles" },
            success: function (response) {
                if (response.respuesta) {
                    let select = $('#profileCode');
                    select.empty().append('<option value="">Seleccione un perfil</option>'); // Opción por defecto
                    response.contenido.forEach(function (profile) {
                        select.append(`<option value="${profile.codigo}">${profile.descripcion}</option>`);
                    });
                } else {
                    toastr.error("Error al cargar perfiles: " + response.mensaje);
                }
            },
            error: function () {
                toastr.error("Error de conexión al cargar perfiles.");
            }
        });
    }

    // Función para cargar el personal en el dropdown
    function loadPersonal() {
        $.ajax({
            url: "php_libs/soporte/Usuarios/Usuarios.php",
            type: "POST",
            dataType: "json",
            data: { accion: "GetPersonal" },
            success: function (response) {
                if (response.respuesta) {
                    let select = $('#personalId');
                    select.empty().append('<option value="">Seleccione personal</option>'); // Opción por defecto
                    response.contenido.forEach(function (person) {
                        select.append(`<option value="${person.id_personal}">${person.nombres} ${person.apellidos}</option>`);
                    });
                } else {
                    toastr.error("Error al cargar personal: " + response.mensaje);
                }
            },
            error: function () {
                toastr.error("Error de conexión al cargar personal.");
            }
        });
    }

    // Nueva función para cargar las instituciones en el dropdown
    function loadInstitutions() {
        $.ajax({
            url: "php_libs/soporte/Usuarios/Usuarios.php",
            type: "POST",
            dataType: "json",
            data: { accion: "GetInstituciones" },
            success: function (response) {
                if (response.respuesta) {
                    let select = $('#schoolCode');
                    select.empty().append('<option value="">Seleccione una institución</option>'); // Opción por defecto
                    response.contenido.forEach(function (institution) {
                        select.append(`<option value="${institution.codigo_institucion}">${institution.nombre_institucion}</option>`);
                    });
                } else {
                    toastr.error("Error al cargar instituciones: " + response.mensaje);
                }
            },
            error: function () {
                toastr.error("Error de conexión al cargar instituciones.");
            }
        });
    }

    // Evento click para el botón "Nuevo Usuario"
    $('#btnAddNewUser').on('click', function () {
        $('#userModalLabel').text('Crear Nuevo Usuario'); // Título del modal
        $('#accion').val('CreateUser'); // Establecer acción a "Crear"
        $('#userId').val(''); // Limpiar ID de usuario
        $('#userForm')[0].reset(); // Resetear el formulario
        $('#password').attr('required', true); // La contraseña es obligatoria para nuevos usuarios
        $('#password').val(''); // Asegurarse de que el campo de contraseña esté vacío
        $('#userForm').validate().resetForm(); // Limpiar mensajes de validación
        $('.form-control').removeClass('is-invalid is-valid'); // Limpiar clases de validación
        userModal.show(); // Mostrar el modal
    });

    // Evento click para los botones "Editar" (delegación de eventos para elementos dinámicos)
    $('#usersTable tbody').on('click', '.edit-user', function () {
        let userId = $(this).data('id'); // Obtener el ID del usuario del atributo data-id
        $('#userModalLabel').text('Editar Usuario'); // Título del modal
        $('#accion').val('UpdateUser'); // Establecer acción a "Actualizar"
        $('#userId').val(userId); // Establecer el ID de usuario en el campo oculto
        $('#password').attr('required', false); // La contraseña no es obligatoria para actualizar
        $('#password').val(''); // Limpiar el campo de contraseña al editar
        $('#userForm').validate().resetForm(); // Limpiar mensajes de validación
        $('.form-control').removeClass('is-invalid is-valid'); // Limpiar clases de validación

        // Realizar llamada AJAX para obtener los datos del usuario a editar
        $.ajax({
            url: "php_libs/soporte/Usuarios/Usuarios.php",
            type: "POST",
            dataType: "json",
            data: { accion: "GetUserById", userId: userId },
            success: function (response) {
                if (response.respuesta) {
                    // Rellenar el formulario con los datos del usuario
                    $('#username').val(response.contenido.username);
                    $('#personalId').val(response.contenido.codigo_personal);
                    $('#profileCode').val(response.contenido.codigo_perfil);
                    $('#schoolCode').val(response.contenido.codigo_escuela); // Cargar la institución
                    userModal.show(); // Mostrar el modal
                } else {
                    toastr.error("Error al obtener datos del usuario: " + response.mensaje);
                }
            },
            error: function () {
                toastr.error("Error de conexión al obtener datos del usuario.");
            }
        });
    });

    // Evento click para los botones "Eliminar" (delegación de eventos)
    $('#usersTable tbody').on('click', '.delete-user', function () {
        let userId = $(this).data('id'); // Obtener el ID del usuario

        // Confirmación antes de eliminar
        if (confirm("¿Estás seguro de que quieres eliminar este usuario? Esta acción no se puede deshacer.")) {
            $.ajax({
                url: "php_libs/soporte/Usuarios/Usuarios.php",
                type: "POST",
                dataType: "json",
                data: { accion: "DeleteUser", userId: userId },
                success: function (response) {
                    if (response.respuesta) {
                        toastr.success(response.mensaje);
                        usersTable.ajax.reload(); // Recargar DataTables para reflejar los cambios
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

    // Configuración y manejo del formulario de creación/actualización de usuario
    $('#userForm').validate({
        rules: {
            username: {
                required: true,
                minlength: 4,
                maxlength: 100
            },
            password: {
                required: function() {
                    // La contraseña es requerida solo si es un nuevo usuario (accion = CreateUser)
                    return $('#accion').val() === 'CreateUser';
                },
                minlength: 4,
                maxlength: 20
            },
            personalId: {
                required: true
            },
            profileCode: {
                required: true
            },
            schoolCode: { // Nueva regla de validación para la institución
                required: true
            }
        },
        messages: {
            username: {
                required: "Por favor, ingrese el nombre de usuario.",
                minlength: "El usuario debe tener al menos {0} caracteres.",
                maxlength: "El usuario no puede exceder los {0} caracteres."
            },
            password: {
                required: "Por favor, ingrese la contraseña.",
                minlength: "La contraseña debe tener al menos {0} caracteres.",
                maxlength: "La contraseña no puede exceder los {0} caracteres."
            },
            personalId: {
                required: "Por favor, seleccione el personal asociado."
            },
            profileCode: {
                required: "Por favor, seleccione un perfil."
            },
            schoolCode: { // Mensaje de validación para la institución
                required: "Por favor, seleccione una institución."
            }
        },
        errorElement: "em",
        errorPlacement: function (error, element) {
            // Añadir la clase 'invalid-feedback' al elemento de error
            error.addClass("invalid-feedback");
            if (element.prop("type") === "checkbox") {
                error.insertAfter(element.next("label"));
            } else {
                error.insertAfter(element);
            }
        },
        highlight: function (element, errorClass, validClass) {
            $(element).addClass("is-invalid").removeClass("is-valid");
        },
        unhighlight: function (element, errorClass, validClass) {
            $(element).addClass("is-valid").removeClass("is-invalid");
        },
        submitHandler: function (form) {
            let formData = $(form).serialize(); // Serializar los datos del formulario
            $.ajax({
                url: "php_libs/soporte/Usuarios/Usuarios.php",
                type: "POST",
                dataType: "json",
                data: formData,
                success: function (response) {
                    if (response.respuesta) {
                        toastr.success(response.mensaje);
                        userModal.hide(); // Cerrar el modal
                        usersTable.ajax.reload(); // Recargar DataTables
                    } else {
                        toastr.error("Error: " + response.mensaje);
                    }
                },
                error: function () {
                    toastr.error("Error de conexión al guardar usuario.");
                }
            });
            return false; // Prevenir el envío de formulario por defecto
        }
    });

    // Cargar los dropdowns al inicio
    loadProfiles();
    loadPersonal();
    loadInstitutions(); // Cargar las instituciones al inicio
});

// Funciones de Toastr (pueden ser reutilizadas o definidas aquí)
function ok_msg(message) {
    toastr.success(message || "Operación Exitosa.");
}

function error_msg(message) {
    toastr.error(message || "Ha ocurrido un error.");
}

function warning_msg(message) {
    toastr.warning(message || "Advertencia.");
}
