var tblVencidos = null;
var tblProximos = null;

function cargarContadores() {
    $.get("../ajax/lote.php?op=contadores", function(resp) {
        var r = {};
        try { r = JSON.parse(resp); } catch(e) { return; }
        var v   = r.vencidos  || 0;
        var v30 = r.vence_30  || 0;
        var v60 = r.vence_60  || 0;
        var v90 = r.vence_90  || 0;

        var html = '';
        html += kpiBox((v  > 0 ? 'bg-red'    : 'bg-green'), 'fa-times-circle',    'Vencidos',          v   + ' lote(s)');
        html += kpiBox((v30> 0 ? 'bg-red'    : 'bg-green'), 'fa-exclamation',     '≤ 30 días',         v30 + ' lote(s)');
        html += kpiBox((v60> 0 ? 'bg-yellow'  : 'bg-green'), 'fa-clock-o',         '≤ 60 días',         v60 + ' lote(s)');
        html += kpiBox('bg-aqua',                             'fa-calendar',        '≤ 90 días',         v90 + ' lote(s)');
        $("#contadoresVenc").html(html);
    }).fail(function() {
        $("#contadoresVenc").html('<div class="col-xs-12"><div class="alert alert-warning">No se pudo cargar los contadores. Verifica que la migración de lotes haya sido ejecutada.</div></div>');
    });
}

function kpiBox(bgClass, icon, label, value) {
    return '<div class="col-md-3 col-sm-6 col-xs-12">' +
        '<div class="info-box">' +
        '<span class="info-box-icon ' + bgClass + '"><i class="fa ' + icon + '"></i></span>' +
        '<div class="info-box-content">' +
        '<span class="info-box-text">' + label + '</span>' +
        '<span class="info-box-number">' + value + '</span>' +
        '</div></div></div>';
}

function initTblVencidos() {
    tblVencidos = $('#tblVencidos').dataTable({
        aProcessing: true,
        aServerSide: true,
        dom: 'Bfrtip',
        buttons: window.appDataTableButtons ? window.appDataTableButtons('Lotes Vencidos', true) : ['copy','csv','excel','print'],
        ajax: {
            url: '../ajax/lote.php?op=vencidos',
            type: 'get',
            dataType: 'json',
            error: function(e) { console.log(e.responseText); }
        },
        bDestroy: true,
        iDisplayLength: 20,
        order: [[4, 'desc']]
    }).DataTable();
}

function initTblProximos(dias) {
    if (tblProximos) {
        tblProximos.destroy();
        $('#tblProximos').empty();
        tblProximos = null;
    }
    tblProximos = $('#tblProximos').dataTable({
        aProcessing: true,
        aServerSide: true,
        dom: 'Bfrtip',
        buttons: window.appDataTableButtons ? window.appDataTableButtons('Proximos a Vencer', true) : ['copy','csv','excel','print'],
        ajax: {
            url: '../ajax/lote.php?op=proximosVencer&dias=' + (dias || 30),
            type: 'get',
            dataType: 'json',
            error: function(e) { console.log(e.responseText); }
        },
        bDestroy: true,
        iDisplayLength: 20,
        order: [[4, 'asc']]
    }).DataTable();
}

$(function() {
    cargarContadores();
    initTblVencidos();

    $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
        if ($(e.target).attr('href') === '#tabProximos' && !tblProximos) {
            initTblProximos(parseInt($("#selDiasVenc").val(), 10) || 30);
        }
    });

    $("#selDiasVenc").on('change', function() {
        initTblProximos(parseInt($(this).val(), 10) || 30);
    });
});
