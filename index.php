<?php
// Redirigir al portal preservando los parámetros (como el ID del panel)
$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: portal/index.php' . $query);
exit;
?>