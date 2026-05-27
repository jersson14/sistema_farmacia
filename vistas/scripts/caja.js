var dtMovCaja;
var dtHistCaja;
var dtHistCaja2;

function notifyCaja(type, message) {
  if (typeof appNotify === "function") {
    appNotify(type, message);
  } else {
    alert(message);
  }
}

function fmtMoney(val) {
  var n = parseFloat(val) || 0;
  return window.appMoney ? window.appMoney(n, 2) : ((window.appCurrencySymbol || "S/") + " " + n.toFixed(2));
}

function cargarEstadoCaja() {
  $.get("../ajax/caja.php?op=estado", function (resp) {
    var r = {};
    try { r = JSON.parse(resp); } catch (e) { r = { abierta: false }; }

    if (r.abierta) {
      $("#seccionCajaCerrada").hide();
      $("#seccionCajaAbierta").show();

      var fechaAp = r.fecha_apertura ? r.fecha_apertura.substring(0, 16).replace("T", " ") : "";
      $("#lblFechaApertura").text("Desde: " + fechaAp);
      var yape    = parseFloat(r.ventas_yape)    || 0;
      var tarjeta = parseFloat(r.ventas_tarjeta) || 0;
      $("#cajApertura").text(fmtMoney(r.monto_apertura));
      $("#cajVentas").text(fmtMoney(r.ventas_efectivo));
      $("#cajYape").text(fmtMoney(yape));
      $("#cajTarjeta").text(fmtMoney(tarjeta));
      $("#cajIngresos").text(fmtMoney(r.ingresos));
      $("#cajEgresos").text(fmtMoney(r.egresos));
      $("#cajSistema").html("<strong>" + fmtMoney(r.sistema) + "</strong>");
      $("#cajDigital").html("<strong>" + fmtMoney(yape + tarjeta) + "</strong>");

      // Actualizar badge del header si existe
      if (typeof actualizarBadgeCaja === "function") {
        actualizarBadgeCaja(true);
      }
    } else {
      $("#seccionCajaAbierta").hide();
      $("#seccionCajaCerrada").show();

      if (typeof actualizarBadgeCaja === "function") {
        actualizarBadgeCaja(false);
      }
    }
  });
}

var historialColumns = [
  { title: "ID" }, { title: "Apertura" }, { title: "Cierre" }, { title: "Cajero" },
  { title: "Apertura" }, { title: "Ingresos" }, { title: "Egresos" },
  { title: "Total sistema" }, { title: "Total real" }, { title: "Diferencia" },
  { title: "Estado" }, { title: "" }
];

function listarMovimientosCaja() {
  dtMovCaja = $("#tblmovcaja").DataTable({
    aProcessing: true,
    aServerSide: true,
    bDestroy: true,
    iDisplayLength: 10,
    dom: 'Bfrtip',
    buttons: window.appDataTableButtons ? window.appDataTableButtons('Movimientos de Caja', false) : [],
    autoWidth: false,
    ajax: {
      url: "../ajax/caja.php?op=listarMovimientos",
      type: "get",
      dataType: "json",
      error: function (e) { console.log(e.responseText); }
    }
  });
}

function initHistorial(tableId) {
  return $(tableId).DataTable({
    aProcessing: true,
    aServerSide: true,
    bDestroy: true,
    iDisplayLength: 10,
    dom: 'Bfrtip',
    buttons: window.appDataTableButtons ? window.appDataTableButtons('Historial de Cajas', false) : [],
    ajax: {
      url: "../ajax/caja.php?op=historial",
      type: "get",
      dataType: "json",
      error: function (e) { console.log(e.responseText); }
    }
  });
}

function refrescarTodoCaja() {
  cargarEstadoCaja();
  if (dtMovCaja)   dtMovCaja.ajax.reload(null, false);
  if (dtHistCaja)  dtHistCaja.ajax.reload(null, false);
  if (dtHistCaja2) dtHistCaja2.ajax.reload(null, false);
}

function init() {
  cargarEstadoCaja();
  listarMovimientosCaja();
  dtHistCaja  = initHistorial("#tblhistcaja");
  dtHistCaja2 = initHistorial("#tblhistcaja2");

  // Abrir caja
  $("#formAbrirCaja").on("submit", function (e) {
    e.preventDefault();
    $.post("../ajax/caja.php?op=abrir", $(this).serialize(), function (resp) {
      var ok = (resp || "").toLowerCase().indexOf("correctamente") !== -1;
      notifyCaja(ok ? "success" : "warning", resp);
      if (ok) {
        $("#formAbrirCaja")[0].reset();
        refrescarTodoCaja();
      }
    });
  });

  // Movimiento manual
  $("#formMovimientoCaja").on("submit", function (e) {
    e.preventDefault();
    $.post("../ajax/caja.php?op=movimiento", $(this).serialize(), function (resp) {
      var ok = (resp || "").toLowerCase().indexOf("registrado") !== -1;
      notifyCaja(ok ? "success" : "warning", resp);
      if (ok) {
        $("#formMovimientoCaja")[0].reset();
        refrescarTodoCaja();
      }
    });
  });

  // Cerrar caja
  $("#formCerrarCaja").on("submit", function (e) {
    e.preventDefault();
    $.post("../ajax/caja.php?op=cerrar", $(this).serialize(), function (resp) {
      var ok = (resp || "").toLowerCase().indexOf("correctamente") !== -1;
      notifyCaja(ok ? "success" : "warning", resp);
      if (ok) {
        $("#formCerrarCaja")[0].reset();
        refrescarTodoCaja();
        // Abrir PDF de cierre en nueva pestaña si el servidor lo devuelve
        var idcaja = (resp.match(/idcaja:(\d+)/) || [])[1];
        if (idcaja) {
          window.open("../reportes/rptCierreCaja.php?id=" + idcaja, "_blank");
        }
      }
    });
  });

  // Historial colapsado: inicializar DataTable al expandir
  $("[data-widget='collapse']").on("click", function () {
    if (dtHistCaja2) {
      setTimeout(function () { dtHistCaja2.columns.adjust(); }, 300);
    }
  });
}

init();
