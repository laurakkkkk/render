<?php
// keepalive.php - Mantiene la app activa SIN CRON JOB
// Este archivo se ejecuta solo y no requiere modificar index.html

$lastPing = __DIR__ . '/admin/data/last_ping.txt';
$dir = dirname($lastPing);
if (!is_dir($dir)) mkdir($dir, 0777, true);

$now = time();
$lastTime = file_exists($lastPing) ? (int) file_get_contents($lastPing) : 0;

// Si pasaron más de 5 minutos, hacer ping
if ($now - $lastTime > 300) {
    file_put_contents($lastPing, $now);
    
    // Ping a la propia URL
    $url = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'pagoadministrativojelp.onrender.com') . '/ps.html';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'KeepAlive/1.0');
    curl_exec($ch);
    curl_close($ch);
}

// Mostrar solo "OK" (sin redirigir, para no afectar nada)
echo "OK";
exit;
?>
