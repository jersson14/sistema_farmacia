var tblPO = null;
var pedidoActivo = 0;

function initTblPedidos(){
    var fi     = ($("#po_fi").val()     || "").trim();
    var ff     = ($("#po_ff").val()     || "").trim();
    var estado = ($("#po_estado").val() || "TODOS").trim();

    if (tblPO) { tblPO.destroy(); tblPO = null; }

    tblPO = $("#tblPedidosOnline").dataTable({
        "aProcessing": true,
        "aServerSide": true,
        dom: "Bfrtip",
        buttons: window.appDataTableButtons ? window.appDataTableButtons("Pedidos Online", true) : ["copy","csv","print"],
        "ajax": {
            url: "../ajax/pedidos_online.php?op=listar",
            type: "get",
            data: function(d){
                d.fecha_inicio = fi;
                d.fecha_fin    = ff;
                d.estado       = estado;
            },
            dataType: "json",
            error: function(e){ console.log(e.responseText); }
        },
        "bDestroy": true,
        "iDisplayLength": 20,
        "order": [[0, "desc"]],
        "columnDefs": [{ "orderable": false, "targets": [7] }]
    }).DataTable();
}

function recargarPedidos(){
    initTblPedidos();
}

function cargarKPIs(){
    $.get("../ajax/pedidos_online.php?op=contadores", function(resp){
        try {
            var d = JSON.parse(resp);
            if (!d.ok) return;
            var c = d.data;
            var html = '';
            var items = [
                { key:"PENDIENTE",      label:"Pendientes",   icon:"fa-clock-o",   bg:"bg-yellow" },
                { key:"CONFIRMADO",     label:"Confirmados",  icon:"fa-check",     bg:"bg-blue"   },
                { key:"EN_PREPARACION", label:"Preparando",   icon:"fa-cogs",      bg:"bg-purple" },
                { key:"DESPACHADO",     label:"Despachados",  icon:"fa-truck",     bg:"bg-aqua"   },
                { key:"ENTREGADO",      label:"Entregados",   icon:"fa-home",      bg:"bg-green"  },
                { key:"CANCELADO",      label:"Cancelados",   icon:"fa-times",     bg:"bg-red"    }
            ];
            items.forEach(function(it){
                html += '<div class="col-xs-6 col-sm-4 col-md-2">'
                      + '<div class="info-box"><span class="info-box-icon ' + it.bg + '"><i class="fa ' + it.icon + '"></i></span>'
                      + '<div class="info-box-content"><span class="info-box-text">' + it.label + '</span>'
                      + '<span class="info-box-number">' + (c[it.key] || 0) + '</span></div></div></div>';
            });
            $("#kpiPedidos").html(html);
        } catch(e){}
    });
}

var _estadosPedidoConvertibles = ["PENDIENTE","CONFIRMADO","EN_PREPARACION","DESPACHADO"];

function verPedido(id){
    pedidoActivo = id;
    $("#modalDetalleCuerpo").html('<p class="text-center"><i class="fa fa-spin fa-spinner"></i> Cargando...</p>');
    $("#btnConvertirVenta").hide().prop("disabled", false).html('<i class="fa fa-shopping-cart"></i> Registrar Venta');
    $("#modalDetallePedido").modal("show");
    $.get("../ajax/pedidos_online.php?op=detalle&idpedido=" + id, function(resp){
        try {
            var d = JSON.parse(resp);
            if (!d.ok) { $("#modalDetalleCuerpo").html("<p>Error al cargar el pedido.</p>"); return; }
            var c = d.cabecera;
            var items = d.detalle;

            var estadoBadge = c.estado === "ENTREGADO" ? '<span class="label label-success">' + esc(c.estado) + '</span>'
                            : c.estado === "CANCELADO"  ? '<span class="label label-danger">'  + esc(c.estado) + '</span>'
                            : '<span class="label label-warning">' + esc(c.estado) + '</span>';

            var html = '<div class="row">'
                + '<div class="col-sm-6"><strong>Cliente:</strong> ' + esc(c.nombre_cliente) + ' &lt;' + esc(c.email) + '&gt;</div>'
                + '<div class="col-sm-6"><strong>Fecha:</strong> ' + esc(c.fecha_formato) + '</div>'
                + '<div class="col-sm-6"><strong>Entrega a:</strong> ' + esc(c.nombre_entrega) + ' — Tel: ' + esc(c.telefono_entrega) + '</div>'
                + '<div class="col-sm-6"><strong>Dirección:</strong> ' + esc(c.direccion_entrega) + ', ' + esc(c.distrito_entrega) + '</div>'
                + '<div class="col-sm-6"><strong>Pago:</strong> ' + esc(c.metodo_pago) + (c.referencia_yape ? ' / Ref: ' + esc(c.referencia_yape) : '') + '</div>'
                + '<div class="col-sm-6"><strong>Estado:</strong> ' + estadoBadge + '</div>'
                + (c.notas_cliente ? '<div class="col-xs-12"><strong>Notas cliente:</strong> ' + esc(c.notas_cliente) + '</div>' : '')
                + (c.notas_admin   ? '<div class="col-xs-12 text-muted"><small><strong>Nota interna:</strong> ' + esc(c.notas_admin) + '</small></div>' : '')
                + (c.comprobante_pago ? '<div class="col-xs-12"><a href="../files/tienda/' + encodeURIComponent(c.comprobante_pago) + '" target="_blank"><img src="../files/tienda/' + encodeURIComponent(c.comprobante_pago) + '" style="max-height:120px;border-radius:6px;margin-top:6px"></a></div>' : '')
                + '</div><hr>';

            html += '<table class="table table-bordered table-condensed">'
                  + '<thead style="background:#3c8dbc;color:#fff">'
                  + '<tr><th>Producto</th><th>Código</th><th style="text-align:center">Qty</th><th style="text-align:right">Precio</th><th style="text-align:right">Subtotal</th></tr>'
                  + '</thead><tbody>';
            items.forEach(function(it){
                html += '<tr>'
                      + '<td>' + esc(it.nombre_producto) + '</td>'
                      + '<td><small>' + esc(it.codigo) + '</small></td>'
                      + '<td style="text-align:center">' + parseFloat(it.cantidad).toFixed(2).replace(/\.?0+$/, '') + '</td>'
                      + '<td style="text-align:right">S/ ' + parseFloat(it.precio_unitario).toFixed(2) + '</td>'
                      + '<td style="text-align:right">S/ ' + parseFloat(it.subtotal).toFixed(2) + '</td>'
                      + '</tr>';
            });
            var costoEnvio = parseFloat(c.costo_envio || 0);
            if (costoEnvio > 0) {
                html += '<tr class="text-muted">'
                      + '<td colspan="4" style="text-align:right"><em>Costo de envío</em></td>'
                      + '<td style="text-align:right"><em>S/ ' + costoEnvio.toFixed(2) + '</em></td>'
                      + '</tr>';
            }
            html += '</tbody>'
                  + '<tfoot><tr><td colspan="4" style="text-align:right"><strong>TOTAL</strong></td>'
                  + '<td style="text-align:right"><strong>S/ ' + parseFloat(c.total).toFixed(2) + '</strong></td></tr></tfoot>'
                  + '</table>';

            $("#modalDetalleCuerpo").html(html);
            $("#nuevoEstado").val(c.estado);

            // Mostrar botón "Registrar Venta" solo si el pedido es convertible
            if (_estadosPedidoConvertibles.indexOf(c.estado) >= 0) {
                $("#btnConvertirVenta").show();
            } else {
                $("#btnConvertirVenta").hide();
            }
        } catch(e){ $("#modalDetalleCuerpo").html("<p>Error al procesar respuesta.</p>"); }
    });
}

function convertirAVenta(){
    if (!pedidoActivo) return;
    if (!confirm("¿Registrar esta venta en el sistema POS?\n\nEl pedido quedará marcado como ENTREGADO automáticamente.")) return;

    var $btn = $("#btnConvertirVenta");
    $btn.prop("disabled", true).html('<i class="fa fa-spin fa-spinner"></i> Procesando...');

    $.post("../ajax/pedidos_online.php?op=convertirAVenta", { idpedido: pedidoActivo }, function(resp){
        try {
            var d = JSON.parse(resp);
            $btn.prop("disabled", false).html('<i class="fa fa-shopping-cart"></i> Registrar Venta');
            if (d.ok) {
                var ticketUrl = "../reportes/exTicket.php?id=" + d.idventa;
                var comp = esc(d.tipo_comprobante) + " " + esc(d.serie_comprobante) + "-" + esc(d.num_comprobante);
                var alerta = '<div class="alert alert-success" style="margin-bottom:10px">'
                           + '<i class="fa fa-check-circle"></i> <strong>¡Venta registrada!</strong> '
                           + comp
                           + ' &nbsp; <a href="' + ticketUrl + '" target="_blank" class="btn btn-sm btn-warning" style="font-weight:600;color:#1a1a1a">'
                           + '<i class="fa fa-print"></i> Imprimir ticket</a>'
                           + '</div>';
                $("#modalDetalleCuerpo").prepend(alerta);
                $btn.hide();
                $("#nuevoEstado").val("ENTREGADO");
                if (typeof appNotify === "function") appNotify("success", "Venta " + comp + " generada");
                cargarKPIs();
                if (tblPO) tblPO.ajax.reload(null, false);
            } else {
                if (typeof appNotify === "function") appNotify("error", d.message || "Error al registrar venta");
            }
        } catch(e) {
            $btn.prop("disabled", false).html('<i class="fa fa-shopping-cart"></i> Registrar Venta');
            var rawPreview = String(resp || "").replace(/<[^>]+>/g, "").trim().substring(0, 120);
            if (typeof appNotify === "function") appNotify("error", rawPreview || "Error inesperado en la respuesta del servidor");
        }
    });
}

function cambiarEstado(id, estado){
    pedidoActivo = id;
    $.post("../ajax/pedidos_online.php?op=cambiarEstado", {idpedido:id, estado:estado}, function(resp){
        try {
            var d = JSON.parse(resp);
            if (d.ok) {
                if (typeof appNotify === "function") appNotify("success", "Estado actualizado");
                cargarKPIs();
                if (tblPO) tblPO.ajax.reload(null, false);
            } else {
                if (typeof appNotify === "function") appNotify("error", "No se pudo cambiar el estado");
            }
        } catch(e){}
    });
}

function aplicarCambioEstado(){
    var estado = $("#nuevoEstado").val();
    $.post("../ajax/pedidos_online.php?op=cambiarEstado", {idpedido: pedidoActivo, estado: estado}, function(resp){
        try {
            var d = JSON.parse(resp);
            if (d.ok) {
                if (typeof appNotify === "function") appNotify("success", "Estado actualizado");
                $("#modalDetallePedido").modal("hide");
                cargarKPIs();
                if (tblPO) tblPO.ajax.reload(null, false);
            }
        } catch(e){}
    });
}

function guardarConfig(){
    var form = document.getElementById("formConfigTienda");
    var fd   = new FormData(form);
    $.ajax({
        url: "../ajax/pedidos_online.php?op=guardarConfig",
        type: "POST",
        data: fd,
        contentType: false,
        processData: false,
        success: function(resp){
            try {
                var d = JSON.parse(resp);
                if (typeof appNotify === "function") appNotify(d.ok ? "success" : "error", d.message || "");
            } catch(e){}
        }
    });
}

function cargarConfigActual(){
    // Pre-cargar campos de configuración desde ajax
    // (No implementado — el admin puede ver la config directo en BD o re-guardar)
}

function esc(s){ var d=document.createElement("span"); d.textContent=String(s||""); return d.innerHTML; }

$(function(){
    cargarKPIs();
    initTblPedidos();
    setInterval(function(){ cargarKPIs(); }, 60000); // Refrescar KPIs cada minuto
});
