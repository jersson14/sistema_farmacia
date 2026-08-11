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
   });
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
		})
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


//funcion para desactivar
function desactivar(idarticulo){
	bootbox.confirm("¿Esta seguro de desactivar este dato?", function(result){
		if (result) {
			$.post("../ajax/articulo.php?op=desactivar", {idarticulo : idarticulo}, function(e){
				notifyFromResponse(e);
				tabla.ajax.reload();
			});
		}
	})
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

