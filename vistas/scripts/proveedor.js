var tabla;

//funcion que se ejecuta al inicio
function init(){
   mostrarform(false);
   listar();

   $("#formulario").on("submit",function(e){
   	guardaryeditar(e);
   });
}

//funcion limpiar
function limpiar(){

	$("#nombre").val("");
	$("#num_documento").val("");
	$("#direccion").val("");
	$("#telefono").val("");
	$("#email").val("");
	$("#idpersona").val("");
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
		buttons: window.appDataTableButtons('Reporte de Proveedores', true),
		"ajax":
		{
			url:'../ajax/persona.php?op=listarp',
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
     var formData=new FormData($("#formulario")[0]);

     $.ajax({
     	url: "../ajax/persona.php?op=guardaryeditar",
     	type: "POST",
     	data: formData,
     	contentType: false,
     	processData: false,

     	success: function(datos){
     		bootbox.alert(datos);
     		mostrarform(false);
     		tabla.ajax.reload();
     	}
     });

     limpiar();
}

// Registrar proveedores es libre; editar o eliminar requiere el permiso
// "Editar y anular compras" o la autorización de un administrador.
function nombreProveedorFila(idpersona){
	var nombre = "";
	if (tabla) {
		tabla.rows().every(function(){
			var d = this.data();
			if (d && String(d[0]).indexOf("(" + idpersona + ")") !== -1) nombre = $("<span>").html(d[1]).text();
		});
	}
	return nombre;
}

function mostrar(idpersona){
	appAutorizar({
		recurso: "proveedor:" + idpersona,
		titulo: "Editar proveedor",
		detalle: nombreProveedorFila(idpersona),
		mensaje: "Podrás modificar los datos de este proveedor durante los próximos minutos.",
		boton: "Autorizar",
		onAutorizado: function(){ mostrarAutorizado(idpersona); }
	});
}

function mostrarAutorizado(idpersona){
	$.post("../ajax/persona.php?op=mostrar",{idpersona : idpersona},
		function(data,status)
		{
			data=JSON.parse(data);
			mostrarform(true);

			$("#nombre").val(data.nombre);
			$("#tipo_documento").val(data.tipo_documento);
			$("#tipo_documento").selectpicker('refresh');
			$("#num_documento").val(data.num_documento);
			$("#direccion").val(data.direccion);
			$("#telefono").val(data.telefono);
			$("#email").val(data.email);
			$("#idpersona").val(data.idpersona);
		})
}


function eliminar(idpersona){
	var mensaje = "Si tiene compras registradas, se quitará de las listas pero su historial se conserva en los reportes.";
	var aut = {
		recurso: "proveedor:" + idpersona,
		titulo: "Eliminar proveedor",
		detalle: nombreProveedorFila(idpersona),
		mensaje: mensaje,
		boton: "Autorizar y eliminar",
		peligro: true
	};
	var enviar = function(){
		$.post("../ajax/persona.php?op=eliminar", {idpersona: idpersona}, function(raw){
			var r = appAutorizar.parse(raw);
			if (appAutorizar.manejar(r, aut, enviar)) return;
			if (typeof appNotify === "function") appNotify(r.ok ? "success" : "error", r.message || (r.ok ? "Proveedor eliminado" : "No se pudo eliminar el proveedor"));
			if (r.ok) tabla.ajax.reload(null, false);
		}).fail(function(){
			if (typeof appNotify === "function") appNotify("error", "No se pudo conectar con el servidor. Revisa tu conexión e inténtalo de nuevo.");
		});
	};
	appAutorizar($.extend({}, aut, { onAutorizado: function(yaConfirmado){
		if (yaConfirmado) { enviar(); return; }
		appEliminar({
			url: "../ajax/persona.php?op=eliminar",
			data: {idpersona: idpersona},
			titulo: "Eliminar proveedor",
			mensaje: "¿Deseas eliminar este proveedor? " + mensaje,
			onSuccess: function(){ tabla.ajax.reload(null, false); }
		});
	}}));
}


init();
