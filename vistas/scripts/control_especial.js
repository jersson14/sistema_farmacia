var tblCE = null;

function initTblCE() {
    var fi = ($("#ce_fecha_inicio").val() || "").trim();
    var ff = ($("#ce_fecha_fin").val()    || "").trim();

    if (tblCE) {
        tblCE.destroy();
        tblCE = null;
    }

    tblCE = $("#tblControlEspecial").dataTable({
        "aProcessing": true,
        "aServerSide": true,
        dom: "Bfrtip",
        buttons: window.appDataTableButtons ? window.appDataTableButtons("Libro Control Especial", true) : ["copy","csv","print"],
        "ajax": {
            url: "../ajax/control_especial.php?op=listar",
            type: "get",
            data: function(d) {
                d.fecha_inicio = fi;
                d.fecha_fin    = ff;
            },
            dataType: "json",
            error: function(e) { console.log(e.responseText); }
        },
        "bDestroy": true,
        "iDisplayLength": 25,
        "order": [[0, "desc"]],
        "columnDefs": [
            { "orderable": false, "targets": [9] }
        ]
    }).DataTable();
}

function actualizarLinkPdf() {
    var fi = ($("#ce_fecha_inicio").val() || "").trim();
    var ff = ($("#ce_fecha_fin").val()    || "").trim();
    var url = "../reportes/exControlEspecial.php?";
    if (fi) url += "fecha_inicio=" + encodeURIComponent(fi) + "&";
    if (ff) url += "fecha_fin="    + encodeURIComponent(ff);
    $("#btnExportPdf").attr("href", url);
}

$(function(){
    initTblCE();
    actualizarLinkPdf();

    $("#btnFiltrarCE").on("click", function(){
        initTblCE();
        actualizarLinkPdf();
    });

    $("#btnLimpiarCE").on("click", function(){
        $("#ce_fecha_inicio, #ce_fecha_fin").val("");
        initTblCE();
        actualizarLinkPdf();
    });

    $("#ce_fecha_inicio, #ce_fecha_fin").on("change", function(){
        actualizarLinkPdf();
    });
});
