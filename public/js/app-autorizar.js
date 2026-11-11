(function (window, $) {
  // ============================================================
  // Autorización del administrador para acciones sensibles.
  // ============================================================
  // appAutorizar({
  //   recurso:  "venta:12" | "ingreso:5" | "proveedor:7",
  //   titulo:   "Anular venta",                    // qué se quiere hacer
  //   detalle:  "Boleta B001-00000123",            // sobre qué registro
  //   mensaje:  "Se devolverá el stock...",        // consecuencia (opcional)
  //   peligro:  true,                              // botón rojo
  //   onAutorizado: function (yaConfirmado) {...}
  // })
  // Si el usuario tiene el permiso (o ya hay autorización vigente para ese
  // registro) llama onAutorizado(false) y el llamador hace su confirmación
  // normal. Si no, abre el modal: el administrador escribe usuario y clave,
  // y al validar se llama onAutorizado(true) (el modal ya sirvió de confirmación).

  var PERMISO_POR_RECURSO = { venta: "ventas_anular", ingreso: "compras_editar", proveedor: "compras_editar" };
  var vigentes = {}; // recurso -> timestamp ms de expiración (solo para no pedir de nuevo)

  function esc(t) { return $("<span>").text(t == null ? "" : String(t)).html(); }

  function tienePermiso(recurso) {
    var tipo = String(recurso || "").split(":")[0];
    var perms = window.APP_PERMISOS || {};
    return !!perms[PERMISO_POR_RECURSO[tipo]];
  }

  function vigente(recurso) {
    return vigentes[recurso] && vigentes[recurso] > Date.now();
  }

  function asegurarModal() {
    if ($("#appAutModal").length) return;
    $("body").append(
      '<div class="modal fade aut-modal" id="appAutModal" tabindex="-1" role="dialog" aria-labelledby="appAutTitulo">' +
        '<div class="modal-dialog" role="document">' +
          '<div class="modal-content">' +
            '<div class="aut-head">' +
              '<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>' +
              '<div class="aut-icon"><i class="fa fa-shield"></i></div>' +
              '<h4 class="aut-title" id="appAutTitulo">Autorización del administrador</h4>' +
              '<p class="aut-sub">Esta acción solo la puede aprobar un administrador.</p>' +
            '</div>' +
            '<form id="appAutForm" autocomplete="off">' +
              '<div class="modal-body">' +
                '<div class="aut-accion">' +
                  '<div class="aut-accion-titulo" id="appAutAccion"></div>' +
                  '<div class="aut-accion-detalle" id="appAutDetalle"></div>' +
                  '<div class="aut-accion-mensaje" id="appAutMensaje"></div>' +
                '</div>' +
                '<div class="form-group">' +
                  '<label for="appAutLogin">Usuario del administrador</label>' +
                  '<div class="input-group"><span class="input-group-addon"><i class="fa fa-user"></i></span>' +
                  '<input type="text" class="form-control input-lg" id="appAutLogin" autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="Usuario"></div>' +
                '</div>' +
                '<div class="form-group" style="margin-bottom:6px;">' +
                  '<label for="appAutClave">Clave</label>' +
                  '<div class="input-group"><span class="input-group-addon"><i class="fa fa-key"></i></span>' +
                  '<input type="password" class="form-control input-lg" id="appAutClave" autocomplete="new-password" placeholder="Clave del administrador">' +
                  '<span class="input-group-btn"><button type="button" class="btn btn-default btn-lg" id="appAutVer" title="Mostrar u ocultar la clave" tabindex="-1"><i class="fa fa-eye"></i></button></span></div>' +
                '</div>' +
                '<div class="aut-error" id="appAutError" role="alert" style="display:none;"></div>' +
              '</div>' +
              '<div class="modal-footer">' +
                '<button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>' +
                '<button type="submit" class="btn btn-primary aut-btn" id="appAutBtn"><i class="fa fa-check"></i> Autorizar</button>' +
              '</div>' +
            '</form>' +
          '</div>' +
        '</div>' +
      '</div>'
    );
    $("#appAutVer").on("click", function () {
      var $c = $("#appAutClave");
      var ver = $c.attr("type") === "password";
      $c.attr("type", ver ? "text" : "password");
      $(this).find("i").toggleClass("fa-eye", !ver).toggleClass("fa-eye-slash", ver);
    });
    $("#appAutModal").on("shown.bs.modal", function () { $("#appAutLogin").trigger("focus"); });
    $("#appAutModal").on("hidden.bs.modal", function () { $("#appAutClave").val(""); });
  }

  function abrirModal(opts) {
    asegurarModal();
    var $m = $("#appAutModal");
    $("#appAutAccion").html('<i class="fa fa-lock"></i> ' + esc(opts.titulo || "Acción protegida"));
    $("#appAutDetalle").text(opts.detalle || "").toggle(!!opts.detalle);
    $("#appAutMensaje").text(opts.mensaje || "").toggle(!!opts.mensaje);
    $("#appAutError").hide().text("");
    $("#appAutLogin").val("");
    $("#appAutClave").val("").attr("type", "password");
    $("#appAutVer i").addClass("fa-eye").removeClass("fa-eye-slash");
    var $btn = $("#appAutBtn");
    $btn.prop("disabled", false)
      .toggleClass("btn-danger", !!opts.peligro).toggleClass("btn-primary", !opts.peligro)
      .html('<i class="fa fa-check"></i> ' + esc(opts.boton || "Autorizar"));

    $("#appAutForm").off("submit").on("submit", function (e) {
      e.preventDefault();
      var login = $.trim($("#appAutLogin").val());
      var clave = $("#appAutClave").val();
      if (!login || !clave) {
        $("#appAutError").html('<i class="fa fa-exclamation-circle"></i> Escribe el usuario y la clave del administrador.').show();
        $(!login ? "#appAutLogin" : "#appAutClave").trigger("focus");
        return;
      }
      $btn.prop("disabled", true).html('<i class="fa fa-spinner fa-spin"></i> Verificando…');
      $.post("../ajax/autorizacion.php?op=solicitar", {
        recurso: opts.recurso,
        accion: (opts.titulo || "") + (opts.detalle ? " - " + opts.detalle : ""),
        login: login,
        clave: clave
      }, null, "json").done(function (r) {
        if (r && r.ok) {
          vigentes[opts.recurso] = Date.now() + ((r.minutos || 15) * 60 - 30) * 1000;
          $m.one("hidden.bs.modal", function () {
            if (typeof appNotify === "function") appNotify("success", r.message || "Autorizado", 3200);
            if (typeof opts.onAutorizado === "function") opts.onAutorizado(true);
          });
          $m.modal("hide");
        } else {
          $btn.prop("disabled", false).html('<i class="fa fa-check"></i> ' + esc(opts.boton || "Autorizar"));
          $("#appAutError").html('<i class="fa fa-exclamation-circle"></i> ' + esc((r && r.message) || "No se pudo autorizar."));
          $("#appAutError").show();
          $("#appAutClave").val("").trigger("focus");
          $m.find(".modal-content").removeClass("aut-shake");
          setTimeout(function () { $m.find(".modal-content").addClass("aut-shake"); }, 10);
        }
      }).fail(function () {
        $btn.prop("disabled", false).html('<i class="fa fa-check"></i> ' + esc(opts.boton || "Autorizar"));
        $("#appAutError").html('<i class="fa fa-wifi"></i> No se pudo conectar con el servidor. Revisa tu conexión e inténtalo de nuevo.').show();
      });
    });

    $m.modal({ backdrop: "static", keyboard: true });
  }

  window.appAutorizar = function (opts) {
    opts = opts || {};
    if (tienePermiso(opts.recurso) || vigente(opts.recurso)) {
      if (typeof opts.onAutorizado === "function") opts.onAutorizado(false);
      return;
    }
    abrirModal(opts);
  };

  // El usuario puede hacerlo sin pedir autorización
  window.appAutorizar.puede = function (recurso) {
    return tienePermiso(recurso) || vigente(recurso);
  };

  // Si el backend responde que falta autorización (p. ej. venció), olvida la
  // autorización local y vuelve a pedirla; reintentar() repite la acción.
  window.appAutorizar.manejar = function (r, opts, reintentar) {
    if (!r || !r.requiere_autorizacion) return false;
    delete vigentes[opts.recurso];
    abrirModal($.extend({}, opts, {
      mensaje: r.message || opts.mensaje,
      onAutorizado: function () { if (typeof reintentar === "function") reintentar(); }
    }));
    return true;
  };

  // Lee la respuesta (JSON u objeto) del backend
  window.appAutorizar.parse = function (raw) {
    if (raw && typeof raw === "object") return raw;
    try { return JSON.parse(raw); } catch (e) {
      var txt = $.trim(String(raw || ""));
      return { ok: !/no se pudo|error|sin permiso|necesitas/i.test(txt), message: txt };
    }
  };
})(window, jQuery);
