<?php
$GLOBALS['pageTitulo'] = 'Pedido confirmado';
require 'layout.php';
if (empty($_SESSION['tienda_cliente'])) { header('Location: login.php'); exit; }
$idpedido = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$waNum = $cfgTienda->obtener('whatsapp_numero','');
?>

<div class="success-page">
  <div class="success-card">
    <div class="success-icon">✅</div>
    <h2>¡Pedido confirmado!</h2>
    <p class="order-num">Tu pedido <strong>#<?php echo $idpedido; ?></strong> fue registrado correctamente.</p>

    <div class="success-steps">
      <ol>
        <li>Revisaremos tu pedido y confirmaremos la disponibilidad.</li>
        <li>Te contactaremos al número que indicaste para coordinar la entrega.</li>
        <?php if ($waNum): ?>
        <li>También puedes escribirnos por
          <a href="https://wa.me/<?php echo preg_replace('/\D/','',$waNum); ?>?text=Hola,%20mi%20pedido%20es%20%23<?php echo $idpedido; ?>"
             target="_blank" style="color:#0f766e;font-weight:600">WhatsApp</a>
          con tu N° de pedido.
        </li>
        <?php endif; ?>
      </ol>
    </div>

    <a href="mis_pedidos.php" class="btn-primary-lg" style="text-decoration:none;margin-bottom:12px">
      📋 Ver mis pedidos
    </a>
    <a href="index.php" style="display:block;text-align:center;font-size:14px;color:#6b7280;margin-top:4px">
      ← Seguir comprando
    </a>
  </div>
</div>
</body>
</html>
