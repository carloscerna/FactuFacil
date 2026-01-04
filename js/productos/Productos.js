// js/productos/Productos.js

$(function () {
    // 1. Configuración de DataTables (Español y Responsive)
    let tablaProductos = $('#tablaProductos').DataTable({
        "ajax": {
            "url": "admin/productos/crud_productos.php",
            "type": "POST",
            "data": { accion: "listarProductos" },
            "dataSrc": "data"
        },
        "columns": [
            { "data": "id_productos", "className": "text-center fw-bold" },
            { "data": "codigo_interno", "className": "text-center" },
            { "data": "descripcion" },
            { "data": "categoria_descripcion" },
            { 
                "data": "precio_unitario", 
                "className": "text-end fw-bold text-success",
                "render": $.fn.dataTable.render.number(',', '.', 2, '$ ') 
            },
            { 
                "data": "stock_actual", 
                "className": "text-center",
                "render": function(data) {
                    let color = data <= 5 ? 'badge bg-danger' : 'badge bg-success';
                    return `<span class="${color}" style="font-size:0.9em">${data}</span>`;
                }
            },
            // --- NUEVA COLUMNA DE ESTADO ---
            { 
                "data": "activo",
                "className": "text-center",
                "render": function(data, type, row) {
                    // Postgres devuelve booleano true/false o string 't'/'f'
                    let isChecked = (data === true || data === 't' || data === 1) ? 'checked' : '';
                    // Renderizamos un Switch de Bootstrap 5
                    return `
                        <div class="form-check form-switch d-flex justify-content-center">
                            <input class="form-check-input switch-estado" type="checkbox" role="switch" 
                                data-id="${row.id_productos}" ${isChecked}>
                        </div>
                    `;
                }
            },
            // -------------------------------
            { "defaultContent": "<div class='text-center'><div class='btn-group shadow-sm'><button class='btn btn-warning btn-sm btnEditar' title='Editar'><i class='fas fa-edit'></i></button><button class='btn btn-danger btn-sm btnBorrar' title='Eliminar'><i class='fas fa-trash-alt'></i></button></div></div>" }
        ],
        "responsive": true,
        "language": { "url": "php_libs/idioma/es_es.json" },
        "order": [[2, 'asc']] // Ordenar por Descripción
    });

    // 2. Inicializar Select2 con soporte para Modales
    function initSelect2() {
        $('.select2').select2({
            theme: 'bootstrap-5', // Asegúrate de tener el tema bootstrap-5 instalado o usa 'bootstrap4'
            dropdownParent: $('#productoModal'), // CRUCIAL para que funcione el buscador dentro del modal
            width: '100%',
            placeholder: 'Seleccione una opción',
            allowClear: true
        });
    }

    // 3. Lógica de Cálculo de Precios
    function calcularPrecioUnitario() {
        const precioCosto = parseFloat($('#precio_costo').val()) || 0;
        // Obtener el % de ganancia del atributo data (asegurando que existe)
        const gananciaOption = $('#selectGanancia option:selected');
        const porcentajeGanancia = parseFloat(gananciaOption.data('porcentaje')) || 0;
        
        const tipoImpuesto = $('#tipo_impuesto').val();
        let precioConImpuesto = precioCosto;

      // 1. Calcular Costo + Impuesto
        if (tipoImpuesto === 'PORCENTAJE') {
            const porcentajeImpuesto = parseFloat($('#porcentaje_impuesto').val()) || 0;
            precioConImpuesto = precioCosto * (1 + (porcentajeImpuesto / 100));
        } else if (tipoImpuesto === 'MONETARIO') {
            const montoImpuesto = parseFloat($('#monto_impuesto').val()) || 0;
            precioConImpuesto = precioCosto + montoImpuesto;
        }

        // 2. Calcular con Ganancia
        let precioCalculado = precioConImpuesto * (1 + (porcentajeGanancia / 100));
        
        // 3. APLICAR REDONDEO COMERCIAL
        let precioFinal = redondearComercial(precioCalculado);
        
        // Mostrar resultado
        $('#precio_unitario').val(precioFinal);
    }

// --- FUNCIÓN DE REDONDEO INTELIGENTE ---
    // Redondea hacia arriba al múltiplo de 0.05 más cercano (ej: 1.47 -> 1.50, 0.145 -> 0.15)
    function redondearComercial(valor) {
        // Multiplicamos por 20, hacemos techo (ceil), y dividimos entre 20.
        // Esto fuerza saltos de 0.05.
        // Si prefieres saltos de 0.10, cambia los 20 por 10.
        return (Math.ceil(valor * 20) / 20).toFixed(2);
    }

    // Eventos de recálculo
    $('#precio_costo').on('input', calcularPrecioUnitario);
    $('#selectGanancia').on('change', calcularPrecioUnitario);
    
    // Al cambiar impuesto, traer sus detalles
    $('#selectImpuesto').on('change', function() {
        const codigo_impuesto = $(this).val();
        if (codigo_impuesto) {
            $.ajax({
                url: "admin/productos/crud_productos.php",
                type: "POST",
                dataType: "json",
                data: { accion: 'obtenerPorcentajeImpuesto', codigo_impuesto: codigo_impuesto },
                success: function(response) {
                    if (response.respuesta) {
                        const impuesto = response.impuesto;
                        $('#tipo_impuesto').val(impuesto.tipo_impuesto);
                        $('#porcentaje_impuesto').val(impuesto.porcentaje);
                        $('#monto_impuesto').val(impuesto.monto_fijo);
                        calcularPrecioUnitario();
                    }
                }
            });
        }
    });

    // 4. Cargar Catálogos (AJAX)
    function cargarCatalogos() {
        return $.ajax({
            url: "admin/productos/crud_productos.php",
            type: "POST",
            dataType: "json",
            data: { accion: "obtenerCatalogos" },
            success: function (response) {
                if (response.respuesta) {
                    const llenarSelect = (selector, datos, valueField = 'codigo', textField = 'descripcion') => {
                        let select = $(selector);
                        select.empty().append('<option value="">Seleccione...</option>');
                        $.each(datos, function (key, item) {
                            let text = item[textField];
                            // Si es cat_011 o 014 a veces conviene poner el código visualmente
                            if(selector === '#selectUnidadMedida' || selector === '#selectTipoItem') {
                                text = `${item[valueField]} - ${text}`;
                            }
                            select.append(`<option value="${item[valueField]}" ${item.porcentaje ? `data-porcentaje="${item.porcentaje}"` : ''}>${text}</option>`);
                        });
                    };

                    llenarSelect('#selectCategoria', response.catalogos.categorias);
                    llenarSelect('#selectUnidadMedida', response.catalogos.unidades_medida);
                    llenarSelect('#selectTipoItem', response.catalogos.tipos_item);
                    llenarSelect('#selectImpuesto', response.catalogos.impuestos);
                    
                    // Ganancia especial por el data-porcentaje
                    let selectGanancia = $('#selectGanancia');
                    selectGanancia.empty().append('<option value="">Seleccione...</option>');
                    $.each(response.catalogos.ganancias, function (key, item) {
                        selectGanancia.append(`<option value="${item.codigo}" data-porcentaje="${item.porcentaje}">${item.descripcion} (${item.porcentaje}%)</option>`);
                    });
                }
            }
        });
    }

    // 5. Botón Nuevo
    $('#btnNuevoProducto').on('click', function () {
        $('#productoModalLabel').html('<i class="fas fa-plus-circle me-2"></i> Crear Nuevo Producto');
        $('#productoForm')[0].reset();
        $('#id_productos').val('');
        $('#codigo_interno').val('Automático');
        $('#unidades_por_caja').val('1'); // Valor por defecto 1
        
        // Resetear Select2
        $('.select2').val('').trigger('change');
        
        // Recargar catálogos para asegurar frescura
        cargarCatalogos().then(() => {
            $('#productoModal').modal('show');
            setTimeout(() => { initSelect2(); }, 200); // Re-init por seguridad visual
        });

        $('#activo').prop('checked', true); // Por defecto activado

    });

    // 6. Botón Editar
    $('#tablaProductos tbody').on('click', '.btnEditar', function () {
        let fila = $(this).closest("tr");
        let id_productos = tablaProductos.row(fila).data().id_productos; // Obtener ID de DataTables data directo
        
        $('#productoModalLabel').html('<i class="fas fa-edit me-2"></i> Editar Producto');
        
        $.when(cargarCatalogos()).done(function() {
            $.ajax({
                url: "admin/productos/crud_productos.php",
                type: "POST",
                dataType: "json",
                data: { accion: 'obtenerProducto', id_productos: id_productos },
                success: function(response) {
                    if (response.respuesta) {
                        let producto = response.producto;
                        
                        // Llenar campos simples
                        $('#id_productos').val(producto.id_productos);
                        $('#codigo_interno').val(producto.codigo_interno);
                        $('#descripcion').val(producto.descripcion);
                        $('#precio_unitario').val(producto.precio_unitario);
                        $('#precio_costo').val(producto.precio_costo);
                        $('#stock_actual').val(producto.stock_actual);
                        $('#stock_minimo').val(producto.stock_minimo);
                        $('#codigo_barra').val(producto.codigo_barra);
                        $('#comentario').val(producto.comentario);
                        $('#fecha_vencimiento').val(producto.fecha_vencimiento);

                        // Llenar el nuevo campo
                        $('#unidades_por_caja').val(producto.unidades_por_caja || 1);
                        
                        // Campos ocultos de impuestos
                        $('#porcentaje_impuesto').val(producto.porcentaje_impuesto);
                        $('#monto_impuesto').val(producto.monto_fijo);
                        $('#tipo_impuesto').val(producto.tipo_impuesto);

                        // Llenar Selects y Disparar Change para Select2
                        $('#selectCategoria').val(producto.codigo_categoria).trigger('change');
                        $('#selectUnidadMedida').val(producto.unidad_medida).trigger('change');
                        $('#selectTipoItem').val(producto.tipo_item).trigger('change');
                        $('#selectImpuesto').val(producto.impuesto_aplicable).trigger('change');
                        $('#selectGanancia').val(producto.codigo_ganancia).trigger('change');

                        calcularPrecioUnitario(); // Recalcular visualmente
                        
                        // Manejar estado activo/inactivo del modal
                        let isActivo = (producto.activo === true || producto.activo === 't' || producto.activo === 1);
                        $('#activo').prop('checked', isActivo);
                        actualizarLabelActivo();

                        $('#productoModal').modal('show');
                        setTimeout(() => { initSelect2(); }, 200);
                    }
                }
            });
        });
    });

    // 7. Guardar (Submit)
    $('#productoForm').submit(function (e) {
        e.preventDefault();
        
        // Validación básica de HTML5
        if (!this.checkValidity()) {
            e.stopPropagation();
            $(this).addClass('was-validated');
            toastr.warning('Por favor complete los campos obligatorios.');
            return;
        }

        let formData = $(this).serialize();
        
        $.ajax({
            url: "admin/productos/crud_productos.php",
            type: "POST",
            dataType: "json",
            data: formData,
            success: function (response) {
                if (response.respuesta) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Guardado!',
                        text: response.mensaje,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    $('#productoModal').modal('hide');
                    tablaProductos.ajax.reload(null, false); // Reload sin perder paginación
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.mensaje
                    });
                }
            },
            error: function(xhr) {
                toastr.error("Error de servidor: " + xhr.responseText);
            }
        });
    });

   // 8. Eliminar Producto con Confirmación Profesional
   $('#tablaProductos tbody').on('click', '.btnBorrar', function () {
    // Obtenemos los datos de la fila de forma segura (compatible con responsive)
    let fila = $(this).closest("tr");
    // Si usas Responsive extension, la fila visual puede ser child, así que buscamos los datos en el API de Datatable
    let datos = tablaProductos.row(fila).data();
    
    // Fallback por si la fila es un child row en responsive
    if(!datos && $(this).closest('tr').hasClass('child')) {
         datos = tablaProductos.row($(this).closest('tr').prev()).data();
    }

    let id_productos = datos.id_productos;
    let nombre = datos.descripcion;

    Swal.fire({
        title: '¿Eliminar Producto?',
        html: `Se eliminará permanentemente: <br><strong>${nombre}</strong>.<br><br><span class="text-muted small">El sistema verificará primero que no tenga ventas o compras asociadas.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545', // Rojo bootstrap
        cancelButtonColor: '#6c757d',  // Gris bootstrap
        confirmButtonText: '<i class="fas fa-trash-alt"></i> Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true, // Botón de cancelar primero para seguridad
        focusCancel: true // Foco en cancelar por defecto
    }).then((result) => {
        if (result.isConfirmed) {
            // Mostrar loading mientras verifica
            Swal.fire({
                title: 'Verificando...',
                text: 'Comprobando historial del producto',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            $.ajax({
                url: "admin/productos/crud_productos.php",
                type: "POST",
                dataType: "json",
                data: { accion: 'eliminar', id_productos: id_productos },
                success: function (response) {
                    if (response.respuesta) {
                        Swal.fire({
                            title: '¡Eliminado!',
                            text: response.mensaje,
                            icon: 'success',
                            confirmButtonColor: '#0d6efd'
                        });
                        tablaProductos.ajax.reload(null, false);
                    } else {
                        // Mensaje de error (ej: Ya tiene ventas)
                        Swal.fire({
                            title: 'No se pudo eliminar',
                            text: response.mensaje,
                            icon: 'error',
                            confirmButtonColor: '#0d6efd',
                            footer: '<i class="fas fa-info-circle"></i> Para mantener la integridad contable, solo puede desactivar este producto.'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({
                        title: 'Error Técnico',
                        text: 'No se pudo conectar con el servidor.',
                        icon: 'error'
                    });
                    console.error(xhr.responseText);
                }
            });
        }
    });
    });

    // --- EVENTO: CAMBIAR ESTADO DESDE LA TABLA ---
    $('#tablaProductos tbody').on('change', '.switch-estado', function() {
        let id_productos = $(this).data('id');
        let estado = $(this).is(':checked'); // true o false
        let switchElem = $(this);

        $.ajax({
            url: "admin/productos/crud_productos.php",
            type: "POST",
            dataType: "json",
            data: { 
                accion: 'cambiarEstado', 
                id_productos: id_productos,
                estado: estado 
            },
            success: function(response) {
                if(response.respuesta) {
                    const toast = Swal.mixin({
                        toast: true, position: 'top-end', showConfirmButton: false, timer: 3000
                    });
                    toast.fire({
                        icon: estado ? 'success' : 'info',
                        title: estado ? 'Producto Activado' : 'Producto Desactivado'
                    });
                } else {
                    // Si falla, revertimos el switch visualmente
                    switchElem.prop('checked', !estado);
                    toastr.error(response.mensaje);
                }
            },
            error: function() {
                switchElem.prop('checked', !estado);
                toastr.error("Error de conexión al cambiar estado.");
            }
        });
    });

    // Efecto visual para el label dentro del modal
    $('#activo').on('change', actualizarLabelActivo);

    function actualizarLabelActivo() {
        if($('#activo').is(':checked')) {
            $('#labelActivo').html('<i class="fas fa-check-circle me-1"></i> Producto Activo').removeClass('text-secondary').addClass('text-success');
        } else {
            $('#labelActivo').html('<i class="fas fa-ban me-1"></i> Producto Inactivo').removeClass('text-success').addClass('text-secondary');
        }
    }
    // Carga inicial
    initSelect2(); // Inicializar select2 aunque estén vacíos
    cargarCatalogos();
});