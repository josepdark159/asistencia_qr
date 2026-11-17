<?php
require_once __DIR__ . '/config/funciones.php';
$pdo = conectarBaseDatos();
asegurarTablaUsuarios($pdo);
$error = '';
$userCount = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();

// Comprueba las credenciales y crea la sesión del usuario autorizado.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    if ($userCount === 0) {
        $error = 'Aún no hay cuentas. Usa el botón de registro para crear el administrador.';
    } else {
        $username = trim($_POST['usuario'] ?? '');
        $password = $_POST['password'] ?? '';
        $statement = $pdo->prepare('SELECT id, nombre, usuario, password_hash, rol FROM usuarios WHERE usuario = :usuario AND activo = 1 AND aprobado = 1');
        $statement->execute(['usuario' => $username]);
        $user = $statement->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['usuario_id'] = (int) $user['id'];
            $_SESSION['usuario_nombre'] = $user['nombre'];
            $_SESSION['usuario_login'] = $user['usuario'];
            $_SESSION['usuario_rol'] = $user['rol'];
            redirigir('index.php');
        }

        $error = 'Usuario o contraseña incorrectos.';
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión | Fe y Alegría N.° 41</title>
    <link rel="stylesheet" href="css/app.css">
</head>
<body class="auth-body login-body">
    <main class="auth-shell">
        <a class="brand auth-brand" href="login.php"><img class="brand-mark" src="img/escudo-fe-alegria.svg" alt=""><span>Fe y Alegría <span class="brand-number">N.° 41</span></span></a>
        <section class="auth-panel">
            <img class="auth-emblem" src="img/escudo-fe-alegria.svg" alt="Escudo de Fe y Alegría 41 Calasanz">
            <p class="eyebrow">SISTEMA DE ASISTENCIA</p>
            <h1>Iniciar sesión</h1>
            <p class="auth-copy">Ingresa con tu cuenta para consultar el registro escolar.</p>
            <?php if (isset($_GET['configurado'])): ?><p class="notice success" role="status">Administrador creado. Ya puedes iniciar sesión.</p><?php endif; ?>
            <?php if (isset($_GET['registrado'])): ?><p class="notice success" role="status">Cuenta creada. El administrador debe aprobarla antes del primer ingreso.</p><?php endif; ?>
            <?php if ($error !== ''): ?><p class="notice error" role="alert"><?= escapar($error) ?></p><?php endif; ?>
            <form method="post" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>">
                <label>Usuario<input name="usuario" required autocomplete="username" autofocus></label>
                <label>Contraseña<input type="password" name="password" required autocomplete="current-password"></label>
                <button class="button primary full-button" type="submit">Entrar</button>
            </form>
            <div class="auth-register">
                <p><?= $userCount === 0 ? '¿Primera vez en el sistema?' : '¿No tienes una cuenta?' ?></p>
                <a class="button secondary full-button" href="<?= $userCount === 0 ? 'setup_usuario.php' : 'registro.php' ?>"><?= $userCount === 0 ? 'Registrar administrador' : 'Registrarme' ?></a>
            </div>
        </section>
    </main>
</body>
</html>