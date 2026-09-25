var tabla;

function init(){
	mostrarform(false);
	listar();

	$("#formulario").on("submit",function(e){
		guardaryeditar(e);
	});
}

function limpiar(){
	$("#idunidad").val("");
	$("#nombre").val("");
	$("#abreviatura").val("");
	$("#descripcion").val("");
}

function mostrarform(flag){
	limpiar();
	if(flag){
		$("#listadoregistros").hide();
		$("#formularioregistros").show();
		$("#btnGuardar").prop("disabled",false);
	}else{
		$("#listadoregistros").show();
		$("#formularioregistros").hide();
	}
}

function cancelarform(){
	limpiar();
	mostrarform(false);
}

function listar(){
	tabla=$('#tbllistado').dataTable({
		"aProcessing": true,
		"aServerSide": true,
		dom: 'Bfrtip',
		buttons: window.appDataTableButtons('Reporte de Unidades de Medida', true),
		"ajax":
		{
			url:'../ajax/unidad.php?op=listar',
			type: "get",
			dataType : "json",
			error:function(e){
				console.log(e.responseText);
			}
		},
		"bDestroy":true,
		"iDisplayLength":10,
		"order":[[1,"asc"]]
	}).DataTable();
}

function guardaryeditar(e){
	e.preventDefault();
	$("#btnGuardar").prop("disabled",true);
	var formData=new FormData($("#formulario")[0]);

	$.ajax({
		url: "../ajax/unidad.php?op=guardaryeditar",
		type: "POST",
		data: formData,
		contentType: false,
		processData: false,
		dataType: "json",
		success: function(datos){
			if (datos.ok) {
				appNotify(datos.message, "success");
				mostrarform(false);
				tabla.ajax.reload();
			} else {
				bootbox.alert(datos.message);
				$("#btnGuardar").prop("disabled", false);
			}
		},
		error: function(){
			bootbox.alert("Error al conectar con el servidor");
			$("#btnGuardar").prop("disabled", false);
		}
	});

	limpiar();
}

function mostrar(idunidad){
	$.post("../ajax/unidad.php?op=mostrar",{idunidad : idunidad}, function(data){
		data=JSON.parse(data);
		mostrarform(true);
		$("#idunidad").val(data.idunidad);
		$("#nombre").val(data.nombre);
		$("#abreviatura").val(data.abreviatura);
		$("#descripcion").val(data.descripcion);
	});
}

function eliminar(idunidad){
	appEliminar({
		url: "../ajax/unidad.php?op=eliminar",
		data: {idunidad: idunidad},
		titulo: "Eliminar unidad de medida",
		mensaje: "¿Deseas eliminar esta unidad de medida? Si la usan productos, se quitará de las listas pero los productos se conservan.",
		onSuccess: function(){ tabla.ajax.reload(null, false); }
	});
}

function activar(idunidad){
	bootbox.confirm("¿Esta seguro de activar esta unidad?", function(result){
		if (result) {
			$.post("../ajax/unidad.php?op=activar", {idunidad : idunidad}, function(e){
				bootbox.alert(e);
				tabla.ajax.reload();
			});
		}
	});
}

init();

