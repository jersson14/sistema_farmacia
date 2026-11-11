var tblVencidos = null;
var tblProximos = null;

var diasAlerta = 180;

function etiquetaDias(d) {
    if (d === 365) return '365 días (1 año)';
    if (d % 30 === 0 && d >= 60) return d + ' días (' + (d / 30) + ' meses)';
    return d + ' días';
}

// Asegura que el valor exista en el selector (p. ej. 45 días configurado)
function asegurarOpcionDias(d) {
    var $sel = $("#selDiasVenc");
    if (!$sel.find('option[value="' + d + '"]').length) {
        $sel.append('<option value="' + d + '">' + etiquetaDias(d) + '</option>');
        var opts = $sel.find('option').get().sort(function(a, b) { return a.value - b.value; });
        $sel.empty().append(opts);
    }
}

function pintarConfigAlerta(puedeConfig) {
    var html;
    if (puedeConfig) {
        html = '<form id="frmDiasAlerta" class="form-inline" style="display:inline-block;">' +
            '<label for="txtDiasAlerta" style="margin-right:6px;"><i class="fa fa-bell-o"></i> Avisar con</label>' +
            '<input type="number" id="txtDiasAlerta" class="form-control" min="1" max="730" style="width:90px;" value="' + diasAlerta + '">' +
            '<span style="margin:0 6px;">días de anticipación</span>' +
            '<button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-save"></i> Guardar</button>' +
            '</form>';
    } else {
        html = '<span class="text-muted"><i class="fa fa-bell-o"></i> Se avisa con <strong>' + etiquetaDias(diasAlerta) + '</strong> de anticipación</span>';
    }
    $("#cfgAlertaVenc").html(html);
}

function cargarContadores(callback) {
    $.get("../ajax/lote.php?op=contadores", function(resp) {
        var r = {};
        try { r = (typeof resp === 'object') ? resp : JSON.parse(resp); } catch(e) { return; }
        var v   = r.vencidos  || 0;
        var v30 = r.vence_30  || 0;
        var v60 = r.vence_60  || 0;
        var v90 = r.vence_90  || 0;
        var vA  = r.vence_alerta || 0;
        diasAlerta = parseInt(r.dias_alerta, 10) || 180;

        var html = '';
        html += kpiBox((v  > 0 ? 'bg-red' : 'bg-green'), 'fa-times-circle', 'Vencidos',  v   + ' lote(s)');
        html += kpiBox((v30> 0 ? 'bg-red' : 'bg-green'), 'fa-exclamation', '≤ 30 días', v30 + ' lote(s)');
        if (diasAlerta > 90) {
            html += kpiBox((v90> 0 ? 'bg-yellow' : 'bg-green'), 'fa-clock-o', '≤ 90 días', v90 + ' lote(s)');
            html += kpiBox('bg-aqua', 'fa-calendar', '≤ ' + etiquetaDias(diasAlerta), vA + ' lote(s)');
        } else {
            html += kpiBox((v60> 0 ? 'bg-yellow' : 'bg-green'), 'fa-clock-o', '≤ 60 días', v60 + ' lote(s)');
            html += kpiBox('bg-aqua', 'fa-calendar', '≤ 90 días', v90 + ' lote(s)');
        }
        $("#contadoresVenc").html(html);
        pintarConfigAlerta(!!r.puede_config);
        if (callback) callback();
    }).fail(function() {
        $("#contadoresVenc").html('<div class="col-xs-12"><div class="alert alert-warning">No se pudo cargar los contadores. Verifica que la migración de lotes haya sido ejecutada.</div></div>');
    });
}

function seleccionarDiasAlerta() {
    asegurarOpcionDias(diasAlerta);
    $("#selDiasVenc").val(String(diasAlerta));
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
    if (tblVencidos) {
        tblVencidos.destroy();
        tblVencidos = null;
    }
    tblVencidos = $('#tblVencidos').DataTable({
        processing: true,
        dom: 'Bfrtip',
        buttons: window.appDataTableButtons ? window.appDataTableButtons('Lotes Vencidos', true) : ['copy','csv','excel','print'],
        ajax: {
            url: '../ajax/lote.php?op=vencidos',
            type: 'get',
            dataSrc: 'aaData',
            error: function(e) { console.log(e.responseText); }
        },
        iDisplayLength: 20,
        order: [[5, 'desc']]
    });
}

function initTblProximos(dias) {
    if (tblProximos) {
        tblProximos.destroy();
        tblProximos = null;
    }
    tblProximos = $('#tblProximos').DataTable({
        processing: true,
        dom: 'Bfrtip',
        buttons: window.appDataTableButtons ? window.appDataTableButtons('Proximos a Vencer', true) : ['copy','csv','excel','print'],
        ajax: {
            url: '../ajax/lote.php?op=proximosVencer&dias=' + (dias || 30),
            type: 'get',
            dataSrc: 'aaData',
            error: function(e) { console.log(e.responseText); }
        },
        iDisplayLength: 20,
        order: [[5, 'asc']]
    });
}

$(function() {
    // El selector arranca en los días de aviso configurados
    cargarContadores(seleccionarDiasAlerta);
    initTblVencidos();

    $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
        if ($(e.target).attr('href') === '#tabProximos' && !tblProximos) {
            initTblProximos(parseInt($("#selDiasVenc").val(), 10) || diasAlerta);
        }
    });

    $("#selDiasVenc").on('change', function() {
        initTblProximos(parseInt($(this).val(), 10) || diasAlerta);
    });

    $(document).on('submit', '#frmDiasAlerta', function(e) {
        e.preventDefault();
        var dias = parseInt($("#txtDiasAlerta").val(), 10);
        if (!dias || dias < 1 || dias > 730) {
            if (typeof appNotify === 'function') appNotify('error', 'Ingresa una cantidad de días entre 1 y 730.');
            return;
        }
        $.post("../ajax/lote.php?op=guardarDiasAlerta", { dias: dias }, function(r) {
            if (typeof r !== 'object') {
                try { r = JSON.parse(r); } catch(err) { r = { ok: false, message: 'No se pudo guardar el ajuste.' }; }
            }
            if (typeof appNotify === 'function') appNotify(r.ok ? 'success' : 'error', r.message);
            if (r.ok) {
                cargarContadores(function() {
                    seleccionarDiasAlerta();
                    if (tblProximos) initTblProximos(diasAlerta);
                });
                if (typeof _vencPoll === 'function') _vencPoll();
            }
        });
    });
});
