var tabla;
var tablaArticulos;
var empresaDefaultsIngreso = {
	serie_boleta: "B001",
	serie_factura: "F001",
	serie_ticket: "T001",
	impuesto_default: 18,
	moneda: "PEN",
	simbolo_moneda: "S/"
};
var correlativoRequestIdIngreso = 0;
var proveedoresCargados = false;
var numeroComprobanteManualIngreso = false;
var modoAmpliar = false;
var modoVer = false;
var estadoIngresoActual = '';          // '', 'Borrador', 'Aceptado'
var modoAmpliarData = { idingreso: 0, proveedor: '', comprobante: '' };
var borradorTimerIngreso = null;
var cabeceraPromesa = null;            // creación de cabecera en curso (una sola vez)
var cabeceraTimer = null;
var tablaDetallesOriginal = '';

function notifyIngreso(type, message){
	if (typeof appNotify === "function") {
		appNotify(type, message);
		return;
	}
	alert(message);
}

function parseJsonIngreso(resp){
	if (resp && typeof resp === "object") return resp;
	try { return JSON.parse(resp); } catch (e) { return {ok:false, message:"Respuesta inválida del servidor"}; }
}

function moneyIngreso(v){
	var n = parseFloat(v || 0);
	if (!isFinite(n)) n = 0;
	return window.appMoney ? window.appMoney(n, 2) : ((window.appCurrencySymbol || "S/") + " " + n.toFixed(2));
}

function normalizarCantidadEntera(valor, minimo){
	var num = parseFloat(valor);
	if (!isFinite(num)) {
		return minimo;
	}
	num = Math.round(num);
	if (num < minimo) {
		num = minimo;
	}
	return num;
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

//funcion que se ejecuta al inicio
function init(){
   tablaDetallesOriginal = $("#detalles").html();
   mostrarform(false);
   listar();

   $("#formulario").on("submit",function(e){
   	guardaryeditar(e);
   });

   $("#btnFiltrarIngreso").on("click", function(){
   	recargarListadoIngreso();
   	cargarResumenPagosIngreso();
   });
   $("#btnLimpiarFiltroIngreso").on("click", function(){
   	$("#filtro_ingreso_inicio").val("");
   	$("#filtro_ingreso_fin").val("");
   	recargarListadoIngreso();
   	cargarResumenPagosIngreso();
   });

   cargarResumenPagosIngreso();
   cargarProveedores();

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
   cargarDefaultsEmpresaIngreso();
   setInterval(guardarBorradorIngreso, 30000);

   // ── Autoguardado por fila: cualquier cambio en una fila del carrito ──
   $(document).on("input change", "#detalles .filas input", function(ev){
   	var $fila = $(this).closest("tr.filas");
   	if (!$fila.find('input[name="idarticulo[]"]').length) return; // filas de solo lectura (ver detalle)
   	clearTimeout(borradorTimerIngreso);
   	borradorTimerIngreso = setTimeout(guardarBorradorIngreso, 1500);
   	marcarFilaSucia($fila);
   	programarAutoguardado($fila, ev.type === "change" ? 250 : 900);
   });

   // ── Autoguardado de cabecera (proveedor, comprobante, pago, temperatura) ──
   $("#idproveedor, #tipo_comprobante, #serie_comprobante, #num_comprobante, #fecha_hora, #ing_metodo_pago, #temperatura_recepcion, #temp_observacion")
   	.on("change", function(){
   		programarAutoguardadoCabecera();
   	});

   $("#formProveedorRapido").on("submit", function(e){
   	guardarProveedorRapido(e);
   });
   $("#modalProveedorIngreso").on("shown.bs.modal", function(){
   	$("#prv_nombre").focus();
   });
   $("#modalProveedorIngreso").on("hidden.bs.modal", function(){
   	limpiarFormProveedorRapido();
   });
   $("#num_comprobante").on("input", function(){
   	numeroComprobanteManualIngreso = true;
   });
   $("#serie_comprobante").on("change blur", function(){
   	cargarCorrelativoIngreso();
   });

}

function cargarProveedores(idPreferido){
	var idActual = (typeof idPreferido !== "undefined" && idPreferido !== null && idPreferido !== "") ? idPreferido : ($("#idproveedor").val() || "");
	$.post("../ajax/ingreso.php?op=selectProveedor", function(r){
		$("#idproveedor").html(r);
		var existeActual = false;
		if (idActual !== "") {
			$("#idproveedor option").each(function(){
				if (String($(this).val()) === String(idActual)) {
					existeActual = true;
					return false;
				}
			});
		}
		var idFinal = existeActual ? String(idActual) : ($("#idproveedor option:first").val() || "");
		$("#idproveedor").val(idFinal);
		$("#idproveedor").selectpicker("refresh");
		proveedoresCargados = true;
	});
}

function limpiarFormProveedorRapido(){
	$("#prv_nombre").val("");
	$("#prv_tipo_documento").val("DNI");
	$("#prv_num_documento").val("");
	$("#prv_direccion").val("");
	$("#prv_telefono").val("");
	$("#prv_email").val("");
	$("#btnGuardarProveedorRapido").prop("disabled", false);
}

function guardarProveedorRapido(e){
	e.preventDefault();
	var nombre = $.trim($("#prv_nombre").val());
	if (!nombre) {
		notifyIngreso("warning", "El nombre del proveedor es obligatorio.");
		return;
	}
	$("#btnGuardarProveedorRapido").prop("disabled", true);
	$.ajax({
		url: "../ajax/ingreso.php?op=crearProveedorRapido",
		type: "POST",
		data: $("#formProveedorRapido").serialize(),
		success: function(resp){
			var r = parseJsonIngreso(resp);
			if (!r.ok) {
				notifyIngreso("error", r.message || "No se pudo registrar el proveedor.");
				$("#btnGuardarProveedorRapido").prop("disabled", false);
				return;
			}
			notifyIngreso("success", r.message || "Proveedor registrado correctamente.");
			$("#modalProveedorIngreso").modal("hide");
			cargarProveedores(r.idproveedor || "");
			setTimeout(programarAutoguardadoCabecera, 600);
		},
		error: function(){
			notifyIngreso("error", "Ocurrio un error al registrar el proveedor.");
			$("#btnGuardarProveedorRapido").prop("disabled", false);
		}
	});
}

function cargarResumenPagosIngreso() {
	var fi = $("#filtro_ingreso_inicio").val() || "";
	var ff = $("#filtro_ingreso_fin").val() || "";
	var sym = window.appCurrencySymbol || "S/";
	$.get("../ajax/ingreso.php?op=resumenPagos", {fecha_inicio: fi, fecha_fin: ff}, function(r) {
		if (!r || !r.ok || !r.data) return;
		var d = r.data;
		$("#iResTotal").text(sym + " " + parseFloat(d.total_general || 0).toFixed(2) + " (" + (d.cantidad || 0) + " compras)");
	}, "json");
}

function cargarDefaultsEmpresaIngreso(){
	$.get("../ajax/empresa.php?op=defaults", function(resp){
		try{
			var r = JSON.parse(resp);
			empresaDefaultsIngreso.serie_boleta = r.serie_boleta || empresaDefaultsIngreso.serie_boleta;
			empresaDefaultsIngreso.serie_factura = r.serie_factura || empresaDefaultsIngreso.serie_factura;
			empresaDefaultsIngreso.serie_ticket = r.serie_ticket || empresaDefaultsIngreso.serie_ticket;
			empresaDefaultsIngreso.impuesto_default = parseFloat(r.impuesto_default || empresaDefaultsIngreso.impuesto_default);
			empresaDefaultsIngreso.moneda = r.moneda || empresaDefaultsIngreso.moneda;
			empresaDefaultsIngreso.simbolo_moneda = r.simbolo_moneda || empresaDefaultsIngreso.simbolo_moneda;
		}catch(e){}
		aplicarSerieImpuestoIngreso();
	});
}

//funcion limpiar
function limpiar(){

	$("#idingreso").val("");
	estadoIngresoActual = '';
	cabeceraPromesa = null;
	clearTimeout(cabeceraTimer);
	if (proveedoresCargados) {
		var primerProveedor = $("#idproveedor option:first").val() || "";
		$("#idproveedor").val(primerProveedor);
		$("#idproveedor").selectpicker("refresh");
	} else {
		$("#idproveedor").val("");
	}
	$("#proveedor").val("");
	$("#serie_comprobante").val("");
	$("#num_comprobante").val("");
	$("#impuesto").val("");
	numeroComprobanteManualIngreso = false;

	$("#total_compra").val("");
	// Restaurar la tabla del carrito (la vista "ver detalle" la reemplaza por completo)
	if (tablaDetallesOriginal) {
		$("#detalles").html(tablaDetallesOriginal);
	} else {
		$(".filas").remove();
	}
	cont = 0;
	detalles = 0;
	$("#total").html(moneyIngreso(0));
	$("#autoguardadoEstado").text("");
	actualizarContadorItems();

	$("#fecha_hora").val(fechaHoraActualInput());

	//marcamos el primer tipo_documento
	$("#tipo_comprobante").val("Boleta");
	$("#tipo_comprobante").selectpicker('refresh');

	$("#ing_metodo_pago").val("EFECTIVO");
	$("#temperatura_recepcion").val("");
	$("#temp_observacion").val("");
	$("#panelTemperatura").hide();
	habilitarCabecera(true);
	$("#bannerBorradorDB, #bannerBorradorPendiente, #bannerBorradorIngreso, #bannerBorradorAmpliar").remove();

	aplicarSerieImpuestoIngreso();

}

function habilitarCabecera(habilitar){
	$("#tipo_comprobante, #serie_comprobante, #num_comprobante, #fecha_hora, #ing_metodo_pago, #temperatura_recepcion, #temp_observacion").prop("disabled", !habilitar);
	$("#idproveedor").prop("disabled", !habilitar);
	try { $("#idproveedor").selectpicker("refresh"); } catch(e) {}
	try { $("#tipo_comprobante").selectpicker("refresh"); } catch(e) {}
	$("#btnNuevoProveedor").toggle(!!habilitar);
}

//funcion mostrar formulario
function mostrarform(flag, esNuevo){
	limpiar();
	if(flag){
		$("#listadoregistros").hide();
		$("#formularioregistros").show();
		$("#btnagregar").hide();

		$("#btnGuardar").hide();
		$("#btnCancelar").show();
		detalles=0;
		$("#btnAgregarArt").show();

		if (esNuevo) {
			modoAmpliar = false;
			modoVer = false;
			$("#btnGuardar").html('<i class="fa fa-check-circle"></i> Confirmar compra');
			detectarBorradores();
		}
	}else{
		modoAmpliar = false;
		modoVer = false;
		$("#bannerAmpliar, #panelItemsExistentes").remove();
		$("#serie_comprobante, #num_comprobante, #fecha_hora").prop("readonly", false);
		$("#btnGuardar").html('<i class="fa fa-check-circle"></i> Confirmar compra').prop("disabled", false);
		$("#listadoregistros").show();
		$("#formularioregistros").hide();
		$("#btnagregar").show();
	}
}

function salirFormulario(){
	modoAmpliar = false;
	modoVer = false;
	modoAmpliarData = { idingreso: 0, proveedor: '', comprobante: '' };
	$("#bannerAmpliar, #panelItemsExistentes, #bannerBorradorAmpliar, #bannerBorradorDB, #bannerBorradorPendiente, #bannerBorradorIngreso").remove();
	$("#serie_comprobante, #num_comprobante, #fecha_hora").prop("readonly", false);
	mostrarform(false);
}

//cancelar form
function cancelarform(){
	if (modoVer) {
		salirFormulario();
		return;
	}
	var idingreso = parseInt($("#idingreso").val(), 10) || 0;
	var filasGuardadas = filasCarrito().filter(function(){ return $(this).attr("data-guardado") === "1"; }).length;
	var enVuelo = filasCarrito().filter(function(){ return !!$(this).data("guardando"); }).length;
	if (enVuelo > 0) {
		notifyIngreso("info", "Espera un momento, se están guardando filas…");
		return;
	}
	if (idingreso > 0 && estadoIngresoActual === 'Borrador') {
		if (filasGuardadas === 0) {
			// Borrador vacío: se elimina en silencio
			$.post("../ajax/ingreso.php?op=descartarBorrador", { idingreso: idingreso });
			limpiarBorradorIngreso();
			salirFormulario();
			listar();
			return;
		}
		bootbox.dialog({
			title: "Compra en borrador",
			message: "Esta compra tiene <strong>" + filasGuardadas + " artículo(s)</strong> ya guardados como borrador.<br>¿Qué deseas hacer?",
			buttons: {
				seguir: { label: "Seguir editando", className: "btn-default" },
				guardar: {
					label: '<i class="fa fa-clock-o"></i> Guardar borrador y salir',
					className: "btn-primary",
					callback: function(){
						notifyIngreso("info", "La compra quedó en borrador. Puedes continuarla desde el listado.");
						salirFormulario();
						listar();
					}
				},
				descartar: {
					label: '<i class="fa fa-trash"></i> Descartar compra',
					className: "btn-danger",
					callback: function(){ descartarBorradorActual(idingreso); }
				}
			}
		});
		return;
	}
	if (modoAmpliar && filasGuardadas > 0) {
		notifyIngreso("info", filasGuardadas + " artículo(s) ya quedaron guardados en la compra.");
		limpiarBorradorAmpliar();
		salirFormulario();
		listar();
		cargarResumenPagosIngreso();
		return;
	}
	salirFormulario();
}

function descartarBorradorActual(idingreso){
	$.post("../ajax/ingreso.php?op=descartarBorrador", { idingreso: idingreso }, function(resp){
		var r = parseJsonIngreso(resp);
		if (r.ok) {
			notifyIngreso("success", r.message || "Borrador descartado.");
			limpiarBorradorIngreso();
			salirFormulario();
			listar();
		} else {
			notifyIngreso("error", r.message || "No se pudo descartar el borrador.");
		}
	}).fail(function(){
		notifyIngreso("error", "Sin conexión con el servidor. No se pudo descartar.");
	});
}

//funcion listar
function listar(){
	tabla=$('#tbllistado').dataTable({
		"aProcessing": true,//activamos el procedimiento del datatable
		"aServerSide": true,//paginacion y filrado realizados por el server
		dom: 'Bfrtip',//definimos los elementos del control de la tabla
		buttons: window.appDataTableButtons('Reporte de Ingresos', true),
		"ajax":
		{
			url:'../ajax/ingreso.php?op=listar',
			type: "get",
			data: function(d){
				d.fecha_inicio = ($("#filtro_ingreso_inicio").val() || "").trim();
				d.fecha_fin = ($("#filtro_ingreso_fin").val() || "").trim();
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

function recargarListadoIngreso(){
	var fi = ($("#filtro_ingreso_inicio").val() || "").trim();
	var ff = ($("#filtro_ingreso_fin").val() || "").trim();
	if (fi && ff && fi > ff) {
		notifyIngreso("warning", "La fecha 'Desde' no puede ser mayor que 'Hasta'.");
		return;
	}
	if (tabla) {
		tabla.ajax.reload();
	}
}

function listarArticulos(){
	tablaArticulos=$('#tblarticulos').dataTable({
		"aProcessing": true,//activamos el procedimiento del datatable
		"aServerSide": true,//paginacion y filrado realizados por el server
		"autoWidth": false,
		dom: 'frtip',//definimos los elementos del control de la tabla
		"ajax":
		{
			url:'../ajax/ingreso.php?op=listarArticulos',
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
			"sSearch":"Buscar artículo:",
			"sSearchPlaceholder":"Nombre o código"
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

// ── Datos de cabecera que viajan al servidor ──────────────────────
function datosCabecera(){
	return {
		idproveedor:           $("#idproveedor").val() || "",
		tipo_comprobante:      $("#tipo_comprobante").val() || "Boleta",
		serie_comprobante:     $("#serie_comprobante").val() || "",
		num_comprobante:       $("#num_comprobante").val() || "",
		fecha_hora:            $("#fecha_hora").val() || "",
		impuesto:              $("#impuesto").val() || "",
		metodo_pago:           $("#ing_metodo_pago").val() || "EFECTIVO",
		temperatura_recepcion: $("#temperatura_recepcion").val() || "",
		temp_observacion:      $("#temp_observacion").val() || ""
	};
}

function setAutoguardadoEstado(texto, clase){
	$("#autoguardadoEstado").attr("class", clase || "text-success").text(texto || "");
}

/** Crea la cabecera en borrador si aún no existe. Devuelve promesa con idingreso. */
function asegurarCabecera(){
	var id = parseInt($("#idingreso").val(), 10) || 0;
	if (id > 0) {
		return $.Deferred().resolve(id).promise();
	}
	if (cabeceraPromesa) {
		return cabeceraPromesa;
	}
	var d = $.Deferred();
	var prov = ($("#idproveedor").val() || "").toString().trim();
	if (!prov) {
		notifyIngreso("warning", "Selecciona un proveedor para poder guardar los artículos.");
		d.reject("Falta elegir el proveedor");
		return d.promise();
	}
	cabeceraPromesa = d.promise();
	$.post("../ajax/ingreso.php?op=crearBorrador", datosCabecera()).done(function(resp){
		var r = parseJsonIngreso(resp);
		if (r.ok && r.idingreso) {
			$("#idingreso").val(r.idingreso);
			estadoIngresoActual = 'Borrador';
			if (r.serie_comprobante) $("#serie_comprobante").val(r.serie_comprobante);
			if (r.num_comprobante) {
				$("#num_comprobante").val(r.num_comprobante);
				numeroComprobanteManualIngreso = true;
				correlativoRequestIdIngreso++;
			}
			mostrarBannerBorradorDB({
				serie_comprobante: r.serie_comprobante,
				num_comprobante: r.num_comprobante,
				tipo_comprobante: r.tipo_comprobante
			});
			d.resolve(r.idingreso);
		} else {
			notifyIngreso("error", r.message || "No se pudo iniciar la compra.");
			d.reject(r.message || "No se pudo iniciar la compra");
		}
	}).fail(function(){
		notifyIngreso("error", "Sin conexión con el servidor. No se pudo iniciar la compra.");
		d.reject("Sin conexión con el servidor");
	}).always(function(){
		cabeceraPromesa = null;
	});
	return cabeceraPromesa;
}

function mostrarBannerBorradorDB(c){
	$("#bannerBorradorDB").remove();
	var comp = (c.tipo_comprobante || "") + " " + (c.serie_comprobante || "") + "-" + (c.num_comprobante || "");
	$('<div id="bannerBorradorDB" class="alert alert-info" style="font-weight:600;margin-bottom:10px;padding:8px 12px;">' +
		'<i class="fa fa-magic"></i>&nbsp; COMPRA EN BORRADOR <strong>' + escHtml(comp) + '</strong> &nbsp;|&nbsp; ' +
		'Cada fila completa se guarda sola. Al terminar presiona <strong>Confirmar compra</strong>.' +
	'</div>').prependTo("#formularioregistros form");
}

/** Guarda la cabecera (sin confirmar) con retardo, solo si la compra ya existe. */
function programarAutoguardadoCabecera(){
	clearTimeout(cabeceraTimer);
	if (modoVer) return;
	var id = parseInt($("#idingreso").val(), 10) || 0;
	if (id <= 0) {
		// Aún no hay cabecera: reintentar filas que fallaron (p.ej. faltaba proveedor)
		filasCarrito().filter('[data-estado="error"], [data-estado="incompleta"]').each(function(){
			programarAutoguardado($(this), 400);
		});
		return;
	}
	cabeceraTimer = setTimeout(function(){
		guardarCabeceraAhora();
	}, 900);
}

function guardarCabeceraAhora(){
	var id = parseInt($("#idingreso").val(), 10) || 0;
	if (id <= 0 || modoVer) return;
	var datos = $.extend({ idingreso: id }, datosCabecera());
	$.post("../ajax/ingreso.php?op=guardarCabecera", datos, function(resp){
		var r = parseJsonIngreso(resp);
		if (r.ok) {
			if (r.num_comprobante && $("#num_comprobante").val() !== r.num_comprobante) {
				$("#num_comprobante").val(r.num_comprobante);
			}
			setAutoguardadoEstado("Datos de la compra guardados ✓", "text-success");
		} else {
			setAutoguardadoEstado(r.message || "No se pudo guardar la cabecera", "text-danger");
			notifyIngreso("error", r.message || "No se pudieron guardar los datos de la compra.");
		}
	}).fail(function(){
		setAutoguardadoEstado("Sin conexión: los datos de la compra no se guardaron", "text-danger");
	});
}

//funcion para guardaryeditar → ahora CONFIRMA que todo está guardado
function guardaryeditar(e){
     e.preventDefault();
     confirmarCompra();
}

function filasCarrito(){
	return $("#detalles .filas").filter(function(){
		return $(this).find('input[name="idarticulo[]"]').length > 0;
	});
}

function nombreFila($fila){
	return $fila.find('td').eq(1).clone().find('input').remove().end().text().trim() || '?';
}

function confirmarCompra(){
	if (modoVer) return;
	var $filas = filasCarrito();
	if ($filas.length === 0) {
		notifyIngreso("warning", "Agrega al menos un artículo a la compra.");
		return;
	}
	var proveedorSeleccionado = ($("#idproveedor").val() || "").toString().trim();
	if (!proveedorSeleccionado) {
		notifyIngreso("warning", "Selecciona un proveedor antes de confirmar.");
		return;
	}
	var $btn = $("#btnGuardar");
	var htmlBtn = $btn.html();
	$btn.prop("disabled", true).html('<i class="fa fa-spinner fa-spin"></i> Verificando guardado…');
	function restaurarBtn(){ $btn.prop("disabled", false).html(htmlBtn); }

	// 1) Forzar el guardado de toda fila que aún no esté guardada
	var promesas = [];
	$filas.each(function(){
		var $f = $(this);
		clearTimeout($f.data("timer"));
		if ($f.attr("data-guardado") !== "1" || $f.data("guardando")) {
			promesas.push(autoguardarFila($f));
		}
	});

	$.when.apply($, promesas).always(function(){
		// 2) Revisar que TODAS quedaron guardadas
		var incompletas = [], errores = [];
		filasCarrito().each(function(){
			var $f = $(this);
			if ($f.attr("data-guardado") !== "1") {
				if ($f.attr("data-estado") === "error") errores.push(nombreFila($f));
				else incompletas.push(nombreFila($f));
			}
		});
		if (incompletas.length > 0) {
			notifyIngreso("warning", "Completa las filas marcadas antes de confirmar: " + incompletas.join(", "));
			restaurarBtn();
			return;
		}
		if (errores.length > 0) {
			notifyIngreso("error", "No se pudieron guardar: " + errores.join(", ") + ". Usa el botón Reintentar de la fila.");
			restaurarBtn();
			return;
		}
		// 3) Confirmar la compra (cabecera + estado Aceptado)
		var datos = $.extend({ idingreso: $("#idingreso").val() }, datosCabecera());
		$.post("../ajax/ingreso.php?op=confirmarIngreso", datos, function(resp){
			var r = parseJsonIngreso(resp);
			if (r.ok) {
				var msg = (modoAmpliar ? "Compra actualizada: " : "Compra confirmada: ") + (r.items || 0) + " artículo(s), total " + moneyIngreso(r.total);
				notifyIngreso("success", msg);
				limpiarBorradorIngreso();
				limpiarBorradorAmpliar();
				salirFormulario();
				listar();
				cargarResumenPagosIngreso();
			} else {
				notifyIngreso("error", r.message || "No se pudo confirmar la compra.");
				restaurarBtn();
			}
		}).fail(function(){
			notifyIngreso("error", "Sin conexión con el servidor. Las filas ya guardadas no se pierden; intenta confirmar de nuevo.");
			restaurarBtn();
		});
	});
}

// ── Fijar cabecera desde la BD (ver, ampliar, continuar borrador) ──
function fijarCabeceraDesdeBD(d){
	$("#idingreso").val(d.idingreso);
	if (proveedoresCargados) {
		$("#idproveedor").val(d.idproveedor);
		try { $("#idproveedor").selectpicker("refresh"); } catch(e) {}
	} else {
		cargarProveedores(d.idproveedor);
	}
	$("#tipo_comprobante").val(d.tipo_comprobante);
	try { $("#tipo_comprobante").selectpicker("refresh"); } catch(e) {}
	$("#serie_comprobante").val(d.serie_comprobante);
	$("#num_comprobante").val(d.num_comprobante);
	numeroComprobanteManualIngreso = true;
	correlativoRequestIdIngreso++;   // invalida correlativos en vuelo para no pisar el número real
	$("#fecha_hora").val(normalizarFechaHoraInput(d.fecha));
	$("#impuesto").val(d.impuesto);
	$("#ing_metodo_pago").val(d.metodo_pago || "EFECTIVO");
	if (!$("#ing_metodo_pago").val()) { $("#ing_metodo_pago").val("EFECTIVO"); }
	var temp = (d.temperatura_recepcion === null || typeof d.temperatura_recepcion === "undefined") ? "" : d.temperatura_recepcion;
	$("#temperatura_recepcion").val(temp);
	$("#temp_observacion").val(d.temp_observacion || "");
	if (temp !== "" || (d.temp_observacion || "") !== "") {
		$("#panelTemperatura").show();
	}
	estadoIngresoActual = d.estado || '';
}

function mostrar(idingreso){
	$.post("../ajax/ingreso.php?op=mostrar",{idingreso : idingreso},
		function(data,status)
		{
			data=parseJsonIngreso(data);
			mostrarform(true);
			modoVer = true;
			fijarCabeceraDesdeBD(data);
			habilitarCabecera(false);

			//ocultar y mostrar los botones
			$("#btnGuardar").hide();
			$("#btnCancelar").show();
			$("#btnAgregarArt").hide();

			// Cargar detalle DESPUÉS de limpiar el formulario para evitar race condition
			$.post("../ajax/ingreso.php?op=listarDetalle&id="+idingreso, function(r){
				$("#detalles").html(r);
				actualizarContadorItems();
			});
		});
}


//funcion para desactivar
function anular(idingreso){
	bootbox.confirm("¿Esta seguro de desactivar este dato?", function(result){
		if (result) {
			$.post("../ajax/ingreso.php?op=anular", {idingreso : idingreso}, function(e){
				notifyIngreso("warning", e);
				tabla.ajax.reload();
			});
		}
	});
}

//funcion para recuperar / reactivar una compra anulada
function recuperarIngreso(idingreso){
	bootbox.confirm("¿Deseas recuperar esta compra? Volverá al estado Aceptado.", function(result){
		if (result) {
			$.post("../ajax/ingreso.php?op=activar", {idingreso : idingreso}, function(e){
				notifyIngreso("success", e);
				tabla.ajax.reload();
			});
		}
	});
}

//funcion para eliminar definitivamente una compra anulada (no se puede deshacer)
function eliminarDefinitivoIngreso(idingreso){
	bootbox.confirm({
		title: "Eliminar compra definitivamente",
		message: "Esta acción no se puede deshacer. Se eliminará la compra, su detalle y se descontará el stock que había ingresado. ¿Deseas continuar?",
		buttons: {
			confirm: { label: "Sí, eliminar definitivo", className: "btn-danger" },
			cancel: { label: "Cancelar", className: "btn-default" }
		},
		callback: function(result){
			if (result) {
				$.post("../ajax/ingreso.php?op=eliminarDefinitivo", {idingreso : idingreso}, function(data){
					var r = parseJsonIngreso(data);
					notifyIngreso(r.ok ? "success" : "error", r.message || (r.ok ? "Compra eliminada" : "No se pudo eliminar la compra"));
					if (r.ok) {
						tabla.ajax.reload();
					}
				});
			}
		}
	});
}

// Descartar un borrador desde el listado
function descartarBorradorLista(idingreso){
	bootbox.confirm({
		title: "Descartar compra en borrador",
		message: "Se eliminará el borrador y el stock que ya había sumado volverá a su valor anterior. ¿Deseas continuar?",
		buttons: {
			confirm: { label: "Sí, descartar", className: "btn-danger" },
			cancel: { label: "Cancelar", className: "btn-default" }
		},
		callback: function(result){
			if (!result) return;
			$.post("../ajax/ingreso.php?op=descartarBorrador", {idingreso : idingreso}, function(data){
				var r = parseJsonIngreso(data);
				notifyIngreso(r.ok ? "success" : "error", r.message || "");
				if (r.ok) {
					tabla.ajax.reload();
				}
			}).fail(function(){
				notifyIngreso("error", "Sin conexión con el servidor.");
			});
		}
	});
}

//declaramos variables necesarias para trabajar con las compras y sus detalles
var impuesto=18;
var cont=0;
var detalles=0;

$("#btnGuardar").hide();
$("#tipo_comprobante").change(marcarImpuesto);

function marcarImpuesto(){
	aplicarSerieImpuestoIngreso();
}

function aplicarSerieImpuestoIngreso(){
	var tipo = $("#tipo_comprobante").val();
	var igv = (empresaDefaultsIngreso.impuesto_default || 18).toFixed(2);
	if (tipo==='Factura') {
		$("#serie_comprobante").val(empresaDefaultsIngreso.serie_factura || "F001");
	} else if (tipo==='Ticket') {
		$("#serie_comprobante").val(empresaDefaultsIngreso.serie_ticket || "T001");
	} else {
		$("#serie_comprobante").val(empresaDefaultsIngreso.serie_boleta || "B001");
	}
	$("#impuesto").val(igv);
	cargarCorrelativoIngreso();
}

function cargarCorrelativoIngreso(){
	var tipo = ($("#tipo_comprobante").val() || "Boleta").trim();
	var serie = ($("#serie_comprobante").val() || "").trim();
	if (!serie) {
		$("#num_comprobante").val("");
		return;
	}
	correlativoRequestIdIngreso++;
	var reqId = correlativoRequestIdIngreso;
	$.get("../ajax/ingreso.php?op=siguienteCorrelativo", {
		tipo_comprobante: tipo,
		serie_comprobante: serie
	}, function(resp){
		if (reqId !== correlativoRequestIdIngreso) {
			return;
		}
		var r = parseJsonIngreso(resp);
		if (!r.ok) {
			return;
		}
		$("#serie_comprobante").val(r.serie_comprobante || serie);
		if (!numeroComprobanteManualIngreso || !$.trim($("#num_comprobante").val())) {
			$("#num_comprobante").val(r.numero || "");
			numeroComprobanteManualIngreso = false;
			programarAutoguardadoCabecera();
		}
	});
}

// ── Estado visual de cada fila ────────────────────────────────────
function estadoFilaHtml(estado, msg){
	msg = msg || '';
	switch (estado) {
		case 'guardado':
			return '<span class="label label-success" title="Guardado en la base de datos"><i class="fa fa-check"></i> Guardado</span>';
		case 'guardando':
			return '<span class="label label-info"><i class="fa fa-spinner fa-spin"></i> ' + escHtml(msg || 'Guardando…') + '</span>';
		case 'incompleta':
			return '<span class="label label-warning" title="' + escHtml(msg) + '"><i class="fa fa-pencil"></i> ' + escHtml(msg || 'Incompleta') + '</span>';
		case 'error':
			return '<span class="label label-danger" title="' + escHtml(msg) + '"><i class="fa fa-exclamation-triangle"></i> Error</span> ' +
				'<button type="button" class="btn btn-xs btn-default" onclick="reintentarFila(this)" title="Volver a intentar guardar"><i class="fa fa-refresh"></i> Reintentar</button>' +
				'<div style="font-size:10px;color:#a94442;max-width:170px;line-height:1.2;margin-top:2px;">' + escHtml(msg) + '</div>';
		default:
			return '<span class="label label-default"><i class="fa fa-clock-o"></i> Pendiente</span>';
	}
}

function setEstadoFila($fila, estado, msg){
	$fila.attr("data-estado", estado);
	$fila.find(".td-estado-fila").html(estadoFilaHtml(estado, msg));
}

function marcarFilaSucia($fila){
	$fila.attr("data-guardado", "0");
	if (!$fila.data("guardando")) {
		setEstadoFila($fila, "pendiente");
	}
}

function programarAutoguardado($fila, delay){
	clearTimeout($fila.data("timer"));
	$fila.data("timer", setTimeout(function(){
		autoguardarFila($fila);
	}, delay || 900));
}

function reintentarFila(btn){
	var $fila = $(btn).closest("tr.filas");
	if ($fila.length) autoguardarFila($fila);
}

function leerFila($fila){
	return {
		iddetalle:         parseInt($fila.attr("data-iddetalle"), 10) || 0,
		idarticulo:        $fila.find('input[name="idarticulo[]"]').val(),
		cantidad:          $fila.find('input[name="cantidad[]"]').val(),
		precio_compra:     $fila.find('input[name="precio_compra[]"]').val(),
		precio_venta:      $fila.find('input[name="precio_venta[]"]').val(),
		numero_lote:       $fila.find('input[name="numero_lote[]"]').val(),
		fecha_vencimiento: $fila.find('input[name="fecha_vencimiento[]"]').val()
	};
}

/** Devuelve {ok, msg}. Una fila está completa cuando tiene cantidad, precios y vencimiento válidos. */
function validarFilaCarrito($fila){
	var d = leerFila($fila);
	var faltan = [];
	if (!(parseFloat(d.cantidad) > 0)) faltan.push("cantidad");
	if (!(parseFloat(d.precio_compra) > 0)) faltan.push("precio compra");
	if (!(parseFloat(d.precio_venta) > 0)) faltan.push("precio venta");
	var $fv = $fila.find('input[name="fecha_vencimiento[]"]');
	if (!d.fecha_vencimiento) {
		faltan.push("vencimiento");
		$fv.css("border", "2px solid #d9534f");
	} else {
		var hoy = new Date(); hoy.setHours(0,0,0,0);
		var fv = new Date(d.fecha_vencimiento + "T00:00:00");
		if (isNaN(fv.getTime())) {
			faltan.push("vencimiento inválido");
			$fv.css("border", "2px solid #d9534f");
		} else if (fv < hoy) {
			faltan.push("vencimiento ya pasó");
			$fv.css("border", "2px solid #d9534f");
		} else {
			$fv.css("border", "1px solid #ccc");
		}
	}
	if (faltan.length) {
		return { ok:false, msg: "Falta: " + faltan.join(", ") };
	}
	return { ok:true, msg:"" };
}

/**
 * Guarda una fila en la BD (inserta o actualiza). Nunca rechaza: resuelve true/false.
 * Si ya hay un guardado en curso para la fila, se vuelve a guardar al terminar.
 */
function autoguardarFila($fila){
	if (!$fila || !$fila.length || !$.contains(document, $fila[0])) {
		return $.Deferred().resolve(false).promise();
	}
	if ($fila.data("guardando")) {
		$fila.data("reintentar", true);
		return $fila.data("promesa") || $.Deferred().resolve(false).promise();
	}
	var d = $.Deferred();
	var v = validarFilaCarrito($fila);
	if (!v.ok) {
		setEstadoFila($fila, "incompleta", v.msg);
		d.resolve(false);
		return d.promise();
	}
	$fila.data("guardando", true).data("promesa", d.promise()).data("reintentar", false);
	setEstadoFila($fila, "guardando");
	var datos = leerFila($fila);

	function finalizar(ok){
		$fila.data("guardando", false);
		if ($fila.data("eliminarAlTerminar")) {
			d.resolve(ok);
			return;
		}
		if ($fila.data("reintentar")) {
			$fila.data("reintentar", false);
			autoguardarFila($fila).always(function(ok2){ d.resolve(!!ok2); });
		} else {
			d.resolve(ok);
		}
		actualizarResumenGuardado();
	}

	asegurarCabecera().done(function(idingreso){
		datos.idingreso = idingreso;
		$.post("../ajax/ingreso.php?op=guardarFila", datos).done(function(resp){
			var r = parseJsonIngreso(resp);
			if (r.ok) {
				if (r.iddetalle) $fila.attr("data-iddetalle", r.iddetalle);
				if (!$fila.data("reintentar")) {
					$fila.attr("data-guardado", "1");
					setEstadoFila($fila, "guardado");
				}
				if (typeof r.nuevo_total !== "undefined") {
					$fila.data("totalServidor", parseFloat(r.nuevo_total));
					window._totalGuardadoIngreso = parseFloat(r.nuevo_total);
				}
				guardarBorradorIngreso();
				finalizar(true);
			} else {
				setEstadoFila($fila, "error", r.message || "No se pudo guardar");
				notifyIngreso("error", r.message || "No se pudo guardar la fila.");
				finalizar(false);
			}
		}).fail(function(){
			setEstadoFila($fila, "error", "Sin conexión con el servidor");
			finalizar(false);
		});
	}).fail(function(msg){
		setEstadoFila($fila, "error", msg || "No se pudo iniciar la compra");
		finalizar(false);
	});
	return d.promise();
}

function actualizarResumenGuardado(){
	var $filas = filasCarrito();
	var total = $filas.length;
	var guardadas = $filas.filter(function(){ return $(this).attr("data-guardado") === "1"; }).length;
	if (total === 0) { setAutoguardadoEstado(""); return; }
	var texto = "Guardadas " + guardadas + " de " + total + " filas";
	if (typeof window._totalGuardadoIngreso === "number") {
		texto += " · Total en BD: " + moneyIngreso(window._totalGuardadoIngreso);
	}
	setAutoguardadoEstado(texto, guardadas === total ? "text-success" : "text-warning");
}

// ── Construcción de filas del carrito ─────────────────────────────
function filaCarritoHtml(it, idx){
	var cant = normalizarCantidadEntera(it.cantidad || 1, 1);
	var pc = parseFloat(it.precio_compra || 0); if (!isFinite(pc)) pc = 0;
	var pv = parseFloat(it.precio_venta || 0);  if (!isFinite(pv)) pv = 0;
	var iddet = parseInt(it.iddetalle || 0, 10) || 0;
	var fv = it.fecha_vencimiento || '';
	var sub = (cant * pc).toFixed(2);
	var fvBorder = fv ? '1px solid #ccc' : '2px solid #d9534f';
	return '<tr class="filas" id="fila' + idx + '" data-idx="' + idx + '" data-iddetalle="' + iddet + '" data-guardado="' + (iddet > 0 ? '1' : '0') + '" data-estado="' + (iddet > 0 ? 'guardado' : 'pendiente') + '">' +
		'<td><button type="button" class="btn btn-danger" onclick="eliminarDetalle(' + idx + ')" title="Quitar de la compra">X</button></td>' +
		'<td><input type="hidden" name="idarticulo[]" value="' + escHtml(it.idarticulo) + '">' + escHtml(it.nombre) + '</td>' +
		'<td>' + escHtml(it.unidad || 'und') + '</td>' +
		'<td><input type="number" step="1" min="1" name="cantidad[]" value="' + cant + '" oninput="modificarSubtotales()"></td>' +
		'<td><input type="number" step="0.01" min="0.01" name="precio_compra[]" value="' + pc.toFixed(2) + '" oninput="modificarSubtotales()"></td>' +
		'<td><input type="number" step="0.01" min="0.01" name="precio_venta[]" value="' + pv.toFixed(2) + '"></td>' +
		'<td><input type="text" name="numero_lote[]" maxlength="50" placeholder="N° Lote" style="width:90px" value="' + escHtml(it.numero_lote || '') + '"></td>' +
		'<td><input type="date" name="fecha_vencimiento[]" value="' + escHtml(fv) + '" style="width:130px;border:' + fvBorder + ';" title="Fecha de vencimiento obligatoria"><span style="color:#d9534f;font-size:10px;display:block;">* obligatorio</span></td>' +
		'<td><span id="subtotal' + idx + '" name="subtotal">' + sub + '</span></td>' +
		'<td class="td-estado-fila" style="min-width:120px;">' + estadoFilaHtml(iddet > 0 ? 'guardado' : 'pendiente') + '</td>' +
	'</tr>';
}

function agregarFilaCarrito(it){
	var idx = cont;
	var html = filaCarritoHtml(it, idx);
	cont++;
	detalles++;
	var $tbody = $("#detalles tbody");
	if ($tbody.length) { $tbody.append(html); } else { $("#detalles").append(html); }
	return $("#fila" + idx);
}

function agregarDetalle(idarticulo,articulo,unidad,precio_compra_ref){
	if (modoVer) return;
	var precio_compra=(precio_compra_ref && parseFloat(precio_compra_ref) > 0) ? parseFloat(precio_compra_ref) : 1;
	var articulos = document.getElementsByName("idarticulo[]");
	var cantidades = document.getElementsByName("cantidad[]");

	if (idarticulo!="") {
		for (var i = 0; i < articulos.length; i++) {
			if (parseInt(articulos[i].value, 10) === parseInt(idarticulo, 10)) {
				var nuevaCantidad = normalizarCantidadEntera(parseFloat(cantidades[i].value || 0) + 1, 1);
				cantidades[i].value = nuevaCantidad;
				modificarSubtotales();
				var $filaExistente = $(cantidades[i]).closest("tr.filas");
				marcarFilaSucia($filaExistente);
				programarAutoguardado($filaExistente, 300);
				$('#myModal').modal('hide');
				notifyIngreso("info", "El artículo ya estaba agregado. Se incrementó la cantidad.");
				guardarBorradorIngreso();
				return;
			}
		}

		var $fila = agregarFilaCarrito({
			idarticulo: idarticulo, nombre: articulo, unidad: unidad || "und",
			cantidad: 1, precio_compra: precio_compra, precio_venta: 1, numero_lote: '', fecha_vencimiento: ''
		});
		setEstadoFila($fila, "incompleta", "Falta: vencimiento");
		modificarSubtotales();
		actualizarContadorItems();
		$('#myModal').modal('hide');
		notifyIngreso("success", "Artículo agregado. Completa cantidad, precios y vencimiento: la fila se guarda sola.");
		guardarBorradorIngreso();
		setTimeout(function(){ $fila.find('input[name="cantidad[]"]').focus().select(); }, 150);

	}else{
		notifyIngreso("warning", "No se pudo agregar el artículo. Revisa la información del producto.");
	}
}

function modificarSubtotales(){
	var cant=document.getElementsByName("cantidad[]");
	var prec=document.getElementsByName("precio_compra[]");
	var sub=document.getElementsByName("subtotal");

	for (var i = 0; i < cant.length; i++) {
		var inpC=cant[i];
		var inpP=prec[i];
		var inpS=sub[i];

		inpC.value=normalizarCantidadEntera(inpC.value, 1);
		inpS.value=(parseFloat(inpC.value||0)*parseFloat(inpP.value||0)).toFixed(2);
		document.getElementsByName("subtotal")[i].innerHTML=inpS.value;
	}

	calcularTotales();
}

function calcularTotales(){
	var sub = document.getElementsByName("subtotal");
	var total=0.0;

	for (var i = 0; i < sub.length; i++) {
		total += parseFloat(document.getElementsByName("subtotal")[i].value || 0);
	}
	$("#total").html(moneyIngreso(total));
	$("#total_compra").val(total.toFixed(2));
	evaluar();
}

function evaluar(){

	if (detalles>0 && !modoVer)
	{
		$("#btnGuardar").show();
	}
	else
	{
		$("#btnGuardar").hide();
		cont=0;
	}
}

function quitarFilaLocal(indice){
	$("#fila"+indice).remove();
	calcularTotales();
	detalles=detalles-1;
	if (detalles < 0) detalles = 0;
	actualizarContadorItems();
	actualizarResumenGuardado();
	guardarBorradorIngreso();
}

function eliminarDetalle(indice){
	var $fila = $("#fila"+indice);
	if (!$fila.length) return;
	clearTimeout($fila.data("timer"));
	if ($fila.data("guardando")) {
		// Esperar a que termine el guardado en curso para no dejar la fila huérfana en la BD
		$fila.data("eliminarAlTerminar", true);
		setEstadoFila($fila, "guardando", "Quitando…");
		($fila.data("promesa") || $.Deferred().resolve().promise()).always(function(){
			$fila.data("eliminarAlTerminar", false);
			eliminarDetalle(indice);
		});
		return;
	}
	var iddet = parseInt($fila.attr("data-iddetalle"), 10) || 0;
	if (iddet <= 0) {
		quitarFilaLocal(indice);
		return;
	}
	setEstadoFila($fila, "guardando", "Quitando…");
	$.post("../ajax/ingreso.php?op=eliminarDetalle", { iddetalle: iddet }, function(resp){
		var r = parseJsonIngreso(resp);
		if (r.ok) {
			if (typeof r.nuevo_total !== "undefined") window._totalGuardadoIngreso = parseFloat(r.nuevo_total);
			quitarFilaLocal(indice);
			notifyIngreso("info", "Artículo quitado de la compra.");
		} else {
			setEstadoFila($fila, "guardado");
			notifyIngreso("error", r.message || "No se pudo quitar el artículo.");
		}
	}).fail(function(){
		setEstadoFila($fila, "guardado");
		notifyIngreso("error", "Sin conexión con el servidor. No se pudo quitar el artículo.");
	});
}

function actualizarContadorItems(){
	var count = document.getElementsByName("idarticulo[]").length;
	$("#comprasItemsSeleccionados").text(count);
}

function estilizarBuscadorCatalogo(){
	var $filtro = $("#tblarticulos_filter");
	if ($filtro.length) {
		$filtro.addClass("catalog-search-wrap");
		$filtro.find("label").addClass("catalog-search-label");
		$filtro.find("input").addClass("catalog-search-input").attr("placeholder","Buscar por nombre o código");
	}
}

// ── Utilidad HTML escape ──────────────────────────────────────────
function escHtml(s) {
	return String(s === null || typeof s === "undefined" ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Detección de borradores al abrir "Agregar" ────────────────────
function detectarBorradores(){
	$.get("../ajax/ingreso.php?op=borradorPendiente", function(resp){
		var r = parseJsonIngreso(resp);
		if (r.ok && r.borrador && r.borrador.idingreso) {
			mostrarBannerBorradorPendiente(r.borrador);
			return;
		}
		restaurarBorradorIngreso();
	}).fail(function(){
		restaurarBorradorIngreso();
	});
}

function mostrarBannerBorradorPendiente(b){
	window._borradorPendiente = b;
	$("#bannerBorradorPendiente").remove();
	$('<div id="bannerBorradorPendiente" class="alert alert-warning" style="font-size:13px;font-weight:600;">' +
		'<i class="fa fa-clock-o"></i> Tienes una compra en borrador sin confirmar: ' +
		'<strong>' + escHtml(b.proveedor || '?') + '</strong> · ' + escHtml(b.tipo_comprobante || '') + ' ' + escHtml(b.serie_comprobante || '') + '-' + escHtml(b.num_comprobante || '') +
		' · ' + (parseInt(b.items, 10) || 0) + ' artículo(s) · ' + escHtml(b.fecha || '') +
		' &nbsp;<button type="button" class="btn btn-xs btn-success" onclick="continuarBorradorPendiente()"><i class="fa fa-edit"></i> Continuar esa compra</button>' +
		' &nbsp;<button type="button" class="btn btn-xs btn-default" onclick="descartarBorradorPendiente()">Descartar</button>' +
	'</div>').prependTo("#formularioregistros form");
}

function continuarBorradorPendiente(){
	var b = window._borradorPendiente;
	if (!b) return;
	continuarBorrador(b.idingreso);
}

function descartarBorradorPendiente(){
	var b = window._borradorPendiente;
	if (!b) return;
	bootbox.confirm({
		title: "Descartar borrador",
		message: "Se eliminará ese borrador (" + (parseInt(b.items, 10) || 0) + " artículo(s)) y el stock volverá a su valor anterior. ¿Continuar?",
		buttons: { confirm: { label: "Sí, descartar", className: "btn-danger" }, cancel: { label: "Cancelar", className: "btn-default" } },
		callback: function(result){
			if (!result) return;
			$.post("../ajax/ingreso.php?op=descartarBorrador", { idingreso: b.idingreso }, function(resp){
				var r = parseJsonIngreso(resp);
				notifyIngreso(r.ok ? "success" : "error", r.message || "");
				if (r.ok) {
					$("#bannerBorradorPendiente").remove();
					window._borradorPendiente = null;
					if (tabla) tabla.ajax.reload(null, false);
					restaurarBorradorIngreso();
				}
			});
		}
	});
}

/** Reabre una compra en Borrador con sus filas ya guardadas. */
function continuarBorrador(idingreso){
	modoAmpliar = false;
	modoVer = false;
	mostrarform(true, false);
	$("#btnGuardar").html('<i class="fa fa-check-circle"></i> Confirmar compra');
	$.get("../ajax/ingreso.php?op=detalleJson&id=" + idingreso, function(resp){
		var r = parseJsonIngreso(resp);
		if (!r.ok || !r.cabecera) {
			notifyIngreso("error", r.message || "No se pudo cargar el borrador.");
			salirFormulario();
			return;
		}
		var c = r.cabecera;
		if (c.estado !== 'Borrador') {
			notifyIngreso("warning", "Esta compra ya no está en borrador.");
			salirFormulario();
			listar();
			return;
		}
		fijarCabeceraDesdeBD(c);
		estadoIngresoActual = 'Borrador';
		mostrarBannerBorradorDB(c);
		$("#detalles .filas").remove();
		cont = 0; detalles = 0;
		var items = r.items || [];
		for (var i = 0; i < items.length; i++) {
			agregarFilaCarrito(items[i]);
		}
		window._totalGuardadoIngreso = parseFloat(c.total_compra || 0);
		// Filas que quedaron sin guardar en este navegador (borrador local)
		var local = leerBorradorLocal();
		var pendientes = 0;
		if (local && String(local.idingreso) === String(idingreso) && local.items) {
			for (var j = 0; j < local.items.length; j++) {
				if (!local.items[j].iddetalle) {
					var $f = agregarFilaCarrito($.extend({}, local.items[j], { iddetalle: 0 }));
					setEstadoFila($f, "incompleta", "Revisa la fila");
					pendientes++;
				}
			}
		}
		modificarSubtotales();
		actualizarContadorItems();
		actualizarResumenGuardado();
		$("#btnAgregarArt").show();
		notifyIngreso("info", "Continuando compra en borrador: " + items.length + " artículo(s) ya guardados" + (pendientes ? ", " + pendientes + " pendiente(s) de completar" : "") + ".");
	}).fail(function(){
		notifyIngreso("error", "Sin conexión con el servidor.");
		salirFormulario();
	});
}

// ── Borrador local (localStorage): solo respalda filas aún no guardadas ──
var BORRADOR_KEY_ING = 'farmacia_borrador_ingreso';

function itemsCarritoLocal(){
	var items = [];
	filasCarrito().each(function() {
		var $f = $(this);
		var d = leerFila($f);
		items.push({
			iddetalle:         d.iddetalle,
			idarticulo:        d.idarticulo,
			nombre:            nombreFila($f),
			unidad:            $f.find('td').eq(2).text().trim(),
			cantidad:          d.cantidad,
			precio_compra:     d.precio_compra,
			precio_venta:      d.precio_venta,
			numero_lote:       d.numero_lote,
			fecha_vencimiento: d.fecha_vencimiento
		});
	});
	return items;
}

function leerBorradorLocal(){
	var raw;
	try { raw = localStorage.getItem(BORRADOR_KEY_ING); } catch(e) { return null; }
	if (!raw) return null;
	try { return JSON.parse(raw); } catch(e) { return null; }
}

function guardarBorradorIngreso() {
	if (!$("#formularioregistros").is(":visible")) return;
	if (modoVer) return;
	if (modoAmpliar) { guardarBorradorAmpliar(); return; }
	var items = itemsCarritoLocal();
	if (items.length === 0) {
		limpiarBorradorIngreso();
		return;
	}
	try {
		localStorage.setItem(BORRADOR_KEY_ING, JSON.stringify({
			ts: Date.now(),
			idingreso:        parseInt($("#idingreso").val(), 10) || 0,
			idproveedor:      $("#idproveedor").val(),
			proveedor_nombre: $("#idproveedor option:selected").text(),
			tipo_comprobante: $("#tipo_comprobante").val(),
			metodo_pago:      $("#ing_metodo_pago").val(),
			items: items
		}));
	} catch(e) {}
}

function limpiarBorradorIngreso() {
	try { localStorage.removeItem(BORRADOR_KEY_ING); } catch(e) {}
}

function restaurarBorradorIngreso() {
	var b = leerBorradorLocal();
	if (!b || !b.items || b.items.length === 0) return;
	var pendientes = [];
	for (var i = 0; i < b.items.length; i++) {
		if (!b.items[i].iddetalle) pendientes.push(b.items[i]);
	}
	if (pendientes.length === 0) {
		// Todo lo que había ya está en la BD (o el borrador se confirmó/descartó)
		limpiarBorradorIngreso();
		return;
	}
	var hace = '';
	if (b.ts) {
		var mins = Math.round((Date.now() - b.ts) / 60000);
		hace = mins < 60 ? ('hace ' + mins + ' min') : ('hace ' + Math.floor(mins/60) + ' h');
	}
	window._borradorIngreso = $.extend({}, b, { items: pendientes });
	$('<div id="bannerBorradorIngreso" class="alert alert-warning" style="font-size:13px;font-weight:600;">' +
		'<i class="fa fa-clock-o"></i> Tienes ' + pendientes.length + ' artículo(s) sin guardar de una compra anterior ' + hace +
		' (Proveedor: ' + escHtml(b.proveedor_nombre || '?') + ').' +
		' &nbsp;<button type="button" class="btn btn-xs btn-success" onclick="restaurarBorradorIngresoConfirmar()">Restaurar</button>' +
		' &nbsp;<button type="button" class="btn btn-xs btn-default" onclick="descartarBorradorIngreso()">Descartar</button>' +
	'</div>').prependTo("#formularioregistros form");
}

function restaurarBorradorIngresoConfirmar() {
	var b = window._borradorIngreso;
	if (!b) return;
	if (b.idproveedor && !(parseInt($("#idingreso").val(), 10) > 0)) {
		$("#idproveedor").val(b.idproveedor);
		try { $("#idproveedor").selectpicker("refresh"); } catch(e) {}
	}
	if (b.tipo_comprobante && !(parseInt($("#idingreso").val(), 10) > 0)) {
		$("#tipo_comprobante").val(b.tipo_comprobante);
		try { $("#tipo_comprobante").selectpicker("refresh"); } catch(e) {}
		aplicarSerieImpuestoIngreso();
	}
	if (b.metodo_pago) $("#ing_metodo_pago").val(b.metodo_pago);
	for (var i = 0; i < b.items.length; i++) {
		var $f = agregarFilaCarrito($.extend({}, b.items[i], { iddetalle: 0 }));
		setEstadoFila($f, "incompleta", "Revisa la fila");
		programarAutoguardado($f, 1200);
	}
	modificarSubtotales();
	actualizarContadorItems();
	$("#bannerBorradorIngreso").remove();
	notifyIngreso("success", "Filas restauradas: " + b.items.length + ". Se guardarán solas al estar completas.");
}

function descartarBorradorIngreso() {
	limpiarBorradorIngreso();
	window._borradorIngreso = null;
	$("#bannerBorradorIngreso").remove();
	notifyIngreso("info", "Borrador descartado.");
}

// ── Borrador local en modo AMPLIAR (por idingreso) ────────────────
var BORRADOR_KEY_AMP = 'farmacia_borrador_ampliar_';

function guardarBorradorAmpliar() {
	var idingreso = modoAmpliarData.idingreso || $("#idingreso").val();
	if (!idingreso) return;
	var items = itemsCarritoLocal().filter(function(it){ return !it.iddetalle; });
	if (items.length === 0) {
		try { localStorage.removeItem(BORRADOR_KEY_AMP + idingreso); } catch(e) {}
		return;
	}
	try {
		localStorage.setItem(BORRADOR_KEY_AMP + idingreso, JSON.stringify({
			ts:          Date.now(),
			idingreso:   idingreso,
			proveedor:   modoAmpliarData.proveedor,
			comprobante: modoAmpliarData.comprobante,
			items:       items
		}));
	} catch(e) {}
}

function limpiarBorradorAmpliar() {
	var idingreso = modoAmpliarData.idingreso || $("#idingreso").val();
	if (!idingreso) return;
	try { localStorage.removeItem(BORRADOR_KEY_AMP + idingreso); } catch(e) {}
}

function restaurarBorradorAmpliar(idingreso) {
	var raw;
	try { raw = localStorage.getItem(BORRADOR_KEY_AMP + idingreso); } catch(e) { return; }
	if (!raw) return;
	var b;
	try { b = JSON.parse(raw); } catch(e) { return; }
	if (!b || !b.items || b.items.length === 0) return;
	var hace = '';
	if (b.ts) {
		var mins = Math.round((Date.now() - b.ts) / 60000);
		hace = mins < 60 ? ('hace ' + mins + ' min') : ('hace ' + Math.floor(mins / 60) + ' h');
	}
	window._borradorAmpliar = b;
	$('<div id="bannerBorradorAmpliar" class="alert alert-warning" style="font-size:13px;font-weight:600;margin-top:8px;">' +
		'<i class="fa fa-clock-o"></i>&nbsp; Borrador guardado ' + hace + ' — ' +
		b.items.length + ' artículo(s) sin guardar en esta compra.' +
		'&nbsp;&nbsp;<button type="button" class="btn btn-xs btn-success" onclick="restaurarBorradorAmpliarConfirmar()"><i class="fa fa-undo"></i> Restaurar</button>' +
		'&nbsp;<button type="button" class="btn btn-xs btn-default" onclick="descartarBorradorAmpliar()">Descartar</button>' +
	'</div>').insertAfter("#bannerAmpliar");
}

function restaurarBorradorAmpliarConfirmar() {
	var b = window._borradorAmpliar;
	if (!b) return;
	for (var i = 0; i < b.items.length; i++) {
		var $f = agregarFilaCarrito($.extend({}, b.items[i], { iddetalle: 0 }));
		setEstadoFila($f, "incompleta", "Revisa la fila");
		programarAutoguardado($f, 1200);
	}
	modificarSubtotales();
	actualizarContadorItems();
	$("#bannerBorradorAmpliar").remove();
	notifyIngreso("success", "Filas restauradas: " + b.items.length + ".");
}

function descartarBorradorAmpliar() {
	limpiarBorradorAmpliar();
	window._borradorAmpliar = null;
	$("#bannerBorradorAmpliar").remove();
	notifyIngreso("info", "Borrador descartado.");
}

// ── Ampliar ingreso existente (Aceptado) ──────────────────────────
function abrirAmpliarIngreso(idingreso) {
	modoAmpliar = true;
	modoVer = false;
	mostrarform(true);
	modoAmpliar = true;
	$.post("../ajax/ingreso.php?op=mostrar", { idingreso: idingreso }, function(data) {
		var d = parseJsonIngreso(data);
		if (!d || !d.idingreso) {
			notifyIngreso("error", "No se pudo cargar el ingreso.");
			salirFormulario(); return;
		}
		if (d.estado !== 'Aceptado') {
			notifyIngreso("warning", "Solo se pueden ampliar compras en estado Aceptado.");
			salirFormulario(); return;
		}
		fijarCabeceraDesdeBD(d);
		estadoIngresoActual = 'Aceptado';
		window._totalGuardadoIngreso = parseFloat(d.total_compra || 0);

		modoAmpliarData = {
			idingreso:   d.idingreso,
			proveedor:   d.proveedor || '',
			comprobante: (d.tipo_comprobante||'') + ' ' + (d.serie_comprobante||'') + '-' + (d.num_comprobante||'')
		};

		$('<div id="bannerAmpliar" class="alert alert-success" style="font-weight:600;margin-bottom:10px;">' +
			'<i class="fa fa-plus-circle"></i>&nbsp; MODO AMPLIAR COMPRA &nbsp;|&nbsp; ' +
			'Proveedor: <strong>' + escHtml(d.proveedor) + '</strong> &nbsp;|&nbsp; ' +
			'Comprobante: <strong>' + escHtml(d.tipo_comprobante) + ' ' + escHtml(d.serie_comprobante) + '-' + escHtml(d.num_comprobante) + '</strong>' +
			' &nbsp;|&nbsp; <small>Cada artículo nuevo se guarda solo al completar su fila.</small>' +
		'</div>').prependTo("#formularioregistros form");

		$("#btnGuardar").html('<i class="fa fa-check-circle"></i> Confirmar cambios').hide();
		$("#btnAgregarArt").show();

		// Verificar si hay borrador guardado para este ingreso
		restaurarBorradorAmpliar(d.idingreso);

		$.get("../ajax/ingreso.php?op=listarDetalle&id=" + idingreso, function(html) {
			$('<div id="panelItemsExistentes" style="clear:both;margin-bottom:12px;">' +
				'<div class="panel panel-default" style="margin-bottom:0;">' +
				'<div class="panel-heading" style="background:#dff0d8;padding:8px 12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">' +
				'<div style="flex:1;cursor:pointer;min-width:0;" onclick="$(\'#itemsExistentesBody\').toggle();">' +
				'<i class="fa fa-list-ul"></i> <strong>Artículos ya registrados en esta compra</strong> <small class="text-muted">(click para ver/ocultar)</small>' +
				'</div>' +
				'<div style="flex-shrink:0;">' +
				'<div class="input-group input-group-sm" style="width:220px;">' +
				'<span class="input-group-addon" style="background:#fff;"><i class="fa fa-search text-muted"></i></span>' +
				'<input type="text" id="buscarItemExistente" class="form-control" placeholder="Buscar artículo..." ' +
				'oninput="filtrarItemsExistentes(this.value)" onclick="event.stopPropagation();" ' +
				'style="border-left:0;" />' +
				'</div></div></div>' +
				'<div id="itemsExistentesBody" style="display:none;overflow-x:auto;">' +
				'<table id="tablaItemsExistentes" class="table table-condensed table-bordered" style="margin:0;">' + html + '</table>' +
				'</div></div></div>'
			).insertBefore($("#detalles").closest(".form-group"));
		});
	});
}

function filtrarItemsExistentes(valor) {
	var termino = (valor || "").toLowerCase().trim();
	$("#tablaItemsExistentes tbody tr, #tablaItemsExistentes tr.filas").each(function() {
		var nombre = $(this).find("td").eq(1).text().toLowerCase();
		$(this).toggle(termino === "" || nombre.indexOf(termino) !== -1);
	});
}

// ── Edición interactiva de filas ya guardadas (ver detalle / ampliar) ──
function recalcFilaDetalle(input) {
	var $fila = $(input).closest('tr');
	var cant = parseFloat($fila.find('input[name="det_cantidad"]').val() || 0);
	var pcom = parseFloat($fila.find('input[name="det_precio_compra"]').val() || 0);
	$fila.find('.det-subtotal').text((cant * pcom).toFixed(2));
}

function guardarFilaDetalle(iddetalle) {
	var $fila = $('[data-iddetalle="' + iddetalle + '"]').filter(function(){ return $(this).find('input[name="det_cantidad"]').length > 0; }).first();
	var cantidad          = $fila.find('input[name="det_cantidad"]').val();
	var precio_compra     = $fila.find('input[name="det_precio_compra"]').val();
	var precio_venta      = $fila.find('input[name="det_precio_venta"]').val();
	var numero_lote       = $fila.find('input[name="det_numero_lote"]').val();
	var fecha_vencimiento = $fila.find('input[name="det_fecha_vencimiento"]').val();

	if (!cantidad || parseFloat(cantidad) <= 0) {
		notifyIngreso("warning", "La cantidad debe ser mayor que cero.");
		return;
	}
	if (!fecha_vencimiento) {
		notifyIngreso("warning", "La fecha de vencimiento es obligatoria.");
		$fila.find('input[name="det_fecha_vencimiento"]').css("border", "2px solid #d9534f").focus();
		return;
	}
	var hoy = new Date(); hoy.setHours(0,0,0,0);
	var fv = new Date(fecha_vencimiento + 'T00:00:00');
	if (isNaN(fv.getTime()) || fv < hoy) {
		notifyIngreso("warning", "La fecha de vencimiento debe ser válida y no estar vencida.");
		$fila.find('input[name="det_fecha_vencimiento"]').css("border", "2px solid #d9534f").focus();
		return;
	}
	var $btn = $fila.find('button');
	$btn.prop("disabled", true);

	$.post("../ajax/ingreso.php?op=actualizarDetalle", {
		iddetalle:        iddetalle,
		cantidad:         cantidad,
		precio_compra:    precio_compra,
		precio_venta:     precio_venta,
		numero_lote:      numero_lote,
		fecha_vencimiento: fecha_vencimiento
	}, function(resp) {
		var r = parseJsonIngreso(resp);
		if (r.ok) {
			notifyIngreso("success", r.message || "Detalle actualizado.");
			if (typeof r.nuevo_total !== "undefined") {
				$("#det-total-view").text(moneyIngreso(r.nuevo_total));
				window._totalGuardadoIngreso = parseFloat(r.nuevo_total);
			}
			var idingreso = $("#idingreso").val();
			if (idingreso) {
				$.post("../ajax/ingreso.php?op=listarDetalle&id=" + idingreso, function(html) {
					if (modoAmpliar) {
						$("#tablaItemsExistentes").html(html);
					} else {
						$("#detalles").html(html);
					}
					actualizarContadorItems();
				});
			}
			if (tabla) { tabla.ajax.reload(null, false); }
		} else {
			notifyIngreso("error", r.message || "No se pudo actualizar.");
		}
		$btn.prop("disabled", false);
	}).fail(function(){
		notifyIngreso("error", "Error de conexión al guardar.");
		$btn.prop("disabled", false);
	});
}

function eliminarFilaDetalle(iddetalle) {
	if (!confirm("¿Eliminar este artículo de la compra? Esta acción no se puede deshacer.")) {
		return;
	}
	var $fila = $('[data-iddetalle="' + iddetalle + '"]').filter(function(){ return $(this).find('input[name="det_cantidad"]').length > 0; }).first();
	var $btn = $fila.find('.btn-danger');
	$btn.prop("disabled", true);

	$.post("../ajax/ingreso.php?op=eliminarDetalle", { iddetalle: iddetalle }, function(resp) {
		var r = parseJsonIngreso(resp);
		if (r.ok) {
			notifyIngreso("success", r.message || "Artículo eliminado de la compra.");
			if (typeof r.nuevo_total !== "undefined") {
				$("#det-total-view").text(moneyIngreso(r.nuevo_total));
				window._totalGuardadoIngreso = parseFloat(r.nuevo_total);
			}
			var idingreso = $("#idingreso").val();
			if (idingreso) {
				$.get("../ajax/ingreso.php?op=listarDetalle&id=" + idingreso, function(html) {
					if (modoAmpliar) {
						$("#tablaItemsExistentes").html(html);
					} else {
						$("#detalles").html(html);
					}
					actualizarContadorItems();
				});
			}
			if (tabla) { tabla.ajax.reload(null, false); }
		} else {
			notifyIngreso("error", r.message || "No se pudo eliminar el artículo.");
			$btn.prop("disabled", false);
		}
	}).fail(function(){
		notifyIngreso("error", "Error de conexión al eliminar.");
		$btn.prop("disabled", false);
	});
}

init();
