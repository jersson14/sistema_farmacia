var dtAlertaStock;
var dtSinMov;
var dtTop;
var dtUtil;
var dtSug;
var dtKardex;
var loadedTabs = {
  alertas: false,
  utilidad: false,
  sugerencias: false
};

function notifyPro(type, msg) {
  if (typeof appNotify === "function") {
    appNotify(type, msg);
  } else {
    alert(msg);
  }
}

function hoyISO() {
  var d = new Date();
  var y = d.getFullYear();
  var m = ("0" + (d.getMonth() + 1)).slice(-2);
  var da = ("0" + d.getDate()).slice(-2);
  return y + "-" + m + "-" + da;
}

function haceDiasISO(n) {
  var d = new Date();
  d.setDate(d.getDate() - n);
  var y = d.getFullYear();
  var m = ("0" + (d.getMonth() + 1)).slice(-2);
  var da = ("0" + d.getDate()).slice(-2);
  return y + "-" + m + "-" + da;
}

function cargarSelectArticulos() {
  $.post("../ajax/procenter.php?op=selectArticulo", function (r) {
    $("#kardex_articulo").html(r);
    $('#kardex_articulo').selectpicker('refresh');
  });
}

function escPro(s) {
  return String(s === null || typeof s === "undefined" ? "" : s)
    .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}

function moneyPro(v) {
  var n = parseFloat(v || 0);
  if (!isFinite(n)) n = 0;
  return window.appMoney ? window.appMoney(n, 2) : ("S/ " + n.toFixed(2));
}

function fechaCortaPro(iso) {
  if (!iso) return "";
  var p = iso.split("-");
  return p.length === 3 ? (p[2] + "/" + p[1] + "/" + p[0]) : iso;
}

function infoBoxPro(color, icono, titulo, valor, detalle) {
  return "<div class='col-md-3 col-sm-6 col-xs-12'>" +
    "<div class='info-box' style='margin-bottom:10px;min-height:78px;'>" +
    "<span class='info-box-icon " + color + "' style='height:78px;line-height:78px;'><i class='fa " + icono + "'></i></span>" +
    "<div class='info-box-content'>" +
    "<span class='info-box-text'>" + titulo + "</span>" +
    "<span class='info-box-number'>" + valor + "</span>" +
    (detalle ? "<span class='progress-description' style='font-size:12px;'>" + detalle + "</span>" : "") +
    "</div></div></div>";
}

function construirResumenKardex(data) {
  var unidad = escPro(data.unidad || "und");
  var rango = (data.desde || data.hasta)
    ? (fechaCortaPro(data.desde) || "inicio") + " al " + (fechaCortaPro(data.hasta) || "hoy")
    : "todo el historial";
  var html = "";

  html += "<div class='row' style='margin-bottom:4px;'>" +
    "<div class='col-md-12'>" +
    "<div style='display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:10px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;margin-bottom:10px;'>" +
    "<i class='fa fa-medkit' style='font-size:26px;color:#0284c7;'></i>" +
    "<div style='flex:1;min-width:200px;'>" +
    "<div style='font-size:17px;font-weight:700;color:#0f172a;'>" + escPro(data.articulo) + "</div>" +
    "<div class='text-muted' style='font-size:12px;'>Código: <strong>" + escPro(data.codigo || "-") + "</strong> &nbsp;|&nbsp; Unidad: <strong>" + unidad + "</strong>" +
    " &nbsp;|&nbsp; Precio de venta actual: <strong>" + moneyPro(data.precio_venta) + "</strong> &nbsp;|&nbsp; Periodo: <strong>" + escPro(rango) + "</strong></div>" +
    "</div>" +
    (data.bajo_minimo ? "<span class='label label-danger' style='font-size:12px;'><i class='fa fa-exclamation-triangle'></i> Stock bajo mínimo</span>" : "<span class='label label-success' style='font-size:12px;'><i class='fa fa-check'></i> Stock OK</span>") +
    "</div></div></div>";

  html += "<div class='row'>" +
    infoBoxPro(data.bajo_minimo ? "bg-red" : "bg-aqua", "fa-cubes", "Stock actual", escPro(data.stock_actual) + " " + unidad, "Mínimo: " + escPro(data.stock_minimo) + " " + unidad) +
    infoBoxPro("bg-yellow", "fa-flag-o", "Saldo al inicio del periodo", escPro(data.saldo_inicial) + " " + unidad, "Antes del " + (fechaCortaPro(data.desde) || "primer movimiento")) +
    infoBoxPro("bg-green", "fa-arrow-circle-down", "Entradas (compras)", escPro(data.entradas_rango) + " " + unidad, (data.n_compras || 0) + " compra(s) en el periodo") +
    infoBoxPro("bg-purple", "fa-arrow-circle-up", "Salidas (ventas)", escPro(data.salidas_rango) + " " + unidad, (data.n_ventas || 0) + " venta(s) en el periodo") +
    "</div>";

  var uc = data.ultima_compra;
  if (uc) {
    html += "<div class='alert' style='background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:10px 14px;margin-bottom:12px;'>" +
      "<i class='fa fa-truck'></i> <strong>Última compra en el periodo:</strong> " + escPro(uc.fecha) +
      " &nbsp;·&nbsp; Proveedor: <strong>" + escPro(uc.proveedor) + "</strong>" +
      " &nbsp;·&nbsp; Documento: <a href='../reportes/exIngreso.php?id=" + uc.iddoc + "' target='_blank' title='Ver PDF de la compra'>" + escPro(uc.documento) + "</a>" +
      " &nbsp;·&nbsp; Cantidad: <strong>" + escPro(uc.cantidad) + " " + unidad + "</strong>" +
      " &nbsp;·&nbsp; Costo unit.: <strong>" + moneyPro(uc.costo) + "</strong>" +
      (uc.lote ? " &nbsp;·&nbsp; Lote: <strong>" + escPro(uc.lote) + "</strong>" : "") +
      (uc.vencimiento ? " &nbsp;·&nbsp; Vence: <strong>" + escPro(uc.vencimiento) + "</strong>" : "") +
      "</div>";
  } else {
    html += "<div class='alert alert-warning' style='padding:8px 14px;margin-bottom:12px;'><i class='fa fa-info-circle'></i> No hay compras de este producto en el periodo seleccionado. Amplía el rango de fechas para ver a qué proveedor se le compró.</div>";
  }
  $("#kardexResumen").html(html);
}

function construirKardex(data) {
  if (dtKardex) {
    dtKardex.destroy();
  }
  var body = "";
  for (var i = 0; i < data.movimientos.length; i++) {
    var r = data.movimientos[i];
    var esCompra = (r.tipo === "INGRESO");
    var tipoHtml = esCompra
      ? "<span class='label bg-aqua'><i class='fa fa-arrow-down'></i> COMPRA</span>"
      : "<span class='label bg-green'><i class='fa fa-arrow-up'></i> VENTA</span>";
    var docHtml = esCompra
      ? "<a href='../reportes/exIngreso.php?id=" + r.iddoc + "' target='_blank' title='Ver PDF de la compra'>" + escPro(r.documento) + "</a>"
      : escPro(r.documento);
    var terceroHtml = esCompra
      ? "<i class='fa fa-truck text-primary'></i> <strong>" + escPro(r.tercero) + "</strong>"
      : "<i class='fa fa-user text-muted'></i> " + escPro(r.tercero);
    body += "<tr>" +
      "<td style='white-space:nowrap;'>" + escPro(r.fecha) + "</td>" +
      "<td>" + tipoHtml + "</td>" +
      "<td style='white-space:nowrap;'>" + docHtml + "</td>" +
      "<td>" + terceroHtml + "</td>" +
      "<td>" + (r.lote ? escPro(r.lote) : "<span class='text-muted'>-</span>") + "</td>" +
      "<td style='white-space:nowrap;'>" + (r.vencimiento ? escPro(r.vencimiento) : "<span class='text-muted'>-</span>") + "</td>" +
      "<td class='text-center'>" + (esCompra ? "<strong class='text-green'>+" + escPro(r.entrada) + "</strong>" : "<span class='text-muted'>-</span>") + "</td>" +
      "<td class='text-center'>" + (!esCompra ? "<strong class='text-red'>-" + escPro(r.salida) + "</strong>" : "<span class='text-muted'>-</span>") + "</td>" +
      "<td class='text-center'><strong>" + escPro(r.saldo) + "</strong></td>" +
      "<td class='text-right'>" + moneyPro(r.costo) + "</td>" +
      "<td class='text-right'>" + moneyPro(r.precio_ref) + "</td>" +
      "</tr>";
  }
  $("#tblkardex tbody").html(body);
  dtKardex = $("#tblkardex").DataTable({
    bDestroy: true,
    autoWidth: false,
    scrollX: true,
    dom: 'Bfrtip',
    buttons: window.appDataTableButtons('Kardex de Inventario', false),
    iDisplayLength: 10,
    order: [[0, "asc"]],
    initComplete: function () {
      fixDataTableLayout("#tblkardex", this.api());
    },
    drawCallback: function () {
      fixDataTableLayout("#tblkardex", this.api());
    }
  });
  fixDataTableLayout("#tblkardex", dtKardex);

  construirResumenKardex(data);
}

function fixDataTableLayout(selector, api) {
  $(selector).css("width", "100%");
  $(selector + "_wrapper").css("width", "100%");
  if (api && typeof api.columns === "function") {
    api.columns.adjust();
  }
}

function adjustVisibleTables() {
  if (dtKardex) fixDataTableLayout("#tblkardex", dtKardex);
  if (dtAlertaStock) fixDataTableLayout("#tblalertstock", dtAlertaStock);
  if (dtSinMov) fixDataTableLayout("#tblsinmov", dtSinMov);
  if (dtTop) fixDataTableLayout("#tbltopvend", dtTop);
  if (dtUtil) fixDataTableLayout("#tblutilidad", dtUtil);
  if (dtSug) fixDataTableLayout("#tblsugerencias", dtSug);
}

function cargarKardex() {
  var idarticulo = $("#kardex_articulo").val();
  if (!idarticulo) {
    notifyPro("warning", "Selecciona un articulo para generar kardex.");
    return;
  }
  var desde = $("#kardex_desde").val();
  var hasta = $("#kardex_hasta").val();

  $.get("../ajax/procenter.php", {
    op: "kardex",
    idarticulo: idarticulo,
    desde: desde,
    hasta: hasta
  }, function (resp) {
    var r;
    try {
      r = JSON.parse(resp);
    } catch (e) {
      notifyPro("error", "No se pudo generar el kardex.");
      return;
    }

    if (!r.ok) {
      notifyPro("warning", r.message || "No se pudo generar kardex.");
      return;
    }

    construirKardex(r);
  });
}

function tablaAjax(selector, url, buttonsTitle) {
  return $(selector).DataTable({
    aProcessing: true,
    aServerSide: true,
    bDestroy: true,
    autoWidth: false,
    scrollX: true,
    iDisplayLength: 10,
    dom: 'Bfrtip',
    buttons: window.appDataTableButtons(buttonsTitle, false),
    ajax: {
      url: url,
      type: 'get',
      dataType: 'json',
      error: function (e) {
        console.log(e.responseText);
      }
    },
    initComplete: function () {
      fixDataTableLayout(selector, this.api());
    },
    drawCallback: function () {
      fixDataTableLayout(selector, this.api());
    }
  });
}

function cargarAlertaStock() {
  if (dtAlertaStock) {
    dtAlertaStock.ajax.reload();
    return;
  }
  dtAlertaStock = tablaAjax("#tblalertstock", "../ajax/procenter.php?op=alertaStock", "Alerta Stock Minimo");
}

function cargarSinMov() {
  var dias = $("#alerta_dias").val() || 30;
  var url = "../ajax/procenter.php?op=alertaSinMov&dias=" + dias;
  if (dtSinMov) {
    dtSinMov.ajax.url(url).load();
    return;
  }
  dtSinMov = tablaAjax("#tblsinmov", url, "Articulos Sin Movimiento");
}

function cargarTopVendidos() {
  var desde = $("#top_desde").val();
  var hasta = $("#top_hasta").val();
  var url = "../ajax/procenter.php?op=topVendidos&desde=" + encodeURIComponent(desde) + "&hasta=" + encodeURIComponent(hasta);
  if (dtTop) {
    dtTop.ajax.url(url).load();
    return;
  }
  dtTop = tablaAjax("#tbltopvend", url, "Top Vendidos");
}

function cargarUtilidad() {
  var desde = $("#util_desde").val();
  var hasta = $("#util_hasta").val();
  var agrupar = $("#util_agrupar").val();
  var url = "../ajax/procenter.php?op=utilidad&desde=" + encodeURIComponent(desde) + "&hasta=" + encodeURIComponent(hasta) + "&agrupar=" + encodeURIComponent(agrupar);
  if (dtUtil) {
    dtUtil.ajax.url(url).load();
    return;
  }
  dtUtil = tablaAjax("#tblutilidad", url, "Reporte de Utilidad");
}

function cargarSugerencias() {
  var da = $("#sug_dias_analisis").val() || 30;
  var dc = $("#sug_dias_cobertura").val() || 15;
  var url = "../ajax/procenter.php?op=sugerencias&dias_analisis=" + da + "&dias_cobertura=" + dc;

  if (dtSug) {
    dtSug.ajax.url(url).load();
    return;
  }
  dtSug = tablaAjax("#tblsugerencias", url, "Compras Sugeridas");
}

function init() {
  cargarSelectArticulos();
  $("#kardex_desde").val(haceDiasISO(30));
  $("#kardex_hasta").val(hoyISO());

  $("#top_desde").val(haceDiasISO(30));
  $("#top_hasta").val(hoyISO());
  $("#util_desde").val(haceDiasISO(30));
  $("#util_hasta").val(hoyISO());

  // Cargar solo pestaña inicial para evitar tablas encogidas en tabs ocultos
  setTimeout(adjustVisibleTables, 120);

  $("#btnKardexBuscar").on("click", cargarKardex);
  $("#btnAlertaSinMov").on("click", cargarSinMov);
  $("#btnTopVendidos").on("click", cargarTopVendidos);
  $("#btnUtilidad").on("click", cargarUtilidad);
  $("#btnSugerencias").on("click", cargarSugerencias);

  $('a[data-toggle="tab"]').on("shown.bs.tab", function (e) {
    var target = $(e.target).attr("href");

    if (target === "#tabAlertas" && !loadedTabs.alertas) {
      cargarAlertaStock();
      cargarSinMov();
      cargarTopVendidos();
      loadedTabs.alertas = true;
    }
    if (target === "#tabUtilidad" && !loadedTabs.utilidad) {
      cargarUtilidad();
      loadedTabs.utilidad = true;
    }
    if (target === "#tabSugerencias" && !loadedTabs.sugerencias) {
      cargarSugerencias();
      loadedTabs.sugerencias = true;
    }

    setTimeout(adjustVisibleTables, 80);
  });
}

init();
