(function (window, $) {
  // Confirmación + POST + notificación estándar para eliminar registros.
  // opts: { url, data, titulo, mensaje, onSuccess }
  // El backend responde {ok, message} (JSON) o texto plano (compatibilidad).
  window.appEliminar = function (opts) {
    opts = opts || {};
    bootbox.confirm({
      title: '<i class="fa fa-trash text-red"></i> ' + (opts.titulo || "Eliminar registro"),
      message: opts.mensaje || "Esta acción no se puede deshacer. ¿Deseas eliminarlo?",
      buttons: {
        confirm: { label: '<i class="fa fa-trash"></i> Sí, eliminar', className: "btn-danger" },
        cancel: { label: "Cancelar", className: "btn-default" }
      },
      callback: function (result) {
        if (!result) return;
        $.post(opts.url, opts.data || {}, function (raw) {
          var r = raw;
          if (typeof raw === "string") {
            try { r = JSON.parse(raw); } catch (e) { r = null; }
          }
          if (!r || typeof r !== "object") {
            var txt = $.trim(String(raw || ""));
            var fallo = txt === "" || /no se pudo|error|sin permiso/i.test(txt);
            r = { ok: !fallo, message: txt || "No se pudo eliminar" };
          }
          if (typeof appNotify === "function") {
            appNotify(r.ok ? "success" : "error", r.message || (r.ok ? "Eliminado correctamente" : "No se pudo eliminar"), r.ok ? 3600 : 5200);
          }
          if (r.ok && typeof opts.onSuccess === "function") opts.onSuccess(r);
        }).fail(function () {
          if (typeof appNotify === "function") appNotify("error", "No se pudo conectar con el servidor. Revisa tu conexión e inténtalo de nuevo.");
        });
      }
    });
  };

  // Botones de acción estándar para las tablas (HTML generado en PHP también usa esta forma).
  window.appBtnEliminar = function (fn, id) {
    return '<button class="btn btn-danger btn-xs" title="Eliminar" onclick="' + fn + '(' + id + ')"><i class="fa fa-trash"></i></button>';
  };
})(window, jQuery);
