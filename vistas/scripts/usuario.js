var tabla;
var PERFIL_MODE = !!window.appPerfilMode;
var PERFIL_ID   = window.appPerfilId || null;

var PERMISOS_ROL = {
    "Administrador": [1,2,3,4,5,6,7,8,9,10,11],
    "Vendedor":      [1,4]
};

// Marca los checkboxes ocultos según el rol elegido
function aplicarPermisosPorRol(rol){
    var ids = PERMISOS_ROL[rol] || PERMISOS_ROL["Vendedor"];
    $("#permisos input[type=checkbox]").each(function(){
        $(this).prop("checked", ids.indexOf(parseInt($(this).val())) >= 0);
    });
}

// Carga los checkboxes de permisos (ocultos) y aplica el rol actual
function cargarYAplicarPermisos(idUsuario, rolActual){
    $.post("../ajax/usuario.php?op=permisos&id=" + (idUsuario || ""), function(r){
        $("#permisos").html(r);
        aplicarPermisosPorRol(rolActual || $("#cargo").val());
    });
}

function limpiar(){
    $("#idusuario").val("");
    $("#nombre").val("");
    $("#tipo_documento").val("DNI");
    $("#num_documento").val("");
    $("#direccion").val("");
    $("#telefono").val("");
    $("#email").val("");
    $("#cargo").val("Vendedor");
    $("#login").val("");
    $("#clave").val("").attr("placeholder","Clave");
    $("#claveLabelHint").hide();
    $("#imagenmuestra").attr("src","").hide();
    $("#imagenactual").val("");
}

function mostrarform(flag){
    limpiar();
    if(flag){
        $("#listadoregistros").hide();
        $("#formularioregistros").show();
        $("#btnGuardar").prop("disabled",false);
        $("#btnagregar").hide();
    } else {
        $("#listadoregistros").show();
        $("#formularioregistros").hide();
        $("#btnagregar").show();
    }
}

// Para el botón Agregar: siempre recarga permisos limpios para nuevo usuario
function mostrarformNuevo(){
    mostrarform(true);
    cargarYAplicarPermisos("", "Vendedor");
}

function cancelarform(){
    if(PERFIL_MODE && PERFIL_ID){
        mostrar(PERFIL_ID);
        return;
    }
    mostrarform(false);
}

function listar(){
    tabla = $("#tbllistado").dataTable({
        "aProcessing": true,
        "aServerSide": true,
        dom: "Bfrtip",
        buttons: window.appDataTableButtons ? window.appDataTableButtons("Reporte de Usuarios", true) : ["copy","csv","print"],
        "ajax":{
            url: "../ajax/usuario.php?op=listar",
            type: "get",
            dataType: "json",
            error: function(e){ console.log(e.responseText); }
        },
        "bDestroy": true,
        "iDisplayLength": 10,
        "order": [[0,"desc"]]
    }).DataTable();
}

function guardaryeditar(e){
    e.preventDefault();
    $("#btnGuardar").prop("disabled", true);
    var formData = new FormData($("#formulario")[0]);
    $.ajax({
        url: "../ajax/usuario.php?op=guardaryeditar",
        type: "POST",
        data: formData,
        contentType: false,
        processData: false,
        success: function(datos){
            bootbox.alert(datos, function(){
                if(PERFIL_MODE && PERFIL_ID){
                    mostrarform(true);
                    mostrar(PERFIL_ID);
                } else {
                    mostrarform(false);
                    if(tabla) tabla.ajax.reload();
                }
            });
            $("#btnGuardar").prop("disabled", false);
        }
    });
}

function mostrar(idusuario){
    $.post("../ajax/usuario.php?op=mostrar", {idusuario: idusuario}, function(data){
        data = JSON.parse(data);
        mostrarform(true);

        $("#nombre").val(data.nombre);
        $("#tipo_documento").val(data.tipo_documento);
        if(typeof $.fn.selectpicker !== "undefined") $("#tipo_documento").selectpicker("refresh");
        $("#num_documento").val(data.num_documento);
        $("#direccion").val(data.direccion);
        $("#telefono").val(data.telefono);
        $("#email").val(data.email);
        $("#login").val(data.login);
        $("#clave").val("").attr("placeholder","Nueva clave (dejar vacío para no cambiar)");
        $("#claveLabelHint").show();
        $("#imagenmuestra").attr("src","../files/usuarios/"+data.imagen).show();
        $("#imagenactual").val(data.imagen);
        $("#idusuario").val(data.idusuario);

        // Determinar rol por cargo guardado
        var cargo = (data.cargo || "").toLowerCase();
        var rol = (cargo.indexOf("admin") >= 0) ? "Administrador" : "Vendedor";
        $("#cargo").val(rol);

        // Cargar checkboxes ocultos y aplicar permisos del rol
        cargarYAplicarPermisos(idusuario, rol);
    });
}

function desactivar(idusuario){
    bootbox.confirm("¿Desactivar este usuario?", function(result){
        if(result){
            $.post("../ajax/usuario.php?op=desactivar", {idusuario: idusuario}, function(e){
                bootbox.alert(e);
                tabla.ajax.reload();
            });
        }
    });
}

function activar(idusuario){
    bootbox.confirm("¿Activar este usuario?", function(result){
        if(result){
            $.post("../ajax/usuario.php?op=activar", {idusuario: idusuario}, function(e){
                bootbox.alert(e);
                tabla.ajax.reload();
            });
        }
    });
}

$(function(){
    mostrarform(PERFIL_MODE ? true : false);

    if(!PERFIL_MODE){
        listar();
    } else {
        $("#listadoregistros").hide();
        $("#btnagregar").hide();
        if(PERFIL_ID) mostrar(PERFIL_ID);
    }

    $("#formulario").on("submit", function(e){ guardaryeditar(e); });
    $("#imagenmuestra").hide();

    // Cargar permisos ocultos para formulario nuevo con rol por defecto
    if(!PERFIL_MODE){
        cargarYAplicarPermisos("", "Vendedor");
    }

    // Al cambiar el rol, reasignar permisos automáticamente
    $("#cargo").on("change", function(){
        aplicarPermisosPorRol($(this).val());
    });
});
