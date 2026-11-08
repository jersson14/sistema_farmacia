var tabla;

function notify(type, message){
	if (typeof appNotify === "function") {
		appNotify(type, message);
		return;
	}
	alert(message);
}

function notifyFromResponse(message){
	if (typeof appNotifyFromResponse === "function") {
		appNotifyFromResponse(message);
		return;
	}
	alert(message);
}

//funcion que se ejecuta al inicio
function init(){
   mostrarform(false);
   listar();

   $("#formulario").on("submit",function(e){
   	guardaryeditar(e);
   })

   //cargamos los items al celect categoria
   $.post("../ajax/articulo.php?op=selectCategoria", function(r){
   	$("#idcategoria").html(r);
   	$("#idcategoria").selectpicker('refresh');
   });

   //cargamos unidades de medida administrables
   $.post("../ajax/articulo.php?op=selectUnidad", function(r){
   	$("#idunidad").html(r);
   	$("#idunidad").selectpicker('refresh');
   	renderPresentaciones();
   });
   // La fila base de presentaciones refleja la unidad, el precio y el código del producto
   $("#idunidad").on("changed.bs.select change", function(){ avisoCambioUnidadBase(); renderPresentaciones(); });
   $("#precio_venta, #codigo, #stock").on("input change", renderPresentacionBase);
   $("#precio_venta").on("input change", recalcularPreciosAuto);
   $("#convUnidad, #convFactor, #convPrecio").on("input change", previewConvertir);
   //galeria: vista previa en vivo de cada uno de los 3 slots
   SLOTS_IMAGEN.forEach(function(campo){
      limpiarSlotImagen(campo);
      $("#" + campo).on("change", function(){
         var file = this.files[0];
         if (!file) return;
         if (file.size > MAX_PESO_IMAGEN) {
            notify("warning", "La imagen pesa mas de 5 MB. Elige una mas liviana.");
            this.value = "";
            return;
         }
         var reader = new FileReader();
         reader.onload = function(e){
            pintarSlotImagen(campo, e.target.result);
            $("#quitar_" + campo).val("");
         };
         reader.readAsDataURL(file);
      });
   });

   $("#precio_venta, #descuento_porcentaje, #en_oferta").on("input change", actualizarPreviewOferta);
}

//los 3 slots de imagen que admite un articulo (el primero es la imagen principal)
var SLOTS_IMAGEN = ["imagen", "imagen2", "imagen3"];
//peso maximo por imagen (5 MB)
var MAX_PESO_IMAGEN = 5 * 1024 * 1024;

function slotContenedor(campo){
	return $(".img-slot[data-slot='" + campo + "']");
}

function slotPlaceholder(campo){
	return slotContenedor(campo).find(".img-slot-placeholder");
}

//muestra una imagen en el slot indicado
function pintarSlotImagen(campo, src){
	$("#" + campo + "muestra").attr("src", src).css("display", "block");
	slotPlaceholder(campo).css("display", "none");
	slotContenedor(campo).addClass("tiene-imagen");
}

//deja el slot vacio (solo visual, no toca el flag de quitar)
function limpiarSlotImagen(campo){
	$("#" + campo + "muestra").attr("src", "").css("display", "none");
	slotPlaceholder(campo).css("display", "flex");
	slotContenedor(campo).removeClass("tiene-imagen");
}

//quita la imagen del slot: la marca para borrar al guardar
function quitarImagen(campo){
	limpiarSlotImagen(campo);
	$("#" + campo).val("");
	$("#quitar_" + campo).val("1");
}

//vista previa en vivo del precio con descuento
function actualizarPreviewOferta(){
	var $prev = $("#ofertaPreview");
	var activa = $("#en_oferta").is(":checked");
	var precio = parseFloat($("#precio_venta").val()) || 0;
	var pct    = parseFloat($("#descuento_porcentaje").val()) || 0;

	if (!activa || pct <= 0 || precio <= 0) {
		$prev.hide();
		return;
	}

	pct = Math.min(100, Math.max(0, pct));
	var simbolo = window.appCurrencySymbol || "S/";
	var final   = Math.round((precio * (1 - pct / 100)) * 100) / 100;

	$prev.html("Precio de oferta: <strong>" + simbolo + " " + final.toFixed(2) + "</strong> " +
		"<span style='text-decoration:line-through;color:#999;margin-left:6px'>" + simbolo + " " + precio.toFixed(2) + "</span>").show();
}

//funcion limpiar
function limpiar(){
	$("#codigo").val("");
	$("#nombre").val("");
	$("#descripcion").val("");
	$("#stock").val("0");
	$("#stock_minimo").val("1");
	$("#precio_venta").val("0.00");
	$("#idunidad").val("");
	$("#idunidad").selectpicker('refresh');
	SLOTS_IMAGEN.forEach(function(campo){
		limpiarSlotImagen(campo);
		$("#" + campo).val("");
		$("#" + campo + "actual").val("");
		$("#quitar_" + campo).val("");
	});
	$("#print").hide();
	$("#idarticulo").val("");
	// Campos farmacéuticos
	$("#principio_activo").val("");
	$("#concentracion").val("");
	$("#forma_farmaceutica").val("");
	try { $("#forma_farmaceutica").selectpicker("refresh"); } catch(e) {}
	$("#via_administracion").val("");
	try { $("#via_administracion").selectpicker("refresh"); } catch(e) {}
	$("#laboratorio").val("");
	$("#registro_sanitario").val("");
	$("#requiere_frio").prop("checked", false);
	$("#tipo_venta").val("OTC");
	try { $("#tipo_venta").selectpicker("refresh"); } catch(e) {}
	// Oferta
	$("#en_oferta").prop("checked", false);
	$("#descuento_porcentaje").val("0");
	$("#oferta_fecha_inicio").val("");
	$("#oferta_fecha_fin").val("");
	$("#ofertaPreview").hide();
	// Presentaciones
	presForm = [];
	presUnidadOriginal = null;
	presStockOriginal = 0;
	$("#presConvertir, #presStockInfo, #presSinMigracion").hide();
	$("#btnAgregarPres").prop("disabled", false);
	renderPresentaciones();
}

//funcion mostrar formulario
function mostrarform(flag){
	limpiar();
	if(flag){
		$("#listadoregistros").hide();
		$("#formularioregistros").show();
		$("#btnGuardar").prop("disabled",false);
		$("#btnagregar").hide();
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
		buttons: window.appDataTableButtons('Reporte de Articulos', true),
		"ajax":
		{
			url:'../ajax/articulo.php?op=listar',
			type: "get",
			dataType : "json",
			error:function(e){
				console.log(e.responseText);
			}
		},
		"bDestroy":true,
		"iDisplayLength":10,//paginacion
		"order":[[0,"desc"]],//ordenar (columna, orden)
		"createdRow": function(row, data) {
			var stock    = parseInt(data[5]) || 0;
			var stockMin = parseInt(data[6]) || 0;
			if (stock === 0) {
				$(row).addClass('stock-agotado');
			} else if (stock <= stockMin) {
				$(row).addClass('stock-bajo');
			}
		}
	}).DataTable();
}
//funcion para guardaryeditar
function guardaryeditar(e){
     e.preventDefault();//no se activara la accion predeterminada 
     var errPres = validarPresentacionesForm();
     if (errPres) {
     	notify("warning", errPres);
     	$(".pres-box").removeClass("collapsed-box").find(".box-body").show();
     	return;
     }
     $("#presentaciones_json").val(JSON.stringify(leerPresentacionesForm()));
     $("#btnGuardar").prop("disabled",true);
	 $("#stock").val(normalizarEnteroNoNegativo($("#stock").val(), 0));
	 $("#stock_minimo").val(normalizarEnteroNoNegativo($("#stock_minimo").val(), 1));
     var formData=new FormData($("#formulario")[0]);

     $.ajax({
     	url: "../ajax/articulo.php?op=guardaryeditar",
     	type: "POST",
     	data: formData,
     	contentType: false,
     	processData: false,

     	success: function(datos){
     		// Presentaciones que no tienen sentido: nada se guardó y el formulario queda abierto para corregir.
     		if (/^No se guardó/.test(String(datos).trim())) {
     			notify("error", String(datos).trim());
     			$("#btnGuardar").prop("disabled",false);
     			return;
     		}
     		notifyFromResponse(datos);
     		mostrarform(false);
     		tabla.ajax.reload();
     		$("#btnGuardar").prop("disabled",false);
     	},
     	error: function(xhr){
     		notify("error", "Ocurrio un error al guardar el articulo.");
     		console.log(xhr.responseText);
     		$("#btnGuardar").prop("disabled",false);
     	}
     });
}

function mostrar(idarticulo){
	$.post("../ajax/articulo.php?op=mostrar",{idarticulo : idarticulo},
		function(data,status)
		{
			data=JSON.parse(data);
			mostrarform(true);

			$("#idcategoria").val(data.idcategoria);
			$("#idcategoria").selectpicker('refresh');
			$("#idunidad").val(data.idunidad);
			$("#idunidad").selectpicker('refresh');
			$("#codigo").val(data.codigo);
			$("#nombre").val(data.nombre);
			$("#stock").val(normalizarEnteroNoNegativo(data.stock, 0));
			$("#stock_minimo").val(normalizarEnteroNoNegativo(data.stock_minimo, 1));
			$("#precio_venta").val(parseFloat(data.precio_venta || 0).toFixed(2));
			$("#descripcion").val(data.descripcion);
			//galeria: hasta 3 imagenes ya guardadas
			SLOTS_IMAGEN.forEach(function(campo){
				var archivo = data[campo] || "";
				$("#" + campo + "actual").val(archivo);
				$("#quitar_" + campo).val("");
				if (archivo) {
					pintarSlotImagen(campo, "../files/articulos/" + archivo);
				} else {
					limpiarSlotImagen(campo);
				}
			});
			$("#idarticulo").val(data.idarticulo);
			generarbarcode(true);
			// Campos farmacéuticos
			$("#principio_activo").val(data.principio_activo || "");
			$("#concentracion").val(data.concentracion || "");
			$("#forma_farmaceutica").val(data.forma_farmaceutica || "");
			try { $("#forma_farmaceutica").selectpicker("refresh"); } catch(e) {}
			$("#via_administracion").val(data.via_administracion || "");
			try { $("#via_administracion").selectpicker("refresh"); } catch(e) {}
			$("#laboratorio").val(data.laboratorio || "");
			$("#registro_sanitario").val(data.registro_sanitario || "");
			$("#requiere_frio").prop("checked", data.requiere_frio == 1);
			$("#tipo_venta").val(data.tipo_venta || "OTC");
			try { $("#tipo_venta").selectpicker("refresh"); } catch(e) {}
			// Oferta
			$("#en_oferta").prop("checked", data.en_oferta == 1);
			$("#descuento_porcentaje").val(parseFloat(data.descuento_porcentaje || 0));
			$("#oferta_fecha_inicio").val(mysqlDatetimeALocal(data.oferta_fecha_inicio));
			$("#oferta_fecha_fin").val(mysqlDatetimeALocal(data.oferta_fecha_fin));
			actualizarPreviewOferta();
			// Presentaciones de venta
			var precioUnidad = parseFloat(data.precio_venta || 0);
			presForm = (data.presentaciones || []).filter(function(p){ return !p.es_base; }).map(function(p){
				var auto = redondear2(precioUnidad * parseFloat(p.factor || 0));
				return { idunidad: p.idunidad, factor: p.factor, precio: p.precio_lista, codigo: p.codigo || "",
					manual: !(precioUnidad > 0) || Math.abs(parseFloat(p.precio_lista || 0) - auto) > 0.005 };
			});
			presUnidadOriginal = String(data.idunidad);
			presStockOriginal = parseFloat(data.stock || 0);
			if (data.presentaciones_disponible === false) {
				$("#presSinMigracion").show();
				$("#btnAgregarPres").prop("disabled", true);
			} else {
				$("#presConvertir").show();
			}
			if (data.stock_desglose) {
				$("#presStockInfo").html('<i class="fa fa-archive"></i> Stock actual: <b>' + $("<span>").text(data.stock_desglose).html() + '</b>').show();
			}
			renderPresentaciones();
		})
}

// ── Presentaciones de venta ───────────────────────────────────────
// Base = unidad del producto (factor 1, precio "Precio de venta"). Las demás
// se editan aquí y se envían como JSON en presentaciones_json.
var presForm = [];
var presUnidadOriginal = null;
var presStockOriginal = 0;

function escArt(t){
	return String(t == null ? "" : t).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");
}

function unidadesDisponibles(){
	var out = [];
	$("#idunidad option").each(function(){
		var v = $(this).val();
		if (v) out.push({ id: String(v), texto: $(this).text() });
	});
	return out;
}

function textoUnidad(id){
	var t = $("#idunidad option[value='" + id + "']").text();
	return t || "";
}

function abrevUnidad(id){
	var m = /\(([^)]+)\)\s*$/.exec(textoUnidad(id));
	return m ? m[1] : "und";
}

function nombreUnidad(id){
	return textoUnidad(id).replace(/\s*\([^)]*\)\s*$/, "") || "unidad";
}

function renderPresentacionBase(){
	var idBase = $("#idunidad").val();
	var precio = parseFloat($("#precio_venta").val() || 0);
	var $b = $("#presFilaBase");
	if (!$b.length) return;
	$b.find(".pres-base-unidad").html(idBase ? escArt(nombreUnidad(idBase)) + ' <span class="label label-primary">base</span>' : '<span class="text-muted">Elige la unidad base arriba</span>');
	$b.find(".pres-base-precio").text((window.appCurrencySymbol || "S/") + " " + (isFinite(precio) ? precio.toFixed(2) : "0.00"));
	$b.find(".pres-base-codigo").text($("#codigo").val() || "—");
	$(".pres-abrev-base").text(idBase ? abrevUnidad(idBase) : "und");
}

function renderPresentaciones(){
	var idBase = String($("#idunidad").val() || "");
	var unidades = unidadesDisponibles();
	var sym = window.appCurrencySymbol || "S/";
	var html = '<tr id="presFilaBase" class="pres-fila-base">' +
		'<td class="pres-base-unidad"></td>' +
		'<td><span class="text-muted">1 (unidad base)</span></td>' +
		'<td><span class="pres-base-precio"></span> <small class="text-muted">· se edita en "Precio de venta"</small></td>' +
		'<td class="pres-base-codigo text-muted"></td>' +
		'<td></td></tr>';
	presForm.forEach(function(p, i){
		var opts = '<option value="">— Elige —</option>';
		unidades.forEach(function(u){
			if (u.id === idBase) return;
			opts += '<option value="' + u.id + '"' + (String(p.idunidad) === u.id ? ' selected' : '') + '>' + escArt(u.texto) + '</option>';
		});
		html += '<tr data-i="' + i + '">' +
			'<td><select class="form-control input-sm" onchange="presCampo(' + i + ',\'idunidad\',this.value)">' + opts + '</select></td>' +
			'<td><div class="input-group input-group-sm pres-factor"><span class="input-group-addon">x</span>' +
				'<input type="number" class="form-control" min="2" step="1" placeholder="10" value="' + escArt(p.factor || "") + '" oninput="presCampo(' + i + ',\'factor\',this.value)">' +
				'<span class="input-group-addon pres-abrev-base">und</span></div></td>' +
			'<td><div class="input-group input-group-sm pres-precio"><span class="input-group-addon">' + escArt(sym) + '</span>' +
				'<input type="number" class="form-control" min="0.01" step="0.01" placeholder="0.00" value="' + (p.precio ? parseFloat(p.precio).toFixed(2) : "") + '" oninput="presCampo(' + i + ',\'precio\',this.value)"></div>' +
				'<small class="pres-auto"></small>' +
				'<small class="pres-ref text-muted"></small></td>' +
			'<td><input type="text" class="form-control input-sm" maxlength="50" placeholder="Código de la caja / blíster" value="' + escArt(p.codigo || "") + '" oninput="presCampo(' + i + ',\'codigo\',this.value)"></td>' +
			'<td><button type="button" class="btn btn-danger btn-xs" title="Quitar presentación" onclick="quitarPresentacionFila(' + i + ')"><i class="fa fa-trash"></i></button></td>' +
		'</tr>';
	});
	$("#presTbody").html(html);
	renderPresentacionBase();
	presForm.forEach(function(p, i){ pintarEstadoPrecio(i); refPrecioPresentacion(i); });
}

// Muestra cuánto sale la unidad dentro de la presentación (ayuda a poner precios coherentes)
function refPrecioPresentacion(i){
	var p = presForm[i];
	var $ref = $('#presTbody tr[data-i="' + i + '"] .pres-ref');
	var f = parseFloat(p.factor), pr = parseFloat(p.precio), base = parseFloat($("#precio_venta").val() || 0);
	if (f >= 2 && pr > 0) {
		var unit = pr / f;
		var txt = "= " + (window.appCurrencySymbol || "S/") + " " + unit.toFixed(3) + " c/" + abrevUnidad($("#idunidad").val());
		if (base > 0) {
			var ahorro = Math.round((1 - unit / base) * 100);
			if (ahorro > 0) txt += " (" + ahorro + "% menos que suelto)";
			else if (ahorro < 0) txt += " (más caro que suelto)";
		}
		$ref.text(txt);
	} else {
		$ref.text("");
	}
}

function redondear2(n){ return Math.round((parseFloat(n) || 0) * 100) / 100; }

// Precio automático de una presentación = precio de 1 unidad x cantidad que trae.
// Si el usuario escribe otro precio (ej. descuento por caja) queda como "manual"
// y ya no se toca, hasta que pulse "Calcular".
function precioAutoPresentacion(p){
	var base = parseFloat($("#precio_venta").val() || 0);
	var f = parseFloat(p.factor);
	return (base > 0 && f >= 2) ? redondear2(base * f) : 0;
}

function pintarEstadoPrecio(i){
	var p = presForm[i];
	if (!p) return;
	var $fila = $('#presTbody tr[data-i="' + i + '"]');
	var $auto = $fila.find(".pres-auto");
	var sym = window.appCurrencySymbol || "S/";
	var base = parseFloat($("#precio_venta").val() || 0);
	var auto = precioAutoPresentacion(p);
	if (!(base > 0)) {
		$auto.attr("class", "pres-auto text-warning").html('<i class="fa fa-info-circle"></i> Pon arriba el precio de 1 ' + escArt(nombreUnidad($("#idunidad").val()).toLowerCase()) + ' y se calcula solo');
	} else if (!(parseFloat(p.factor) >= 2)) {
		$auto.attr("class", "pres-auto text-muted").text("Indica cuántas trae y se calcula solo");
	} else if (!p.manual) {
		$auto.attr("class", "pres-auto text-success").html('<i class="fa fa-magic"></i> Automático: ' + (+p.factor) + ' × ' + sym + ' ' + base.toFixed(2));
	} else {
		$auto.attr("class", "pres-auto text-primary").html('<i class="fa fa-pencil"></i> Precio a mano · ' +
			'<a href="javascript:void(0)" onclick="volverPrecioAuto(' + i + ')" title="Usar ' + (+p.factor) + ' × precio de 1 unidad">↺ Calcular (' + sym + ' ' + auto.toFixed(2) + ')</a>');
	}
}

function aplicarPrecioAuto(i){
	var p = presForm[i];
	if (!p || p.manual) return;
	var auto = precioAutoPresentacion(p);
	p.precio = auto > 0 ? auto.toFixed(2) : "";
	$('#presTbody tr[data-i="' + i + '"] .pres-precio input').val(p.precio);
}

function volverPrecioAuto(i){
	if (!presForm[i]) return;
	presForm[i].manual = false;
	aplicarPrecioAuto(i);
	pintarEstadoPrecio(i);
	refPrecioPresentacion(i);
}

// Cambió el precio de 1 unidad: las presentaciones automáticas se recalculan solas
function recalcularPreciosAuto(){
	presForm.forEach(function(p, i){
		aplicarPrecioAuto(i);
		pintarEstadoPrecio(i);
		refPrecioPresentacion(i);
	});
}

function presCampo(i, campo, valor){
	if (!presForm[i]) return;
	presForm[i][campo] = valor;
	if (campo === "factor") {
		aplicarPrecioAuto(i);
	} else if (campo === "precio") {
		// Escribió un precio distinto al calculado: se respeta como precio a mano
		var auto = precioAutoPresentacion(presForm[i]);
		presForm[i].manual = !(auto > 0) || Math.abs(redondear2(valor) - auto) > 0.005;
	}
	if (campo === "factor" || campo === "precio") {
		pintarEstadoPrecio(i);
		refPrecioPresentacion(i);
	}
}

function agregarPresentacionFila(){
	if (!$("#idunidad").val()) {
		notify("warning", "Primero elige la unidad base del producto (la más pequeña que vendes, ej. Tableta).");
		return;
	}
	presForm.push({ idunidad: "", factor: "", precio: "", codigo: "", manual: false });
	renderPresentaciones();
	$('#presTbody tr[data-i="' + (presForm.length - 1) + '"] select').focus();
}

function quitarPresentacionFila(i){
	presForm.splice(i, 1);
	renderPresentaciones();
}

function leerPresentacionesForm(){
	return presForm.filter(function(p){ return p.idunidad; }).map(function(p){
		return { idunidad: parseInt(p.idunidad, 10), factor: parseFloat(p.factor), precio: parseFloat(p.precio), codigo: $.trim(p.codigo || "") };
	});
}

/** Devuelve un mensaje si algo está mal, o "" si todo está bien */
function validarPresentacionesForm(){
	var idBase = String($("#idunidad").val() || "");
	var vistos = {};
	for (var i = 0; i < presForm.length; i++) {
		var p = presForm[i];
		if (!p.idunidad) {
			if (!p.factor && !p.precio && !p.codigo) continue; // fila vacía: se ignora
			return "Elige la unidad de la presentación #" + (i + 1) + " o quítala.";
		}
		var nom = nombreUnidad(p.idunidad);
		if (String(p.idunidad) === idBase) return '"' + nom + '" ya es la unidad base. Elige otra unidad para la presentación.';
		if (vistos[p.idunidad]) return 'La presentación "' + nom + '" está repetida.';
		vistos[p.idunidad] = true;
		var f = parseFloat(p.factor);
		if (!(f >= 2) || Math.floor(f) !== f) return 'Indica cuántas ' + abrevUnidad(idBase) + ' trae 1 "' + nom + '" (número entero desde 2).';
		if (!(parseFloat(p.precio) > 0)) return 'Falta el precio de venta de "' + nom + '".';
	}
	return "";
}

// Avisa si cambian la unidad base de un producto con stock: eso NO convierte cantidades
function avisoCambioUnidadBase(){
	var actual = String($("#idunidad").val() || "");
	if (presUnidadOriginal && actual && actual !== presUnidadOriginal && presStockOriginal > 0) {
		notify("warning", "Cambiar la unidad base no convierte el stock. Para pasar de " + nombreUnidad(presUnidadOriginal) +
			" a una unidad más pequeña usa el botón \"Vender por unidad más pequeña\".");
	}
}

// ── Convertir a una unidad base más pequeña (ej. Caja → Tableta) ──────
function abrirConvertirUnidad(){
	var id = $("#idarticulo").val();
	if (!id) return;
	var idBase = presUnidadOriginal || String($("#idunidad").val() || "");
	var nomBase = nombreUnidad(idBase);
	var opts = '<option value="">— Elige —</option>';
	unidadesDisponibles().forEach(function(u){
		if (u.id === idBase) return;
		var usada = presForm.some(function(p){ return String(p.idunidad) === u.id; });
		if (usada) return;
		opts += '<option value="' + u.id + '">' + escArt(u.texto) + '</option>';
	});
	$("#convUnidad").html(opts);
	$("#convFactor, #convPrecio").val("");
	$("#convIntro").html('Hoy <b>' + escArt($("#nombre").val()) + '</b> se cuenta en <b>' + escArt(nomBase) + '</b> (stock: ' +
		escArt(presStockOriginal) + '). Elige la unidad más pequeña en la que también lo vas a vender.');
	$("#convFactorLabel").text("¿Cuántas trae 1 " + nomBase.toLowerCase() + "?");
	$("#convPreview").hide();
	$("#modalConvertirUnidad").modal("show");
}

function previewConvertir(){
	var idNueva = $("#convUnidad").val();
	var n = parseFloat($("#convFactor").val());
	var precio = parseFloat($("#convPrecio").val());
	var idBase = presUnidadOriginal || String($("#idunidad").val() || "");
	var nomNueva = idNueva ? nombreUnidad(idNueva) : "unidad";
	$("#convPrecioLabel").text("Precio de venta de 1 " + nomNueva.toLowerCase());
	if (!idNueva || !(n >= 2) || Math.floor(n) !== n) { $("#convPreview").hide(); return; }
	var sym = window.appCurrencySymbol || "S/";
	var precioCaja = parseFloat($("#precio_venta").val() || 0);
	var html = '<div><i class="fa fa-archive"></i> El stock pasa de <b>' + presStockOriginal + ' ' + escArt(abrevUnidad(idBase)) + '</b> a <b>' +
		(presStockOriginal * n) + ' ' + escArt(abrevUnidad(idNueva)) + '</b>.</div>' +
		'<div><i class="fa fa-cubes"></i> <b>' + escArt(nombreUnidad(idBase)) + ' x' + n + '</b> sigue a la venta a ' + sym + ' ' + precioCaja.toFixed(2) + '.</div>' +
		'<div><i class="fa fa-history"></i> Los lotes y el historial de ventas y compras se convierten igual.</div>';
	if (precio > 0 && precioCaja > 0 && precio * n < precioCaja) {
		html += '<div class="text-danger"><i class="fa fa-exclamation-triangle"></i> Ojo: ' + n + ' × ' + escArt(nomNueva) + ' (' + sym + ' ' + (precio * n).toFixed(2) + ') salen más baratas que 1 ' +
			escArt(nombreUnidad(idBase)) + ' (' + sym + ' ' + precioCaja.toFixed(2) + ').</div>';
	}
	$("#convPreview").html(html).show();
}

function confirmarConvertirUnidad(){
	var datos = {
		idarticulo: $("#idarticulo").val(),
		idunidad_nueva: $("#convUnidad").val(),
		factor: $("#convFactor").val(),
		precio_base: $("#convPrecio").val()
	};
	if (!datos.idunidad_nueva) { notify("warning", "Elige la unidad más pequeña."); return; }
	var n = parseFloat(datos.factor);
	if (!(n >= 2) || Math.floor(n) !== n) { notify("warning", "Indica cuántas unidades trae (número entero desde 2)."); return; }
	if (!(parseFloat(datos.precio_base) > 0)) { notify("warning", "Indica el precio de venta de 1 unidad."); return; }
	bootbox.confirm({
		title: "Convertir unidad base",
		message: "Se recalcularán el stock, los lotes y el historial de este producto. ¿Continuar?",
		buttons: {
			confirm: { label: '<i class="fa fa-check"></i> Sí, convertir', className: "btn-warning" },
			cancel: { label: "Cancelar", className: "btn-default" }
		},
		callback: function(ok){
			if (!ok) return;
			var $btn = $("#btnConvertir").prop("disabled", true);
			$.post("../ajax/articulo.php?op=convertirUnidadBase", datos, function(raw){
				var r; try { r = JSON.parse(raw); } catch(e) { r = { ok:false, message:"Respuesta inválida del servidor" }; }
				$btn.prop("disabled", false);
				notify(r.ok ? "success" : "error", r.message || (r.ok ? "Producto convertido" : "No se pudo convertir"));
				if (r.ok) {
					$("#modalConvertirUnidad").modal("hide");
					tabla.ajax.reload(null, false);
					mostrar(datos.idarticulo);
				}
			}).fail(function(){
				$btn.prop("disabled", false);
				notify("error", "No se pudo conectar con el servidor.");
			});
		}
	});
}

//convierte 'YYYY-MM-DD HH:MM:SS' (MySQL) a 'YYYY-MM-DDTHH:MM' (input datetime-local)
function mysqlDatetimeALocal(valor){
	if (!valor) return "";
	return String(valor).trim().replace(" ", "T").substring(0, 16);
}

function normalizarEnteroNoNegativo(valor, fallback){
	var num = parseFloat(valor);
	if (!isFinite(num)) {
		return fallback;
	}
	num = Math.round(num);
	if (num < 0) {
		num = 0;
	}
	return num;
}


//funcion para eliminar
function eliminar(idarticulo){
	appEliminar({
		url: "../ajax/articulo.php?op=eliminar",
		data: {idarticulo: idarticulo},
		titulo: "Eliminar producto",
		mensaje: "¿Deseas eliminar este producto? Si ya tiene ventas o compras, desaparecerá del catálogo y del POS, pero su historial se conserva en los reportes.",
		onSuccess: function(){ tabla.ajax.reload(null, false); }
	});
}

function activar(idarticulo){
	bootbox.confirm("¿Esta seguro de activar este dato?" , function(result){
		if (result) {
			$.post("../ajax/articulo.php?op=activar" , {idarticulo : idarticulo}, function(e){
				notifyFromResponse(e);
				tabla.ajax.reload();
			});
		}
	})
}

function generarCodigoArticulo(){
	var nombre = $.trim($("#nombre").val()).toUpperCase().replace(/[^A-Z0-9]/g, "");
	var prefijo = nombre.length >= 3 ? nombre.substring(0, 3) : "ART";
	var aleatorio = Math.floor(100000 + (Math.random() * 900000));
	var codigo = prefijo + "-" + aleatorio;

	$("#codigo").val(codigo);
	generarbarcode();
	notify("success", "Codigo generado correctamente: " + codigo);
}

function generarbarcode(silencioso){
	var codigo=$.trim($("#codigo").val());

	if (!codigo) {
		notify("warning", "Ingresa o genera un codigo antes de crear el codigo de barras.");
		return;
	}

	if (typeof JsBarcode !== "function") {
		notify("error", "No se pudo cargar la libreria de codigo de barras.");
		return;
	}

	try{
		JsBarcode("#barcode",codigo,{
			format:"CODE128",
			lineColor:"#0f172a",
			width:2,
			height:60,
			displayValue:true
		});
		$("#print").show();
		if (!silencioso) {
			notify("success", "Codigo de barras generado correctamente.");
		}
	}catch(err){
		notify("error", "No se pudo generar el codigo de barras.");
		console.log(err);
	}

}

function imprimir(){
	if (!$.trim($("#codigo").val())) {
		notify("warning", "No hay codigo para imprimir.");
		return;
	}
	$("#print").printArea();
}

init();

