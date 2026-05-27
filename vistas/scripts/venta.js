var tabla;
var tablaArticulos;
var recetaModalConfirmada = false;

// POS grid state
var posProductos = [];         // full product list loaded from server
var posCatActiva = '';         // '' = all
var posSearchTimer = null;
var empresaDefaults = {
	serie_boleta: "B001",
	serie_factura: "F001",
	serie_ticket: "T001",
	impuesto_default: 18,
	moneda: "PEN",
	simbolo_moneda: "S/"
};
var posScanTimer = null;
var posCodeQueue = [];
var posProcessing = false;
var correlativoRequestId = 0;
var clientesCargados = false;
var numeroComprobanteManual = false;

function notifyVenta(type, message){
	if (typeof appNotify === "function") {
		appNotify(type, message);
		return;
	}
	alert(message);
}

function normalizarEnteroNoNegativo(valor){
	var num = parseFloat(valor);
	if (!isFinite(num)) { return 0; }
	num = Math.round(num);
	if (num < 0) { num = 0; }
	return num;
}

// Mantiene decimales (hasta 4 decimales) para venta fraccionada
function normalizarCantidad(valor, minimo){
	var num = parseFloat(valor);
	if (!isFinite(num) || num < 0) { num = minimo || 0.5; }
	num = Math.round(num * 10000) / 10000;
	if (minimo !== undefined && num < minimo) { num = minimo; }
	return num;
}

// Alias mantenido por compatibilidad — ahora permite decimales
function normalizarCantidadEntera(valor, minimo){
	return normalizarCantidad(valor, minimo);
}

function fechaHoraActualInput(){
	var now = new Date();
	var y = now.getFullYear();
	var m = ("0" + (now.getMonth() + 1)).slice(-2);
	var d = ("0" + now.getDate()).slice(-2);
	var h = ("0" + now.getHours()).slice(-2);
	var min = ("0" + now.getMinutes()).slice(-2);
	return y + "-" + m + "-" + d + "T" + h + ":" + min;
}

function normalizarFechaHoraInput(valor){
	var raw = (valor || "").toString().trim();
	if (!raw) {
		return fechaHoraActualInput();
	}
	var m = raw.match(/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})/);
	if (m) {
		return m[1] + "T" + m[2];
	}
	var d = new Date(raw);
	if (!isNaN(d.getTime())) {
		var y = d.getFullYear();
		var mm = ("0" + (d.getMonth() + 1)).slice(-2);
		var dd = ("0" + d.getDate()).slice(-2);
		var hh = ("0" + d.getHours()).slice(-2);
		var mi = ("0" + d.getMinutes()).slice(-2);
		return y + "-" + mm + "-" + dd + "T" + hh + ":" + mi;
	}
	return fechaHoraActualInput();
}

// ── POS Product Grid ─────────────────────────────────────────────

function cargarProductosGrid() {
	$("#posProductGrid").html('<div class="pos-grid-loading"><i class="fa fa-spinner fa-spin fa-2x"></i><p>Cargando productos...</p></div>');
	$.get("../ajax/venta.php?op=listarArticulosGrid", function(resp) {
		var r = {};
		try { r = JSON.parse(resp); } catch(e) {}
		if (!r.ok || !r.data) {
			$("#posProductGrid").html('<div class="pos-grid-empty"><i class="fa fa-exclamation-circle fa-2x"></i><br>No se pudieron cargar los productos.</div>');
			return;
		}
		posProductos = r.data;
		renderGrid();
	}).fail(function() {
		$("#posProductGrid").html('<div class="pos-grid-empty">Error al cargar productos.</div>');
	});
}

function cargarCategoriasGrid() {
	$.get("../ajax/venta.php?op=listarCategoriasVenta", function(resp) {
		var r = {};
		try { r = JSON.parse(resp); } catch(e) {}
		if (!r.ok || !r.data) return;
		var $bar = $("#posCatBar");
		r.data.forEach(function(cat) {
			var $pill = $('<button type="button" class="pos-cat-pill">' + $('<span>').text(cat.nombre).html() + '</button>');
			$pill.attr('data-cat', cat.idcategoria);
			$bar.append($pill);
		});
	});
}

function renderGrid() {
	var termino = ($("#posSearchInput").val() || "").toLowerCase().trim();
	var catId   = posCatActiva ? parseInt(posCatActiva) : 0;

	var lista = posProductos.filter(function(p) {
		if (catId && p.idcategoria !== catId) return false;
		if (termino) {
			var haystack = (p.nombre + ' ' + p.principio_activo + ' ' + p.codigo + ' ' + p.categoria).toLowerCase();
			return haystack.indexOf(termino) !== -1;
		}
		return true;
	});

	if (lista.length === 0) {
		$("#posProductGrid").html('<div class="pos-grid-empty"><i class="fa fa-search fa-2x"></i><br>Sin resultados.</div>');
		return;
	}

	var html = '';
	lista.forEach(function(p) {
		var sinStock = p.stock <= 0;
		var precio   = (p.precio_venta > 0) ? ((window.appCurrencySymbol || 'S/') + ' ' + p.precio_venta.toFixed(2)) : 'Sin precio';
		var badgeCls = p.tipo_venta === 'OTC' ? 'badge-otc' : (p.tipo_venta === 'RX' ? 'badge-rx' : 'badge-ctrl');
		var badgeTxt = p.tipo_venta === 'CONTROL_ESPECIAL' ? 'CTRL' : p.tipo_venta;
		var imgSrc   = p.imagen ? '../files/articulos/' + p.imagen : '../public/img/default-50x50.gif';
		var generico = p.principio_activo ? (p.principio_activo + (p.concentracion ? ' ' + p.concentracion : '')) : '';

		var cardCls = 'pos-card' + (sinStock ? ' sin-stock' : '');
		var onclick  = sinStock ? '' : 'onclick="posCardClick(' + p.idarticulo + ')"';

		html += '<div class="' + cardCls + '" data-id="' + p.idarticulo + '" ' + onclick + '>' +
			'<img class="pos-card-img" src="' + imgSrc + '" onerror="this.src=\'../public/img/default-50x50.gif\'" alt="">' +
			'<div class="pos-card-nombre">' + $('<span>').text(p.nombre).html() + '</div>' +
			(generico ? '<div class="pos-card-generico">' + $('<span>').text(generico).html() + '</div>' : '') +
			'<div class="pos-card-footer">' +
				'<span class="pos-card-precio">' + precio + '</span>' +
				'<span class="pos-card-badge ' + badgeCls + '">' + badgeTxt + '</span>' +
			'</div>' +
		'</div>';
	});
	$("#posProductGrid").html(html);
}

function posCardClick(idarticulo) {
	var p = null;
	for (var i = 0; i < posProductos.length; i++) {
		if (posProductos[i].idarticulo === idarticulo) { p = posProductos[i]; break; }
	}
	if (!p) return;
	if (p.precio_venta <= 0) {
		notifyVenta("warning", "Este producto no tiene precio de venta asignado. Actualiza el precio en el módulo de Ingresos.");
		return;
	}
	agregarDetalle(p.idarticulo, p.nombre, p.precio_venta, p.abreviatura, p.stock, p.tipo_venta);
}

// ── Visual cart ───────────────────────────────────────────────────

function renderCarritoVisual() {
	var filas = document.querySelectorAll('#detalles .filas');
	var $cont = $("#posCarritoItems");

	if (filas.length === 0) {
		$cont.html(
			'<div class="pos-carrito-empty" id="posCarritoEmpty">' +
			'<i class="fa fa-shopping-basket fa-3x"></i>' +
			'<p>Sin productos en el carrito</p>' +
			'<small>Selecciona un medicamento o escanea su código</small>' +
			'</div>'
		);
		return;
	}

	var html = '';
	for (var i = 0; i < filas.length; i++) {
		var fila   = filas[i];
		var idx    = fila.id.replace('fila', '');
		var tds    = fila.querySelectorAll('td');
		var nombre = tds[1] ? tds[1].textContent.trim() : '';
		var unidad = tds[2] ? tds[2].textContent.trim() : '';
		var cantInp   = fila.querySelector('input[name="cantidad[]"]');
		var precioInp = fila.querySelector('input[name="precio_venta[]"]');
		var stockInp  = fila.querySelector('input[name="stock_disponible[]"]');
		var cantidad  = cantInp  ? parseFloat(cantInp.value  || 1)    : 1;
		var precio    = precioInp ? parseFloat(precioInp.value || 0)   : 0;
		var stockMax  = stockInp  ? parseFloat(stockInp.value || 9999) : 9999;
		var subtotal  = (cantidad * precio).toFixed(2);
		var sym       = window.appCurrencySymbol || 'S/';

		html += '<div class="pos-item" id="posItem' + idx + '">' +
			'<div class="pos-item-info">' +
				'<div class="pos-item-nombre" title="' + $('<span>').text(nombre).html() + '">' + $('<span>').text(nombre).html() + '</div>' +
				(unidad ? '<div class="pos-item-generico">' + $('<span>').text(unidad).html() + '</div>' : '') +
			'</div>' +
			'<div class="pos-item-controls">' +
				'<button type="button" class="pos-item-qty-btn" onclick="posItemDecrement(' + idx + ',' + stockMax + ')">&#8722;</button>' +
				'<input type="text" class="pos-item-qty" id="posItemQty' + idx + '" value="' + cantidad + '" onchange="posItemQtyChange(' + idx + ',' + stockMax + ',this.value)" onclick="this.select()">' +
				'<button type="button" class="pos-item-qty-btn" onclick="posItemIncrement(' + idx + ',' + stockMax + ')">&#43;</button>' +
			'</div>' +
			'<div class="pos-item-precio">' + sym + ' ' + subtotal + '</div>' +
			'<button type="button" class="pos-item-del" onclick="eliminarDetalle(' + idx + ')" title="Quitar"><i class="fa fa-times"></i></button>' +
		'</div>';
	}
	$cont.html(html);
}

function posItemDecrement(idx, stockMax) {
	var cantInp = document.querySelector('#fila' + idx + ' input[name="cantidad[]"]');
	if (!cantInp) return;
	var actual = normalizarCantidad(cantInp.value, 0.5);
	var paso   = (actual > 1) ? 1 : 0.5;
	var nuevo  = Math.max(0.5, actual - paso);
	cantInp.value = nuevo;
	modificarSubtotales();
}

function posItemIncrement(idx, stockMax) {
	var cantInp = document.querySelector('#fila' + idx + ' input[name="cantidad[]"]');
	if (!cantInp) return;
	var actual = normalizarCantidad(cantInp.value, 0.5);
	var nuevo  = actual + 1;
	if (stockMax > 0 && nuevo > stockMax) {
		notifyVenta("warning", "Stock máximo disponible: " + stockMax);
		return;
	}
	cantInp.value = nuevo;
	modificarSubtotales();
}

function posItemQtyChange(idx, stockMax, valor) {
	var cantInp = document.querySelector('#fila' + idx + ' input[name="cantidad[]"]');
	if (!cantInp) return;
	var num = normalizarCantidad(valor, 0.5);
	if (stockMax > 0 && num > stockMax) { num = stockMax; }
	cantInp.value = num;
	modificarSubtotales();
}

// ── POS totals display ────────────────────────────────────────────

function actualizarTotalesPOS(total) {
	var impPct = parseFloat($("#impuesto").val() || 0);
	var sym    = window.appCurrencySymbol || 'S/';
	var subtotalSinIgv, igvMonto, totalFinal;

	if (impPct > 0) {
		subtotalSinIgv = total / (1 + impPct / 100);
		igvMonto       = total - subtotalSinIgv;
		totalFinal     = total;
		$("#posIgvRow").show();
		$("#posIgvLabel").text("IGV (" + impPct + "%)");
		$("#posIgvMonto").text(sym + ' ' + igvMonto.toFixed(2));
	} else {
		subtotalSinIgv = total;
		igvMonto       = 0;
		totalFinal     = total;
		$("#posIgvRow").hide();
	}

	$("#posTotalSinIgv").text(sym + ' ' + subtotalSinIgv.toFixed(2));
	$("#posTotalFinal").text(sym + ' ' + totalFinal.toFixed(2));
	$("#btnCobrarTotal").text(sym + ' ' + totalFinal.toFixed(2));
	$("#posSerieBadge").text(
		($("#serie_comprobante").val() || '···') +
		($("#num_comprobante").val() ? '-' + $("#num_comprobante").val() : '')
	);
	actualizarFloatCartBtn();
}

//funcion que se ejecuta al inicio
function init(){
   mostrarform(false);
   listar();

   $("#formulario").on("submit",function(e){
   	guardaryeditar(e);
   });

   $("#btnFiltrarVenta").on("click", function(){
   	recargarListadoVenta();
   	cargarResumenPagosVenta();
   });
   $("#btnLimpiarFiltroVenta").on("click", function(){
   	$("#filtro_venta_inicio").val("");
   	$("#filtro_venta_fin").val("");
   	recargarListadoVenta();
   	cargarResumenPagosVenta();
   });

   cargarResumenPagosVenta();
   cargarClientes();
   cargarCategoriasGrid();

   // POS search bar
   $("#posSearchInput").on("input keyup", function() {
   	clearTimeout(posSearchTimer);
   	posSearchTimer = setTimeout(renderGrid, 200);
   });
   $("#posSearchInput").on("keydown", function(e) {
   	if (e.key === "Enter") { clearTimeout(posSearchTimer); renderGrid(); }
   });

   // Category pills (delegated, pills added dynamically)
   $(document).on("click", ".pos-cat-pill", function() {
   	$(".pos-cat-pill").removeClass("active");
   	$(this).addClass("active");
   	posCatActiva = $(this).attr("data-cat") || '';
   	renderGrid();
   });

   // Document type toggle buttons
   $(document).on("click", ".pos-doc-btn", function() {
   	$(".pos-doc-btn").removeClass("active");
   	$(this).addClass("active");
   	var tipo = $(this).attr("data-tipo") || "Boleta";
   	$("#tipo_comprobante").val(tipo);
   	aplicarSerieImpuesto();
   });

   // Payment method buttons
   $(document).on("click", ".pos-metodo-btn", function() {
   	$(".pos-metodo-btn").removeClass("active");
   	$(this).addClass("active");
   	var metodo = $(this).attr("data-metodo") || "EFECTIVO";
   	$("#metodo_pago").val(metodo);
   	sincronizarMontoPago();
   });

   $("#myModal").on("shown.bs.modal", function(){
   	if (!tablaArticulos) {
   		listarArticulos();
   	} else {
   		$("#tblarticulos, #tblarticulos_wrapper").css("width","100%");
   		tablaArticulos.columns.adjust();
   		tablaArticulos.ajax.reload(null, false);
   	}
   	estilizarBuscadorCatalogo();
   	setTimeout(function(){
   		$("#tblarticulos_filter input").focus();
   	}, 80);
   });
   cargarDefaultsEmpresa();

   $("#formClienteRapido").on("submit", function(e){
   	guardarClienteRapido(e);
   });
   $("#modalClienteVenta").on("shown.bs.modal", function(){
   	$("#cli_nombre").focus();
   });
   $("#modalClienteVenta").on("hidden.bs.modal", function(){
   	limpiarFormClienteRapido();
   });
   $("#num_comprobante").on("input", function(){
   	numeroComprobanteManual = true;
   });

   // Receta médica Rx
   $("#btnConfirmarReceta").on("click", function(){
   	var nombreMedico = $.trim($("#rx_nombre_medico").val());
   	var colegiatura  = $.trim($("#rx_colegiatura").val());
   	if (!nombreMedico) {
   		notifyVenta("warning", "El nombre del médico es obligatorio para medicamentos Rx.");
   		$("#rx_nombre_medico").focus();
   		return;
   	}
   	if (!colegiatura) {
   		notifyVenta("warning", "El número de colegiatura es obligatorio para medicamentos Rx.");
   		$("#rx_colegiatura").focus();
   		return;
   	}
   	recetaModalConfirmada = true;
   	$('#modalReceta').modal('hide');
   	ejecutarGuardarVenta();
   });
   $("#btnCancelarReceta").on("click", function(){
   	recetaModalConfirmada = false;
   	$('#modalReceta').modal('hide');
   });

   // Método de pago
   $("#metodo_pago").on("change", function(){
   	sincronizarMontoPago();
   	try { $("#metodo_pago").selectpicker("refresh"); } catch(e) {}
   });
   $(".vis-monto").on("input", function(){
   	sincronizarMontoPago();
   });

   $("#btnBuscarCodigo").on("click", function(){
   	encolarCodigoPOS($("#codigo_rapido").val());
   });
   $("#codigo_rapido").on("keypress", function(e){
   	if (e.which===13) {
   		e.preventDefault();
   		clearTimeout(posScanTimer);
   		encolarCodigoPOS($(this).val());
   	}
   });
   $("#codigo_rapido").on("input", function(){
   	clearTimeout(posScanTimer);
   	var codigoLeido = ($(this).val() || "").trim();
   	if (!codigoLeido || codigoLeido.length < 3) {
   		return;
   	}
   	posScanTimer = setTimeout(function(){
   		encolarCodigoPOS(codigoLeido);
   	}, 220);
   });
   $("#serie_comprobante").on("change blur", function(){
   	cargarCorrelativoComprobante();
   });

   // Búsqueda rápida de cliente por DNI
   $("#btnBuscarDni").on("click", function(){
   	buscarClientePorDni();
   });
   $("#posDniBuscar").on("keydown", function(e){
   	if (e.key === "Enter") { e.preventDefault(); buscarClientePorDni(); }
   });

   $(document).on("keydown", function(e){
   	if (e.ctrlKey && (e.key === "b" || e.key === "B")) {
   		e.preventDefault();
   		$("#codigo_rapido").focus().select();
   	}
   	if (e.key === "F2") {
   		e.preventDefault();
   		$('#myModal').modal('show');
   	}
   	if (e.key === "F10") {
   		e.preventDefault();
   		if ($("#btnGuardar").is(":visible")) {
   			$("#formulario").trigger("submit");
   		}
   	}
   });

}

function cargarClientes(idPreferido){
	var idActual = (typeof idPreferido !== "undefined" && idPreferido !== null && idPreferido !== "") ? idPreferido : ($("#idcliente").val() || "");
	$.post("../ajax/venta.php?op=selectCliente", function(r){
		$("#idcliente").html(r);
		var existeActual = false;
		if (idActual !== "") {
			$("#idcliente option").each(function(){
				if (String($(this).val()) === String(idActual)) {
					existeActual = true;
					return false;
				}
			});
		}
		var idFinal = existeActual ? String(idActual) : ($("#idcliente option:first").val() || "");
		$("#idcliente").val(idFinal);
		$('#idcliente').selectpicker('refresh');
		clientesCargados = true;
	});
}

function limpiarFormClienteRapido(){
	$("#cli_nombre").val("");
	$("#cli_tipo_documento").val("DNI");
	$("#cli_num_documento").val("");
	$("#cli_direccion").val("");
	$("#cli_telefono").val("");
	$("#cli_email").val("");
	$("#btnGuardarClienteRapido").prop("disabled", false);
}

function guardarClienteRapido(e){
	e.preventDefault();
	var nombre = $.trim($("#cli_nombre").val());
	if (!nombre) {
		notifyVenta("warning", "El nombre del cliente es obligatorio.");
		return;
	}
	$("#btnGuardarClienteRapido").prop("disabled", true);
	$.ajax({
		url: "../ajax/venta.php?op=crearClienteRapido",
		type: "POST",
		data: $("#formClienteRapido").serialize(),
		success: function(resp){
			var r = {};
			try {
				r = JSON.parse(resp);
			} catch (e) {
				r = {ok:false, message:"No se pudo registrar el cliente."};
			}
			if (!r.ok) {
				notifyVenta("error", r.message || "No se pudo registrar el cliente.");
				$("#btnGuardarClienteRapido").prop("disabled", false);
				return;
			}
			notifyVenta("success", r.message || "Cliente registrado correctamente.");
			$("#modalClienteVenta").modal("hide");
			cargarClientes(r.idcliente || "");
		},
		error: function(){
			notifyVenta("error", "Ocurrio un error al registrar el cliente.");
			$("#btnGuardarClienteRapido").prop("disabled", false);
		}
	});
}

/* ── Carrito móvil (bottom-sheet) ────────────────────────── */
function esMobil(){
	return window.innerWidth <= 599;
}

function abrirCarritoMobile(){
	if (!esMobil()) return;
	$("#posCarritoPanel").addClass("carrito-open");
	$("#posCarritoBackdrop").addClass("active");
	document.body.style.overflow = "hidden";
}

function cerrarCarritoMobile(){
	$("#posCarritoPanel").removeClass("carrito-open");
	$("#posCarritoBackdrop").removeClass("active");
	document.body.style.overflow = "";
}

function actualizarFloatCartBtn(){
	if (!esMobil()) return;
	var count = document.querySelectorAll('#detalles .filas').length;
	var total = $("#posTotalFinal").text() || "S/ 0.00";
	$("#posFloatBadge").text(count);
	$("#posFloatTotal").text(total);
	// Mostrar/ocultar según si hay productos
	if (count > 0) {
		$("#posFloatCartBtn").fadeIn(150);
	} else {
		$("#posFloatCartBtn").hide();
		cerrarCarritoMobile();
	}
}

function buscarClientePorDni(){
	var dni = $.trim($("#posDniBuscar").val());
	if (!dni || dni.length < 3) {
		notifyVenta("warning", "Ingresa al menos 3 dígitos del DNI.");
		$("#posDniBuscar").focus();
		return;
	}
	$.get("../ajax/venta.php?op=buscarClienteDni&dni=" + encodeURIComponent(dni), function(resp){
		var r = {};
		try { r = JSON.parse(resp); } catch(e) {}
		if (r.ok) {
			// Intentar seleccionar en el select existente
			var encontrado = false;
			$("#idcliente option").each(function(){
				if (String($(this).val()) === String(r.idpersona)) {
					encontrado = true;
					return false;
				}
			});
			if (encontrado) {
				$("#idcliente").val(r.idpersona);
				$("#idcliente").selectpicker("refresh");
				notifyVenta("success", "Cliente: " + r.nombre);
			} else {
				cargarClientes(r.idpersona);
				notifyVenta("success", "Cliente: " + r.nombre);
			}
			$("#posDniBuscar").val("").focus();
		} else {
			// No encontrado: abrir modal pre-llenado con el DNI
			limpiarFormClienteRapido();
			$("#cli_tipo_documento").val("DNI");
			$("#cli_num_documento").val(dni);
			$("#modalClienteVenta").modal("show");
			setTimeout(function(){ $("#cli_nombre").focus(); }, 350);
			notifyVenta("info", "DNI no encontrado. Registra el nuevo cliente.");
		}
	}).fail(function(){
		notifyVenta("error", "Error al buscar cliente por DNI.");
	});
}

function cargarResumenPagosVenta() {
	var fi = $("#filtro_venta_inicio").val() || "";
	var ff = $("#filtro_venta_fin").val() || "";
	var sym = window.appCurrencySymbol || "S/";
	$.get("../ajax/venta.php?op=resumenPagos", {fecha_inicio: fi, fecha_fin: ff}, function(r) {
		if (!r || !r.ok || !r.data) return;
		var d = r.data;
		var fmt = function(v){ return sym + " " + parseFloat(v || 0).toFixed(2); };
		$("#vResEfectivo").text(fmt(d.total_efectivo));
		$("#vResYape").text(fmt(d.total_yape));
		$("#vResTarjeta").text(fmt(d.total_tarjeta));
		$("#vResTotal").text(fmt(d.total_general) + " (" + (d.cantidad || 0) + " ventas)");
	}, "json");
}

function cargarDefaultsEmpresa(){
	$.get("../ajax/empresa.php?op=defaults", function(resp){
		try{
			var r = JSON.parse(resp);
			empresaDefaults.serie_boleta = r.serie_boleta || empresaDefaults.serie_boleta;
			empresaDefaults.serie_factura = r.serie_factura || empresaDefaults.serie_factura;
			empresaDefaults.serie_ticket = r.serie_ticket || empresaDefaults.serie_ticket;
			empresaDefaults.impuesto_default = parseFloat(r.impuesto_default || empresaDefaults.impuesto_default);
			empresaDefaults.moneda = r.moneda || empresaDefaults.moneda;
			empresaDefaults.simbolo_moneda = r.simbolo_moneda || empresaDefaults.simbolo_moneda;
		}catch(e){}
		aplicarSerieImpuesto();
	});
}

//funcion limpiar
function limpiar(){

	$("#idventa").val("");
	if (clientesCargados) {
		var primerCliente = $("#idcliente option:first").val() || "";
		$("#idcliente").val(primerCliente);
		$("#idcliente").selectpicker("refresh");
	} else {
		$("#idcliente").val("");
	}
	$("#cliente").val("");
	$("#serie_comprobante").val("");
	$("#num_comprobante").val("");
	$("#impuesto").val("");
	numeroComprobanteManual = false;

	$("#total_venta").val("");
	$(".filas").remove();
	detalles = 0;
	cont = 0;
	$("#total").html(window.appMoney ? window.appMoney(0,2) : ((window.appCurrencySymbol || "S/") + " 0.00"));
	actualizarContadorItems();
	renderCarritoVisual();
	actualizarTotalesPOS(0);

	// Reset pago
	$(".pos-metodo-btn").removeClass("active");
	$('.pos-metodo-btn[data-metodo="EFECTIVO"]').addClass("active");
	$(".pos-doc-btn").removeClass("active");
	$('.pos-doc-btn[data-tipo="Boleta"]').addClass("active");
	$("#metodo_pago").val("EFECTIVO");
	try { $("#metodo_pago").selectpicker("refresh"); } catch(e) {}
	$("#seccionMixto").hide();
	$("#vis_efectivo, #vis_tarjeta, #vis_digital").val("0");
	$("#monto_efectivo, #monto_tarjeta, #monto_digital").val("0");

	// Reset receta
	recetaModalConfirmada = false;
	$("#rx_nombre_medico, #rx_colegiatura, #rx_establecimiento, #rx_observaciones").val("");
	$("#rx_fecha_emision").val("");
	$("#rx_tipo_receta").val("SIMPLE");

	$("#fecha_hora").val(fechaHoraActualInput());

	//marcamos el primer tipo_documento
	$("#tipo_comprobante").val("Boleta");
	$("#tipo_comprobante").selectpicker('refresh');
	aplicarSerieImpuesto();

}

//funcion mostrar formulario
function mostrarform(flag){
	limpiar();
	if(flag){
		$("#listadoregistros").hide();
		$("#formularioregistros").show();
		$("#btnagregar").hide();
		$("#btnGuardar").hide();
		$("#btnCancelar").show();
		detalles=0;
		$("#btnAgregarArt").show();
		// Load product grid only once; reload if empty
		if (posProductos.length === 0) {
			cargarProductosGrid();
		} else {
			renderGrid();
		}
		renderCarritoVisual();
		// Focus search input
		setTimeout(function(){ $("#posSearchInput").focus(); }, 150);
	}else{
		$("#listadoregistros").show();
		$("#formularioregistros").hide();
		$("#btnagregar").show();
	}
}

//cancelar form
function cancelarform(){
	limpiar();
	mostrarform(false);
}

//funcion listar
function listar(){
	tabla=$('#tbllistado').dataTable({
		"aProcessing": true,//activamos el procedimiento del datatable
		"aServerSide": true,//paginacion y filrado realizados por el server
		dom: 'Bfrtip',//definimos los elementos del control de la tabla
		buttons: window.appDataTableButtons('Reporte de Ventas', true),
		"ajax":
		{
			url:'../ajax/venta.php?op=listar',
			type: "get",
			data: function(d){
				d.fecha_inicio = ($("#filtro_venta_inicio").val() || "").trim();
				d.fecha_fin = ($("#filtro_venta_fin").val() || "").trim();
			},
			dataType : "json",
			error:function(e){
				console.log(e.responseText);
			}
		},
		"bDestroy":true,
		"iDisplayLength":10,//paginacion
		"order":[[0,"desc"]]//ordenar (columna, orden)
	}).DataTable();
}

function listarArticulos(){
	tablaArticulos=$('#tblarticulos').dataTable({
		"aProcessing": true,//activamos el procedimiento del datatable
		"aServerSide": true,//paginacion y filrado realizados por el server
		"autoWidth": false,
		dom: 'frtip',//definimos los elementos del control de la tabla
		"ajax":
		{
			url:'../ajax/venta.php?op=listarArticulos',
			type: "get",
			dataType : "json",
			error:function(e){
				console.log(e.responseText);
			}
		},
		"bDestroy":true,
		"iDisplayLength":10,//paginacion
		"order":[[1,"asc"]],//ordenar por nombre
		"language":{
			"sSearch":"Buscar artÃ­culo:",
			"sSearchPlaceholder":"Nombre o cÃ³digo"
		},
		"initComplete":function(){
			$("#tblarticulos, #tblarticulos_wrapper").css("width","100%");
			this.api().columns.adjust();
			estilizarBuscadorCatalogo();
		},
		"drawCallback":function(){
			$("#tblarticulos, #tblarticulos_wrapper").css("width","100%");
			this.api().columns.adjust();
			estilizarBuscadorCatalogo();
		}
	}).DataTable();
}

function recargarListadoVenta(){
	var fi = ($("#filtro_venta_inicio").val() || "").trim();
	var ff = ($("#filtro_venta_fin").val() || "").trim();
	if (fi && ff && fi > ff) {
		notifyVenta("warning", "La fecha 'Desde' no puede ser mayor que 'Hasta'.");
		return;
	}
	if (tabla) {
		tabla.ajax.reload();
	}
}
//funcion para guardaryeditar
function guardaryeditar(e){
     e.preventDefault();
     // 0 = "Consumidor Final" — es válido vender sin identificar al cliente
     var clienteSeleccionado = ($("#idcliente").val() || "0").toString().trim();
     if (clienteSeleccionado === "") {
     	$("#idcliente").val("0").trigger("change");
     	clienteSeleccionado = "0";
     }
     if (!validarStockDetalleAntesGuardar()) return;
     sincronizarMontoPago();
     if (!validarMontoPago()) return;

     if (tieneArticulosRx()) {
     	abrirModalReceta();
     } else {
     	ejecutarGuardarVenta();
     }
}

function tieneArticulosControlEspecial(){
	var filas = document.querySelectorAll('#detalles .filas');
	for (var i = 0; i < filas.length; i++) {
		if ((filas[i].getAttribute('data-tipo-venta') || '') === 'CONTROL_ESPECIAL') return true;
	}
	return false;
}

function tieneArticulosRx(){
	var filas = document.querySelectorAll('#detalles .filas');
	for (var i = 0; i < filas.length; i++) {
		var tv = filas[i].getAttribute('data-tipo-venta') || 'OTC';
		if (tv === 'RX' || tv === 'CONTROL_ESPECIAL') return true;
	}
	return false;
}

function abrirModalReceta(){
	var nombres = [];
	$('#detalles .filas').each(function(){
		var tv = $(this).attr('data-tipo-venta') || 'OTC';
		if (tv === 'RX' || tv === 'CONTROL_ESPECIAL') {
			var nombreTd = $(this).find('td').eq(1).text().trim();
			nombres.push(nombreTd || 'Medicamento Rx');
		}
	});
	var plural = nombres.length > 1 ? 'medicamentos requieren' : 'medicamento requiere';
	var msg = '<strong>' + nombres.length + '</strong> ' + plural + ' receta médica:<br><em>' + nombres.join(', ') + '</em>';
	$('#rxAlertaProductos').html(msg);
	var hoy = new Date();
	var yyyy = hoy.getFullYear();
	var mm = ("0"+(hoy.getMonth()+1)).slice(-2);
	var dd = ("0"+hoy.getDate()).slice(-2);
	$('#rx_fecha_emision').val(yyyy+'-'+mm+'-'+dd);
	$('#modalReceta').modal('show');
	setTimeout(function(){ $('#rx_nombre_medico').focus(); }, 350);
}

function ejecutarGuardarVenta(){
     var formData=new FormData($("#formulario")[0]);
     $.ajax({
     	url: "../ajax/venta.php?op=guardaryeditar",
     	type: "POST",
     	data: formData,
     	contentType: false,
     	processData: false,
     	success: function(datos){
     		var r = null;
     		try { r = JSON.parse(datos); } catch(e) {}
     		if (r && typeof r.ok !== "undefined") {
     			if (r.ok) {
     				var idventaNueva = r.idventa || 0;
     				var hayControlEsp = tieneArticulosControlEspecial();
     				if (recetaModalConfirmada && idventaNueva > 0) {
     					guardarReceta(idventaNueva, function(idrecetaNueva){
     						if (hayControlEsp && idventaNueva > 0) {
     							guardarControlEspecial(idventaNueva, idrecetaNueva || 0, function(){
     								recetaModalConfirmada = false;
     								procesarExitoVenta(r);
     							});
     						} else {
     							recetaModalConfirmada = false;
     							procesarExitoVenta(r);
     						}
     					});
     				} else {
     					recetaModalConfirmada = false;
     					procesarExitoVenta(r);
     				}
     			} else {
     				notifyVenta("error", r.message || "No se pudo registrar la venta.");
     			}
     		} else {
     			notifyVenta("success", datos);
     			mostrarform(false);
     			listar();
     		}
    	}
     });
}

function guardarControlEspecial(idventa, idreceta, callback){
	$.post("../ajax/control_especial.php?op=guardarDesdeVenta", {
		idventa:  idventa,
		idreceta: idreceta || 0
	}, function(resp){
		// Fallo silencioso: la venta ya está guardada, solo loggear
		var r = {};
		try { r = JSON.parse(resp); } catch(e) {}
		if (!r.ok) {
			console.warn("Control especial no registrado:", r.message || "");
		}
		if (typeof callback === "function") callback();
	}).fail(function(){
		if (typeof callback === "function") callback();
	});
}

function guardarReceta(idventa, callback){
	$.post("../ajax/receta.php?op=guardar", {
		idventa:         idventa,
		idcliente:       $("#idcliente").val() || 0,
		nombre_medico:   $.trim($("#rx_nombre_medico").val()),
		colegiatura:     $.trim($("#rx_colegiatura").val()),
		establecimiento: $.trim($("#rx_establecimiento").val()),
		fecha_emision:   $("#rx_fecha_emision").val(),
		tipo_receta:     $("#rx_tipo_receta").val(),
		observaciones:   $.trim($("#rx_observaciones").val())
	}, function(resp){
		var r = {};
		try { r = JSON.parse(resp); } catch(e) {}
		if (!r.ok) {
			notifyVenta("warning", "Venta guardada pero no se pudo registrar la receta: " + (r.message || ""));
		}
		if (typeof callback === "function") callback(r.idreceta || 0);
	}).fail(function(){
		if (typeof callback === "function") callback(0);
	});
}

function procesarExitoVenta(r){
	notifyVenta("success", r.message || "Datos registrados correctamente");
	if (r.alertas && r.alertas.length > 0) {
		var texto = "Alerta de stock bajo: " + r.alertas.map(function(a){
			return (a.nombre || "Articulo") + " (" + normalizarEnteroNoNegativo(a.stock) + ")";
		}).join(", ");
		notifyVenta("warning", texto);
	}
	// Abrir comprobante automáticamente en nueva pestaña para imprimir
	var idventaNueva = r.idventa || 0;
	var tipoComp = ($("#tipo_comprobante").val() || "Boleta");
	if (idventaNueva > 0) {
		var urlComp = (tipoComp === "Factura")
			? "../reportes/exFactura.php?id=" + idventaNueva
			: "../reportes/exTicket.php?id="  + idventaNueva;
		var ventana = window.open(urlComp, "_blank");
		if (!ventana) {
			// El navegador bloqueó el popup — mostrar botón manual
			notifyVenta("info", 'Ticket listo. <a href="' + urlComp + '" target="_blank" style="color:#fff;text-decoration:underline">Abrir ticket</a>');
		}
	}
	mostrarform(false);
	listar();
}

function mostrar(idventa){
	$.post("../ajax/venta.php?op=mostrar",{idventa : idventa},
		function(data,status)
		{
			data=JSON.parse(data);
			mostrarform(true);

			$("#idcliente").val(data.idcliente);
			$("#idcliente").selectpicker('refresh');
			$("#tipo_comprobante").val(data.tipo_comprobante);
			$("#tipo_comprobante").selectpicker('refresh');
			$("#serie_comprobante").val(data.serie_comprobante);
			$("#num_comprobante").val(data.num_comprobante);
			$("#fecha_hora").val(normalizarFechaHoraInput(data.fecha));
			$("#impuesto").val(data.impuesto);
			$("#idventa").val(data.idventa);
			
			//ocultar y mostrar los botones
			$("#btnGuardar").hide();
			$("#btnCancelar").show();
			$("#btnAgregarArt").hide();
		});
	$.post("../ajax/venta.php?op=listarDetalle&id="+idventa,function(r){
		$("#detalles").html(r);
	});

}


//funcion para desactivar
function anular(idventa){
	bootbox.confirm("Â¿Esta seguro de desactivar este dato?", function(result){
		if (result) {
			$.post("../ajax/venta.php?op=anular", {idventa : idventa}, function(e){
				notifyVenta("warning", e);
				tabla.ajax.reload();
			});
		}
	})
}

//declaramos variables necesarias para trabajar con las compras y sus detalles
var impuesto=18;
var cont=0;
var detalles=0;

$("#btnGuardar").hide();
$("#tipo_comprobante").change(marcarImpuesto);

function marcarImpuesto(){
	aplicarSerieImpuesto();
}

function aplicarSerieImpuesto(){
	var tipo = $("#tipo_comprobante").val();
	if (tipo==='Factura') {
		$("#serie_comprobante").val(empresaDefaults.serie_factura || "F001");
		$("#impuesto").val((empresaDefaults.impuesto_default || impuesto).toFixed(2));
	} else if (tipo==='Ticket') {
		$("#serie_comprobante").val(empresaDefaults.serie_ticket || "T001");
		$("#impuesto").val("0");
	} else {
		$("#serie_comprobante").val(empresaDefaults.serie_boleta || "B001");
		$("#impuesto").val("0");
	}
	cargarCorrelativoComprobante();
}

function cargarCorrelativoComprobante(){
	var tipo = ($("#tipo_comprobante").val() || "Boleta").trim();
	var serie = ($("#serie_comprobante").val() || "").trim();
	if (!serie) {
		$("#num_comprobante").val("");
		return;
	}
	correlativoRequestId++;
	var reqId = correlativoRequestId;
	$.get("../ajax/venta.php?op=siguienteCorrelativo", {
		tipo_comprobante: tipo,
		serie_comprobante: serie
	}, function(resp){
		if (reqId !== correlativoRequestId) {
			return;
		}
		var r = {};
		try {
			r = JSON.parse(resp);
		} catch (e) {
			return;
		}
		if (!r.ok) {
			return;
		}
		$("#serie_comprobante").val(r.serie_comprobante || serie);
		if (!numeroComprobanteManual || !$.trim($("#num_comprobante").val())) {
			$("#num_comprobante").val(r.numero || "");
			numeroComprobanteManual = false;
		}
		var serieActual = r.serie_comprobante || $("#serie_comprobante").val() || '···';
		var numActual   = r.numero || $("#num_comprobante").val() || '';
		$("#posSerieBadge").text(serieActual + (numActual ? '-' + numActual : ''));
	});
}

function agregarDetalle(idarticulo,articulo,precio_venta,unidad,stockDisponible,tipoVenta){
	var cantidad=1;
	var descuento=0;
	var unidadTexto = unidad || "und";
	var tipoVentaNorm = (tipoVenta || 'OTC').toUpperCase();
	var stockDisponibleNum = normalizarEnteroNoNegativo(stockDisponible || 0);
	var articulos = document.getElementsByName("idarticulo[]");
	var cantidades = document.getElementsByName("cantidad[]");
	var stocksDisponibles = document.getElementsByName("stock_disponible[]");
	if (stockDisponibleNum <= 0) {
		notifyVenta("warning", "Este articulo no tiene stock disponible.");
		return;
	}
	if (idarticulo!="") {
		for (var i = 0; i < articulos.length; i++) {
			if (parseInt(articulos[i].value, 10) === parseInt(idarticulo, 10)) {
				var stockFila = normalizarEnteroNoNegativo((stocksDisponibles[i] && stocksDisponibles[i].value) ? stocksDisponibles[i].value : stockDisponibleNum);
				var nuevaCantidadNum = normalizarCantidadEntera(parseFloat(cantidades[i].value || 0) + 1, 1);
				if (nuevaCantidadNum > stockFila) {
					notifyVenta("warning", "No puedes vender mas de " + stockFila + " " + unidadTexto + " para este articulo.");
					return;
				}
				cantidades[i].value = nuevaCantidadNum;
				modificarSubtotales();
				renderCarritoVisual();
				$('#myModal').modal('hide');
				notifyVenta("info", "El articulo ya estaba agregado. Se incremento la cantidad.");
				return;
			}
		}
		var subtotal=cantidad*precio_venta;
		var fila='<tr class="filas" id="fila'+cont+'" data-tipo-venta="'+tipoVentaNorm+'">'+
        '<td><button type="button" class="btn btn-danger" onclick="eliminarDetalle('+cont+')">X</button></td>'+
        '<td><input type="hidden" name="idarticulo[]" value="'+idarticulo+'"><input type="hidden" name="stock_disponible[]" value="'+stockDisponibleNum+'">'+articulo+'</td>'+
        '<td>'+unidadTexto+'</td>'+
        '<td><input type="number" step="0.5" min="0.5" max="'+stockDisponibleNum+'" name="cantidad[]" id="cantidad[]" value="'+cantidad+'" oninput="modificarSubtotales()"></td>'+
        '<td><input type="number" step="0.01" min="0.01" name="precio_venta[]" id="precio_venta[]" value="'+precio_venta+'" oninput="modificarSubtotales()"></td>'+
        '<td><input type="number" step="0.01" min="0.00" name="descuento[]" value="'+descuento+'" oninput="modificarSubtotales()"></td>'+
        '<td><span id="subtotal'+cont+'" name="subtotal">'+subtotal+'</span></td>'+
        '<td><button type="button" onclick="modificarSubtotales()" class="btn btn-info"><i class="fa fa-refresh"></i></button></td>'+
		'</tr>';
		cont++;
		detalles++;
		$('#detalles').append(fila);
		modificarSubtotales();
		actualizarContadorItems();
		renderCarritoVisual();
		$('#myModal').modal('hide');
		notifyVenta("success", "Articulo agregado a la venta.");
	}else{
		notifyVenta("warning", "No se pudo agregar el articulo. Revisa la informacion del producto.");
	}
}
function modificarSubtotales(){
	var cant=document.getElementsByName("cantidad[]");
	var prev=document.getElementsByName("precio_venta[]");
	var desc=document.getElementsByName("descuento[]");
	var sub=document.getElementsByName("subtotal");
	var stockDisp=document.getElementsByName("stock_disponible[]");
	var huboAjusteStock=false;
	for (var i = 0; i < cant.length; i++) {
		var inpV=cant[i];
		var inpP=prev[i];
		var inpS=sub[i];
		var des=desc[i];
		var maxStock = parseFloat((stockDisp[i] && stockDisp[i].value) ? stockDisp[i].value : 0) || 0;
		var cantidadActual = normalizarCantidad(inpV.value, 0.5);
		if (cantidadActual <= 0) { cantidadActual = 0.5; }
		if (maxStock > 0 && cantidadActual > maxStock) {
			cantidadActual = maxStock;
			huboAjusteStock = true;
		}
		inpV.value = cantidadActual;
		inpS.value=((parseFloat(inpV.value||0)*parseFloat(inpP.value||0))-parseFloat(des.value||0)).toFixed(2);
		document.getElementsByName("subtotal")[i].innerHTML=inpS.value;
	}
	if (huboAjusteStock) {
		notifyVenta("warning", "Se ajusto la cantidad al stock disponible.");
	}
	calcularTotales();
	renderCarritoVisual();
}
function validarStockDetalleAntesGuardar(){
	var cant=document.getElementsByName("cantidad[]");
	var stockDisp=document.getElementsByName("stock_disponible[]");
	for (var i = 0; i < cant.length; i++) {
		var cantidad = normalizarCantidad(cant[i].value, 0.5);
		var stock = parseFloat((stockDisp[i] && stockDisp[i].value) ? stockDisp[i].value : 0) || 0;
		cant[i].value = cantidad;
		if (isNaN(cantidad) || cantidad <= 0) {
			notifyVenta("warning", "Hay un articulo con cantidad invalida. Corrige antes de guardar.");
			return false;
		}
		if (isNaN(stock) || stock < 0) { stock = 0; }
		if (stock > 0 && cantidad > stock) {
			notifyVenta("warning", "Hay un articulo con cantidad mayor al stock disponible. Corrige antes de guardar.");
			return false;
		}
	}
	return true;
}
function calcularTotales(){
	var sub = document.getElementsByName("subtotal");
	var total=0.0;

	for (var i = 0; i < sub.length; i++) {
		total += parseFloat(document.getElementsByName("subtotal")[i].value || 0);
	}
	$("#total").html(window.appMoney ? window.appMoney(total,2) : ((window.appCurrencySymbol || "S/") + " " + total.toFixed(2)));
	$("#total_venta").val(total.toFixed(2));
	sincronizarMontoPago();
	actualizarTotalesPOS(total);
	evaluar();
}

// --- Lógica de método de pago ---
function sincronizarMontoPago() {
	var metodo = ($("#metodo_pago").val() || "EFECTIVO").toUpperCase();
	var total  = parseFloat($("#total_venta").val() || 0);

	if (metodo === "MIXTO") {
		$("#seccionMixto").show();
		// En MIXTO, el usuario llena los campos visibles; sincronizar a ocultos
		$("#monto_efectivo").val(parseFloat($("#vis_efectivo").val() || 0).toFixed(2));
		$("#monto_tarjeta").val(parseFloat($("#vis_tarjeta").val()   || 0).toFixed(2));
		$("#monto_digital").val(parseFloat($("#vis_digital").val()   || 0).toFixed(2));
	} else {
		$("#seccionMixto").hide();
		// Para métodos simples, el monto va todo al campo correspondiente
		var ef = 0, tj = 0, dg = 0;
		if (metodo === "EFECTIVO")      { ef = total; }
		else if (metodo === "TARJETA" || metodo === "TRANSFERENCIA") { tj = total; }
		else if (metodo === "YAPE" || metodo === "PLIN")             { dg = total; }
		$("#monto_efectivo").val(ef.toFixed(2));
		$("#monto_tarjeta").val(tj.toFixed(2));
		$("#monto_digital").val(dg.toFixed(2));
	}
}

function validarMontoPago() {
	var metodo = ($("#metodo_pago").val() || "EFECTIVO").toUpperCase();
	if (metodo !== "MIXTO") return true;
	var total = parseFloat($("#total_venta").val() || 0);
	var suma  = parseFloat($("#vis_efectivo").val() || 0)
	          + parseFloat($("#vis_tarjeta").val()  || 0)
	          + parseFloat($("#vis_digital").val()  || 0);
	if (Math.abs(suma - total) > 0.01) {
		notifyVenta("warning", "La suma de montos (" + suma.toFixed(2) + ") no coincide con el total de la venta (" + total.toFixed(2) + ").");
		return false;
	}
	return true;
}

function evaluar(){

	if (detalles>0) 
	{
		$("#btnGuardar").show();
	}
	else
	{
		$("#btnGuardar").hide();
		cont=0;
	}
}

function eliminarDetalle(indice){
	$("#fila"+indice).remove();
	calcularTotales();
	detalles=detalles-1;
	actualizarContadorItems();
	renderCarritoVisual();
}

function actualizarContadorItems(){
	var count = document.getElementsByName("idarticulo[]").length;
	$("#ventasItemsSeleccionados").text(count);
}

function estilizarBuscadorCatalogo(){
	var $filtro = $("#tblarticulos_filter");
	if ($filtro.length) {
		$filtro.addClass("catalog-search-wrap");
		$filtro.find("label").addClass("catalog-search-label");
		$filtro.find("input").addClass("catalog-search-input").attr("placeholder","Buscar por nombre o cÃ³digo");
	}
}

function encolarCodigoPOS(codigo){
	var limpio = (codigo || "").trim();
	if (!limpio) {
		notifyVenta("warning", "Ingresa o escanea un codigo de producto.");
		return;
	}
	clearTimeout(posScanTimer);
	posCodeQueue.push(limpio);
	$("#codigo_rapido").val("");
	procesarColaPOS();
}

function procesarColaPOS(){
	if (posProcessing || posCodeQueue.length === 0) {
		return;
	}
	posProcessing = true;
	var codigo = posCodeQueue.shift();
	buscarCodigoRapido(codigo, function(){
		posProcessing = false;
		procesarColaPOS();
	});
}

function extraerCodigoDeQR(raw) {
	var s = (raw || "").trim();
	// Si parece una URL, extraer parámetro "code", "codigo" o el último segmento del path
	if (/^https?:\/\//i.test(s)) {
		try {
			var url = new URL(s);
			var code = url.searchParams.get("code") || url.searchParams.get("codigo") || url.searchParams.get("sku");
			if (code) return code.trim();
			// último segmento del path
			var segs = url.pathname.split("/").filter(Boolean);
			if (segs.length > 0) return segs[segs.length - 1].trim();
		} catch(e) {}
	}
	return s;
}

function buscarCodigoRapido(codigoForzado, callback){
	var codigoRaw = (codigoForzado || $("#codigo_rapido").val() || "").trim();
	var codigo = extraerCodigoDeQR(codigoRaw);
	if (!codigo) {
		notifyVenta("warning", "Ingresa o escanea un codigo de producto.");
		if (typeof callback === "function") {
			callback();
		}
		return;
	}

	$.post("../ajax/venta.php?op=buscarArticuloCodigo", {codigo: codigo}, function(resp){
		var r = {};
		try {
			r = JSON.parse(resp);
		} catch (e) {
			notifyVenta("error", "No se pudo buscar el articulo por codigo.");
			if (typeof callback === "function") {
				callback();
			}
			return;
		}

		if (!r.ok) {
			notifyVenta("warning", r.message || "No se encontro el articulo");
			if (typeof callback === "function") {
				callback();
			}
			return;
		}

		agregarDetalle(r.idarticulo, r.nombre, r.precio_venta || 0, r.unidad || "und", r.stock || 0, r.tipo_venta || 'OTC');
		$("#codigo_rapido").focus();
		if (typeof callback === "function") {
			callback();
		}
	});
}

// Auto-foco en el campo de escaneo cuando no hay modales activos
$(document).on("click", function(e){
	if ($(".modal.in").length > 0) return;
	if ($("#formularioregistros").is(":visible")) {
		var $t = $(e.target);
		if (!$t.is("input,select,textarea,button,a") && !$t.closest("input,select,textarea,button,a,table").length) {
			$("#codigo_rapido").focus();
		}
	}
});
// Re-foco después de cerrar cualquier modal
$(document).on("hidden.bs.modal", ".modal", function(){
	if ($("#formularioregistros").is(":visible")) {
		setTimeout(function(){ $("#codigo_rapido").focus(); }, 200);
	}
});

init();

// ── Perfil farmacológico del paciente ────────────────────────────
function verPerfilPaciente(){
	var idcliente = ($("#idcliente").val() || "").toString().trim();
	if (!idcliente || idcliente === "0") {
		notifyVenta("warning", "Selecciona un cliente antes de ver el perfil.");
		return;
	}
	$("#perfilMsg").hide();
	$("#pp_alergias,#pp_condiciones,#pp_medicamentos,#pp_observaciones").val("");
	$("#perfilNombrePaciente").text("");
	$("#modalPerfilPaciente").modal("show");
	$.get("../ajax/paciente_perfil.php?op=obtener&idpersona=" + idcliente, function(resp){
		var r = {};
		try { r = (typeof resp === "string") ? JSON.parse(resp) : resp; } catch(e) {}
		if (r.ok && r.data && r.data.idperfil) {
			$("#pp_alergias").val(r.data.alergias || "");
			$("#pp_condiciones").val(r.data.condiciones_cron || "");
			$("#pp_medicamentos").val(r.data.medicamentos_cron || "");
			$("#pp_observaciones").val(r.data.observaciones || "");
			$("#perfilNombrePaciente").text("Paciente: " + (r.data.nombre || ""));
			if (r.data.alergias && r.data.alergias.trim()) {
				$("#perfilMsg").removeClass("alert-info alert-success").addClass("alert alert-danger")
					.html("<strong><i class='fa fa-exclamation-triangle'></i> ALERTA:</strong> Este paciente tiene alergias registradas: " + r.data.alergias)
					.show();
			}
		} else {
			var nombre = $("#idcliente option:selected").text();
			$("#perfilNombrePaciente").text("Paciente: " + nombre + " (sin perfil registrado aún)");
		}
	});
}

function guardarPerfilPaciente(){
	var idcliente = ($("#idcliente").val() || "").toString().trim();
	if (!idcliente) { notifyVenta("warning", "Selecciona un cliente primero."); return; }
	var fd = new FormData();
	fd.append("idpersona",        idcliente);
	fd.append("alergias",         $("#pp_alergias").val());
	fd.append("condiciones_cron", $("#pp_condiciones").val());
	fd.append("medicamentos_cron",$("#pp_medicamentos").val());
	fd.append("observaciones",    $("#pp_observaciones").val());
	var $btn = $("#btnGuardarPerfil").prop("disabled", true).html('<i class="fa fa-spinner fa-spin"></i> Guardando...');
	fetch("../ajax/paciente_perfil.php?op=guardar", {method:"POST", body:fd})
		.then(function(r){ return r.json(); })
		.then(function(r){
			$btn.prop("disabled", false).html('<i class="fa fa-save"></i> Guardar perfil');
			if (r.ok) {
				notifyVenta("success", "Perfil guardado correctamente.");
				$("#modalPerfilPaciente").modal("hide");
			} else {
				notifyVenta("error", r.message || "No se pudo guardar el perfil.");
			}
		})
		.catch(function(){
			$btn.prop("disabled", false).html('<i class="fa fa-save"></i> Guardar perfil');
		});
}
