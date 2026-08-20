$(function () {
    cargar();

    $('#aiConfigForm').on('submit', function (e) {
        e.preventDefault();
        guardar();
    });

    $('#btnProbarOpenAI').on('click', probarOpenAI);
    $('#btnProbarSMTP').on('click', probarSMTP);
});

function cargar() {
    $.getJSON('../ajax/ai_config.php?op=obtener', function (r) {
        if (!r.ok) { notify(r.message, 'error'); return; }
        var d = r.data || {};
        $('#openai_model').val(d.openai_model || 'gpt-4o-mini');
        $('#openai_org').val(d.openai_org || '');
        $('#smtp_host').val(d.smtp_host || '');
        $('#smtp_port').val(d.smtp_port || 587);
        $('#smtp_secure').val(d.smtp_secure || 'tls');
        $('#smtp_user').val(d.smtp_user || '');
        $('#smtp_from').val(d.smtp_from || '');
        $('#smtp_from_name').val(d.smtp_from_name || '');

        if (d.openai_api_key_mask) {
            $('#openai_api_key').attr('placeholder', d.openai_api_key_mask + ' (deja vacio para conservar)');
        }
        if (d.smtp_pass_mask) {
            $('#smtp_pass').attr('placeholder', '(guardada — deja vacio para conservar)');
        }
    });
}

// Convierte cualquier respuesta a un objeto seguro para leer .ok y .message
function parseResp(r) {
    if (r && typeof r === 'object') return r;
    if (typeof r === 'string') {
        try { return JSON.parse(r); }
        catch (e) { return { ok: false, message: 'Respuesta invalida del servidor: ' + r.substring(0, 200) }; }
    }
    return { ok: false, message: 'Sin respuesta del servidor.' };
}

function guardar() {
    var payload = $('#aiConfigForm').serialize() + '&op=guardar';
    $.post('../ajax/ai_config.php', payload, function (r) {
        var resp = parseResp(r);
        notify(resp.message || 'Guardado', resp.ok ? 'success' : 'error');
        if (resp.ok) cargar();
    }).fail(function (xhr) {
        notify('Error HTTP ' + xhr.status + ': ' + (xhr.responseText || '').substring(0, 200), 'error');
    });
}

function probarOpenAI() {
    var $r = $('#resultProbarOpenAI').html('<i class="fa fa-spinner fa-spin"></i> Probando...');
    var payload = $('#aiConfigForm').serialize() + '&op=guardar';
    $.post('../ajax/ai_config.php', payload, function () {
        $.post('../ajax/ai_config.php?op=probar_openai', function (r) {
            var resp = parseResp(r);
            $r.html(resp.ok
                ? '<span class="text-success"><i class="fa fa-check"></i> ' + resp.message + '</span>'
                : '<span class="text-danger"><i class="fa fa-times"></i> ' + resp.message + '</span>');
        }).fail(function (xhr) {
            $r.html('<span class="text-danger">HTTP ' + xhr.status + ': ' + (xhr.responseText || '').substring(0, 300) + '</span>');
        });
    });
}

function probarSMTP() {
    var destino = $('#destino_prueba').val().trim();
    if (!destino) { notify('Ingresa un correo destino.', 'warning'); return; }
    var $r = $('#resultProbarSMTP').html('<i class="fa fa-spinner fa-spin"></i> Enviando...');
    var payload = $('#aiConfigForm').serialize() + '&op=guardar';
    $.post('../ajax/ai_config.php', payload, function () {
        $.post('../ajax/ai_config.php?op=probar_smtp', { destino: destino }, function (r) {
            var resp = parseResp(r);
            $r.html(resp.ok
                ? '<div class="alert alert-success" style="margin-top:8px;"><i class="fa fa-check"></i> ' + resp.message + '</div>'
                : '<div class="alert alert-danger" style="margin-top:8px;"><i class="fa fa-times"></i> ' + resp.message + '</div>');
        }).fail(function (xhr) {
            $r.html('<div class="alert alert-danger" style="margin-top:8px;">HTTP ' + xhr.status + ': ' + (xhr.responseText || '').substring(0, 300) + '</div>');
        });
    });
}

function notify(msg, type) {
    if (typeof appNotify === 'function') { appNotify(msg, type); return; }
    alert(msg);
}
