// js/proveedores/Proveedores.js

$(function () {
    let tablaProveedores = $('#tablaProveedores').DataTable({
        "ajax": {
            "url": "admin/proveedores/crud_proveedores.php",
            "type": "POST",
            "data": { accion: "listarProveedores" },
            "dataSrc": "data"
        },
        "columns": [
            { "data": "id_proveedores" },
            { "data": "codigo" },
            { "data": "nombres" },
            { "data": "apellidos" },
            { "data": "nombre_empresa" },
            { "data": "telefono_celular" },
            { "defaultContent": "<div class='text-center'><div class='btn-group'><button class='btn btn-warning btn-sm btnEditar'><i class='fas fa-edit'></i></button><button class='btn btn-danger btn-sm btnBorrar'><i class='fas fa-trash-alt'></i></button></div></div>" }
        ],
        "language": { "url": "php_libs/idioma/es_es.json" }
    });

    // Apply masks to input fields
    $('#dui').mask('00000000-0');
    $('#nit').mask('0000-000000-000-0');
    $('#numero_registro').mask('000000-0');
    $('#telefono_celular').mask('0000-0000');
    $('#telefono_residencia').mask('0000-0000');

    // --- Select2 Initialization ---
    $('#selectPais').select2({
        dropdownParent: $('#proveedorModal'),
        theme: 'bootstrap-5'
    });
    $('#selectGiro').select2({
        dropdownParent: $('#proveedorModal'),
        theme: 'bootstrap-5'
    });

    function cargarCatalogos() {
        return $.ajax({
            url: "admin/proveedores/crud_proveedores.php",
            type: "POST",
            dataType: "json",
            data: { accion: "obtenerCatalogos" },
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

                    let selectPais = $('#selectPais');
                    selectPais.empty().append('<option value="">Seleccione...</option>');
                    $.each(response.catalogos.paises, function (key, item) {
                        selectPais.append(`<option value="${item.codigo}">${item.descripcion}</option>`);
                    });
                    selectPais.val('10005').trigger('change');

                    let selectGiro = $('#selectGiro');
                    selectGiro.empty().append('<option value="">Seleccione...</option>');
                    $.each(response.catalogos.giros, function (key, item) {
                        selectGiro.append(`<option value="${item.codigo}">${item.descripcion}</option>`);
                    });
                    selectGiro.val('9300').trigger('change');
                }
            }
        });
    }

    $('#selectDepartamento').on('change', function() {
        const codigo_departamento = $(this).val();
        let selectMunicipio = $('#selectMunicipio');
        let selectDistrito = $('#selectDistrito');

        selectMunicipio.empty().append('<option value="">Seleccione...</option>');
        selectDistrito.empty().append('<option value="">Seleccione...</option>');

        if (codigo_departamento) {
            $.ajax({
                url: "admin/proveedores/crud_proveedores.php",
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

    $('#selectMunicipio').on('change', function() {
        const codigo_municipio = $(this).val();
        const codigo_departamento = $('#selectDepartamento').val();
        let selectDistrito = $('#selectDistrito');

        selectDistrito.empty().append('<option value="">Seleccione...</option>');

        if (codigo_municipio) {
            $.ajax({
                url: "admin/proveedores/crud_proveedores.php",
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

    $('#btnNuevoProveedor').on('click', function () {
        $('#proveedorModalLabel').text('Crear Nuevo Proveedor');
        $('#proveedorForm')[0].reset();
        $('#id_proveedores').val('');
        $('#codigo').val('Pendiente...');
        $('.form-control').removeClass('is-invalid is-valid');
        
        cargarCatalogos();
        
        $('#proveedorModal').modal('show');
    });

    $('#tablaProveedores tbody').on('click', '.btnEditar', function () {
        let fila = $(this).closest("tr");
        let id_proveedores = parseInt(fila.find('td:eq(0)').text());
        $('#proveedorModalLabel').text('Editar Proveedor');
        
        cargarCatalogos().done(function() {
            $.ajax({
                url: "admin/proveedores/crud_proveedores.php",
                type: "POST",
                dataType: "json",
                data: { accion: 'obtenerProveedor', id_proveedores: id_proveedores },
                success: function(response) {
                    if (response.respuesta) {
                        let proveedor = response.proveedor;
                        for (const key in proveedor) {
                            if (proveedor.hasOwnProperty(key) && typeof proveedor[key] === 'string') {
                                proveedor[key] = proveedor[key].trim();
                            }
                        }

                        $('#id_proveedores').val(proveedor.id_proveedores);
                        $('#codigo').val(proveedor.codigo);
                        $('#nombres').val(proveedor.nombres);
                        $('#apellidos').val(proveedor.apellidos);
                        $('#nombre_empresa').val(proveedor.nombre_empresa);
                        $('#giro').val(proveedor.giro);
                        $('#direccion').val(proveedor.direccion);
                        $('#dui').val(proveedor.dui);
                        $('#nit').val(proveedor.nit);
                        $('#numero_registro').val(proveedor.numero_registro);
                        $('#telefono_celular').val(proveedor.telefono_celular);
                        $('#telefono_residencia').val(proveedor.telefono_residencia);
                        $('#correo_electronico').val(proveedor.correo_electronico);

                        $('#selectEstatus').val(proveedor.codigo_estatus).trigger('change');
                        $('#selectPais').val(proveedor.codigo_pais).trigger('change');
                        $('#selectGiro').val(proveedor.codigo_giro).trigger('change');

                        $('#selectDepartamento').val(proveedor.codigo_departamento).trigger('change');
                        
                        setTimeout(function() {
                            $('#selectMunicipio').val(proveedor.codigo_municipio).trigger('change');
                        }, 500);

                        setTimeout(function() {
                            $('#selectDistrito').val(proveedor.codigo_distrito);
                        }, 1000);

                        $('#proveedorModal').modal('show');
                    }
                }
            });
        });
    });

    $('#proveedorForm').submit(function (e) {
        e.preventDefault();
        let formData = $(this).serialize();
        
        $.ajax({
            url: "admin/proveedores/crud_proveedores.php",
            type: "POST",
            dataType: "json",
            data: formData,
            success: function (response) {
                if (response.respuesta) {
                    toastr.success(response.mensaje);
                    $('#proveedorModal').modal('hide');
                    tablaProveedores.ajax.reload();
                } else {
                    toastr.error("Error: " + response.mensaje);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                toastr.error("Error al enviar el formulario: " + textStatus + ", " + errorThrown);
            }
        });
    });

    $('#tablaProveedores tbody').on('click', '.btnBorrar', function () {
        let fila = $(this).closest("tr");
        let id_proveedores = parseInt(fila.find('td:eq(0)').text());

        if (confirm("¿Estás seguro de que quieres eliminar este registro?")) {
            $.ajax({
                url: "admin/proveedores/crud_proveedores.php",
                type: "POST",
                dataType: "json",
                data: { accion: 'eliminar', id_proveedores: id_proveedores },
                success: function (response) {
                    if (response.respuesta) {
                        toastr.success(response.mensaje);
                        tablaProveedores.ajax.reload();
                    } else {
                        toastr.error(response.mensaje);
                    }
                }
            });
        }
    });

    cargarCatalogos();
});