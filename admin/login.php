<?php
// admin/login.php - Página de login
session_start();

// Credenciales fijas
$USUARIO_VALIDO = 'Skyjelpit345';
$CONTRASENA_VALIDA = 'Bod3g4';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'] ?? '';
    $contrasena = $_POST['contrasena'] ?? '';
    
    if ($usuario === $USUARIO_VALIDO && $contrasena === $CONTRASENA_VALIDA) {
        $_SESSION['admin_logueado'] = true;
        $_SESSION['admin_usuario'] = $usuario;
        $_SESSION['admin_login_time'] = time();
        
        // Redirigir al panel
        header('Location: index.php');
        exit;
    } else {
        $error = '❌ Usuario o contraseña incorrectos';
    }
}

if (isset($_SESSION['admin_logueado']) && $_SESSION['admin_logueado'] === true) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Panel Administrativo</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f0f4f8;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .login-container {
            background: #ffffff;
            border-radius: 16px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            border-top: 4px solid #1a56db;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h1 {
            font-size: 28px;
            color: #1a1a2e;
            font-weight: 300;
        }
        .login-header h1 span {
            color: #1a56db;
            font-weight: 700;
        }
        .login-header .subtitle {
            color: #888;
            font-size: 14px;
            margin-top: 5px;
        }
        .login-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 5px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d0d7e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            background: #f8fafc;
            color: #1a1a2e;
        }
        .form-group input:focus {
            border-color: #1a56db;
            box-shadow: 0 0 0 3px rgba(26, 86, 219, 0.1);
            outline: none;
            background: #ffffff;
        }
        .login-btn {
            width: 100%;
            padding: 12px;
            background: #1a56db;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .login-btn:hover {
            background: #1e40af;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 86, 219, 0.3);
        }
        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
            border-left: 4px solid #dc2626;
        }
        .security-badge {
            text-align: center;
            margin-top: 15px;
            font-size: 12px;
            color: #999;
        }
        .security-badge span {
            margin: 0 5px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-icon">🔐</div>
            <h1>Panel de <span>Sesiones</span></h1>
            <p class="subtitle">Acceso restringido - Administradores</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="usuario">👤 Usuario</label>
                <input type="text" id="usuario" name="usuario" required placeholder="Ingresa tu usuario" autofocus>
            </div>
            
            <div class="form-group">
                <label for="contrasena">🔑 Contraseña</label>
                <input type="password" id="contrasena" name="contrasena" required placeholder="Ingresa tu contraseña">
            </div>
            
            <button type="submit" class="login-btn">Ingresar al Panel</button>
        </form>
        
        <div class="security-badge">
            <span>🔒</span> Conexión segura
        </div>
    </div>
</body>
</html>