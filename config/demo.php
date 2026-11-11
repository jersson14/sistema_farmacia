<?php
// ============================================================
// MODO DEMO
// ------------------------------------------------------------
// Rama `demo`: versión para demostraciones públicas.
// Bloquea la administración del sistema (usuarios, permisos,
// empresa, página web, backup, integraciones IA, config. de tienda)
// para que los visitantes no puedan cambiar claves, credenciales
// ni restaurar/descargar la base de datos.
//
// Para desactivarlo en un servidor concreto, define en
// config/global.php:   define('DEMO_MODE', false);
// ============================================================

if (!defined('DEMO_MODE')) {
    $globalDemo = __DIR__ . '/global.php';
    if (is_file($globalDemo)) require_once $globalDemo;
    if (!defined('DEMO_MODE')) define('DEMO_MODE', true);
}

define('DEMO_MENSAJE', 'Esta opción está deshabilitada en la versión de demostración.');

/**
 * Corta la petición AJAX si la operación no está permitida en demo.
 * @param array $permitidas ops de $_GET['op'] que sí se pueden usar
 */
function demoBloquearAjax(array $permitidas = array())
{
    if (!DEMO_MODE) return;
    $op = $_GET['op'] ?? ($_POST['op'] ?? '');
    if (in_array($op, $permitidas, true)) return;

    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(array(
        'ok'      => false,
        'success' => false,
        'status'  => 'error',
        'message' => DEMO_MENSAJE,
        'error'   => DEMO_MENSAJE,
    ));
    exit;
}

/**
 * Muestra un aviso en lugar de la pantalla bloqueada.
 * Se llama después de session_start(), antes de cualquier HTML.
 */
function demoBloquearVista()
{
    if (!DEMO_MODE) return;
    if (!isset($_SESSION['nombre'])) {
        header('Location: login.html');
        exit;
    }
    // header.php se incluye dentro de esta función: la conexión debe quedar global
    require_once __DIR__ . '/Conexion.php';
    if (isset($conexion)) $GLOBALS['conexion'] = $conexion;
    require __DIR__ . '/../vistas/header.php';
    ?>
    <div class="content-wrapper">
      <section class="content">
        <div class="box box-warning" style="max-width:620px;margin:40px auto;text-align:center;padding:30px 20px;">
          <i class="bi bi-lock" style="font-size:48px;color:#f39c12;"></i>
          <h3 style="margin-top:12px;">Versión de demostración</h3>
          <p style="font-size:15px;color:#555;"><?php echo DEMO_MENSAJE; ?><br>
            En la versión completa aquí se administran usuarios, permisos y la configuración de la farmacia.</p>
          <a href="escritorio.php" class="btn btn-primary"><i class="bi bi-house"></i> Volver al inicio</a>
        </div>
      </section>
    </div>
    <?php
    require __DIR__ . '/../vistas/footer.php';
    exit;
}

/** true si el menú debe ocultar las opciones de administración. */
function demoActivo()
{
    return DEMO_MODE;
}
