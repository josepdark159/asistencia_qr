<?php
require_once __DIR__ . '/config/funciones.php';
$pdo = conectarBaseDatos();
asegurarTablaUsuarios($pdo);
$userCount = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
// El registro público solo está disponible después de crear la primera cuenta administradora.
if ($userCount === 0) {
    redirigir('login.php');
}

$error = '';
// Valida los datos y crea una cuenta pendiente de aprobación administrativa.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $name = trim($_POST['nombre'] ?? '');
    $username = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmation = $_POST['confirmar_password'] ?? '';

    if ($name === '' || !preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
        $error = 'Ingresa tu nombre y un usuario de 3 a 50 caracteres (letras, números, punto, guion o guion bajo).';
    } elseif (strlen($password) < 10) {
        $error = 'La contraseña debe tener al menos 10 caracteres.';
    } elseif ($password !== $confirmation) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $statement = $pdo->prepare("INSERT INTO usuarios (nombre, usuario, password_hash, rol, activo, aprobado) VALUES (:nombre, :usuario, :password_hash, 'usuario', 1, 0)");
        try {
            $statement->execute([
                'nombre' => $name,
                'usuario' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            redirigir('login.php?registrado=1');
        } catch (PDOException $exception) {
            $error = 'No se pudo crear la cuenta. Puede que ese usuario ya exista.';
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registrarse | Fe y Alegría N.° 41</title>
    <link rel="stylesheet" href="css/app.css">
</head>
<body class="auth-body">
    <main class="auth-shell">
        <a class="brand auth-brand" href="login.php"><span class="brand-mark" aria-hidden="true">F</span><span>Fe y Alegría <span class="brand-number">N.° 41</span></span></a>
        <section class="auth-panel">
            <p class="eyebrow">SOLICITUD DE ACCESO</p>
            <h1>Crear cuenta</h1>
            <p class="auth-copy">El administrador debe aprobar la cuenta antes de que puedas consultar asistencias.</p>
            <?php if ($error !== ''): ?><p class="notice error" role="alert"><?= escapar($error) ?></p><?php endif; ?>
            <form method="post" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>">
                <label>Nombre completo<input name="nombre" maxlength="100" required autocomplete="name"></label>
                <label>Usuario<input name="usuario" minlength="3" maxlength="50" required autocomplete="username"></label>
                <label>Contraseña<input type="password" name="password" minlength="10" required autocomplete="new-password"></label>
                <label>Confirmar contraseña<input type="password" name="confirmar_password" minlength="10" required autocomplete="new-password"></label>
                <button class="button primary full-button" type="submit">Solicitar cuenta</button>
            </form>
            <p class="auth-register"><a href="login.php">Volver al inicio de sesión</a></p>
        </section>
    </main>
</body>
</html>