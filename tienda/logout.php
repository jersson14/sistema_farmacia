<?php
if (strlen(session_id()) < 1) session_start();
unset($_SESSION['tienda_cliente']);
header('Location: index.php');
exit;
?>
