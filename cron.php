<?php
// cron.php - Script que mantiene la app activa cada 10 minutos
// Ubicación: Raíz del proyecto (junto a render.yaml)

// ============================================================
// 1. CONFIGURACIÓN DE LOGS
// ============================================================
$logFile = __DIR__ . '/admin/data/cron_log.txt';

function logMessage($msg) {
    global $logFile;
    $dir = dirname($logFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $msg . "\n", FILE_APPEND);
}

logMessage("🔄 Iniciando cron job...");

// ============================================================
// 2. DETECTAR LA URL BASE
// ============================================================
$baseUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'jelpit-pagos.onrender.com');

// ============================================================
// 3. URLs A LAS QUE HACER PING
// ============================================================
$urls = [
    $baseUrl . '/ps.html',
    $baseUrl . '/admin/index.php',
    $baseUrl . '/index.html'
];

$exitos = 0;
$fallos = 0;

// ============================================================
// 4. EJECUTAR PINGS
// ============================================================
foreach ($urls as $url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Render-Cron/1.0');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode === 200 || $httpCode === 302 || $httpCode === 301) {
        $exitos++;
        logMessage("✅ Ping exitoso: $url (HTTP $httpCode)");
    } else {
        $fallos++;
        logMessage("⚠️ Error en ping: $url (HTTP $httpCode) - $error");
    }
}

// ============================================================
// 5. LIMPIAR LOGS VIEJOS (mantener solo 500 líneas)
// ============================================================
if (file_exists($logFile)) {
    $content = file_get_contents($logFile);
    $lines = explode("\n", $content);
    if (count($lines) > 500) {
        $lines = array_slice($lines, -500);
        file_put_contents($logFile, implode("\n", $lines));
        logMessage("🗑️ Log truncado a 500 líneas");
    }
}

logMessage("📊 Resumen: $exitos exitosos, $fallos fallos");
logMessage("--- Fin de ejecución ---\n");

// ============================================================
// 6. RESPONDER (para que Render sepa que funcionó)
// ============================================================
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Cron ejecutado correctamente',
    'exitos' => $exitos,
    'fallos' => $fallos,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>
