var tabla;
var filtroActivo = '';

function initTabla() {
    tabla = $('#tblLotes').dataTable({
        "aProcessing": true,
        "aServerSide": true,
        dom: 'Bfrtip',
        buttons: window.appDataTableButtons('Lotes por Producto', true),
        "ajax": {
            url: '../ajax/lote.php?op=listarTodos',
            type: 'get',
            dataType: 'json',
            error: function(e) { console.log(e.responseText); }
        },
        "bDestroy": true,
        "iDisplayLength": 25,
        "order": [[1, 'asc'], [6, 'asc']],
        "columnDefs": [
            { "targets": [7, 8], "className": "text-center" },
            { "targets": [10], "visible": false, "searchable": false }
        ]
    }).DataTable();

    // Filtro por texto del badge de estado
    $.fn.dataTable.ext.search.push(function(settings, data) {
        if (settings.nTable.id !== 'tblLotes') return true;
        if (!filtroActivo) return true;
        return data[9].indexOf(filtroActivo) !== -1;
    });
}

$(document).ready(function() {
    initTabla();

    $(document).on('click', '.btn-filtro', function() {
        $('.btn-filtro').removeClass('active');
        $(this).addClass('active');
        filtroActivo = $(this).data('filtro');
        tabla.draw();
    });
});
