// Editor de la página web pública. El formulario se dibuja desde el esquema que
// envía el servidor (modelos/Landing.php), el mismo que valida al guardar.
var lwEsquema = {};
var lwValores = {};
var lwSucio = {};
var lwSeccion = "";
var lwImagenDestino = null; // input de texto que recibirá la ruta de la imagen subida

var LW_ICONOS = [
  "capsule-pill", "capsule", "prescription2", "heart-pulse-fill", "heart-fill", "shield-fill-check", "shield-plus", "award-fill",
  "patch-check-fill", "person-badge-fill", "people-fill", "bag-check-fill", "bag-heart-fill", "cart-check-fill", "shop-window", "truck",
  "clock-fill", "telephone-fill", "whatsapp", "geo-alt-fill", "thermometer-half", "droplet-fill", "clipboard2-pulse-fill", "clipboard-heart",
  "bandaid-fill", "hospital-fill", "lungs-fill", "virus", "emoji-smile-fill", "star-fill", "stars", "gift-fill",
  "percent", "credit-card-fill", "cash-coin", "lock-fill", "check-circle-fill", "lightning-charge-fill", "calendar-check-fill", "chat-heart-fill"
];

function lwNotify(tipo, msg) {
  if (typeof appNotify === "function") appNotify(tipo, msg);
}

function lwEsc(t) {
  return $("<span>").text(t == null ? "" : String(t)).html();
}

function lwUrlImagen(v) {
  if (!v) return "";
  return /^https?:\/\//i.test(v) ? v : "../" + v;
}

/* ── Navegación de secciones ───────────────────────────────── */
function lwRenderNav() {
  var html = "";
  $.each(lwEsquema, function (clave, sec) {
    var oculta = sec.campos.visible && lwValores[clave] && lwValores[clave].visible === false;
    html += '<a href="#" data-sec="' + clave + '" class="' + (clave === lwSeccion ? "active " : "") + (lwSucio[clave] ? "sucio" : "") + '">' +
      '<i class="bi ' + sec.icono + '"></i> ' + lwEsc(sec.titulo) +
      (oculta ? '<span class="lw-off">oculta</span>' : '') +
      '<span class="lw-dot" title="Cambios sin guardar"></span></a>';
  });
  $("#lwNav").html(html);
}

function lwAbrir(clave) {
  if (lwSeccion) lwValores[lwSeccion] = lwRecoger();
  lwSeccion = clave;
  var sec = lwEsquema[clave];
  $("#lwTitulo").html('<i class="bi ' + sec.icono + '"></i> ' + lwEsc(sec.titulo));
  $("#lwAyuda").text(sec.ayuda || "").toggle(!!sec.ayuda);
  lwRenderCampos();
  lwRenderNav();
}

/* ── Dibujo de campos ──────────────────────────────────────── */
function lwRenderCampos() {
  var sec = lwEsquema[lwSeccion];
  var val = lwValores[lwSeccion];
  var $c = $("#lwCampos").empty();
  var $grid = null;

  $.each(sec.campos, function (nombre, def) {
    var $campo = lwCampo(def, nombre, val[nombre]);
    // Campos cortos (texto, color, select) de a dos por fila; el resto a lo ancho
    var corto = def.tipo === "color" || def.tipo === "select" || (def.tipo === "text" && !def.ayuda) || def.tipo === "url";
    if (corto) {
      if (!$grid) { $grid = $('<div class="lw-grid2"></div>').appendTo($c); }
      $grid.append($campo);
    } else {
      $grid = null;
      $c.append($campo);
    }
  });
}

function lwCampo(def, nombre, valor) {
  var $w = $('<div class="lw-campo"></div>').attr("data-campo", nombre);
  var ayuda = def.ayuda ? '<p class="help-block">' + lwEsc(def.ayuda) + "</p>" : "";

  switch (def.tipo) {
    case "bool":
      $w.append('<label class="lw-check"><input type="checkbox" data-k="' + nombre + '"' + (valor ? " checked" : "") + "> " + lwEsc(def.label) + "</label>" + ayuda);
      return $w;
    case "list":
      $w.append("<label>" + lwEsc(def.label) + "</label>");
      $w.append(lwLista(def, nombre, valor || []));
      $w.append(ayuda);
      return $w;
    case "products":
      $w.append("<label>" + lwEsc(def.label) + "</label>");
      $w.append(lwProductos(nombre, valor || []));
      $w.append(ayuda);
      return $w;
  }
  $w.append("<label>" + lwEsc(def.label) + "</label>");
  $w.append(lwInput(def, nombre, valor));
  $w.append(ayuda);
  return $w;
}

function lwInput(def, k, valor) {
  var v = valor == null ? "" : valor;
  var max = def.max ? ' maxlength="' + def.max + '"' : "";
  switch (def.tipo) {
    case "textarea":
      return $('<textarea class="form-control" rows="3"' + max + "></textarea>").attr("data-k", k).val(v);
    case "url":
      return $('<input type="text" class="form-control" placeholder="https://…">').attr("data-k", k).val(v);
    case "color":
      var $col = $('<div class="lw-color"><input type="color"><input type="text" class="form-control" maxlength="7" style="max-width:110px"></div>');
      $col.find("input[type=color]").val(v);
      $col.find("input[type=text]").attr("data-k", k).val(v);
      return $col;
    case "select":
      var $s = $('<select class="form-control"></select>').attr("data-k", k);
      $.each(def.opciones, function (op, txt) { $s.append($("<option>").val(op).text(txt)); });
      return $s.val(String(v));
    case "icon":
      var $ic = $('<div class="lw-icon"><span class="lw-icon-prev"><i class="bi"></i></span>' +
        '<input type="text" class="form-control" placeholder="bi-capsule-pill">' +
        '<button type="button" class="btn btn-default lw-icon-btn" title="Elegir ícono"><i class="fa fa-th"></i></button></div>');
      $ic.find("input").attr("data-k", k).val(v);
      $ic.find(".lw-icon-prev i").addClass(v);
      return $ic;
    case "image":
      var $im = $('<div class="lw-img"><div class="lw-img-prev"><i class="bi bi-image"></i></div><div class="lw-img-ctrl">' +
        '<button type="button" class="btn btn-default btn-sm lw-img-subir" style="align-self:flex-start"><i class="fa fa-upload"></i> Subir imagen</button>' +
        '<input type="text" class="form-control input-sm" placeholder="o pega el enlace https:// de una imagen">' +
        '<span class="help-block" style="margin:0">JPG, PNG o GIF de hasta 3 MB.</span></div></div>');
      $im.find("input").attr("data-k", k).val(v);
      lwPreviewImagen($im.find("input"));
      return $im;
    default:
      return $('<input type="text" class="form-control"' + max + ">").attr("data-k", k).val(v);
  }
}

function lwPreviewImagen($input) {
  var url = lwUrlImagen($.trim($input.val()));
  var $prev = $input.closest(".lw-img").find(".lw-img-prev");
  if (url) {
    $prev.css("background-image", 'url("' + url.replace(/"/g, "") + '")').find("i").hide();
  } else {
    $prev.css("background-image", "none").find("i").show();
  }
}

/* ── Listas (servicios, cifras, beneficios…) ───────────────── */
function lwLista(def, nombre, items) {
  var $l = $('<div class="lw-list"></div>').attr("data-lista", nombre);
  $.each(items, function (i, item) {
    var $it = $('<div class="lw-list-item"></div>');
    $it.append('<div class="lw-list-hd"><strong>#' + (i + 1) + "</strong><span>" +
      '<button type="button" class="btn btn-default btn-xs lw-mover" data-dir="-1" title="Subir"' + (i === 0 ? " disabled" : "") + '><i class="fa fa-arrow-up"></i></button> ' +
      '<button type="button" class="btn btn-default btn-xs lw-mover" data-dir="1" title="Bajar"' + (i === items.length - 1 ? " disabled" : "") + '><i class="fa fa-arrow-down"></i></button> ' +
      '<button type="button" class="btn btn-danger btn-xs lw-quitar" title="Quitar"><i class="fa fa-trash"></i></button></span></div>');
    var $g = $('<div class="lw-grid2"></div>');
    $.each(def.item, function (sub, defSub) {
      var $c = $('<div class="lw-campo"></div>').append("<label>" + lwEsc(defSub.label) + "</label>").append(lwInput(defSub, sub, item[sub]));
      if (defSub.tipo === "image" || defSub.tipo === "textarea") {
        $it.append($c);
      } else {
        $g.append($c);
      }
    });
    $it.children(".lw-list-hd").after($g);
    $l.append($it);
  });
  var lleno = items.length >= def.max;
  $l.append('<button type="button" class="btn btn-default btn-sm lw-agregar"' + (lleno ? " disabled" : "") + '><i class="fa fa-plus"></i> Agregar' +
    (lleno ? " (máximo " + def.max + ")" : "") + "</button>");
  return $l;
}

/* ── Selector de productos ─────────────────────────────────── */
function lwProductos(k, ids) {
  var $w = $('<div class="lw-prods"><div style="position:relative">' +
    '<input type="text" class="form-control lw-prod-q" placeholder="Busca por nombre, código o principio activo…">' +
    '<div class="lw-prod-res" style="display:none"></div></div>' +
    '<ul class="lw-prod-sel"></ul><input type="hidden"></div>');
  $w.find("input[type=hidden]").attr("data-k", k).val(ids.join(","));
  if (ids.length) {
    $.post("../ajax/landing.php?op=buscarArticulos", { ids: ids.join(",") }, function (r) {
      if (r && r.ok) $.each(r.data, function (_, p) { lwProdAgregar($w, p, true); });
    }, "json");
  }
  return $w;
}

// Los nombres de artículos se guardan con entidades HTML (limpiarCadena); se decodifican para mostrarlos
function lwDecode(t) {
  return $("<textarea>").html(t == null ? "" : String(t)).text();
}

function lwProdAgregar($w, p, carga) {
  var $ul = $w.find(".lw-prod-sel");
  if ($ul.find('li[data-id="' + p.id + '"]').length) return;
  var img = p.imagen ? "../files/articulos/" + encodeURIComponent(p.imagen) : "../public/img/default-50x50.gif";
  $ul.append('<li data-id="' + p.id + '"><img src="' + img + '" alt=""><span>' + lwEsc(lwDecode(p.nombre)) + "</span>" +
    '<button type="button" class="btn btn-default btn-xs lw-prod-mover" data-dir="-1"><i class="fa fa-arrow-up"></i></button>' +
    '<button type="button" class="btn btn-danger btn-xs lw-prod-quitar"><i class="fa fa-times"></i></button></li>');
  lwProdSync($w, carga);
}

function lwProdSync($w, carga) {
  var ids = $w.find(".lw-prod-sel li").map(function () { return $(this).data("id"); }).get();
  $w.find("input[type=hidden]").val(ids.join(","));
  if (!carga) lwMarcarSucio();
}

/* ── Recoger valores del formulario actual ─────────────────── */
function lwLeer(def, $el) {
  if (!$el.length) return def.tipo === "bool" ? false : "";
  if (def.tipo === "bool") return $el.is(":checked");
  if (def.tipo === "products") return $el.val() ? $el.val().split(",").map(Number) : [];
  return $el.val();
}

function lwRecoger() {
  var sec = lwEsquema[lwSeccion];
  var out = {};
  var $c = $("#lwCampos");
  $.each(sec.campos, function (nombre, def) {
    var $campo = $c.find('.lw-campo[data-campo="' + nombre + '"]').first();
    if (def.tipo === "list") {
      out[nombre] = $campo.find(".lw-list-item").map(function () {
        var $it = $(this), fila = {};
        $.each(def.item, function (sub, defSub) { fila[sub] = lwLeer(defSub, $it.find('[data-k="' + sub + '"]')); });
        return fila;
      }).get();
    } else {
      out[nombre] = lwLeer(def, $campo.find('[data-k="' + nombre + '"]'));
    }
  });
  return out;
}

function lwMarcarSucio() {
  if (!lwSucio[lwSeccion]) {
    lwSucio[lwSeccion] = true;
    $('#lwNav a[data-sec="' + lwSeccion + '"]').addClass("sucio");
  }
}

/* ── Acciones ──────────────────────────────────────────────── */
function lwGuardar() {
  var datos = lwRecoger();
  var $b = $("#lwGuardar").prop("disabled", true).html('<i class="fa fa-spinner fa-spin"></i> Guardando…');
  $.post("../ajax/landing.php?op=guardar", { seccion: lwSeccion, datos: JSON.stringify(datos) }, function (r) {
    if (r && r.ok) {
      lwValores[lwSeccion] = r.data;
      lwSucio[lwSeccion] = false;
      lwRenderCampos();
      lwRenderNav();
      lwNotify("success", r.message);
    } else {
      lwNotify("error", (r && r.message) || "No se pudo guardar");
    }
  }, "json").fail(function () {
    lwNotify("error", "No se pudo conectar con el servidor. Revisa tu conexión e inténtalo de nuevo.");
  }).always(function () {
    $b.prop("disabled", false).html('<i class="fa fa-save"></i> Guardar y publicar');
  });
}

function lwRestaurar() {
  bootbox.confirm({
    title: "Restaurar textos originales",
    message: "Se reemplazará todo el contenido de <strong>" + lwEsc(lwEsquema[lwSeccion].titulo) + "</strong> por el texto original del sistema. ¿Deseas continuar?",
    buttons: {
      confirm: { label: "Sí, restaurar", className: "btn-warning" },
      cancel: { label: "Cancelar", className: "btn-default" }
    },
    callback: function (ok) {
      if (!ok) return;
      $.post("../ajax/landing.php?op=restaurar", { seccion: lwSeccion }, function (r) {
        if (r && r.ok) {
          lwValores[lwSeccion] = r.data;
          lwSucio[lwSeccion] = false;
          lwRenderCampos();
          lwRenderNav();
          lwNotify("success", r.message);
        } else {
          lwNotify("error", (r && r.message) || "No se pudo restaurar");
        }
      }, "json");
    }
  });
}

function lwSubirImagen(file) {
  if (!file || !lwImagenDestino) return;
  var fd = new FormData();
  fd.append("imagen", file);
  var $btn = lwImagenDestino.closest(".lw-img").find(".lw-img-subir").prop("disabled", true).html('<i class="fa fa-spinner fa-spin"></i> Subiendo…');
  $.ajax({ url: "../ajax/landing.php?op=subirImagen", type: "POST", data: fd, processData: false, contentType: false, dataType: "json" })
    .done(function (r) {
      if (r && r.ok) {
        lwImagenDestino.val(r.data.ruta);
        lwPreviewImagen(lwImagenDestino);
        lwMarcarSucio();
        lwNotify("success", "Imagen lista. Recuerda guardar para publicarla.");
      } else {
        lwNotify("error", (r && r.message) || "No se pudo subir la imagen");
      }
    })
    .fail(function () { lwNotify("error", "No se pudo subir la imagen. Inténtalo de nuevo."); })
    .always(function () { $btn.prop("disabled", false).html('<i class="fa fa-upload"></i> Subir imagen'); });
}

/* ── Eventos ───────────────────────────────────────────────── */
$(function () {
  $.getJSON("../ajax/landing.php?op=cargar", function (r) {
    if (!r || !r.ok) {
      $("#lwCampos").html('<div class="alert alert-danger">' + lwEsc((r && r.message) || "No se pudo cargar el contenido") + "</div>");
      return;
    }
    lwEsquema = r.data.esquema;
    lwValores = r.data.valores;
    $("#lwMigracion").toggle(!r.data.migracion);
    lwAbrir(Object.keys(lwEsquema)[0]);
  }).fail(function () {
    $("#lwCampos").html('<div class="alert alert-danger">No se pudo cargar el contenido. Recarga la página.</div>');
  });

  $("#lwNav").on("click", "a", function (e) {
    e.preventDefault();
    lwAbrir($(this).data("sec"));
  });
  $("#lwGuardar").on("click", lwGuardar);
  $("#lwRestaurar").on("click", lwRestaurar);

  var $f = $("#lwForm");
  $f.on("input change", "input, textarea, select", lwMarcarSucio);

  // Colores: selector y texto hex sincronizados
  $f.on("input", ".lw-color input[type=color]", function () {
    $(this).siblings("input[type=text]").val($(this).val().toUpperCase());
  });
  $f.on("input", ".lw-color input[type=text]", function () {
    if (/^#[0-9a-f]{6}$/i.test($(this).val())) $(this).siblings("input[type=color]").val($(this).val());
  });

  // Íconos
  $f.on("input", ".lw-icon input", function () {
    $(this).siblings(".lw-icon-prev").find("i").attr("class", "bi " + $.trim($(this).val()));
  });
  $f.on("click", ".lw-icon-btn", function (e) {
    e.stopPropagation();
    var $w = $(this).closest(".lw-icon");
    var abierto = $w.find(".lw-icon-pick").length;
    $(".lw-icon-pick").remove();
    if (abierto) return;
    var html = '<div class="lw-icon-pick">';
    $.each(LW_ICONOS, function (_, n) { html += '<button type="button" data-ic="bi-' + n + '" title="' + n + '"><i class="bi bi-' + n + '"></i></button>'; });
    $w.append(html + "</div>");
  });
  $f.on("click", ".lw-icon-pick button", function (e) {
    e.stopPropagation();
    var $w = $(this).closest(".lw-icon");
    $w.find("input").val($(this).data("ic")).trigger("input");
    $(".lw-icon-pick").remove();
  });
  $(document).on("click", function (e) {
    if (!$(e.target).closest(".lw-icon-pick").length) $(".lw-icon-pick").remove();
    if (!$(e.target).closest(".lw-prods").length) $(".lw-prod-res").hide();
  });

  // Imágenes
  $f.on("click", ".lw-img-subir", function () {
    lwImagenDestino = $(this).closest(".lw-img").find("input[type=text]");
    $("#lwFile").val("").trigger("click");
  });
  $("#lwFile").on("change", function () { lwSubirImagen(this.files[0]); });
  $f.on("input", ".lw-img input[type=text]", function () { lwPreviewImagen($(this)); });

  // Listas: agregar / quitar / mover (se reconstruye desde los valores actuales)
  function lwEditarLista($btn, fn) {
    var nombre = $btn.closest(".lw-list").data("lista");
    var idx = $btn.closest(".lw-list-item").index();
    lwValores[lwSeccion] = lwRecoger();
    fn(lwValores[lwSeccion][nombre], idx, lwEsquema[lwSeccion].campos[nombre]);
    lwRenderCampos();
    lwMarcarSucio();
  }
  $f.on("click", ".lw-agregar", function () {
    lwEditarLista($(this), function (arr, _, def) {
      var nuevo = {};
      $.each(def.item, function (sub, d) { nuevo[sub] = d.tipo === "icon" ? "bi-check-circle-fill" : ""; });
      arr.push(nuevo);
    });
  });
  $f.on("click", ".lw-quitar", function () {
    lwEditarLista($(this), function (arr, i) { arr.splice(i, 1); });
  });
  $f.on("click", ".lw-mover", function () {
    var dir = parseInt($(this).data("dir"), 10);
    lwEditarLista($(this), function (arr, i) {
      var j = i + dir;
      if (j < 0 || j >= arr.length) return;
      var t = arr[i]; arr[i] = arr[j]; arr[j] = t;
    });
  });

  // Productos elegidos
  var lwProdTimer = null;
  $f.on("input", ".lw-prod-q", function () {
    var $w = $(this).closest(".lw-prods"), q = $.trim($(this).val());
    clearTimeout(lwProdTimer);
    if (q.length < 2) { $w.find(".lw-prod-res").hide(); return; }
    lwProdTimer = setTimeout(function () {
      $.post("../ajax/landing.php?op=buscarArticulos", { q: q }, function (r) {
        var $res = $w.find(".lw-prod-res").empty();
        if (!r || !r.ok || !r.data.length) {
          $res.html('<a href="#" onclick="return false" class="text-muted">Sin resultados</a>').show();
          return;
        }
        $.each(r.data, function (_, p) {
          $('<a href="#"></a>').text(lwDecode(p.nombre)).data("p", p).appendTo($res);
        });
        $res.show();
      }, "json");
    }, 250);
  });
  $f.on("click", ".lw-prod-res a", function (e) {
    e.preventDefault();
    var p = $(this).data("p");
    if (!p) return;
    var $w = $(this).closest(".lw-prods");
    lwProdAgregar($w, p);
    $w.find(".lw-prod-q").val("");
    $w.find(".lw-prod-res").hide();
  });
  $f.on("click", ".lw-prod-quitar", function () {
    var $w = $(this).closest(".lw-prods");
    $(this).closest("li").remove();
    lwProdSync($w);
  });
  $f.on("click", ".lw-prod-mover", function () {
    var $li = $(this).closest("li"), $w = $li.closest(".lw-prods");
    $li.prev().before($li);
    lwProdSync($w);
  });

  window.addEventListener("beforeunload", function (e) {
    for (var k in lwSucio) {
      if (lwSucio[k]) { e.preventDefault(); e.returnValue = ""; return ""; }
    }
  });
});
