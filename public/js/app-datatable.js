(function (window) {
  function safeTitle(text) {
    return (text || "reporte")
      .toString()
      .trim()
      .replace(/\s+/g, "_")
      .replace(/[^\w\-]/g, "")
      .toLowerCase();
  }

  function fileDate() {
    var d = new Date();
    var y = d.getFullYear();
    var m = ("0" + (d.getMonth() + 1)).slice(-2);
    var day = ("0" + d.getDate()).slice(-2);
    return y + "-" + m + "-" + day;
  }

  window.appDataTableButtons = function (title, hasActionsColumn) {
    var reportTitle = title || "Reporte";
    var filename = safeTitle(reportTitle) + "_" + fileDate();
    var exportColumns = hasActionsColumn ? ":visible:not(:first-child)" : ":visible";
    var exportOptions = {
      columns: exportColumns,
      stripHtml: true,
      trim: true
    };

    return [
      {
        extend: "copyHtml5",
        text: '<i class="fa fa-copy"></i> Copiar',
        titleAttr: "Copiar",
        className: "btn btn-export btn-copy",
        exportOptions: exportOptions
      },
      {
        extend: "excelHtml5",
        text: '<i class="fa fa-file-excel-o"></i> Excel',
        titleAttr: "Exportar a Excel",
        className: "btn btn-export btn-excel",
        title: reportTitle,
        filename: filename,
        exportOptions: exportOptions
      },
      {
        extend: "csvHtml5",
        text: '<i class="fa fa-file-text-o"></i> CSV',
        titleAttr: "Exportar a CSV",
        className: "btn btn-export btn-csv",
        title: reportTitle,
        filename: filename,
        bom: true,
        exportOptions: exportOptions
      },
      {
        extend: "pdfHtml5",
        text: '<i class="fa fa-file-pdf-o"></i> PDF',
        titleAttr: "Exportar a PDF",
        className: "btn btn-export btn-pdf",
        title: reportTitle,
        filename: filename,
        orientation: "landscape",
        pageSize: "A4",
        exportOptions: exportOptions
      }
    ];
  };

  if (window.jQuery && window.jQuery.fn && window.jQuery.fn.dataTable) {
    window.jQuery.extend(true, window.jQuery.fn.dataTable.defaults, {
      iDisplayLength: 10,
      lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
      language: {
        sProcessing: "Procesando...",
        sLengthMenu: "Mostrar _MENU_ registros",
        sZeroRecords: "No se encontraron resultados",
        sEmptyTable: "No hay datos disponibles",
        sInfo: "Mostrando _START_ al _END_ de _TOTAL_ registros",
        sInfoEmpty: "Mostrando 0 al 0 de 0 registros",
        sInfoFiltered: "(filtrado de _MAX_ registros totales)",
        sSearch: "Buscar:",
        oPaginate: {
          sFirst: "Primero",
          sLast: "Último",
          sNext: "Siguiente",
          sPrevious: "Anterior"
        }
      }
    });

    // Vista de tarjetas en celular (≤767px, ver custom-theme.css "dt-cards"):
    // cada celda recibe el título de su columna para mostrarlo como etiqueta, y
    // la celda con botones (Opciones) se marca para ir arriba de la tarjeta.
    // Para excluir una tabla, agrégale la clase "dt-no-cards".
    window.jQuery(document).on("draw.dt", function (e, settings) {
      var $ = window.jQuery;
      var api = new $.fn.dataTable.Api(settings);
      var $tabla = $(api.table().node());
      if ($tabla.hasClass("dt-no-cards")) return;
      $tabla.addClass("dt-cards");
      $(api.table().container()).addClass("dt-cards-wrap");
      // aoColumns funciona en la versión de DataTables del sistema (columns(":visible") no)
      var titulos = $.map(settings.aoColumns || [], function (col) {
        return col.bVisible === false ? null : $.trim($(col.nTh).text() || col.sTitle || "");
      });
      $tabla.children("tbody").children("tr").each(function () {
        $(this).children("td").each(function (i) {
          var $td = $(this);
          if ($td.hasClass("dataTables_empty")) return;
          var acciones = $td.find("button, a.btn").length > 0 && $.trim($td.text()) === "";
          $td.toggleClass("dt-acciones", acciones);
          $td.attr("data-label", acciones ? "" : (titulos[i] || ""));
          $td.toggleClass("dt-vacia", !acciones && $.trim($td.text()) === "" && !$td.find("img, input, select, .label").length);
        });
      });
    });
  }
})(window);
