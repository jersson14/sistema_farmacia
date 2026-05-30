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
var modoAmpliarData = { idingreso: 0, proveedor: '', comprobante: '' };
var borradorTimerIngreso = null;

function notifyIngreso(type, message){
	if (typeof appNotify === "function") {
		appNotify(type, message);
		return;
	}
	alert(message);
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
   $(document).on("input change", "#detalles input", function(){
   	clearTimeout(borradorTimerIngreso);
   	borradorTimerIngreso = setTimeout(guardarBorradorIngreso, 1500);
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
			var r = {};
			try {
				r = JSON.parse(resp);
			} catch (e) {
				r = {ok:false, message:"No se pudo registrar el proveedor."};
			}
			if (!r.ok) {
				notifyIngreso("error", r.message || "No se pudo registrar el proveedor.");
				$("#btnGuardarProveedorRapido").prop("disabled", false);
				return;
			}
			notifyIngreso("success", r.message || "Proveedor registrado correctamente.");
			$("#modalProveedorIngreso").modal("hide");
			cargarProveedores(r.idproveedor || "");
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
	$(".filas").remove();
	$("#total").html(window.appMoney ? window.appMoney(0,2) : ((window.appCurrencySymbol || "S/") + " 0.00"));
	actualizarContadorItems();

	$("#fecha_hora").val(fechaHoraActualInput());

	//marcamos el primer tipo_documento
	$("#tipo_comprobante").val("Boleta");
	$("#tipo_comprobante").selectpicker('refresh');
	aplicarSerieImpuestoIngreso();

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
			restaurarBorradorIngreso();
		}
	}else{
		modoAmpliar = false;
		$("#bannerAmpliar, #panelItemsExistentes").remove();
		$("#serie_comprobante, #num_comprobante, #fecha_hora").prop("readonly", false);
		$("#btnGuardar").html('<i class="fa fa-save"></i>  Guardar');
		$("#listadoregistros").show();
		$("#formularioregistros").hide();
		$("#btnagregar").show();
	}
}

//cancelar form
function cancelarform(){
	if (modoAmpliar) {
		modoAmpliar = false;
		$("#bannerAmpliar, #panelItemsExistentes").remove();
		$("#serie_comprobante, #num_comprobante, #fecha_hora").prop("readonly", false);
		$("#btnGuardar").html('<i class="fa fa-save"></i>  Guardar');
	}
	limpiar();
	mostrarform(false);
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

//funcion para guardaryeditar
function guardaryeditar(e){
     e.preventDefault();
     if (modoAmpliar) {
     	guardarAmpliacion();
     	return;
     }
     var proveedorSeleccionado = ($("#idproveedor").val() || "").toString().trim();
     if (!proveedorSeleccionado) {
     	notifyIngreso("warning", "Selecciona un proveedor antes de guardar.");
     	return;
     }
     // Validar fecha_vencimiento obligatoria en todos los artículos
     var fvFaltantes = [];
     $("#detalles .filas").each(function(){
     	var $fv = $(this).find('input[name="fecha_vencimiento[]"]');
     	if ($fv.length && !$.trim($fv.val())) {
     		$fv.css("border", "2px solid #d9534f");
     		var nombre = $(this).find('td').eq(1).clone().find('input').remove().end().text().trim();
     		fvFaltantes.push(nombre || '?');
     	}
     });
     if (fvFaltantes.length > 0) {
     	notifyIngreso("warning", "Falta fecha de vencimiento en: " + fvFaltantes.join(', '));
     	return;
     }
     var formData=new FormData($("#formulario")[0]);

     $.ajax({
     	url: "../ajax/ingreso.php?op=guardaryeditar",
     	type: "POST",
     	data: formData,
     	contentType: false,
     	processData: false,
     	success: function(datos){
     		var r = null;
     		try {
     			r = JSON.parse(datos);
     		} catch (e) {
     			r = null;
     		}

     		if (r && typeof r.ok !== "undefined") {
     			if (r.ok) {
     				notifyIngreso("success", r.message || "Datos registrados correctamente");
     				limpiarBorradorIngreso();
     				mostrarform(false);
     				listar();
     			} else {
     				notifyIngreso("error", r.message || "No se pudo registrar el ingreso.");
     			}
     	} else {
     		if ((datos || "").trim() === "") {
     			notifyIngreso("error", "No se recibio respuesta del servidor.");
     		} else {
     			notifyIngreso("success", datos);
     			limpiarBorradorIngreso();
     			mostrarform(false);
     			listar();
     		}
     	}
     	}
     });
}

function mostrar(idingreso){
	$.post("../ajax/ingreso.php?op=mostrar",{idingreso : idingreso},
		function(data,status)
		{
			data=JSON.parse(data);
			mostrarform(true);

			$("#idproveedor").val(data.idproveedor);
			$("#idproveedor").selectpicker('refresh');
			$("#tipo_comprobante").val(data.tipo_comprobante);
			$("#tipo_comprobante").selectpicker('refresh');
			$("#serie_comprobante").val(data.serie_comprobante);
			$("#num_comprobante").val(data.num_comprobante);
			$("#fecha_hora").val(normalizarFechaHoraInput(data.fecha));
			$("#impuesto").val(data.impuesto);
			$("#idingreso").val(data.idingreso);

			//ocultar y mostrar los botones
			$("#btnGuardar").hide();
			$("#btnCancelar").show();
			$("#btnAgregarArt").hide();
		});
	$.post("../ajax/ingreso.php?op=listarDetalle&id="+idingreso,function(r){
		$("#detalles").html(r);
		actualizarContadorItems();
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
		if (!numeroComprobanteManualIngreso || !$.trim($("#num_comprobante").val())) {
			$("#num_comprobante").val(r.numero || "");
			numeroComprobanteManualIngreso = false;
		}
	});
}

function agregarDetalle(idarticulo,articulo,unidad,precio_compra_ref){
	var cantidad=1;
	var precio_compra=(precio_compra_ref && parseFloat(precio_compra_ref) > 0) ? parseFloat(precio_compra_ref) : 1;
	var precio_venta=1;
	var unidadTexto = unidad || "und";
	var articulos = document.getElementsByName("idarticulo[]");
	var cantidades = document.getElementsByName("cantidad[]");

	if (idarticulo!="") {
		for (var i = 0; i < articulos.length; i++) {
			if (parseInt(articulos[i].value, 10) === parseInt(idarticulo, 10)) {
				var nuevaCantidad = normalizarCantidadEntera(parseFloat(cantidades[i].value || 0) + 1, 1);
				cantidades[i].value = nuevaCantidad;
				modificarSubtotales();
				$('#myModal').modal('hide');
				notifyIngreso("info", "El artículo ya estaba agregado. Se incrementó la cantidad.");
				guardarBorradorIngreso();
				return;
			}
		}

		var subtotal=cantidad*precio_compra;
		var fvStyle = 'style="width:130px;border:2px solid #d9534f;" title="Fecha de vencimiento obligatoria" oninput="this.style.border=this.value?\'1px solid #ccc\':\'2px solid #d9534f\'"';
		var fvLabel = '<span style="color:#d9534f;font-size:10px;display:block;">* obligatorio</span>';
		var fila='<tr class="filas" id="fila'+cont+'">'+
        '<td><button type="button" class="btn btn-danger" onclick="eliminarDetalle('+cont+')">X</button></td>'+
        '<td><input type="hidden" name="idarticulo[]" value="'+idarticulo+'">'+articulo+'</td>'+
        '<td>'+unidadTexto+'</td>'+
        '<td><input type="number" step="1" min="1" name="cantidad[]" id="cantidad[]" value="'+cantidad+'" oninput="modificarSubtotales()"></td>'+
        '<td><input type="number" step="0.01" min="0.01" name="precio_compra[]" id="precio_compra[]" value="'+precio_compra.toFixed(2)+'" oninput="modificarSubtotales()"></td>'+
        '<td><input type="number" step="0.01" min="0.01" name="precio_venta[]" value="'+precio_venta.toFixed(2)+'"></td>'+
        '<td><input type="text" name="numero_lote[]" maxlength="50" placeholder="N° Lote" style="width:90px"></td>'+
        '<td><input type="date" name="fecha_vencimiento[]" '+fvStyle+'>'+fvLabel+'</td>'+
        '<td><span id="subtotal'+cont+'" name="subtotal">'+subtotal.toFixed(2)+'</span></td>'+
        '<td><button type="button" onclick="modificarSubtotales()" class="btn btn-info"><i class="fa fa-refresh"></i></button></td>'+
		'</tr>';
		cont++;
		detalles++;
		$('#detalles').append(fila);
		modificarSubtotales();
		actualizarContadorItems();
		$('#myModal').modal('hide');
		notifyIngreso("success", "Artículo agregado a la compra.");
		guardarBorradorIngreso();

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
	$("#total").html(window.appMoney ? window.appMoney(total,2) : ((window.appCurrencySymbol || "S/") + " " + total.toFixed(2)));
	$("#total_compra").val(total.toFixed(2));
	evaluar();
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
	guardarBorradorIngreso();
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
	return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Borrador automático (localStorage) ───────────────────────────
var BORRADOR_KEY_ING = 'farmacia_borrador_ingreso';

function guardarBorradorIngreso() {
	if (!$("#formularioregistros").is(":visible")) return;
	if (modoAmpliar) { guardarBorradorAmpliar(); return; }
	if ($("#idingreso").val()) return;
	var items = [];
	$("#detalles .filas").each(function() {
		var $f = $(this);
		var nombre = $f.find('td').eq(1).clone().find('input').remove().end().text().trim();
		items.push({
			idarticulo:        $f.find('input[name="idarticulo[]"]').val(),
			nombre:            nombre,
			unidad:            $f.find('td').eq(2).text().trim(),
			cantidad:          $f.find('input[name="cantidad[]"]').val(),
			precio_compra:     $f.find('input[name="precio_compra[]"]').val(),
			precio_venta:      $f.find('input[name="precio_venta[]"]').val(),
			numero_lote:       $f.find('input[name="numero_lote[]"]').val(),
			fecha_vencimiento: $f.find('input[name="fecha_vencimiento[]"]').val()
		});
	});
	if (items.length === 0) return;
	try {
		localStorage.setItem(BORRADOR_KEY_ING, JSON.stringify({
			ts: Date.now(),
			idproveedor:      $("#idproveedor").val(),
			proveedor_nombre: $("#idproveedor option:selected").text(),
			tipo_comprobante: $("#tipo_comprobante").val(),
			items: items
		}));
	} catch(e) {}
}

function limpiarBorradorIngreso() {
	try { localStorage.removeItem(BORRADOR_KEY_ING); } catch(e) {}
}

function restaurarBorradorIngreso() {
	var raw;
	try { raw = localStorage.getItem(BORRADOR_KEY_ING); } catch(e) { return; }
	if (!raw) return;
	var b;
	try { b = JSON.parse(raw); } catch(e) { return; }
	if (!b || !b.items || b.items.length === 0) return;
	var hace = '';
	if (b.ts) {
		var mins = Math.round((Date.now() - b.ts) / 60000);
		hace = mins < 60 ? ('hace ' + mins + ' min') : ('hace ' + Math.floor(mins/60) + ' h');
	}
	window._borradorIngreso = b;
	$('<div id="bannerBorradorIngreso" class="alert alert-warning" style="font-size:13px;font-weight:600;">' +
		'<i class="fa fa-clock-o"></i> Tienes un borrador guardado ' + hace + ' con ' +
		b.items.length + ' artículo(s) (Proveedor: ' + escHtml(b.proveedor_nombre || '?') + ').' +
		' &nbsp;<button type="button" class="btn btn-xs btn-success" onclick="restaurarBorradorIngresoConfirmar()">Restaurar borrador</button>' +
		' &nbsp;<button type="button" class="btn btn-xs btn-default" onclick="descartarBorradorIngreso()">Descartar</button>' +
	'</div>').prependTo("#formularioregistros form");
}

function restaurarBorradorIngresoConfirmar() {
	var b = window._borradorIngreso;
	if (!b) return;
	$(".filas").remove();
	detalles = 0; cont = 0;
	if (b.idproveedor) {
		$("#idproveedor").val(b.idproveedor);
		try { $("#idproveedor").selectpicker("refresh"); } catch(e) {}
	}
	if (b.tipo_comprobante) {
		$("#tipo_comprobante").val(b.tipo_comprobante);
		try { $("#tipo_comprobante").selectpicker("refresh"); } catch(e) {}
		aplicarSerieImpuestoIngreso();
	}
	for (var i = 0; i < b.items.length; i++) {
		var it = b.items[i];
		var pcv = parseFloat(it.precio_compra || 0);
		var sub = (parseFloat(it.cantidad || 1) * pcv).toFixed(2);
		var fila = '<tr class="filas" id="fila' + cont + '">' +
			'<td><button type="button" class="btn btn-danger" onclick="eliminarDetalle(' + cont + ')">X</button></td>' +
			'<td><input type="hidden" name="idarticulo[]" value="' + escHtml(it.idarticulo) + '">' + escHtml(it.nombre) + '</td>' +
			'<td>' + escHtml(it.unidad) + '</td>' +
			'<td><input type="number" step="1" min="1" name="cantidad[]" value="' + parseFloat(it.cantidad || 1) + '" oninput="modificarSubtotales()"></td>' +
			'<td><input type="number" step="0.01" min="0.01" name="precio_compra[]" value="' + pcv.toFixed(2) + '" oninput="modificarSubtotales()"></td>' +
			'<td><input type="number" step="0.01" min="0.01" name="precio_venta[]" value="' + parseFloat(it.precio_venta || 0).toFixed(2) + '"></td>' +
			'<td><input type="text" name="numero_lote[]" maxlength="50" placeholder="N° Lote" style="width:90px" value="' + escHtml(it.numero_lote || '') + '"></td>' +
			'<td><input type="date" name="fecha_vencimiento[]" style="width:130px;border:' + (it.fecha_vencimiento ? '1px solid #ccc' : '2px solid #d9534f') + ';" oninput="this.style.border=this.value?\'1px solid #ccc\':\'2px solid #d9534f\'" value="' + escHtml(it.fecha_vencimiento || '') + '" title="Obligatorio"><span style="color:#d9534f;font-size:10px;display:block;">* obligatorio</span></td>' +
			'<td><span id="subtotal' + cont + '" name="subtotal">' + sub + '</span></td>' +
			'<td><button type="button" onclick="modificarSubtotales()" class="btn btn-info"><i class="fa fa-refresh"></i></button></td>' +
			'</tr>';
		cont++; detalles++;
		$('#detalles').append(fila);
	}
	modificarSubtotales();
	actualizarContadorItems();
	$("#bannerBorradorIngreso").remove();
	notifyIngreso("success", "Borrador restaurado: " + b.items.length + " artículo(s).");
}

function descartarBorradorIngreso() {
	limpiarBorradorIngreso();
	window._borradorIngreso = null;
	$("#bannerBorradorIngreso").remove();
	notifyIngreso("info", "Borrador descartado.");
}

// ── Borrador automático en modo AMPLIAR (localStorage por idingreso) ──
var BORRADOR_KEY_AMP = 'farmacia_borrador_ampliar_';

function guardarBorradorAmpliar() {
	var idingreso = modoAmpliarData.idingreso || $("#idingreso").val();
	if (!idingreso) return;
	var items = [];
	$("#detalles .filas").each(function() {
		var $f = $(this);
		var nombre = $f.find('td').eq(1).clone().find('input').remove().end().text().trim();
		items.push({
			idarticulo:        $f.find('input[name="idarticulo[]"]').val(),
			nombre:            nombre,
			unidad:            $f.find('td').eq(2).text().trim(),
			cantidad:          $f.find('input[name="cantidad[]"]').val(),
			precio_compra:     $f.find('input[name="precio_compra[]"]').val(),
			precio_venta:      $f.find('input[name="precio_venta[]"]').val(),
			numero_lote:       $f.find('input[name="numero_lote[]"]').val(),
			fecha_vencimiento: $f.find('input[name="fecha_vencimiento[]"]').val()
		});
	});
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
		b.items.length + ' artículo(s) pendiente(s) de agregar a esta compra.' +
		'&nbsp;&nbsp;<button type="button" class="btn btn-xs btn-success" onclick="restaurarBorradorAmpliarConfirmar()"><i class="fa fa-undo"></i> Restaurar borrador</button>' +
		'&nbsp;<button type="button" class="btn btn-xs btn-default" onclick="descartarBorradorAmpliar()">Descartar</button>' +
	'</div>').insertAfter("#bannerAmpliar");
}

function restaurarBorradorAmpliarConfirmar() {
	var b = window._borradorAmpliar;
	if (!b) return;
	$(".filas").remove();
	detalles = 0; cont = 0;
	for (var i = 0; i < b.items.length; i++) {
		var it = b.items[i];
		var pcv = parseFloat(it.precio_compra || 0);
		var sub = (parseFloat(it.cantidad || 1) * pcv).toFixed(2);
		var fila = '<tr class="filas" id="fila' + cont + '">' +
			'<td><button type="button" class="btn btn-danger" onclick="eliminarDetalle(' + cont + ')">X</button></td>' +
			'<td><input type="hidden" name="idarticulo[]" value="' + escHtml(it.idarticulo) + '">' + escHtml(it.nombre) + '</td>' +
			'<td>' + escHtml(it.unidad) + '</td>' +
			'<td><input type="number" step="1" min="1" name="cantidad[]" value="' + parseFloat(it.cantidad || 1) + '" oninput="modificarSubtotales()"></td>' +
			'<td><input type="number" step="0.01" min="0.01" name="precio_compra[]" value="' + pcv.toFixed(2) + '" oninput="modificarSubtotales()"></td>' +
			'<td><input type="number" step="0.01" min="0.01" name="precio_venta[]" value="' + parseFloat(it.precio_venta || 0).toFixed(2) + '"></td>' +
			'<td><input type="text" name="numero_lote[]" maxlength="50" placeholder="N° Lote" style="width:90px" value="' + escHtml(it.numero_lote || '') + '"></td>' +
			'<td><input type="date" name="fecha_vencimiento[]" style="width:130px;border:2px solid #d9534f;" title="Obligatorio" value="' + escHtml(it.fecha_vencimiento || '') + '"><span style="color:#d9534f;font-size:10px;display:block;">* obligatorio</span></td>' +
			'<td><span id="subtotal' + cont + '" name="subtotal">' + sub + '</span></td>' +
			'<td><button type="button" onclick="modificarSubtotales()" class="btn btn-info"><i class="fa fa-refresh"></i></button></td>' +
			'</tr>';
		cont++; detalles++;
		$('#detalles').append(fila);
	}
	modificarSubtotales();
	actualizarContadorItems();
	$("#bannerBorradorAmpliar").remove();
	notifyIngreso("success", "Borrador restaurado: " + b.items.length + " artículo(s).");
}

function descartarBorradorAmpliar() {
	limpiarBorradorAmpliar();
	window._borradorAmpliar = null;
	$("#bannerBorradorAmpliar").remove();
	notifyIngreso("info", "Borrador descartado.");
}

// ── Ampliar ingreso existente ─────────────────────────────────────
function abrirAmpliarIngreso(idingreso) {
	modoAmpliar = true;
	numeroComprobanteManualIngreso = true;
	mostrarform(true);
	$.post("../ajax/ingreso.php?op=mostrar", { idingreso: idingreso }, function(data) {
		var d;
		try { d = JSON.parse(data); } catch(e) {
			notifyIngreso("error", "No se pudo cargar el ingreso.");
			modoAmpliar = false; mostrarform(false); return;
		}
		$("#idingreso").val(d.idingreso);
		$("#idproveedor").val(d.idproveedor);
		try { $("#idproveedor").selectpicker("refresh"); } catch(e) {}
		$("#tipo_comprobante").val(d.tipo_comprobante);
		try { $("#tipo_comprobante").selectpicker("refresh"); } catch(e) {}
		$("#serie_comprobante").val(d.serie_comprobante).prop("readonly", true);
		$("#num_comprobante").val(d.num_comprobante).prop("readonly", true);
		$("#fecha_hora").val(normalizarFechaHoraInput(d.fecha)).prop("readonly", true);
		$("#impuesto").val(d.impuesto);

		modoAmpliarData = {
			idingreso:   d.idingreso,
			proveedor:   d.proveedor || '',
			comprobante: (d.tipo_comprobante||'') + ' ' + (d.serie_comprobante||'') + '-' + (d.num_comprobante||'')
		};

		$('<div id="bannerAmpliar" class="alert alert-success" style="font-weight:600;margin-bottom:10px;">' +
			'<i class="fa fa-plus-circle"></i>&nbsp; MODO AMPLIAR COMPRA &nbsp;|&nbsp; ' +
			'Proveedor: <strong>' + escHtml(d.proveedor) + '</strong> &nbsp;|&nbsp; ' +
			'Comprobante: <strong>' + escHtml(d.tipo_comprobante) + ' ' + escHtml(d.serie_comprobante) + '-' + escHtml(d.num_comprobante) + '</strong>' +
		'</div>').prependTo("#formularioregistros form");

		$("#btnGuardar").html('<i class="fa fa-plus-circle"></i> Agregar artículos al ingreso').hide();
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
	$("#tablaItemsExistentes tbody tr").each(function() {
		var nombre = $(this).find("td").eq(1).text().toLowerCase();
		$(this).toggle(termino === "" || nombre.indexOf(termino) !== -1);
	});
}

function guardarAmpliacion() {
	var idingreso = $("#idingreso").val();
	if (!idingreso || !parseInt(idingreso, 10)) {
		notifyIngreso("error", "ID de ingreso inválido."); return;
	}
	if (document.getElementsByName("idarticulo[]").length === 0) {
		notifyIngreso("warning", "Agrega al menos un artículo nuevo para guardar."); return;
	}
	// Validar que todos los artículos nuevos tengan fecha de vencimiento
	var faltanFechas = false;
	$("#detalles .filas").each(function(){
		var $fv = $(this).find('input[name="fecha_vencimiento[]"]');
		if ($fv.length) {
			if (!$.trim($fv.val())) {
				$fv.css("border", "2px solid #d9534f");
				faltanFechas = true;
			} else {
				$fv.css("border", "");
			}
		}
	});
	if (faltanFechas) {
		notifyIngreso("warning", "La fecha de vencimiento es obligatoria para todos los artículos.");
		return;
	}
	$("#btnGuardar").prop("disabled", true);
	var formData = new FormData($("#formulario")[0]);
	$.ajax({
		url: "../ajax/ingreso.php?op=agregarDetalle",
		type: "POST",
		data: formData,
		contentType: false,
		processData: false,
		success: function(resp) {
			var r = {};
			try { r = JSON.parse(resp); } catch(e) {}
			if (r.ok) {
				notifyIngreso("success", r.message || "Artículos agregados correctamente.");
				limpiarBorradorAmpliar();
				modoAmpliar = false;
				modoAmpliarData = { idingreso: 0, proveedor: '', comprobante: '' };
				$("#bannerAmpliar, #panelItemsExistentes, #bannerBorradorAmpliar").remove();
				$("#serie_comprobante, #num_comprobante, #fecha_hora").prop("readonly", false);
				mostrarform(false);
				listar();
				cargarResumenPagosIngreso();
			} else {
				notifyIngreso("error", r.message || "No se pudo agregar los artículos.");
			}
			$("#btnGuardar").prop("disabled", false).html('<i class="fa fa-save"></i>  Guardar');
		},
		error: function() {
			notifyIngreso("error", "Error de conexión al guardar.");
			$("#btnGuardar").prop("disabled", false).html('<i class="fa fa-save"></i>  Guardar');
		}
	});
}

// ── Edición interactiva de filas ya guardadas ──────────────────────
function recalcFilaDetalle(input) {
	var $fila = $(input).closest('tr');
	var cant = parseFloat($fila.find('input[name="det_cantidad"]').val() || 0);
	var pcom = parseFloat($fila.find('input[name="det_precio_compra"]').val() || 0);
	$fila.find('.det-subtotal').text((cant * pcom).toFixed(2));
}

function guardarFilaDetalle(iddetalle) {
	var $fila = $('[data-iddetalle="' + iddetalle + '"]');
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
		var r = {};
		try { r = JSON.parse(resp); } catch(e) {}
		if (r.ok) {
			notifyIngreso("success", r.message || "Detalle actualizado.");
			if (typeof r.nuevo_total !== "undefined") {
				var sym = window.appCurrencySymbol || "S/";
				$("#det-total-view").text(sym + " " + parseFloat(r.nuevo_total).toFixed(2));
			}
			// Recargar la tabla de detalles para reflejar los nuevos valores
			var idingreso = $("#idingreso").val();
			if (idingreso) {
				$.post("../ajax/ingreso.php?op=listarDetalle&id=" + idingreso, function(html) {
					$("#detalles").html(html);
					actualizarContadorItems();
				});
			}
			// Actualizar silenciosamente el listado principal
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

init();
