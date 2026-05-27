<?php 
//activamos almacenamiento en el buffer
ob_start();
session_start();
if (!isset($_SESSION['nombre'])) {
  header("Location: login.html");
}else{

require 'header.php';
if ($_SESSION['almacen']==1) {
 ?>
    <div class="content-wrapper">
    <!-- Main content -->
    <section class="content">

      <!-- Default box -->
      <div class="row">
        <div class="col-md-12">
      <div class="box">
<div class="box-header with-border">
  <h1 class="box-title">Articulo <button class="btn btn-success" onclick="mostrarform(true)" id="btnagregar"><i class="fa fa-plus-circle"></i>Agregar</button> <a target="_blank" href="../reportes/rptarticulos.php"><button class="btn btn-report"><i class="fa fa-file-text-o"></i> Reporte</button></a></h1>
  <div class="box-tools pull-right">
    
  </div>
</div>
<!--box-header-->
<!--centro-->
<div class="panel-body table-responsive" id="listadoregistros">
  <table id="tbllistado" class="table table-striped table-bordered table-condensed table-hover">
    <thead>
      <th>Opciones</th>
      <th>Nombre</th>
      <th>Categoria</th>
      <th>Unidad</th>
      <th>Codigo</th>
      <th>Stock</th>
      <th>Stock Min.</th>
      <th>Precio venta</th>
      <th>Imagen</th>
      <th>Descripcion</th>
      <th>Estado</th>
    </thead>
    <tbody>
    </tbody>
    <tfoot>
      <th>Opciones</th>
      <th>Nombre</th>
      <th>Categoria</th>
      <th>Unidad</th>
      <th>Codigo</th>
      <th>Stock</th>
      <th>Stock Min.</th>
      <th>Precio venta</th>
      <th>Imagen</th>
      <th>Descripcion</th>
      <th>Estado</th>
    </tfoot>   
  </table>
</div>
<div class="panel-body" id="formularioregistros">
  <form action="" name="formulario" id="formulario" method="POST">
    <input type="hidden" name="idarticulo" id="idarticulo">

    <div class="row" style="margin:0">

      <!-- ── Columna izquierda: datos principales ── -->
      <div class="col-md-8 col-xs-12" style="padding-left:0">

        <div class="form-group">
          <label>Nombre <span class="text-danger">*</span></label>
          <input class="form-control" type="text" name="nombre" id="nombre" maxlength="100" placeholder="Nombre del medicamento o producto" required>
        </div>

        <div class="row">
          <div class="form-group col-md-6 col-xs-12">
            <label>Categoria <span class="text-danger">*</span></label>
            <select name="idcategoria" id="idcategoria" class="form-control selectpicker" data-live-search="true" required></select>
          </div>
          <div class="form-group col-md-6 col-xs-12">
            <label>Unidad base <span class="text-danger">*</span></label>
            <select name="idunidad" id="idunidad" class="form-control selectpicker" data-live-search="true" required></select>
          </div>
        </div>

        <div class="row">
          <div class="form-group col-md-4 col-xs-6">
            <label>Stock</label>
            <input class="form-control" type="number" step="1" min="0" name="stock" id="stock" placeholder="0" required>
          </div>
          <div class="form-group col-md-4 col-xs-6">
            <label>Stock mínimo</label>
            <input class="form-control" type="number" step="1" min="0" name="stock_minimo" id="stock_minimo" value="1" placeholder="1" required>
          </div>
          <div class="form-group col-md-4 col-xs-12">
            <label>Precio de venta <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-addon">S/</span>
              <input class="form-control" type="number" step="0.01" min="0" name="precio_venta" id="precio_venta" placeholder="0.00" required>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label>Descripción</label>
          <input class="form-control" type="text" name="descripcion" id="descripcion" maxlength="256" placeholder="Descripción opcional del producto">
        </div>

        <div class="form-group">
          <label>Código de barras <span class="text-danger">*</span></label>
          <div class="input-group">
            <input class="form-control" type="text" name="codigo" id="codigo" placeholder="Código del producto" required>
            <span class="input-group-btn">
              <button class="btn btn-success" type="button" onclick="generarCodigoArticulo()"><i class="fa fa-magic"></i> Generar</button>
              <button class="btn btn-info" type="button" onclick="imprimir()"><i class="fa fa-print"></i> Imprimir</button>
            </span>
          </div>
          <div id="print" style="margin-top:8px">
            <svg id="barcode"></svg>
          </div>
        </div>

      </div><!-- /col izquierda -->

      <!-- ── Columna derecha: imagen ── -->
      <div class="col-md-4 col-xs-12" style="padding-right:0">
        <div class="form-group">
          <label>Imagen del producto</label>
          <input class="form-control" type="file" name="imagen" id="imagen" accept="image/jpg,image/jpeg,image/png,image/gif">
          <input type="hidden" name="imagenactual" id="imagenactual">
        </div>
        <div id="imgPreviewWrap" style="text-align:center; margin-top:6px">
          <img src="" alt="Vista previa" id="imagenmuestra"
               style="display:none; max-width:100%; width:100%; height:260px; object-fit:contain;
                      border:2px dashed #cbd5e1; border-radius:12px; background:#f8fafc; padding:8px;">
          <div id="imgPlaceholder" style="height:260px; border:2px dashed #cbd5e1; border-radius:12px;
               background:#f8fafc; display:flex; align-items:center; justify-content:center;
               flex-direction:column; color:#94a3b8;">
            <i class="fa fa-image" style="font-size:48px; margin-bottom:8px"></i>
            <span style="font-size:13px">Sin imagen</span>
          </div>
        </div>
      </div><!-- /col derecha -->

    </div><!-- /row principal -->
    <!-- Datos farmacéuticos -->
    <div class="col-lg-12 col-md-12 col-xs-12" style="margin-top:10px;">
      <div class="box box-info box-solid">
        <div class="box-header with-border">
          <h4 class="box-title"><i class="fa fa-flask"></i> Datos farmacéuticos</h4>
          <div class="box-tools pull-right">
            <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
          </div>
        </div>
        <div class="box-body">
          <div class="row">
            <div class="form-group col-lg-5 col-md-5 col-xs-12">
              <label>Principio activo (DCI / genérico)</label>
              <input class="form-control" type="text" name="principio_activo" id="principio_activo"
                     maxlength="200" placeholder="Ej: Paracetamol, Amoxicilina">
            </div>
            <div class="form-group col-lg-3 col-md-3 col-xs-6">
              <label>Concentración</label>
              <input class="form-control" type="text" name="concentracion" id="concentracion"
                     maxlength="100" placeholder="Ej: 500 mg, 250 mg/5 ml">
            </div>
            <div class="form-group col-lg-4 col-md-4 col-xs-6">
              <label>Tipo de venta</label>
              <select name="tipo_venta" id="tipo_venta" class="form-control selectpicker">
                <option value="OTC">OTC (sin receta)</option>
                <option value="RX">Rx (con receta médica)</option>
                <option value="CONTROL_ESPECIAL">Control especial (psicotrópico)</option>
              </select>
            </div>
            <div class="form-group col-lg-4 col-md-4 col-xs-6">
              <label>Forma farmacéutica</label>
              <select name="forma_farmaceutica" id="forma_farmaceutica" class="form-control selectpicker">
                <option value="">-- Seleccionar --</option>
                <option value="Tableta">Tableta</option>
                <option value="Cápsula">Cápsula</option>
                <option value="Jarabe">Jarabe</option>
                <option value="Suspensión">Suspensión</option>
                <option value="Solución oral">Solución oral</option>
                <option value="Inyectable">Inyectable</option>
                <option value="Crema">Crema</option>
                <option value="Ungüento">Ungüento</option>
                <option value="Gel">Gel</option>
                <option value="Gotas">Gotas</option>
                <option value="Supositorio">Supositorio</option>
                <option value="Óvulo">Óvulo</option>
                <option value="Parche">Parche</option>
                <option value="Aerosol">Aerosol</option>
                <option value="Otro">Otro</option>
              </select>
            </div>
            <div class="form-group col-lg-4 col-md-4 col-xs-6">
              <label>Vía de administración</label>
              <select name="via_administracion" id="via_administracion" class="form-control selectpicker">
                <option value="">-- Seleccionar --</option>
                <option value="Oral">Oral</option>
                <option value="Tópica">Tópica</option>
                <option value="Intravenosa">Intravenosa (IV)</option>
                <option value="Intramuscular">Intramuscular (IM)</option>
                <option value="Subcutánea">Subcutánea</option>
                <option value="Inhalada">Inhalada</option>
                <option value="Oftálmica">Oftálmica</option>
                <option value="Ótica">Ótica</option>
                <option value="Nasal">Nasal</option>
                <option value="Sublingual">Sublingual</option>
                <option value="Rectal">Rectal</option>
                <option value="Vaginal">Vaginal</option>
                <option value="Otro">Otro</option>
              </select>
            </div>
            <div class="form-group col-lg-4 col-md-4 col-xs-12">
              <label>Laboratorio / Fabricante</label>
              <input class="form-control" type="text" name="laboratorio" id="laboratorio"
                     maxlength="200" placeholder="Nombre del laboratorio">
            </div>
            <div class="form-group col-lg-4 col-md-4 col-xs-6">
              <label>Registro sanitario</label>
              <input class="form-control" type="text" name="registro_sanitario" id="registro_sanitario"
                     maxlength="100" placeholder="N° DIGEMID / INVIMA">
            </div>
            <div class="form-group col-lg-4 col-md-4 col-xs-6">
              <label>&nbsp;</label><br>
              <div class="checkbox">
                <label>
                  <input type="checkbox" name="requiere_frio" id="requiere_frio" value="1">
                  <i class="fa fa-snowflake-o text-aqua"></i> Requiere cadena de frío
                </label>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="form-group col-lg-12 col-md-12 col-sm-12 col-xs-12">
      <button class="btn btn-primary" type="submit" id="btnGuardar"><i class="fa fa-save"></i>  Guardar</button>
      <button class="btn btn-danger" onclick="cancelarform()" type="button"><i class="fa fa-arrow-circle-left"></i> Cancelar</button>
    </div>
  </form>
</div>
<!--fin centro-->
      </div>
      </div>
      </div>
      <!-- /.box -->

    </section>
    <!-- /.content -->
  </div>
<?php 
}else{
 require 'noacceso.php'; 
}
require 'footer.php'
 ?>
 <script src="../public/js/JsBarcode.all.min.js"></script>
 <script src="../public/js/jquery.PrintArea.js"></script>
 <script src="scripts/articulo.js"></script>

 <?php 
}

ob_end_flush();
  ?>
