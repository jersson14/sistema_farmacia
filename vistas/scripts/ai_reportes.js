$(function () {
    cargarReportes();
    cargarHistorial();

    $('#btnGuardarDestinatarios').on('click', guardarDestinatarios);
    $('#btnEnviarAhora').on('click', enviar);
    $('#btnPreview').on('click', preview);

    // Al abrir modal enviar, poner fecha de hoy
    $('#modalEnviar').on('show.bs.modal', function () {
        if (!$('#env_fecha').val()) {
            var d = new Date();
            var iso = d.toISOString().slice(0, 10);
            $('#env_fecha').val(iso);
        }
        $('#env_status').html('');
    });
});

function parseResp(r) {
    if (r && typeof r === 'object') return r;
    if (typeof r === 'string') {
        try { return JSON.parse(r); }
        catch (e) { return { ok: false, message: 'Respuesta invalida: ' + r.substring(0, 200) }; }
    }
    return { ok: false, message: 'Sin respuesta.' };
}

function cargarReportes() {
    $.get('../ajax/ai_reporte.php?op=listar', function (r) {
        var resp = parseResp(r);
        if (!resp.ok) return;
        var $tbody = $('#tblReportes tbody').empty();
        (resp.data || []).forEach(function (rep) {
            var dest = rep.destinatarios || '<span class="text-muted"><em>sin configurar</em></span>';
            var $tr = $('<tr>').append(
                $('<td>').html('<strong>' + rep.nombre + '</strong><br><small class="text-muted">' + rep.codigo + '</small>'),
                $('<td>').text(rep.descripcion || ''),
                $('<td>').html('<small>' + dest + '</small>'),
                $('<td>').html('<span class="label label-default">' + rep.frecuencia + '</span>'),
                $('<td>').html(
                    '<button class="btn btn-xs btn-primary" onclick="abrirEnviar(\'' + rep.codigo + '\',\'' + rep.nombre.replace(/'/g, "\\'") + '\')"><i class="fa fa-paper-plane"></i> Enviar</button> ' +
                    '<button class="btn btn-xs btn-default" onclick="abrirDestinatarios(\'' + rep.codigo + '\',\'' + (rep.destinatarios || '') + '\')"><i class="fa fa-users"></i> Destinatarios</button>'
                )
            );
            $tbody.append($tr);
        });
    });
}

function cargarHistorial() {
    $.get('../ajax/ai_reporte.php?op=historial', function (r) {
        var resp = parseResp(r);
        if (!resp.ok) return;
        var $tbody = $('#tblHistorial tbody').empty();
        (resp.data || []).forEach(function (log) {
            var badge = log.status === 'ok'
                ? '<span class="label label-success">OK</span>'
                : '<span class="label label-danger" title="' + (log.error || '') + '">ERROR</span>';
            $tbody.append($('<tr>').append(
                $('<td>').text(log.creado_en),
                $('<td>').text(log.codigo),
                $('<td>').html('<small>' + (log.destinatarios || '') + '</small>'),
                $('<td>').text(log.asunto || ''),
                $('<td>').html(badge),
                $('<td>').text((parseInt(log.tokens_prompt || 0) + parseInt(log.tokens_respuesta || 0))),
                $('<td>').html(log.status === 'ok'
                    ? '<a class="btn btn-xs btn-info" target="_blank" href="../ajax/ai_reporte.php?op=ver_log&vista=html&idlog=' + log.idlog + '"><i class="fa fa-eye"></i></a>'
                    : '')
            ));
        });
    });
}

function abrirDestinatarios(codigo, actual) {
    $('#dest_codigo').val(codigo);
    $('#dest_lista').val(actual || '');
    $('#modalDestinatarios').modal('show');
}

function guardarDestinatarios() {
    $.post('../ajax/ai_reporte.php', {
        op: 'guardar_destinatarios',
        codigo: $('#dest_codigo').val(),
        destinatarios: $('#dest_lista').val()
    }, function (r) {
        var resp = parseResp(r);
        appNotify ? appNotify(resp.message, resp.ok ? 'success' : 'error') : alert(resp.message);
        if (resp.ok) {
            $('#modalDestinatarios').modal('hide');
            cargarReportes();
        }
    });
}

function abrirEnviar(codigo, nombre) {
    $('#env_codigo').val(codigo);
    $('#env_nombre').text(nombre);
    $('#env_override').val('');
    $('#wrap_fecha').toggle(codigo === 'cierre_diario');
    $('#wrap_dias').toggle(codigo === 'vencimientos');
    $('#modalEnviar').modal('show');
}

function enviar() {
    var $btn = $('#btnEnviarAhora').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Enviando...');
    $('#env_status').html('<div class="alert alert-info"><i class="fa fa-cogs fa-spin"></i> Generando reporte con IA y enviando por correo... puede tardar 10-30 segundos.</div>');
    $.post('../ajax/ai_reporte.php', {
        op: 'enviar',
        codigo: $('#env_codigo').val(),
        fecha:  $('#env_fecha').val(),
        dias:   $('#env_dias').val(),
        destinatarios_override: $('#env_override').val()
    }, function (r) {
        var resp = parseResp(r);
        $btn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Enviar ahora');
        $('#env_status').html(resp.ok
            ? '<div class="alert alert-success"><i class="fa fa-check"></i> ' + resp.message + '</div>'
            : '<div class="alert alert-danger"><i class="fa fa-times"></i> ' + resp.message + '</div>');
        if (resp.ok) cargarHistorial();
    }).fail(function (xhr) {
        $btn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Enviar ahora');
        $('#env_status').html('<div class="alert alert-danger">HTTP ' + xhr.status + ': ' + (xhr.responseText || '').substring(0, 300) + '</div>');
    });
}

function preview() {
    var $btn = $('#btnPreview').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Generando...');
    $('#env_status').html('<div class="alert alert-info"><i class="fa fa-cogs fa-spin"></i> Generando vista previa con IA...</div>');
    $.post('../ajax/ai_reporte.php', {
        op: 'preview',
        codigo: $('#env_codigo').val(),
        fecha:  $('#env_fecha').val(),
        dias:   $('#env_dias').val()
    }, function (r) {
        var resp = parseResp(r);
        $btn.prop('disabled', false).html('<i class="fa fa-eye"></i> Vista previa');
        if (!resp.ok) {
            $('#env_status').html('<div class="alert alert-danger">' + resp.message + '</div>');
            return;
        }
        $('#env_status').html('<div class="alert alert-success">Vista previa lista (tokens: ' + resp.tokens + ')</div>');
        var iframe = document.getElementById('previewFrame');
        iframe.srcdoc = resp.html;
        $('#modalPreview').modal('show');
    }).fail(function (xhr) {
        $btn.prop('disabled', false).html('<i class="fa fa-eye"></i> Vista previa');
        $('#env_status').html('<div class="alert alert-danger">HTTP ' + xhr.status + '</div>');
    });
}
