// js/personal/Personal.js
$(function () {
    let tablaPersonal = $('#tablaPersonal').DataTable({
        "ajax": {
            "url": "admin/personal/crud_personal.php",
            "type": "POST",
            "data": { accion: "listarPersonal" },
            "dataSrc": "data"
        },
        "columns": [
            { "data": "id_personal" },
            { "data": "nombres" },
            { "data": "apellidos" },
            { "data": "dui" },
            { "data": "telefono_celular" },
            { "data": "cargo" },
            { "data": "estatus" },
            {
                "defaultContent": "<div class='text-center'><div class='btn-group'><button class='btn btn-warning btn-sm btnEditar'><i class='fas fa-edit'></i></button><button class='btn btn-danger btn-sm btnBorrar'><i class='fas fa-trash-alt'></i></button></div></div>"
            }
        ],
        "language": { "url": "php_libs/idioma/es_es.json" }
    });

    // Función para poblar todos los catálogos en el formulario
    function cargarCatalogos() {
        return $.ajax({
            url: "admin/personal/crud_personal.php",
            type: "POST",
            dataType: "json",
            data: { accion: "obtenerCatalogos" },
            success: function (response) {
                if (response.respuesta) {
                    $.each(response.catalogos, function (nombreCatalogo, datos) {
                        let select = $(`#select${nombreCatalogo.charAt(0).toUpperCase() + nombreCatalogo.slice(1)}`);
                        select.empty().append('<option value="">Seleccione...</option>');
                        $.each(datos, function (key, item) {
                            select.append(`<option value="${item.codigo}">${item.descripcion}</option>`);
                        });
                    });
                } else {
                    toastr.error("Error en la respuesta del servidor al cargar los catálogos.");
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                toastr.error("Error al cargar los catálogos. Estado: " + textStatus);
            }
        });
    }

    // Aplicar las máscaras de entrada a los campos
    $('#dui').mask('00000000-0');
    $('#nit').mask('0000-000000-000-0');
    $('#isss').mask('000000000-0');
    $('#telefono_celular').mask('0000-0000');
    
    // El campo de correo electrónico no usa máscara, se valida con tipo 'email'

    // Evento para el botón "Nuevo Registro"
    $('#btnNuevoPersonal').on('click', function () {
        $('#personalModalLabel').text('Crear Nuevo Registro');
        $('#personalForm')[0].reset();
        $('#id_personal').val('');
        cargarCatalogos();
        $('.form-control').removeClass('is-invalid is-valid');
    });
// Función para poblar el select de Departamentos (se carga al inicio)
function cargarDepartamentos() {
    // La acción para obtener los departamentos debe existir en tu crud_personal.php
    $.ajax({
        url: "admin/personal/crud_personal.php",
        type: "POST",
        dataType: "json",
        data: { accion: "obtenerDepartamentos" },
        success: function (response) {
            let select = $('#selectDepartamento');
            select.empty().append('<option value="">Seleccione...</option>');
            $.each(response.departamentos, function (key, item) {
                select.append(`<option value="${item.codigo_departamento}">${item.descripcion}</option>`);
            });
        }
    });
}

// Evento que se dispara al seleccionar un Departamento
$('#selectDepartamento').on('change', function() {
    const codigo_departamento = $(this).val();
    let selectMunicipio = $('#selectMunicipio');
    let selectDistrito = $('#selectDistrito');

    // Vaciar y resetear los select de Municipio y Distrito
    selectMunicipio.empty().append('<option value="">Seleccione...</option>');
    selectDistrito.empty().append('<option value="">Seleccione...</option>');

    if (codigo_departamento) {
        // Hacemos la llamada AJAX para obtener los municipios del departamento
        $.ajax({
            url: "admin/personal/crud_personal.php",
            type: "POST",
            dataType: "json",
            data: { accion: "obtenerMunicipios", codigo_departamento: codigo_departamento },
            success: function (response) {
                if (response.respuesta) {
                    $.each(response.municipios, function (key, item) {
                        selectMunicipio.append(`<option value="${item.codigo}">${item.descripcion}</option>`);
                    });
                }
            }
        });
    }
});
// Evento que se dispara al seleccionar un Municipio
$('#selectMunicipio').on('change', function() {
    const codigo_municipio = $("#selectMunicipio").val();
    const codigo_departamento = $('#selectDepartamento').val(); // Obtener el código del departamento seleccionado
    let selectDistrito = $('#selectDistrito');

    // Vaciar y resetear el select de Distrito
    selectDistrito.empty().append('<option value="">Seleccione...</option>');

    if (codigo_municipio) {
        // Hacemos la llamada AJAX para obtener los distritos del municipio
        $.ajax({
            url: "admin/personal/crud_personal.php",
            type: "POST",
            dataType: "json",
            data: { 
                accion: "obtenerDistritos", 
                codigo_municipio: codigo_municipio,
                codigo_departamento: codigo_departamento // ¡Enviar ambas variables!
            },
            success: function (response) {
                if (response.respuesta) {
                    $.each(response.distritos, function (key, item) {
                        selectDistrito.append(`<option value="${item.codigo}">${item.descripcion}</option>`);
                    });
                }
            }
        });
    }
});
    // Evento para el botón "Editar"
    $('#tablaPersonal tbody').on('click', '.btnEditar', function () {
        let fila = $(this).closest("tr");
        let id_personal = parseInt(fila.find('td:eq(0)').text());
        $('#personalModalLabel').text('Editar Registro');
        $('#id_personal').val(id_personal);

        cargarCatalogos().done(function(response) {
            if (response.respuesta) {
                $.ajax({
                    url: "admin/personal/crud_personal.php",
                    type: "POST",
                    dataType: "json",
                    data: { accion: 'obtenerPersonal', id_personal: id_personal },
                    success: function(response) {
                        if (response.respuesta) {
                            let personal = response.personal;

                            // Recorrer el objeto 'personal' y recortar los espacios en blanco
                                    for (const key in personal) {
                                        if (personal.hasOwnProperty(key) && typeof personal[key] === 'string') {
                                            personal[key] = personal[key].trim();
                                        }
                                    }
                            // Lógica para rellenar los selects geográficos
                            $('#selectDepartamento').val(personal.codigo_departamento);
                            
                            // Limpiar y llenar el select de municipios
                                $('#selectMunicipio').empty().append('<option value="">Seleccione...</option>');
                                $.each(response_municipios.municipios, function(key, item) {
                                    $('#selectMunicipio').append(`<option value="${item.codigo}">${item.descripcion}</option>`);
                                });
                                // 4. Seleccionar el Municipio
                                $('#selectMunicipio').val(personal.codigo_municipio);

                                // 5. Cargar los Distritos del Municipio seleccionado
                                $.ajax({
                                    url: "admin/personal/crud_personal.php",
                                    type: "POST",
                                    dataType: "json",
                                    data: { accion: 'obtenerDistritos', codigo_municipio: personal.codigo_municipio },
                                    success: function(response_distritos) {
                                        if (response_distritos.respuesta) {
                                            // Limpiar y llenar el select de distritos
                                            $('#selectDistrito').empty().append('<option value="">Seleccione...</option>');
                                            $.each(response_distritos.distritos, function(key, item) {
                                                $('#selectDistrito').append(`<option value="${item.codigo}">${item.descripcion}</option>`);
                                            });
                                            // 6. Seleccionar el Distrito
                                            $('#selectDistrito').val(personal.codigo_distrito);

                                            // Finalmente, mostrar el modal
                                            $('#personalModal').modal('show');
                                        } else {
                                            toastr.error('Error al cargar los distritos.');
                                        }
                                    }
                                });

                            $('#nombres').val(personal.nombres);
                            $('#apellidos').val(personal.apellidos);
                            $('#dui').val(personal.dui);
                            $('#nit').val(personal.nit);
                            $('#isss').val(personal.isss);
                            $('#fecha_nacimiento').val(personal.fecha_nacimiento);
                            $('#fecha_ingreso').val(personal.fecha_ingreso);
                            $('#salario').val(personal.salario);
                            $('#pago_diario').val(personal.pago_diario);
                            $('#telefono_celular').val(personal.telefono_celular);
                            $('#correo_electronico').val(personal.correo_electronico);
                            $('#direccion').val(personal.direccion);
                            
                            // Poblar los SELECTs
                            $('#selectGenero').val(personal.codigo_genero);
                            $('#selectEstadocivil').val(personal.codigo_estado_civil);
                            $('#selectEstatus').val(personal.codigo_estatus);
                            $('#selectCargo').val(personal.codigo_cargo);
                            // ... (resto de select) ...
                            
                            $('#personalModal').modal('show');
                        } else {
                            toastr.error(response.mensaje);
                        }
                    }
                });
            }
        });
    });

    // Evento para el formulario de creación/edición
    $('#personalForm').submit(function (e) {
        e.preventDefault();
        let formData = $(this).serialize();
    
        $.ajax({
            url: "admin/personal/crud_personal.php",
            type: "POST",
            dataType: "json",
            data: formData,
            success: function (response) {
                if (response.respuesta) {
                    toastr.success(response.mensaje);
                    $('#personalModal').modal('hide');
                    tablaPersonal.ajax.reload();
                } else {
                    toastr.error("Error: " + response.mensaje);
                }
            }
        });
    });
    
    // Evento para el botón "Eliminar"
    $('#tablaPersonal tbody').on('click', '.btnBorrar', function () {
        let fila = $(this).closest("tr");
        let id_personal = parseInt(fila.find('td:eq(0)').text());

        if (confirm("¿Estás seguro de que quieres eliminar este registro?")) {
            $.ajax({
                url: "admin/personal/crud_personal.php",
                type: "POST",
                dataType: "json",
                data: { accion: 'eliminar', id_personal: id_personal },
                success: function (response) {
                    if (response.respuesta) {
                        toastr.success(response.mensaje);
                        tablaPersonal.ajax.reload();
                    } else {
                        toastr.error(response.mensaje);
                    }
                }
            });
        }
    });

    cargarCatalogos();
});