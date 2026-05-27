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
   $("#imagenmuestra").hide();
   $("#imgPlaceholder").show();

   $("#imagen").on("change", function(){
      var file = this.files[0];
      if (!file) return;
      var reader = new FileReader();
      reader.onload = function(e){
         $("#imagenmuestra").attr("src", e.target.result).show();
         $("#imgPlaceholder").hide();
      };
      reader.readAsDataURL(file);
   });
}

//funcion limpiar
function limpiar(){
	$("#codigo").val("");
	$("#nombre").val("");
	$("#descripcion").val("");
	$("#stock").val("");
	$("#stock_minimo").val("1");
	$("#precio_venta").val("0.00");
	$("#idunidad").val("");
	$("#idunidad").selectpicker('refresh');
	$("#imagenmuestra").attr("src","").hide();
	$("#imgPlaceholder").show();
	$("#imagenactual").val("");
	$("#imagen").val("");
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
		"order":[[0,"desc"]]//ordenar (columna, orden)
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
			$("#imagenmuestra").attr("src","../files/articulos/"+data.imagen).show();
			$("#imgPlaceholder").hide();
			$("#imagenactual").val(data.imagen);
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
		})
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

