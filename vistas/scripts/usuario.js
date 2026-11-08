var tabla;
var PERFIL_MODE = !!window.appPerfilMode;
var PERFIL_ID   = window.appPerfilId || null;

// Permisos base por rol. El Administrador recibe todos los permisos existentes.
var PERMISOS_ROL = {
    "Vendedor": [1,4]   // Escritorio + Ventas (vender siempre está permitido)
};

// Permisos que el administrador puede encender o apagar para cada vendedor.
// 14 y 15 los crea la migración 20261108_permisos_autorizacion.sql
var PERMISOS_ESPECIALES = [
    { id: 3,  icono: "fa-truck",  nombre: "Registrar compras",
      desc: "Puede registrar compras nuevas y proveedores. No puede editar ni anular compras ya registradas.", defecto: true },
    { id: 14, icono: "fa-ban",    nombre: "Anular y eliminar ventas sin autorización",
      desc: "Apagado: cada anulación o eliminación de una venta la autoriza un administrador con su clave.", defecto: false },
    { id: 15, icono: "fa-pencil", nombre: "Editar y anular compras sin autorización",
      desc: "Incluye agregar o corregir productos de una compra registrada y editar o eliminar proveedores. Apagado: lo autoriza un administrador.", defecto: false }
];

function checkboxPermiso(id){
    return $("#permisos input[type=checkbox][value='" + id + "']");
}

// Estado actual de los permisos especiales (id -> bool)
function estadoEspeciales(usarDefecto){
    var estado = {};
    PERMISOS_ESPECIALES.forEach(function(p){
        estado[p.id] = usarDefecto ? p.defecto : checkboxPermiso(p.id).prop("checked");
    });
    return estado;
}

// Marca los checkboxes ocultos según el rol y los permisos especiales elegidos
function aplicarPermisosPorRol(rol, especiales){
    var $todos = $("#permisos input[type=checkbox]");
    if (rol === "Administrador") {
        $todos.prop("checked", true);
    } else {
        var ids = PERMISOS_ROL["Vendedor"];
        especiales = especiales || estadoEspeciales(true);
        $todos.each(function(){
            var id = parseInt($(this).val(), 10);
            var marcado = ids.indexOf(id) >= 0 || especiales[id] === true;
            $(this).prop("checked", marcado);
        });
    }
    renderPermisosEspeciales(rol);
}

// Dibuja los interruptores visibles a partir de los checkboxes ocultos
function renderPermisosEspeciales(rol){
    var esAdmin = rol === "Administrador";
    var $lista = $("#permEspecialesLista").empty();
    $("#permEspecialesNota").text(esAdmin
        ? "El administrador tiene todos los permisos y puede autorizar las acciones de los vendedores."
        : "El vendedor siempre puede vender. Lo que esté apagado aquí lo debe autorizar un administrador con su usuario y clave.");
    var faltan = false;
    PERMISOS_ESPECIALES.forEach(function(p){
        var $cb = checkboxPermiso(p.id);
        if (!$cb.length) { faltan = true; return; }
        var on = esAdmin || $cb.prop("checked");
        var $item = $(
            '<label class="perm-item">' +
                '<input type="checkbox">' +
                '<span class="perm-switch" aria-hidden="true"></span>' +
                '<span class="perm-texto">' +
                    '<span class="perm-nombre"><i class="fa ' + p.icono + '"></i> </span>' +
                    '<span class="perm-desc"></span>' +
                '</span>' +
            '</label>'
        );
        $item.find(".perm-nombre").append(document.createTextNode(p.nombre));
        $item.find(".perm-desc").text(p.desc);
        var $input = $item.find("input").prop("checked", on).prop("disabled", esAdmin);
        $item.toggleClass("is-on", on).toggleClass("is-locked", esAdmin);
        $input.on("change", function(){
            var v = $(this).prop("checked");
            $cb.prop("checked", v);
            $item.toggleClass("is-on", v);
        });
        $lista.append($item);
    });
    if (faltan) {
        $lista.append('<p class="text-warning" style="font-size:12.5px;margin:4px 0 8px;"><i class="fa fa-exclamation-triangle"></i> Falta ejecutar la migración <b>20261108_permisos_autorizacion.sql</b> para ver todos los permisos.</p>');
    }
}

// Carga los checkboxes de permisos (ocultos) y aplica el rol actual.
// Usuario existente: respeta los permisos especiales que ya tenía guardados.
function cargarYAplicarPermisos(idUsuario, rolActual){
    $.post("../ajax/usuario.php?op=permisos&id=" + (idUsuario || ""), function(r){
        $("#permisos").html(r);
        var rol = rolActual || $("#cargo").val();
        aplicarPermisosPorRol(rol, idUsuario ? estadoEspeciales(false) : estadoEspeciales(true));
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

function eliminar(idusuario){
	appEliminar({
		url: "../ajax/usuario.php?op=eliminar",
		data: {idusuario: idusuario},
		titulo: "Eliminar usuario",
		mensaje: "¿Deseas eliminar este usuario? Ya no podrá ingresar al sistema. Sus ventas y cajas anteriores se conservan en los reportes.",
		onSuccess: function(){ tabla.ajax.reload(null, false); }
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
        aplicarPermisosPorRol($(this).val(), estadoEspeciales(true));
    });

    // En "Mi perfil" no se administran permisos
    if (PERFIL_MODE) {
        $("#permEspeciales").closest(".form-group").hide();
    }
});
