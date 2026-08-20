// ID de la conversacion actual. Cada nueva ventana = nueva conversacion.
var currentConv = null;

$(function () {
    nuevaConversacion(false);
    cargarLista();

    $('#btnPreguntar').on('click', preguntar);
    $('#btnNuevoChat').on('click', function () { nuevaConversacion(true); });

    $('#txtPregunta').on('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            preguntar();
        }
    });
    $('#txtPregunta').focus();
});

function parseResp(r) {
    if (r && typeof r === 'object') return r;
    if (typeof r === 'string') {
        try { return JSON.parse(r); }
        catch (e) { return { ok: false, message: 'Respuesta invalida: ' + r.substring(0, 200) }; }
    }
    return { ok: false, message: 'Sin respuesta.' };
}

function ask(el) {
    $('#txtPregunta').val($(el).text().trim());
    preguntar();
}

function nuevaConversacion(recargarLista) {
    $.get('../ajax/ai_chat.php?op=nueva', function (r) {
        var resp = parseResp(r);
        if (!resp.ok) return;
        currentConv = resp.idconv;
        limpiarChat();
        if (recargarLista) cargarLista();
        marcarActiva();
    });
}

function limpiarChat() {
    var saludo = '<div class="chat-msg bot">'
               + '<strong>Nueva conversacion iniciada</strong> <i class="bi bi-robot"></i><br>'
               + 'Pregunta lo que quieras sobre tu farmacia. Recordare el contexto dentro de este chat.'
               + '</div>';
    $('#chatLog').html(saludo);
}

function preguntar() {
    var $t = $('#txtPregunta');
    var q = $t.val().trim();
    if (!q) return;

    agregarMsg(q, 'user');
    $t.val('').prop('disabled', true);
    $('#btnPreguntar').prop('disabled', true);

    var $wait = agregarMsg('<i class="fa fa-spinner fa-spin"></i> Consultando la base de datos y generando respuesta...', 'bot');

    $.post('../ajax/ai_chat.php', {
        op: 'preguntar',
        pregunta: q,
        idconv: currentConv || ''
    }, function (r) {
        var resp = parseResp(r);
        $wait.remove();
        $t.prop('disabled', false).focus();
        $('#btnPreguntar').prop('disabled', false);

        if (resp.idconv) currentConv = resp.idconv;

        if (!resp.ok) {
            var errMsg = '<strong><i class="bi bi-exclamation-triangle"></i> ' + resp.message + '</strong>';
            if (resp.sql) errMsg += '<div class="sql-toggle" onclick="$(this).next().toggle()">Ver SQL generado ▾</div><div class="sql-box">' + escaparHTML(resp.sql) + '</div>';
            agregarMsg(errMsg, 'err');
            cargarLista();
            return;
        }

        var html = escaparHTML(resp.respuesta || '').replace(/\n/g, '<br>');
        if (resp.filas && resp.filas.length > 0) {
            html += '<div class="tabla-box">' + filasATabla(resp.filas) + '</div>';
        }
        html += '<div class="sql-toggle" onclick="$(this).next().toggle()">'
              + '<i class="bi bi-code-slash"></i> Ver SQL (' + resp.num_filas + ' filas • ' + resp.tokens + ' tokens) ▾'
              + '</div>'
              + '<div class="sql-box">' + escaparHTML(resp.sql) + '</div>';

        agregarMsg(html, 'bot');
        cargarLista();

    }).fail(function (xhr) {
        $wait.remove();
        $t.prop('disabled', false).focus();
        $('#btnPreguntar').prop('disabled', false);
        agregarMsg('HTTP ' + xhr.status + ': ' + (xhr.responseText || '').substring(0, 200), 'err');
    });
}

function agregarMsg(html, tipo) {
    var $m = $('<div class="chat-msg ' + tipo + '">').html(html);
    $('#chatLog').append($m).scrollTop($('#chatLog')[0].scrollHeight);
    return $m;
}

function filasATabla(filas) {
    if (!filas.length) return '';
    var cols = Object.keys(filas[0]);
    var muestra = filas.slice(0, 20);
    var h = '<table class="table table-condensed table-bordered"><thead><tr>';
    cols.forEach(function (c) { h += '<th>' + escaparHTML(c) + '</th>'; });
    h += '</tr></thead><tbody>';
    muestra.forEach(function (r) {
        h += '<tr>';
        cols.forEach(function (c) { h += '<td>' + escaparHTML(String(r[c] ?? '')) + '</td>'; });
        h += '</tr>';
    });
    h += '</tbody></table>';
    if (filas.length > 20) h += '<small class="text-muted">Mostrando 20 de ' + filas.length + ' filas.</small>';
    return h;
}

function escaparHTML(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
        return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c];
    });
}

function cargarLista() {
    $.get('../ajax/ai_chat.php?op=listar_conversaciones', function (r) {
        var resp = parseResp(r);
        if (!resp.ok) return;
        var $c = $('#convList').empty();
        (resp.data || []).forEach(function (h) {
            var titulo = h.titulo || '(sin titulo)';
            var $it = $('<div class="item">').attr('data-idconv', h.idconv)
                .append($('<div class="q">').text(titulo))
                .append($('<div class="t">').text(h.ultima_actividad + ' • ' + h.num_mensajes + ' mensajes'))
                .append($('<span class="del" title="Eliminar">').html('<i class="fa fa-trash"></i>'));

            $it.on('click', function (e) {
                if ($(e.target).closest('.del').length) return;
                cargarConv(h.idconv);
            });
            $it.find('.del').on('click', function (e) {
                e.stopPropagation();
                if (!confirm('¿Eliminar esta conversacion? Esta accion no se puede deshacer.')) return;
                $.post('../ajax/ai_chat.php', { op: 'eliminar_conversacion', idconv: h.idconv }, function () {
                    if (currentConv === h.idconv) nuevaConversacion(false);
                    cargarLista();
                });
            });
            $c.append($it);
        });
        marcarActiva();
    });
}

function marcarActiva() {
    $('#convList .item').removeClass('active');
    $('#convList .item[data-idconv="' + currentConv + '"]').addClass('active');
}

function cargarConv(idconv) {
    $.get('../ajax/ai_chat.php', { op: 'cargar_conversacion', idconv: idconv }, function (r) {
        var resp = parseResp(r);
        if (!resp.ok) return;
        currentConv = resp.idconv;
        $('#chatLog').empty();
        (resp.data || []).forEach(function (m) {
            agregarMsg(escaparHTML(m.pregunta), 'user');
            if (m.status === 'ok') {
                var html = escaparHTML(m.respuesta || '').replace(/\n/g, '<br>');
                if (m.sql_generado) {
                    html += '<div class="sql-toggle" onclick="$(this).next().toggle()"><i class="bi bi-code-slash"></i> Ver SQL ▾</div>'
                          + '<div class="sql-box">' + escaparHTML(m.sql_generado) + '</div>';
                }
                agregarMsg(html, 'bot');
            } else {
                agregarMsg('<strong><i class="bi bi-exclamation-triangle"></i> ' + escaparHTML(m.error || 'Error') + '</strong>', 'err');
            }
        });
        marcarActiva();
        $('#txtPregunta').focus();
    });
}
