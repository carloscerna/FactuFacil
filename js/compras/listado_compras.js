// js/compras/listado_compras.js

$(function () {
    let tablaListadoCompras = $('#tablaListadoCompras').DataTable({
        "ajax": {
            "url": "admin/compras/crud_compras.php",
            "type": "POST",
            "data": { accion: "listarCompras" },
            "dataSrc": "data"
        },
        "columns": [
            { "data": "id_compra" },
            { "data": "numero_documento" },
            { "data": "proveedor_nombre" },
            { "data": "fecha_emision" },
            { "data": "total_compra", "render": $.fn.dataTable.render.number(',', '.', 2, '$') },
            { "defaultContent": "<div class='text-center'><div class='btn-group'><button class='btn btn-warning btn-sm btnEditar'><i class='fas fa-edit'></i> Editar</button></div></div>" }
        ],
        "language": { "url": "php_libs/idioma/es_es.json" }
    });

    // Evento para el botón de Editar
    $('#tablaListadoCompras tbody').on('click', '.btnEditar', function () {
        let fila = $(this).closest("tr");
        let id_compra = parseInt(fila.find('td:eq(0)').text());
        // El enlace ahora apunta al archivo PHP
        window.location.href = `EditarCompra.php?id_compra=${id_compra}`;
    });
});