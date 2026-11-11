var tabla;
var tablaArticulos;

var QZ_PRINTER_KEY = 'qz_preferred_printer_farmacia';

function abrirGavetaQZ() {
	if (!window.qz) return;
	var printer = '';
	try { printer = localStorage.getItem(QZ_PRINTER_KEY) || ''; } catch(e) {}
	if (!printer) return;

	// ESC p pin t1 t2 (Epson/compatible) y DLE DC4 (impresoras chinas: XPrinter, HOIN, Rongta)
	var pin2a  = btoa('\x1B\x70\x00\x3C\x78');  // pin 2 — 60/120 ms
	var pin2b  = btoa('\x1B\x70\x00\x19\xFA');  // pin 2 — 25/250 ms
	var pin5   = btoa('\x1B\x70\x01\x3C\x78');  // pin 5
	var dleDc4 = btoa('\x10\x14\x01\x00\x05');  // DLE DC4 — alternativo para impresoras chinas

	function enviar(b64) {
		var cfg = qz.configs.create(printer);
		return qz.print(cfg, [{ type: 'raw', format: 'plain', flavor: 'base64', data: b64 }]);
	}

	function enviarComando() {
		return enviar(pin2a)
			.catch(function() { return enviar(pin2b); })
			.catch(function() { return enviar(pin5); })
			.catch(function() { return enviar(dleDc4); });
	}

	function setupSeguridad() {
		qz.security.setCertificatePromise(function(resolve, reject) {
			fetch('../public/qz_cert.pem')
				.then(function(r) { return r.text(); })
				.then(resolve)
				.catch(reject);
		});
		qz.security.setSignatureAlgorithm('SHA512');
		qz.security.setSignaturePromise(function(toSign) {
			return function(resolve, reject) {
				$.ajax({
					url: '../ajax/qz_sign.php',
					type: 'POST',
					data: toSign,
					contentType: 'text/plain',
					success: resolve,
					error: function(xhr) { reject(new Error(xhr.statusText || 'Error al firmar')); }
				});
			};
		});
	}

	if (qz.websocket.isActive()) {
		enviarComando().catch(function() {});
		return;
	}

	setupSeguridad();
	qz.websocket.connect({ host: 'localhost', port: { secure: [8181], insecure: [8182] }, usingSecure: true, keepAlive: 30 })
		.then(enviarComando)
		.catch(function() {
			qz.websocket.connect({ host: 'localhost', port: { secure: [8181], insecure: [8182] }, usingSecure: false, keepAlive: 30 })
				.then(enviarComando)
				.catch(function() {});
		});
}
var recetaModalConfirmada = false;
var borradorTimerVenta = null;

// POS grid state
var posProductos = [];         // full product list loaded from server
var posCatActiva = '';         // '' = all
var posSearchTimer = null;
var posCurrentPage = 1;        // página actual del grid (1-indexed)
var posPorPagina   = 20;       // productos por página
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
		recalcularPorPaginaPOS();
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
		renderPaginador(0, 0);
		return;
	}

	var totalPaginas = Math.ceil(lista.length / posPorPagina);
	if (posCurrentPage > totalPaginas) posCurrentPage = totalPaginas;
	if (posCurrentPage < 1) posCurrentPage = 1;

	var inicio  = (posCurrentPage - 1) * posPorPagina;
	var fin     = Math.min(inicio + posPorPagina, lista.length);
	var pagina  = lista.slice(inicio, fin);

	var html = '';
	pagina.forEach(function(p) {
		var sinStock = p.stock <= 0;
		var simbolo  = window.appCurrencySymbol || 'S/';
		var enOferta = !!p.en_oferta && parseFloat(p.descuento_porcentaje) > 0;
		var precio   = (p.precio_venta > 0) ? (simbolo + ' ' + p.precio_venta.toFixed(2)) : 'Sin precio';
		var precioHtml = precio;
		if (enOferta && p.precio_venta > 0) {
			precioHtml = '<span class="pos-card-precio-tachado">' + simbolo + ' ' + parseFloat(p.precio_venta_original || 0).toFixed(2) + '</span> ' + precio;
		}
		var badgeCls = p.tipo_venta === 'OTC' ? 'badge-otc' : (p.tipo_venta === 'RX' ? 'badge-rx' : 'badge-ctrl');
		var badgeTxt = p.tipo_venta === 'CONTROL_ESPECIAL' ? 'CTRL' : p.tipo_venta;
		var imgSrc   = p.imagen ? '../files/articulos/' + p.imagen : '../public/img/default-50x50.gif';
		var generico = p.principio_activo ? (p.principio_activo + (p.concentracion ? ' ' + p.concentracion : '')) : '';

		var cardCls = 'pos-card' + (sinStock ? ' sin-stock' : '');
		var onclick  = sinStock ? '' : 'onclick="posCardClick(' + p.idarticulo + ')"';

		html += '<div class="' + cardCls + '" data-id="' + p.idarticulo + '" ' + onclick + '>' +
			(enOferta ? '<span class="pos-card-oferta-ribbon">-' + parseFloat(p.descuento_porcentaje).toFixed(0) + '%</span>' : '') +
			'<img class="pos-card-img" src="' + imgSrc + '" onerror="this.src=\'../public/img/default-50x50.gif\'" alt="">' +
			'<div class="pos-card-nombre">' + $('<span>').text(p.nombre).html() + '</div>' +
			(generico ? '<div class="pos-card-generico">' + $('<span>').text(generico).html() + '</div>' : '') +
			((p.presentaciones && p.presentaciones.length > 1)
				? '<div class="pos-card-pres" title="Se vende por: ' + $('<span>').text(p.presentaciones.map(etiquetaPresentacion).join(', ')).html() + '"><i class="fa fa-cubes"></i> ' +
				  $('<span>').text(p.presentaciones.map(function(x){ return x.abrev; }).join(' · ')).html() + '</div>'
				: '') +
			((basePresentacion(p.presentaciones) && parseFloat(basePresentacion(p.presentaciones).precio_dni) > 0)
				? '<div class="pos-card-dni" title="Precio para cliente registrado"><i class="fa fa-id-card"></i> Con DNI ' + simbolo + ' ' + parseFloat(basePresentacion(p.presentaciones).precio_dni).toFixed(2) + '</div>'
				: '') +
			'<div class="pos-card-footer">' +
				'<span class="pos-card-precio">' + precioHtml + '</span>' +
				'<span class="pos-card-badge ' + badgeCls + '">' + badgeTxt + '</span>' +
			'</div>' +
		'</div>';
	});
	$("#posProductGrid").html(html);
	renderPaginador(totalPaginas, lista.length);
	// Siempre volver al tope al cambiar página
	var $grid = $("#posProductGrid");
	if ($grid[0]) $grid[0].scrollTop = 0;
}

function renderPaginador(totalPaginas, totalItems) {
	var $footer = $("#posGridFooter");
	if (totalPaginas <= 1) {
		$footer.hide();
		return;
	}
	$footer.show();

	var desde = (posCurrentPage - 1) * posPorPagina + 1;
	var hasta = Math.min(posCurrentPage * posPorPagina, totalItems);

	var html = '<div class="pos-paginador">';
	html += '<span class="pos-pag-info">' + desde + '-' + hasta + ' de ' + totalItems + '</span>';
	html += '<div class="pos-pag-btns">';

	// Anterior
	html += '<button type="button" class="pos-pag-btn" onclick="posIrPagina(' + (posCurrentPage - 1) + ')"' + (posCurrentPage <= 1 ? ' disabled' : '') + '>' +
	        '<i class="fa fa-chevron-left"></i></button>';

	// Páginas (máx 5 visibles, centradas en la actual)
	var startPg = Math.max(1, posCurrentPage - 2);
	var endPg   = Math.min(totalPaginas, posCurrentPage + 2);
	// Ajustar ventana para mantener siempre 5 botones si hay suficientes
	if (endPg - startPg < 4) {
		if (startPg === 1) { endPg = Math.min(totalPaginas, startPg + 4); }
		else               { startPg = Math.max(1, endPg - 4); }
	}

	if (startPg > 1) {
		html += '<button type="button" class="pos-pag-btn" onclick="posIrPagina(1)">1</button>';
		if (startPg > 2) html += '<span class="pos-pag-dots">&hellip;</span>';
	}
	for (var i = startPg; i <= endPg; i++) {
		html += '<button type="button" class="pos-pag-btn' + (i === posCurrentPage ? ' active' : '') + '" onclick="posIrPagina(' + i + ')">' + i + '</button>';
	}
	if (endPg < totalPaginas) {
		if (endPg < totalPaginas - 1) html += '<span class="pos-pag-dots">&hellip;</span>';
		html += '<button type="button" class="pos-pag-btn" onclick="posIrPagina(' + totalPaginas + ')">' + totalPaginas + '</button>';
	}

	// Siguiente
	html += '<button type="button" class="pos-pag-btn" onclick="posIrPagina(' + (posCurrentPage + 1) + ')"' + (posCurrentPage >= totalPaginas ? ' disabled' : '') + '>' +
	        '<i class="fa fa-chevron-right"></i></button>';

	html += '</div></div>';
	$footer.html(html);
}

function posIrPagina(pag) {
	var totalPaginas = Math.ceil(
		posProductos.filter(function(p) {
			var catId = posCatActiva ? parseInt(posCatActiva) : 0;
			if (catId && p.idcategoria !== catId) return false;
			var termino = ($("#posSearchInput").val() || "").toLowerCase().trim();
			if (termino) {
				return (p.nombre + ' ' + p.principio_activo + ' ' + p.codigo + ' ' + p.categoria)
				       .toLowerCase().indexOf(termino) !== -1;
			}
			return true;
		}).length / posPorPagina
	);
	posCurrentPage = Math.max(1, Math.min(pag, totalPaginas));
	renderGrid();
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
	agregarDetalle(p.idarticulo, p.nombre, p.precio_venta, p.abreviatura, p.stock, p.tipo_venta, p.presentaciones);
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
		var presFila  = leerPresentacionesFila(fila);
		var presSelId = String((fila.querySelector('input[name="idpresentacion[]"]') || {}).value || '');
		var presActual = buscarPresentacion(presFila, presSelId);
		var tagDni = (presActual && parseFloat(presActual.precio_dni) > 0 && Math.round(precio * 100) === Math.round(parseFloat(presActual.precio_dni) * 100))
			? ' <span class="pos-item-dni" title="Precio regular ' + sym + ' ' + parseFloat(presActual.precio).toFixed(2) + '">DNI</span>' : '';
		var lineaUnidad;
		if (presFila.length > 1) {
			var opts = '';
			for (var k = 0; k < presFila.length; k++) {
				var pr = presFila[k];
				opts += '<option value="' + pr.id + '"' + (String(pr.id) === presSelId ? ' selected' : '') + '>' +
					$('<span>').text(etiquetaPresentacion(pr)).html() + '</option>';
			}
			lineaUnidad = '<select class="pos-item-pres" title="Cambiar presentación" onchange="cambiarPresentacionFila(' + idx + ', this.value)">' + opts + '</select> ' +
				sym + ' ' + precio.toFixed(2) + ' c/u' + tagDni;
		} else {
			lineaUnidad = (unidad ? $('<span>').text(unidad).html() + ' · ' : '') + sym + ' ' + precio.toFixed(2) + ' c/u' + tagDni;
		}

		html += '<div class="pos-item" id="posItem' + idx + '">' +
			'<div class="pos-item-info">' +
				'<div class="pos-item-nombre" title="' + $('<span>').text(nombre).html() + '">' + $('<span>').text(nombre).html() + '</div>' +
				'<div class="pos-item-generico">' + lineaUnidad + '</div>' +
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

// Confirmación visual al agregar: la fila del carrito destella (sin notificaciones que tapen
// el carrito). En móvil, el botón flotante hace un pulso porque el carrito está oculto.
function resaltarItemCarrito(idx) {
	var $item = $("#posItem" + idx);
	if ($item.length) {
		$item[0].scrollIntoView({ block: "nearest" });
		$item.removeClass("pos-item-flash");
		void $item[0].offsetWidth;
		$item.addClass("pos-item-flash");
	}
	var $btn = $("#posFloatCartBtn");
	$btn.removeClass("pos-float-pulse");
	void ($btn[0] && $btn[0].offsetWidth);
	$btn.addClass("pos-float-pulse");
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
   	posSearchTimer = setTimeout(function() { posCurrentPage = 1; renderGrid(); }, 200);
   });
   $("#posSearchInput").on("keydown", function(e) {
   	if (e.key === "Enter") { clearTimeout(posSearchTimer); posCurrentPage = 1; renderGrid(); }
   });

   // Category pills (delegated, pills added dynamically)
   $(document).on("click", ".pos-cat-pill", function() {
   	$(".pos-cat-pill").removeClass("active");
   	$(this).addClass("active");
   	posCatActiva = $(this).attr("data-cat") || '';
   	posCurrentPage = 1;
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
   setInterval(guardarBorradorVenta, 30000);
   $(document).on("input", "#detalles input[name='cantidad[]'], #detalles input[name='precio_venta[]'], #detalles input[name='descuento[]']", function(){
   	clearTimeout(borradorTimerVenta);
   	borradorTimerVenta = setTimeout(guardarBorradorVenta, 1500);
   });

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

   // Receta médica Rx (registro opcional: no bloquea la venta)
   $("#btnConfirmarReceta").on("click", function(){
   	var nombreMedico = $.trim($("#rx_nombre_medico").val());
   	var colegiatura  = $.trim($("#rx_colegiatura").val());
   	// Solo se guarda la receta si el cajero ingresó al menos el nombre del médico o la colegiatura.
   	recetaModalConfirmada = !!(nombreMedico || colegiatura);
   	$('#modalReceta').modal('hide');
   	ejecutarGuardarVenta();
   });
   $("#btnVenderSinReceta").on("click", function(){
   	recetaModalConfirmada = false;
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
   	if (e.key === "Escape" && $("#posCarritoPanel").hasClass("carrito-open")) {
   		cerrarCarritoMobile();
   	}
   	if (e.key === "F10") {
   		e.preventDefault();
   		if (!cajaCerrada && $("#btnGuardar").is(":visible")) {
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
		aplicarPrecioClienteCarrito();
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
var POS_BREAK_MOVIL = 767;
function esMobil(){
	return window.innerWidth <= POS_BREAK_MOVIL;
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
	if (!esMobil() || !$("body").hasClass("pos-activo")) {
		$("#posFloatCartBtn").hide();
		return;
	}
	var count = document.querySelectorAll('#detalles .filas').length;
	var total = $("#posTotalFinal").text() || "S/ 0.00";
	$("#posFloatBadge").text(count);
	$("#posFloatTotal").text(total);
	// Mostrar/ocultar según si hay productos
	if (count > 0) {
		$("#posFloatCartBtn").css("display", "flex");
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
				aplicarPrecioClienteCarrito();
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

// Calcula la altura exacta del pos-wrapper: desde su borde superior real
// (debajo del header, sea de 50px, 54px o 80px) hasta el fondo de la ventana.
// visualViewport evita que la barra del navegador móvil tape el botón COBRAR.
function ajustarAlturaPOS() {
	var wrapper = document.getElementById('formularioregistros');
	var cw = document.getElementById('mainCW');
	if (!wrapper || !cw || wrapper.style.display === 'none') return;
	// El alto lo calcula el CSS (100dvh - --pos-top), así se adapta solo al zoom del
	// navegador aunque no llegue ningún evento; aquí solo se mide dónde empieza el POS.
	var top = Math.max(0, Math.round(cw.getBoundingClientRect().top + (window.scrollY || 0)));
	document.documentElement.style.setProperty('--pos-top', top + 'px');
	if (window.scrollY) window.scrollTo(0, 0);
	if (!esMobil()) cerrarCarritoMobile();
	actualizarFloatCartBtn();
	recalcularPorPaginaPOS();
}

// Cuántas tarjetas caben: columnas reales del grid × filas visibles.
// En móvil se hace scroll, así que se muestran más filas por página.
function recalcularPorPaginaPOS() {
	var grid = document.getElementById('posProductGrid');
	if (!grid || !grid.clientWidth) return;
	var cols = (getComputedStyle(grid).gridTemplateColumns || '').split(' ').filter(Boolean).length || 4;
	var card = grid.querySelector('.pos-card');
	var cardH = card ? card.getBoundingClientRect().height + 8 : 150;
	// Si sobra más del 60% de una fila se incluye (queda un leve scroll) para no dejar hueco
	var filas = esMobil() ? 6 : Math.max(2, Math.floor((grid.clientHeight - 16 + cardH * 0.4) / cardH));
	var nuevo = Math.min(Math.max(cols * filas, 8), 60);
	if (nuevo !== posPorPagina) {
		var primero = (posCurrentPage - 1) * posPorPagina;
		posPorPagina = nuevo;
		posCurrentPage = Math.floor(primero / posPorPagina) + 1;
		if (posProductos.length) renderGrid();
	}
}

// Si cambia el alto disponible del grid (aparece/desaparece un aviso, se abre el
// teclado en tablet...) se recalcula cuántos productos caben por página.
var posGridObserver = null;
if (window.ResizeObserver) {
	posGridObserver = new ResizeObserver(function(){
		clearTimeout(posGridTimer);
		posGridTimer = setTimeout(recalcularPorPaginaPOS, 120);
	});
}

// Temporizadores separados: el del grid no debe cancelar el ajuste de altura
var posGridTimer = null;
var posResizeTimer = null;
function onResizePOS() {
	clearTimeout(posResizeTimer);
	posResizeTimer = setTimeout(ajustarAlturaPOS, 120);
}

// En modo POS se colapsa el menú lateral para ganar ancho (salvo en monitores grandes);
// al salir se restaura como estaba.
var posColapsoSidebar = false;
function colapsarSidebarPOS(activar) {
	var $b = $("body");
	if (activar) {
		if (window.innerWidth > POS_BREAK_MOVIL && window.innerWidth < 1600 && !$b.hasClass("sidebar-collapse")) {
			$b.addClass("sidebar-collapse");
			posColapsoSidebar = true;
		}
	} else if (posColapsoSidebar) {
		$b.removeClass("sidebar-collapse");
		posColapsoSidebar = false;
	}
}

//funcion mostrar formulario
function mostrarform(flag, esNuevo){
	limpiar();
	if(flag){
		if (cajaCerrada) {
			notifyVenta("warning", "Debes abrir la caja antes de registrar ventas.");
			return;
		}
		$("body").addClass("pos-activo");
		$("html").css("overflow", "hidden");
		colapsarSidebarPOS(true);
		// scroll al tope antes de mostrar el POS para garantizar posición correcta
		window.scrollTo(0, 0);
		$("#listadoregistros, #resumenPagosVenta").hide();
		$("#formularioregistros").show();
		ajustarAlturaPOS();
		$(window).on('resize.pos orientationchange.pos', onResizePOS);
		if (window.visualViewport) window.visualViewport.addEventListener('resize', onResizePOS);
		if (posGridObserver) posGridObserver.observe(document.getElementById('posProductGrid'));
		// El menú lateral anima su ancho: recalcular cuando termina
		setTimeout(ajustarAlturaPOS, 350);
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
		if (esNuevo) {
			restaurarBorradorVenta();
		}
	}else{
		$(window).off('resize.pos orientationchange.pos');
		if (window.visualViewport) window.visualViewport.removeEventListener('resize', onResizePOS);
		if (posGridObserver) posGridObserver.disconnect();
		cerrarCarritoMobile();
		$("#posFloatCartBtn").hide();
		$("body").removeClass("pos-activo");
		$("html").css("overflow", "");
		colapsarSidebarPOS(false);
		$("#listadoregistros, #resumenPagosVenta").show();
		$("#formularioregistros").hide();
		document.documentElement.style.removeProperty('--pos-top');
		$("#btnagregar").show();
	}
}

//cancelar form
function cancelarform(){
	// Restaurar modo normal al salir de vista
	$('.pos-pagos').show();
	$('.pos-cobrar-section').show();
	$('.pos-topbar').show();
	$('#posSearchInput').prop('disabled', false).attr('placeholder', 'Buscar medicamento por nombre o código... (F2)');
	$('#pos-vista-banner').remove();
	$('#btnCancelar').html('<i class="fa fa-arrow-left"></i> Cancelar venta');
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

     // La receta médica es opcional y no bloquea la emisión de la boleta.
     ejecutarGuardarVenta();
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
	if (tieneArticulosControlEspecial()) {
		$('#rx_bloque_diagnostico').show();
	} else {
		$('#rx_bloque_diagnostico').hide();
		$('#rx_diagnostico').val('');
	}
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
		idventa:     idventa,
		idreceta:    idreceta || 0,
		diagnostico: $.trim($("#rx_diagnostico").val())
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
	limpiarBorradorVenta();
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
		// Abrir gaveta via QZ Tray usando la impresora guardada en localStorage
		abrirGavetaQZ();
	}
	mostrarform(true, true);
	listar();
}

function mostrar(idventa){
	$.post("../ajax/venta.php?op=mostrar", {idventa: idventa}, function(data) {
		data = JSON.parse(data);

		// limpiar + mostrar form primero (esto llama limpiar() internamente)
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

		$("#btnGuardar").hide();
		$("#btnCancelar").show();
		$("#btnAgregarArt").hide();

		// Modo vista: ocultar cobro y pagos
		$('.pos-pagos').hide();
		$('.pos-cobrar-section').hide();
		$('.pos-topbar').hide();
		$('#posSearchInput').prop('disabled', true).attr('placeholder', 'Modo vista — solo lectura');

		if (!$('#pos-vista-banner').length) {
			$('#formularioregistros').prepend(
				'<div id="pos-vista-banner" style="background:#17a2b8;color:#fff;padding:8px 16px;font-weight:600;font-size:13px;text-align:center;">' +
				'<i class="fa fa-eye"></i>&nbsp; MODO VISTA — Esta venta ya fue registrada' +
				'</div>'
			);
		}
		$('#btnCancelar').html('<i class="fa fa-arrow-left"></i> Volver');

		// AHORA cargar el detalle (después de que limpiar() ya corrió)
		$.get("../ajax/venta.php?op=listarDetalleJSON&id=" + idventa, function(r) {
			var items = [];
			try { items = JSON.parse(r); } catch(e) { return; }

			$(".filas").remove();
			cont = 0;
			detalles = 0;

			for (var i = 0; i < items.length; i++) {
				var it  = items[i];
				var sub = (it.cantidad * it.precio_venta - it.descuento).toFixed(2);
				var fila = '<tr class="filas" id="fila' + cont + '" data-tipo-venta="OTC">' +
					'<td></td>' +
					'<td><input type="hidden" name="idarticulo[]" value="' + it.idarticulo + '">' +
					'<input type="hidden" name="stock_disponible[]" value="' + it.stock + '">' +
					$('<span>').text(it.nombre).html() + '</td>' +
					'<td>' + $('<span>').text(it.unidad).html() + '</td>' +
					'<td><input type="number" name="cantidad[]" value="' + it.cantidad + '" readonly style="width:55px;text-align:center;"></td>' +
					'<td><input type="number" name="precio_venta[]" value="' + it.precio_venta + '" readonly style="width:70px;text-align:right;"></td>' +
					'<td><input type="number" name="descuento[]" value="' + it.descuento + '" readonly style="width:55px;text-align:right;"></td>' +
					'<td><span id="subtotal' + cont + '" name="subtotal">' + sub + '</span></td>' +
					'<td></td>' +
					'</tr>';
				cont++;
				detalles++;
				$('#detalles').append(fila);
			}
			modificarSubtotales();
			renderCarritoVisual();
		});
	});
}


// Anular y eliminar ventas: si el usuario no tiene el permiso, un administrador
// debe autorizar con su usuario y clave (ver public/js/app-autorizar.js).
function eliminarVenta(idventa, comprobante){
	var aut = {
		recurso: "venta:" + idventa,
		titulo: "Eliminar venta anulada",
		detalle: comprobante || "",
		mensaje: "La venta anulada y su detalle se borrarán definitivamente. Esta acción no se puede deshacer.",
		boton: "Autorizar y eliminar",
		peligro: true
	};
	var enviar = function(){
		$.post("../ajax/venta.php?op=eliminarDefinitivo", {idventa: idventa}, function(raw){
			var r = appAutorizar.parse(raw);
			if (appAutorizar.manejar(r, aut, enviar)) return;
			notifyVenta(r.ok ? "success" : "error", r.message || (r.ok ? "Venta eliminada" : "No se pudo eliminar la venta"));
			if (r.ok) tabla.ajax.reload(null, false);
		}).fail(function(){ notifyVenta("error", "No se pudo conectar con el servidor. Revisa tu conexión e inténtalo de nuevo."); });
	};
	appAutorizar($.extend({}, aut, { onAutorizado: function(yaConfirmado){
		if (yaConfirmado) { enviar(); return; }
		appEliminar({
			url: "../ajax/venta.php?op=eliminarDefinitivo",
			data: {idventa: idventa},
			titulo: "Eliminar venta anulada",
			mensaje: aut.mensaje,
			onSuccess: function(){ tabla.ajax.reload(null, false); }
		});
	}}));
}

function anular(idventa, comprobante){
	var aut = {
		recurso: "venta:" + idventa,
		titulo: "Anular venta",
		detalle: comprobante || "",
		mensaje: "La venta quedará anulada y, si se cobró en efectivo, se registrará la devolución en caja.",
		boton: "Autorizar y anular",
		peligro: true
	};
	var enviar = function(){
		$.post("../ajax/venta.php?op=anular", {idventa : idventa}, function(raw){
			var r = appAutorizar.parse(raw);
			if (appAutorizar.manejar(r, aut, enviar)) return;
			notifyVenta(r.ok ? "success" : "error", r.message || (r.ok ? "Venta anulada" : "No se pudo anular la venta"));
			if (r.ok) tabla.ajax.reload(null, false);
		}).fail(function(){ notifyVenta("error", "No se pudo conectar con el servidor. Revisa tu conexión e inténtalo de nuevo."); });
	};
	appAutorizar($.extend({}, aut, { onAutorizado: function(yaConfirmado){
		if (yaConfirmado) { enviar(); return; }
		bootbox.confirm("¿Seguro que deseas anular la venta " + $("<span>").text(comprobante || "").html() + "?", function(result){
			if (result) enviar();
		});
	}}));
}

//declaramos variables necesarias para trabajar con las compras y sus detalles
var impuesto=18;
var cont=0;
var detalles=0;
var cajaCerrada = false;

$("#btnGuardar").hide();
$("#tipo_comprobante").change(marcarImpuesto);

// Verificar estado de caja al cargar la pantalla de ventas
(function verificarEstadoCaja() {
	$.getJSON("../ajax/caja.php?op=estado", function(r) {
		if (!r.abierta) {
			cajaCerrada = true;
			var $btn = $("#btnagregar");
			$btn.prop("disabled", true)
				.attr("title", "Debes abrir la caja antes de vender")
				.html('<i class="fa fa-lock"></i> Nueva Venta');

			if (!$("#banner-caja-cerrada").length) {
				$(".content-wrapper").prepend(
					'<div id="banner-caja-cerrada" style="background:#e74c3c;color:#fff;padding:10px 18px;font-weight:600;font-size:14px;text-align:center;">' +
					'<i class="fa fa-exclamation-triangle"></i>&nbsp; La caja está cerrada. ' +
					'<a href="caja.php" style="color:#fff;text-decoration:underline;margin-left:8px;">Abrir caja</a>' +
					'</div>'
				);
			}
		}
	});
})();

function marcarImpuesto(){
	aplicarSerieImpuesto();
}

function aplicarSerieImpuesto(){
	var tipo = $("#tipo_comprobante").val();
	var igv = (empresaDefaults.impuesto_default || 18).toFixed(2);
	if (tipo==='Factura') {
		$("#serie_comprobante").val(empresaDefaults.serie_factura || "F001");
	} else if (tipo==='Ticket') {
		$("#serie_comprobante").val(empresaDefaults.serie_ticket || "T001");
	} else {
		$("#serie_comprobante").val(empresaDefaults.serie_boleta || "B001");
	}
	$("#impuesto").val(igv);
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

// ── Presentaciones de venta (Tableta, Blíster x10, Caja x100...) ──────────
// El stock llega en la unidad base; cada línea guarda su presentación y su factor.
function etiquetaPresentacion(p){
	var nom = p.unidad || p.abrev || 'und';
	return nom + (p.factor > 1 ? ' x' + (+p.factor) : '');
}

// ── Precio con DNI: se cobra cuando la venta tiene un cliente registrado (no "Consumidor Final") ──
function clienteConDni(){
	var v = String($("#idcliente").val() || "0").trim();
	return v !== "" && v !== "0";
}

function precioParaCliente(pres){
	var dni = parseFloat(pres && pres.precio_dni) || 0;
	return (clienteConDni() && dni > 0) ? dni : (parseFloat(pres && pres.precio) || 0);
}

function basePresentacion(lista){
	for (var i = 0; i < (lista || []).length; i++) { if (lista[i].es_base) return lista[i]; }
	return (lista && lista[0]) || null;
}

// Al cambiar el cliente se recalculan los precios del carrito (solo los que el cajero no editó a mano)
function aplicarPrecioClienteCarrito(){
	var filas = document.querySelectorAll('#detalles .filas');
	var cambiadas = 0;
	for (var i = 0; i < filas.length; i++) {
		var fila = filas[i];
		var p = buscarPresentacion(leerPresentacionesFila(fila), (fila.querySelector('input[name="idpresentacion[]"]') || {}).value || 0);
		if (!p || !(parseFloat(p.precio_dni) > 0)) continue;
		var inp = fila.querySelector('input[name="precio_venta[]"]');
		var actual = Math.round((parseFloat(inp.value) || 0) * 100);
		var esAuto = actual === Math.round(parseFloat(p.precio) * 100) || actual === Math.round(parseFloat(p.precio_dni) * 100);
		if (!esAuto) continue;
		var nuevo = precioParaCliente(p);
		if (Math.round(nuevo * 100) !== actual) { inp.value = nuevo.toFixed(2); cambiadas++; }
	}
	if (cambiadas > 0) {
		modificarSubtotales();
		renderCarritoVisual();
		guardarBorradorVenta();
		notifyVenta(clienteConDni() ? "success" : "warning", clienteConDni()
			? "Precio con DNI aplicado a " + cambiadas + " producto(s)."
			: "Consumidor Final: se volvió al precio regular en " + cambiadas + " producto(s).");
	}
}

function presentacionesDe(idarticulo){
	if (typeof posProductos === "undefined" || !posProductos) return null;
	for (var i = 0; i < posProductos.length; i++) {
		if (posProductos[i].idarticulo === parseInt(idarticulo, 10)) return posProductos[i].presentaciones || null;
	}
	return null;
}

function leerPresentacionesFila(fila){
	try { return JSON.parse(fila.getAttribute('data-pres') || '[]'); } catch(e) { return []; }
}

function buscarPresentacion(lista, id){
	for (var i = 0; i < lista.length; i++) { if (String(lista[i].id) === String(id)) return lista[i]; }
	return null;
}

// Construye la fila (oculta) del detalle de venta
function construirFilaVenta(o){
	var esc = function(t){ return $('<span>').text(t == null ? '' : String(t)).html(); };
	var presAttr = esc(JSON.stringify(o.presentaciones || [])).replace(/"/g, '&quot;');
	var subtotal = (o.cantidad * o.precio - (o.descuento || 0)).toFixed(2);
	return '<tr class="filas" id="fila' + o.idx + '" data-tipo-venta="' + o.tipoVenta + '" data-pres="' + presAttr + '" data-stock-base="' + o.stockBase + '">' +
		'<td><button type="button" class="btn btn-danger" onclick="eliminarDetalle(' + o.idx + ')">X</button></td>' +
		'<td><input type="hidden" name="idarticulo[]" value="' + o.idarticulo + '">' +
			'<input type="hidden" name="idpresentacion[]" value="' + (o.idpresentacion || 0) + '">' +
			'<input type="hidden" name="stock_disponible[]" value="' + o.stockMax + '">' + esc(o.nombre) + '</td>' +
		'<td class="td-unidad">' + esc(o.unidad) + '</td>' +
		'<td><input type="number" step="0.5" min="0.5" max="' + o.stockMax + '" name="cantidad[]" value="' + o.cantidad + '" oninput="modificarSubtotales()"></td>' +
		'<td><input type="number" step="0.01" min="0.01" name="precio_venta[]" value="' + o.precio + '" oninput="modificarSubtotales()"></td>' +
		'<td><input type="number" step="0.01" min="0.00" name="descuento[]" value="' + (o.descuento || 0) + '" oninput="modificarSubtotales()"></td>' +
		'<td><span id="subtotal' + o.idx + '" name="subtotal">' + subtotal + '</span></td>' +
		'<td><button type="button" onclick="modificarSubtotales()" class="btn btn-info"><i class="fa fa-refresh"></i></button></td>' +
	'</tr>';
}

function agregarDetalle(idarticulo,articulo,precio_venta,unidad,stockDisponible,tipoVenta,presentaciones,idpresInicial){
	var cantidad=1;
	var descuento=0;
	var tipoVentaNorm = (tipoVenta || 'OTC').toUpperCase();
	var stockBase = normalizarEnteroNoNegativo(stockDisponible || 0);
	var lista = (presentaciones && presentaciones.length) ? presentaciones : (presentacionesDe(idarticulo) || []);
	if (!lista.length) {
		lista = [{ id: 0, unidad: unidad || 'und', abrev: unidad || 'und', factor: 1, precio: parseFloat(precio_venta || 0), es_base: 1 }];
	}
	var base = null;
	for (var b = 0; b < lista.length; b++) { if (lista[b].es_base) { base = lista[b]; break; } }
	var pres = (idpresInicial ? buscarPresentacion(lista, idpresInicial) : null) || base || lista[0];
	var factor = parseFloat(pres.factor || 1) || 1;
	var stockMax = Math.floor(stockBase / factor);
	var unidadTexto = pres.abrev || unidad || "und";

	if (stockBase <= 0) {
		notifyVenta("warning", "Este articulo no tiene stock disponible.");
		return;
	}
	if (stockMax <= 0) {
		notifyVenta("warning", "No alcanza para 1 " + etiquetaPresentacion(pres) + ": quedan " + stockBase + " " + ((base && base.abrev) || "und") + ".");
		return;
	}
	if (idarticulo=="") {
		notifyVenta("warning", "No se pudo agregar el articulo. Revisa la informacion del producto.");
		return;
	}
	var filas = document.querySelectorAll('#detalles .filas');
	for (var i = 0; i < filas.length; i++) {
		var f = filas[i];
		var idArtF  = f.querySelector('input[name="idarticulo[]"]');
		var idPresF = f.querySelector('input[name="idpresentacion[]"]');
		if (!idArtF || parseInt(idArtF.value, 10) !== parseInt(idarticulo, 10)) continue;
		if (String(idPresF ? idPresF.value : 0) !== String(pres.id)) continue;
		var cantF = f.querySelector('input[name="cantidad[]"]');
		var stockFila = normalizarEnteroNoNegativo((f.querySelector('input[name="stock_disponible[]"]') || {}).value || stockMax);
		var nuevaCantidadNum = normalizarCantidadEntera(parseFloat(cantF.value || 0) + 1, 1);
		if (nuevaCantidadNum > stockFila) {
			notifyVenta("warning", "No puedes vender mas de " + stockFila + " " + unidadTexto + " de este producto.");
			return;
		}
		cantF.value = nuevaCantidadNum;
		modificarSubtotales();
		renderCarritoVisual();
		$('#myModal').modal('hide');
		resaltarItemCarrito(f.id.replace('fila', ''));
		guardarBorradorVenta();
		return;
	}
	var precioPres = (pres.precio !== undefined && pres.precio !== null) ? precioParaCliente(pres) : parseFloat(precio_venta || 0);
	if (!(precioPres > 0)) {
		notifyVenta("warning", "La presentación " + etiquetaPresentacion(pres) + " no tiene precio. Actualízalo en el producto.");
		return;
	}
	$('#detalles').append(construirFilaVenta({
		idx: cont, idarticulo: idarticulo, nombre: articulo, unidad: unidadTexto, tipoVenta: tipoVentaNorm,
		presentaciones: lista, idpresentacion: pres.id, stockBase: stockBase, stockMax: stockMax,
		cantidad: cantidad, precio: precioPres, descuento: descuento
	}));
	cont++;
	detalles++;
	modificarSubtotales();
	actualizarContadorItems();
	renderCarritoVisual();
	$('#myModal').modal('hide');
	resaltarItemCarrito(cont - 1);
	guardarBorradorVenta();
}

// El cajero cambia la presentación de una línea (ej. de Tableta a Caja)
function cambiarPresentacionFila(idx, idpres){
	var fila = document.getElementById('fila' + idx);
	if (!fila) return;
	var lista = leerPresentacionesFila(fila);
	var p = buscarPresentacion(lista, idpres);
	if (!p) return;
	var stockBase = parseFloat(fila.getAttribute('data-stock-base') || 0) || 0;
	var max = Math.floor(stockBase / (parseFloat(p.factor) || 1));
	if (max < 1) {
		var base = null;
		for (var b = 0; b < lista.length; b++) { if (lista[b].es_base) { base = lista[b]; break; } }
		notifyVenta("warning", "No alcanza para 1 " + etiquetaPresentacion(p) + ": quedan " + stockBase + " " + ((base && base.abrev) || "und") + ".");
		renderCarritoVisual();
		return;
	}
	fila.querySelector('input[name="idpresentacion[]"]').value = p.id;
	fila.querySelector('input[name="precio_venta[]"]').value = precioParaCliente(p).toFixed(2);
	fila.querySelector('input[name="stock_disponible[]"]').value = max;
	var cantInp = fila.querySelector('input[name="cantidad[]"]');
	cantInp.setAttribute('max', max);
	// Al cambiar de presentación la cantidad vuelve a 1 (evita cobrar 2 cajas cuando eran 2 tabletas)
	cantInp.value = 1;
	$(fila).find('.td-unidad').text(p.abrev || '');
	modificarSubtotales();
	guardarBorradorVenta();
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
	guardarBorradorVenta();
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

		agregarDetalle(r.idarticulo, r.nombre, r.precio_venta || 0, r.unidad || "und", r.stock || 0, r.tipo_venta || 'OTC', r.presentaciones || null, r.idpresentacion || 0);
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

// ── Borrador automático de venta (localStorage) ───────────────────
var BORRADOR_KEY_VEN = 'farmacia_borrador_venta';

function guardarBorradorVenta() {
	if (!$("#formularioregistros").is(":visible")) return;
	if ($("#idventa").val()) return;
	var filas = document.querySelectorAll('#detalles .filas');
	if (filas.length === 0) return;
	var items = [];
	for (var i = 0; i < filas.length; i++) {
		var fila = filas[i];
		var tds = fila.querySelectorAll('td');
		var nombre = $(tds[1]).clone().find('input').remove().end().text().trim();
		var cantInp   = fila.querySelector('input[name="cantidad[]"]');
		var precioInp = fila.querySelector('input[name="precio_venta[]"]');
		var descInp   = fila.querySelector('input[name="descuento[]"]');
		var stockInp  = fila.querySelector('input[name="stock_disponible[]"]');
		var idArtInp  = fila.querySelector('input[name="idarticulo[]"]');
		items.push({
			idarticulo:  idArtInp  ? idArtInp.value  : '',
			nombre:      nombre,
			unidad:      tds[2]    ? tds[2].textContent.trim() : '',
			cantidad:    cantInp   ? cantInp.value   : '1',
			precio_venta:precioInp ? precioInp.value : '0',
			descuento:   descInp   ? descInp.value   : '0',
			stock:       stockInp  ? stockInp.value  : '0',
			tipoVenta:   fila.getAttribute('data-tipo-venta') || 'OTC',
			stockBase:   fila.getAttribute('data-stock-base') || (stockInp ? stockInp.value : '0'),
			presentaciones: leerPresentacionesFila(fila),
			idpresentacion: (fila.querySelector('input[name="idpresentacion[]"]') || {}).value || 0
		});
	}
	try {
		localStorage.setItem(BORRADOR_KEY_VEN, JSON.stringify({
			ts:               Date.now(),
			idcliente:        $("#idcliente").val(),
			cliente_nombre:   $("#idcliente option:selected").text(),
			tipo_comprobante: $("#tipo_comprobante").val(),
			metodo_pago:      $("#metodo_pago").val(),
			items:            items
		}));
	} catch(e) {}
}

function limpiarBorradorVenta() {
	try { localStorage.removeItem(BORRADOR_KEY_VEN); } catch(e) {}
}

function restaurarBorradorVenta() {
	var raw;
	try { raw = localStorage.getItem(BORRADOR_KEY_VEN); } catch(e) { return; }
	if (!raw) return;
	var b;
	try { b = JSON.parse(raw); } catch(e) { return; }
	if (!b || !b.items || b.items.length === 0) return;
	var hace = '';
	if (b.ts) {
		var mins = Math.round((Date.now() - b.ts) / 60000);
		hace = mins < 60 ? ('hace ' + mins + ' min') : ('hace ' + Math.floor(mins/60) + ' h');
	}
	window._borradorVenta = b;
	$('<div id="bannerBorradorVenta" class="alert alert-warning" style="font-size:13px;font-weight:600;margin:8px 0;">' +
		'<i class="fa fa-clock-o"></i> Tienes un carrito guardado ' + hace + ' con ' +
		b.items.length + ' producto(s).' +
		' &nbsp;<button type="button" class="btn btn-xs btn-success" onclick="restaurarBorradorVentaConfirmar()">Restaurar carrito</button>' +
		' &nbsp;<button type="button" class="btn btn-xs btn-default" onclick="descartarBorradorVenta()">Descartar</button>' +
	'</div>').prependTo("#formularioregistros");
}

function restaurarBorradorVentaConfirmar() {
	var b = window._borradorVenta;
	if (!b) return;
	$(".filas").remove();
	detalles = 0; cont = 0;
	for (var i = 0; i < b.items.length; i++) {
		var it = b.items[i];
		var pv  = parseFloat(it.precio_venta || 0);
		var desc = parseFloat(it.descuento || 0);
		var cant = parseFloat(it.cantidad || 1);
		var stk  = normalizarEnteroNoNegativo(it.stock || 0);
		var tv   = (it.tipoVenta || 'OTC').toUpperCase();
		$('#detalles').append(construirFilaVenta({
			idx: cont, idarticulo: it.idarticulo, nombre: it.nombre, unidad: it.unidad || 'und', tipoVenta: tv,
			presentaciones: it.presentaciones || [], idpresentacion: it.idpresentacion || 0,
			stockBase: normalizarEnteroNoNegativo(it.stockBase || stk), stockMax: stk,
			cantidad: cant, precio: pv.toFixed(2), descuento: desc.toFixed(2)
		}));
		cont++; detalles++;
	}
	if (b.idcliente) {
		var encontrado = false;
		$("#idcliente option").each(function(){
			if (String($(this).val()) === String(b.idcliente)) { encontrado = true; return false; }
		});
		if (encontrado) {
			$("#idcliente").val(b.idcliente);
			try { $("#idcliente").selectpicker("refresh"); } catch(e) {}
		}
	}
	if (b.tipo_comprobante) {
		$("#tipo_comprobante").val(b.tipo_comprobante);
		try { $("#tipo_comprobante").selectpicker("refresh"); } catch(e) {}
		$(".pos-doc-btn").removeClass("active");
		$('.pos-doc-btn[data-tipo="' + b.tipo_comprobante + '"]').addClass("active");
	}
	if (b.metodo_pago) {
		$("#metodo_pago").val(b.metodo_pago);
		$(".pos-metodo-btn").removeClass("active");
		$('.pos-metodo-btn[data-metodo="' + b.metodo_pago + '"]').addClass("active");
		try { $("#metodo_pago").selectpicker("refresh"); } catch(e) {}
		sincronizarMontoPago();
	}
	modificarSubtotales();
	actualizarContadorItems();
	renderCarritoVisual();
	$("#bannerBorradorVenta").remove();
	notifyVenta("success", "Carrito restaurado: " + b.items.length + " producto(s).");
}

function descartarBorradorVenta() {
	limpiarBorradorVenta();
	window._borradorVenta = null;
	$("#bannerBorradorVenta").remove();
	notifyVenta("info", "Carrito descartado.");
}


// Precio con DNI: recalcular el carrito cuando cambia el cliente
$(function(){
	$(document).on("change", "#idcliente", aplicarPrecioClienteCarrito);
});
