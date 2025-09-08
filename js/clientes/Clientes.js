// js/clientes/Clientes.js

$(function () {
    let tablaClientes = $('#tablaClientes').DataTable({
        "ajax": {
            "url": "admin/clientes/crud_clientes.php",
            "type": "POST",
            "data": { accion: "listarClientes" },
            "dataSrc": "data"
        },
        "columns": [
            { "data": "id_clientes" },
            { "data": "codigo" },
            { "data": "nombres" },
            { "data": "apellidos" },
            { "data": "nombre_empresa" },
            { "data": "telefono_celular" },
            { "defaultContent": "<div class='text-center'><div class='btn-group'><button class='btn btn-warning btn-sm btnEditar'><i class='fas fa-edit'></i></button><button class='btn btn-danger btn-sm btnBorrar'><i class='fas fa-trash-alt'></i></button></div></div>" }
        ],
        "language": { "url": "php_libs/idioma/es_es.json" }
    });

    // Aplicar máscaras de entrada
    $('#dui').mask('00000000-0');
    $('#nit').mask('0000-000000-000-0');
    $('#numero_registro').mask('000000-0');
    $('#telefono_celular').mask('0000-0000');
    $('#telefono_residencia').mask('0000-0000');

      // --- Aplicar Select2 a los menús desplegables ---
      $('#selectPais').select2({
        dropdownParent: $('#clienteModal'), // Para que funcione dentro del modal
        theme: 'bootstrap-5'
    });
    $('#selectGiro').select2({
        dropdownParent: $('#clienteModal'),
        theme: 'bootstrap-5'
    });
    
    // Funciones para cargar los catálogos
    function cargarCatalogos() {
        return $.ajax({
            url: "admin/clientes/crud_clientes.php",
            type: "POST",
            dataType: "json",
            data: { accion: "obtenerCatalogosGeograficos" },
            success: function (response) {
                if (response.respuesta) {
                    let selectDepartamento = $('#selectDepartamento');
                    selectDepartamento.empty().append('<option value="">Seleccione...</option>');
                    $.each(response.catalogos.departamentos, function (key, item) {
                        selectDepartamento.append(`<option value="${item.codigo}">${item.descripcion}</option>`);
                    });

                    let selectEstatus = $('#selectEstatus');
                    selectEstatus.empty().append('<option value="">Seleccione...</option>');
                    $.each(response.catalogos.estatus, function (key, item) {
                        selectEstatus.append(`<option value="${item.codigo}">${item.descripcion}</option>`);
                    });
                }
            }
        });
    }
    
    // Función para cargar los países
    function cargarPaises() {
        return $.ajax({
            url: "admin/clientes/crud_clientes.php",
            type: "POST",
            dataType: "json",
            data: { accion: "obtenerPaises" },
            success: function(response) {
                if (response.respuesta) {
                    let select = $('#selectPais');
                    select.empty().append('<option value="">Seleccione...</option>');
                    $.each(response.paises, function(key, item) {
                        select.append(`<option value="${item.codigo}">${item.descripcion}</option>`);
                    });
                    // Valor por defecto
                    select.val('9300');
                }
            }
        });
    }
    
    // Función para cargar los giros
    function cargarGiros() {
        return $.ajax({
            url: "admin/clientes/crud_clientes.php",
            type: "POST",
            dataType: "json",
            data: { accion: "obtenerGiros" },
            success: function(response) {
                if (response.respuesta) {
                    let select = $('#selectGiro');
                    select.empty().append('<option value="">Seleccione...</option>');
                    $.each(response.giros, function(key, item) {
                        select.append(`<option value="${item.codigo}">${item.descripcion}</option>`);
                    });
                     // Valor por defecto
                    select.val('10005');
                }
            }
        });
    }

    // Evento que se dispara al seleccionar un Departamento
    $('#selectDepartamento').on('change', function() {
        const codigo_departamento = $(this).val();
        let selectMunicipio = $('#selectMunicipio');
        let selectDistrito = $('#selectDistrito');

        selectMunicipio.empty().append('<option value="">Seleccione...</option>');
        selectDistrito.empty().append('<option value="">Seleccione...</option>');

        if (codigo_departamento) {
            $.ajax({
                url: "admin/clientes/crud_clientes.php",
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
        const codigo_municipio = $(this).val();
        const codigo_departamento = $('#selectDepartamento').val();
        let selectDistrito = $('#selectDistrito');

        selectDistrito.empty().append('<option value="">Seleccione...</option>');

        if (codigo_municipio) {
            $.ajax({
                url: "admin/clientes/crud_clientes.php",
                type: "POST",
                dataType: "json",
                data: { 
                    accion: "obtenerDistritos", 
                    codigo_municipio: codigo_municipio,
                    codigo_departamento: codigo_departamento
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

    // Evento para el botón "Nuevo Cliente"
    $('#btnNuevoCliente').on('click', function () {
        $('#clienteModalLabel').text('Crear Nuevo Cliente');
        $('#clienteForm')[0].reset();
        $('#id_clientes').val('');
        
        $('#codigo').val('Pendiente...');
        
        $('.form-control').removeClass('is-invalid is-valid');
        
        cargarCatalogos();
        cargarPaises();
        cargarGiros();

        $('#clienteModal').modal('show');
    });

    // Evento para el botón "Editar"
    $('#tablaClientes tbody').on('click', '.btnEditar', function () {
        let fila = $(this).closest("tr");
        let id_clientes = parseInt(fila.find('td:eq(0)').text());
        $('#clienteModalLabel').text('Editar Cliente');
        
        $.when(cargarCatalogos(), cargarPaises(), cargarGiros()).done(function() {
            $.ajax({
                url: "admin/clientes/crud_clientes.php",
                type: "POST",
                dataType: "json",
                data: { accion: 'obtenerCliente', id_clientes: id_clientes },
                success: function(response) {
                    if (response.respuesta) {
                        let cliente = response.cliente;
                        for (const key in cliente) {
                            if (cliente.hasOwnProperty(key) && typeof cliente[key] === 'string') {
                                cliente[key] = cliente[key].trim();
                            }
                        }

                        $('#id_clientes').val(cliente.id_clientes);
                        $('#codigo').val(cliente.codigo);
                        $('#nombres').val(cliente.nombres);
                        $('#apellidos').val(cliente.apellidos);
                        $('#cliente_empresa').val(cliente.nombre_empresa);
                        $('#giro').val(cliente.giro);
                        $('#direccion').val(cliente.direccion);
                        $('#dui').val(cliente.dui);
                        $('#nit').val(cliente.nit);
                        $('#numero_registro').val(cliente.numero_registro);
                        $('#telefono_celular').val(cliente.telefono_celular);
                        $('#telefono_residencia').val(cliente.telefono_residencia);
                        $('#correo_electronico').val(cliente.correo_electronico);

                        $('#selectEstatus').val(cliente.codigo_estatus);
                        $('#selectPais').val(cliente.codigo_pais).trigger('change');
                        $('#selectGiro').val(cliente.codigo_giro).trigger('change');
                        
                        $('#selectDepartamento').val(cliente.codigo_departamento).trigger('change');
                        
                        setTimeout(function() {
                            $('#selectMunicipio').val(cliente.codigo_municipio).trigger('change');
                        }, 500);

                        setTimeout(function() {
                            $('#selectDistrito').val(cliente.codigo_distrito);
                        }, 1000);

                        $('#clienteModal').modal('show');
                    }
                }
            });
        });
    });

    // Evento para el formulario de creación/edición
    $('#clienteForm').submit(function (e) {
        e.preventDefault();
        let formData = $(this).serialize();
        
        $.ajax({
            url: "admin/clientes/crud_clientes.php",
            type: "POST",
            dataType: "json",
            data: formData,
            success: function (response) {
                if (response.respuesta) {
                    toastr.success(response.mensaje);
                    $('#clienteModal').modal('hide');
                    tablaClientes.ajax.reload();
                } else {
                    toastr.error("Error: " + response.mensaje);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                toastr.error("Error al enviar el formulario: " + textStatus + ", " + errorThrown);
            }
        });
    });

    // Evento para el botón "Eliminar"
    $('#tablaClientes tbody').on('click', '.btnBorrar', function () {
        let fila = $(this).closest("tr");
        let id_clientes = parseInt(fila.find('td:eq(0)').text());

        if (confirm("¿Estás seguro de que quieres eliminar este registro?")) {
            $.ajax({
                url: "admin/clientes/crud_clientes.php",
                type: "POST",
                dataType: "json",
                data: { accion: 'eliminar', id_clientes: id_clientes },
                success: function (response) {
                    if (response.respuesta) {
                        toastr.success(response.mensaje);
                        tablaClientes.ajax.reload();
                    } else {
                        toastr.error(response.mensaje);
                    }
                }
            });
        }
    });

    cargarCatalogos();
});