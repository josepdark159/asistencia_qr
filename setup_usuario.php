<?php
require_once __DIR__ . '/config/funciones.php';
$pdo = conectarBaseDatos();
asegurarTablaUsuarios($pdo);

$userCount = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
$error = '';

// Permite crear la primera cuenta administradora únicamente si aún no hay usuarios.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userCount === 0) {
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
        $statement = $pdo->prepare("INSERT INTO usuarios (nombre, usuario, password_hash, rol) VALUES (:nombre, :usuario, :password_hash, 'administrador')");
        try {
            $statement->execute([
                'nombre' => $name,
                'usuario' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            redirigir('login.php?configurado=1');
        } catch (PDOException $exception) {
            $error = 'No se pudo crear la cuenta. Verifica el usuario e inténtalo otra vez.';
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crear administrador | Fe y Alegría N.° 41</title>
    <link rel="stylesheet" href="css/app.css">
</head>
<body class="auth-body">
    <main class="auth-shell">
        <a class="brand auth-brand" href="index.php"><span class="brand-mark" aria-hidden="true">F</span><span>Fe y Alegría <span class="brand-number">N.° 41</span></span></a>
        <?php if ($userCount > 0): ?>
            <section class="auth-panel">
                <p class="eyebrow">CONFIGURACIÓN COMPLETADA</p>
                <h1>Ya existe una cuenta.</h1>
                <p class="auth-copy">El alta inicial se deshabilitó para proteger el acceso al sistema.</p>
                <a class="button primary full-button" href="login.php">Ir al inicio de sesión</a>
            </section>
        <?php else: ?>
            <section class="auth-panel">
                <p class="eyebrow">PRIMER INGRESO</p>
                <h1>Crear administrador</h1>
                <p class="auth-copy">Esta cuenta podrá consultar asistencias y administrar otros usuarios.</p>
                <?php if ($error !== ''): ?><p class="notice error" role="alert"><?= escapar($error) ?></p><?php endif; ?>
                <form method="post" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>">
                    <label>Nombre completo<input name="nombre" maxlength="100" required autocomplete="name"></label>
                    <label>Usuario<input name="usuario" minlength="3" maxlength="50" required autocomplete="username"></label>
                    <label>Contraseña<input type="password" name="password" minlength="10" required autocomplete="new-password"></label>
                    <label>Confirmar contraseña<input type="password" name="confirmar_password" minlength="10" required autocomplete="new-password"></label>
                    <button class="button primary full-button" type="submit">Crear cuenta administradora</button>
                </form>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>